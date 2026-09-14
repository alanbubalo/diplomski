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
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Predaja dokumenta odredistu, u dvije izvedbe koje se razlikuju u jednome:
 * pise li se zapis namjere prije predaje ili tek nakon odgovora. Iz te razlike
 * slijede tri kontrasta koja scenariji poglavlja 7 pokazuju:
 *
 *   B1  bez zapisa izostanak odgovora ne ostavlja trag; sa zapisom dokument
 *       sjedi u imenovanom stanju SENT_UNCONFIRMED
 *   E1  bez zapisa poslovni sustav ne moze odgovoriti je li dokument predao
 *   B2  bez zapisa nema rednog pokusaja dostave, pa ponovljena dostava
 *       izvornika ostaje dvoznacna
 *
 * Usporedba je postena: izvedba bez zapisa tumaci odgovor iz svega sto joj
 * ostaje, ukljucujuci spremljeni dokument (Invoice::derivedIntent()). Ne ostaje
 * joj redni pokusaj dostave, jer zapis nastao tek nakon odgovora ne nastaje
 * uopce kada odgovora nema.
 */
final class HandoverService
{
    public function __construct(
        private readonly SubmissionEndpoint $endpoint,
        private readonly StateMachine $stateMachine,
        private readonly ResponseInterpreter $interpreter,
        private readonly RetrySchedule $retrySchedule,
        private readonly bool $withIntentRecord = true,
    ) {}

    /**
     * Izdavanje dokumenta i predaja u jednom potezu. Vraca null samo u izvedbi
     * bez zapisa namjere, kada odgovor ne stigne: tada u bazi ne ostaje nista.
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
     * Ponovni pokusaj. Istrosen rok vodi u DEADLINE_EXPIRED umjesto u slanje.
     * Pokusaj prije zakazanog trenutka nije dopusten bez dogadaja koji ga
     * opravdava (destinationRecovered), inace bi raspored bio ukras.
     *
     * Poslovna namjera se pritom ne mijenja.
     */
    public function retry(Handover $handover): Handover
    {
        $now = CarbonImmutable::now();
        $deadline = $handover->deadline();

        if ($deadline !== null && $deadline->hasExpired($now)) {
            return $this->transition($handover, Trigger::DEADLINE_ELAPSED, $now,
                'rok iz cl. 49. st. 1. istekao prije nego je potvrda stigla');
        }

        if ($handover->next_attempt_at !== null && $now->lessThan($handover->next_attempt_at)) {
            throw new DomainException(sprintf(
                'Pokusaj prije zakazanog trenutka (%s) nije dopusten bez dogadaja oporavka.',
                $handover->next_attempt_at->toDateTimeString(),
            ));
        }

        return $this->attempt($handover);
    }

    /**
     * Vanjski dogadaj: odrediste je opet dostupno. Raspored zakazuje pokusaj za
     * slucaj u kojem se o odredistu ne zna nista novo, pa se saznanje biljezi i
     * zakazani pokusaj premjesta na sada.
     */
    public function destinationRecovered(Handover $handover): Handover
    {
        $now = CarbonImmutable::now();

        $handover->forceFill(['next_attempt_at' => $now])->save();

        $this->record($handover, 'dogadaj oporavka odredista', $handover->state, $handover->state, true,
            'odrediste je opet dostupno, pa se zakazani pokusaj premjesta na sada');

        Log::info('[predaja] odrediste je opet dostupno', [
            'kljuc' => $handover->sender_key,
            'stanje' => $handover->state->value,
        ]);

        return $handover;
    }

    private function attempt(Handover $handover): Handover
    {
        $now = CarbonImmutable::now();

        $handover->forceFill(['attempt' => $handover->attempt + 1])->save();
        $handover = $this->transition($handover, Trigger::SENT, $now,
            sprintf('pokusaj br. %d', $handover->attempt));

        $response = $this->endpoint->send(
            $handover->sender_key,
            $handover->invoice->compositeIdentifier(),
        );

        return $this->receive($handover, $response, $now);
    }

        private function receive(Handover $handover, IntermediaryResponse $response, CarbonImmutable $now): Handover
    {
        // Ako namjera nije zapisana, izvodi se iz spremljenog dokumenta, jer
        // je indikator kopije polje eRacuna i ima ga i takva izvedba.
        $intent = $handover->intent ?? $handover->invoice->derivedIntent();

        $interpretation = $this->interpreter->interpret(
            $response,
            $intent,
            repeatDelivery: $handover->isRepeatDelivery(),
        );

        $handover->forceFill(['last_response' => $response->value])->save();

        if (! $interpretation->unambiguous) {
            return $this->recordUnknownOutcome($handover, $response, $interpretation->reason, $now);
        }

        return $this->transition($handover, $interpretation->trigger, $now, $interpretation->reason);
    }

    /**
     * Stanje se ne mice; zapisuje se sto sustav zna i sto ne zna. Jedino sto
     * izostanak odgovora smije pokrenuti je biljezenje nastupa nemogucnosti,
     * jer od tog dana tece rok iz cl. 49. st. 1.
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
     * Izvedba u kojoj zapis predaje nastaje tek nakon odgovora. Stupac namjere
     * ostaje prazan, a predaja bez potvrde nikada ne dode do zapisivanja.
     *
     * @param  array<string, mixed>  $invoiceData
     */
    private function withoutIntentRecord(array $invoiceData, Intent $intent): ?Handover
    {
        $invoice = Invoice::create($invoiceData);
        $senderKey = (string) Str::uuid();

        $response = $this->endpoint->send($senderKey, $invoice->compositeIdentifier());

        if ($response === IntermediaryResponse::TIMEOUT) {
            Log::warning('[predaja] odgovor nije stigao, a namjera nije bila zapisana', [
                'racun' => $invoice->document_number,
                'namjera koja se nije zapisala' => $intent->value,
            ]);

            // Namjerno se ne zapisuje nista: od ovog trenutka poslovni sustav
            // ne moze odgovoriti je li dokument predao.
            return null;
        }

        $handover = Handover::create([
            'invoice_id' => $invoice->id,
            'sender_key' => $senderKey,
            'intent' => null,
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
