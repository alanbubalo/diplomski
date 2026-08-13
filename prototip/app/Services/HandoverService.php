<?php

declare(strict_types=1);

namespace App\Services;

use App\Domain\Intent;
use App\Domain\IntermediaryResponse;
use App\Domain\ResponseInterpreter;
use App\Domain\RetrySchedule;
use App\Domain\State;
use App\Domain\StateMachine;
use App\Domain\Trigger;
use App\Models\AuditEntry;
use App\Models\Handover;
use App\Models\Invoice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Predaja dokumenta posredniku, u dvije izvedbe koje se razlikuju u jednoj
 * jedinoj stvari: pise li se zapis namjere PRIJE predaje ili POSLIJE odgovora.
 *
 * Ta razlika je cijeli dokazni sadrzaj poglavlja 7. Iz nje slijede sva tri
 * kontrasta koja scenariji pokazuju:
 *
 *   B1  bez zapisa izostanak odgovora ne ostavlja nikakav trag; sa zapisom
 *       dokument sjedi u imenovanom stanju SENT_UNCONFIRMED
 *   E1  bez zapisa poslovni sustav ne moze odgovoriti je li dokument predao
 *   B2  bez zapisa sifra S008 ostaje dvoznacna; sa zapisom je jednoznacna pri
 *       tumacenju
 *
 * Zapis namjere pise se u istoj transakciji kao i sam dokument. Prekid izmedu te
 * dvije radnje inace je najgore mjesto na kojem se dvostruki zapis moze
 * pojaviti: sustav koji je dokument izdao ne bi znao je li ga i predao.
 */
final class HandoverService
{
    public function __construct(
        private readonly Intermediary $intermediary,
        private readonly StateMachine $stateMachine,
        private readonly ResponseInterpreter $interpreter,
        private readonly RetrySchedule $retrySchedule,
        private readonly bool $withIntentRecord = true,
    ) {}

    /**
     * Izdavanje dokumenta i predaja u jednom potezu.
     *
     * Vraca null samo u izvedbi bez zapisa namjere, kada odgovor ne stigne. Tada
     * u bazi ne postoji nista, i upravo je to nalaz.
     *
     * @param  array<string, mixed>  $invoiceData
     */
    public function issueAndHandOver(array $invoiceData, Intent $intent): ?Handover
    {
        if (! $this->withIntentRecord) {
            return $this->withoutIntentRecord($invoiceData, $intent);
        }

        // Dokument i namjera nastaju zajedno ili nijedno ne nastaje.
        $handover = DB::transaction(function () use ($invoiceData, $intent): Handover {
            $invoice = Invoice::create($invoiceData);

            return Handover::create([
                'invoice_id' => $invoice->id,
                'sender_key' => (string) Str::uuid(),
                'intent' => $intent,
                'state' => State::PREPARED,
            ]);
        });

        $this->record($handover, 'zapisana namjera prije predaje', null, $handover->state, true, $intent->description());

        return $this->attempt($handover);
    }

    /**
     * Ponovni pokusaj.
     *
     * Prvo se provjerava rok. Ako je istrosen, ne salje se nista nego se prelazi
     * u DEADLINE_EXPIRED -- prijelaz koji netko mora pokrenuti, a ne stanje u
     * koje se sklizne protekom vremena.
     */
    public function retry(Handover $handover): Handover
    {
        $now = CarbonImmutable::now();
        $deadline = $handover->deadline();

        if ($deadline !== null && $deadline->hasExpired($now)) {
            return $this->transition($handover, Trigger::DEADLINE_ELAPSED, $now,
                'rok iz cl. 49. st. 1. istrosen prije nego je potvrda stigla');
        }

        $handover->forceFill(['intent' => Intent::RETRY])->save();

        return $this->attempt($handover);
    }

    private function attempt(Handover $handover): Handover
    {
        $now = CarbonImmutable::now();

        $handover->forceFill(['attempt' => $handover->attempt + 1])->save();
        $handover = $this->transition($handover, Trigger::SENT, $now,
            sprintf('pokusaj br. %d', $handover->attempt));

        $response = $this->intermediary->handOver(
            $handover->sender_key,
            $handover->invoice->compositeIdentifier(),
        );

        return $this->receive($handover, $response, $now);
    }

