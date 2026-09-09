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
 * Predaja dokumenta posredniku, u dvije izvedbe koje se razlikuju u jednoj
 * jedinoj stvari: pise li se zapis namjere PRIJE predaje ili POSLIJE odgovora.
 *
 * Ta razlika je cijeli dokazni sadrzaj poglavlja 7. Iz nje slijede sva tri
 * kontrasta koja scenariji pokazuju:
 *
 *   B1  bez zapisa izostanak odgovora ne ostavlja nikakav trag; sa zapisom
 *       dokument sjedi u imenovanom stanju SENT_UNCONFIRMED
 *   E1  bez zapisa poslovni sustav ne moze odgovoriti je li dokument predao
 *   B2  bez zapisa nema rednog pokusaja dostave, pa ponovljena dostava
 *       izvornika ostaje dvoznacna; sa zapisom je jednoznacna
 *
 * ⚠ USPOREDBA MORA BITI POSTENA. Izvedba bez zapisa namjere tumaci odgovor iz
 * svega sto joj stvarno ostaje, ukljucujuci spremljeni dokument: poslovna
 * namjera se iz indikatora kopije MOZE izvesti (Invoice::derivedIntent()).
 * Uskracivanje tog konteksta dalo bi kontrast koji dokazuje samo da mu je
 * kontekst uskracen.
 *
 * Ono sto se iz dokumenta ne moze izvesti je REDNI POKUSAJ DOSTAVE. Prva i
 * ponovljena dostava istog ispravka nose isti dokument. Ta razlika zivi samo u
 * zapisu predaje, a zapis nastao TEK NAKON odgovora ne nastaje uopce kada
 * odgovora nema. Upravo zato zapis mora nastati prije predaje: slucaj u kojem
 * je najpotrebniji je slucaj u kojem se poslije nikad ne bi napisao.
 *
 * Zapis namjere pise se u istoj transakciji kao i sam dokument. Prekid izmedu te
 * dvije radnje inace je najgore mjesto na kojem se dvostruki zapis moze
 * pojaviti: sustav koji je dokument izdao ne bi znao je li ga i predao.
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
     *
     * Zatim se provjerava zakazani trenutak. Pokusaj prije njega nije dopusten,
     * jer bi inace raspored iz RetrySchedule bio ukras: sustav bi ga izracunao i
     * ne bi ga se drzao. Raniji pokusaj trazi dogadaj koji ga opravdava, a to je
     * destinationRecovered().
     *
     * Poslovna namjera se pritom NE MIJENJA. Ponavljanje je redni pokusaj
     * dostave, ne nova vrsta dokumenta, pa ponovljena dostava ispravka ostaje
     * ispravak.
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
     * Vanjski dogadaj: odrediste je opet dostupno.
     *
     * Raspored iz RetrySchedule zakazuje pokusaj za slucaj u kojem se o
     * odredistu ne zna nista novo. Kada se sazna da je odrediste opet dostupno,
     * cekanje na taj trenutak nema svrhe. Dogadaj se zato biljezi i premjesta
     * zakazani pokusaj na sada.
     *
     * Bez ovog dogadaja scenarij D1 pokretao bi ponavljanje ranije od
     * zakazanoga, bez icega u evidenciji sto bi taj raniji poziv opravdalo.
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
        // Obje izvedbe tumace odgovor iz onoga sto im STVARNO stoji na
        // raspolaganju. Ako namjera nije zapisana, izvodi se iz spremljenog
        // dokumenta, jer je indikator kopije polje eRacuna i ima ga i izvedba
        // koja namjeru nije zapisala. Uskracivanje tog konteksta dalo bi
        // kontrast koji ne dokazuje nista o trajnosti zapisa.
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
     * Izvedba u kojoj zapis predaje nastaje tek nakon odgovora.
     *
     * Stupac namjere ostaje prazan. Izvedba koja namjeru nije zapisala prije
     * slanja ne moze je zapisati poslije: predaja bez potvrde nikada ne dode do
     * zapisivanja, a i kad dode, zapisala bi ono sto vise nije provjerljivo.
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

            // Namjerno se ne zapisuje nista. Poslovni sustav od ovog trenutka ne
            // moze odgovoriti je li dokument predao.
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