    private function receive(Handover $handover, IntermediaryResponse $response, CarbonImmutable $now): Handover
    {
        // Izvedba bez zapisa namjere u trenutku primitka nema sto konzultirati.
        $intent = $this->withIntentRecord ? $handover->intent : null;
        $interpretation = $this->interpreter->interpret($response, $intent);

        $handover->forceFill(['last_response' => $response->value])->save();

        if (! $interpretation->unambiguous) {
            return $this->recordUnknownOutcome($handover, $response, $interpretation->reason, $now);
        }

        return $this->transition($handover, $interpretation->trigger, $now, $interpretation->reason);
    }

    /**
     * Stanje se ne mice. Zapisuje se sto sustav zna i sto ne zna.
     *
     * Ovdje se radi jedina stvar koju izostanak odgovora smije pokrenuti: ako
     * nemogucnost jos nije zabiljezena, biljezi se sada, jer od tog dana tece
     * rok iz cl. 49. st. 1.
     */
    private function recordUnknownOutcome(
        Handover $handover,
        IntermediaryResponse $response,
        string $reason,
        CarbonImmutable $now,
    ): Handover {
        if ($response === IntermediaryResponse::TIMEOUT && $handover->impossibility_onset_at === null) {
            $handover->forceFill(['impossibility_onset_at' => $now])->save();
            $handover->refresh();
        }

        $deadline = $handover->deadline();

        $handover->forceFill([
            'conclusion' => $reason,
            'next_attempt_at' => $deadline !== null
                ? $this->retrySchedule->deadlineAware($deadline, $now)
                : null,
        ])->save();

        $this->record($handover, 'tumacenje odgovora', $handover->state, $handover->state, false, $reason);

        Log::warning('[predaja] ishod nepoznat', [
            'kljuc' => $handover->sender_key,
            'odgovor' => $response->value,
            'stanje' => $handover->state->value,
            'obrazlozenje' => $reason,
        ]);

        return $handover;
    }

    private function transition(Handover $handover, Trigger $trigger, CarbonImmutable $now, string $reason): Handover
    {
        $before = $handover->state;
        $after = $this->stateMachine->apply($before, $trigger, $handover->deadline(), $now);

        $handover->forceFill([
            'state' => $after,
            'conclusion' => $reason,
            // Iz konacnog stanja nema prijelaza, pa ni zakazanog pokusaja.
            'next_attempt_at' => $after->isFinal() ? null : $handover->next_attempt_at,
        ])->save();

        $this->record($handover, $trigger->value, $before, $after, true, $reason);

        Log::info('[predaja] prijelaz', [
            'kljuc' => $handover->sender_key,
            'iz' => $before->value,
            'poticaj' => $trigger->value,
            'u' => $after->value,
        ]);

        return $handover;
    }

    /**
     * Izvedba u kojoj zapis namjere nastaje tek nakon odgovora.
     *
     * @param  array<string, mixed>  $invoiceData
     */
    private function withoutIntentRecord(array $invoiceData, Intent $intent): ?Handover
    {
        $invoice = Invoice::create($invoiceData);
        $senderKey = (string) Str::uuid();

        $response = $this->intermediary->handOver($senderKey, $invoice->compositeIdentifier());

        if ($response === IntermediaryResponse::TIMEOUT) {
            Log::warning('[predaja] odgovor nije stigao, a namjera nije bila zapisana', [
                'racun' => $invoice->document_number,
            ]);

            // Namjerno se ne zapisuje nista. Poslovni sustav od ovog trenutka ne
            // moze odgovoriti je li dokument predao.
            return null;
        }

        $handover = Handover::create([
            'invoice_id' => $invoice->id,
            'sender_key' => $senderKey,
            'intent' => $intent,
            'state' => State::SENT_UNCONFIRMED,
            'attempt' => 1,
        ]);

        return $this->receive($handover, $response, CarbonImmutable::now());
    }

    private function record(
        Handover $handover,
        string $name,
        ?State $before,
        ?State $after,
        bool $unambiguous,
        string $reason,
    ): void {
        AuditEntry::create([
            'handover_id' => $handover->id,
            'sequence' => $handover->auditEntries()->count() + 1,
            'name' => $name,
            'state_before' => $before?->value,
            'state_after' => $after?->value,
            'unambiguous' => $unambiguous,
            'reason' => $reason,
            'occurred_at' => CarbonImmutable::now(),
        ]);
    }
}
