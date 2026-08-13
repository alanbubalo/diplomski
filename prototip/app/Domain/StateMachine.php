<?php

declare(strict_types=1);

namespace App\Domain;

use Carbon\CarbonInterface;
use DomainException;

/**
 * Automat zivotnog ciklusa predaje.
 *
 * Schneiderova karakterizacija (odjeljak 5.3) trazi da izlazi budu odredeni
 * NIZOM OBRADENIH ZAHTJEVA. Iz toga slijede dva pravila koja ovaj razred
 * provodi doslovno:
 *
 *   1. Prijelaz pokrece poticaj, nikada protek vremena sam po sebi. Zato je
 *      DEADLINE_ELAPSED poticaj koji netko mora dostaviti, a ne pozadinski
 *      posao koji tise mijenja redak u bazi.
 *   2. Rok ulazi kao UVJET NAD PRIJELAZOM. Ponovno slanje je dopusteno samo dok
 *      roka ima; kad ga nema, jedini dopusten prijelaz vodi u DEADLINE_EXPIRED.
 *
 * Nedopusten prijelaz je iznimka, ne tiho ignoriranje. Sustav koji propusti
 * nedopusten prijelaz prestaje biti dokaz o vlastitom stanju.
 */
final class StateMachine
{
    /** @var array<string, array<string, State>> */
    private const TRANSITIONS = [
        State::PREPARED->value => [
            Trigger::SENT->value => State::SENT_UNCONFIRMED,
        ],
        State::SENT_UNCONFIRMED->value => [
            Trigger::SENT->value => State::SENT_UNCONFIRMED,
            Trigger::CONFIRMATION->value => State::CONFIRMED,
            Trigger::CONFIRMATION_FROM_S008->value => State::CONFIRMED,
            Trigger::REJECTION_FROM_S008->value => State::CORRECTION_REJECTED,
            Trigger::DEADLINE_ELAPSED->value => State::DEADLINE_EXPIRED,
        ],
    ];

    public function allows(State $from, Trigger $trigger): bool
    {
        return isset(self::TRANSITIONS[$from->value][$trigger->value]);
    }

    /**
     * $deadline je null dok nemogucnost nije nastupila -- tada nema od cega
     * teci, pa uvjet roka otpada.
     *
     * @throws DomainException kada prijelaz nije dopusten ili uvjet roka ne prolazi
     */
    public function apply(State $from, Trigger $trigger, ?Deadline $deadline, CarbonInterface $now): State
    {
        if (! $this->allows($from, $trigger)) {
            throw new DomainException(
                sprintf('Prijelaz %s --%s--> nije dopusten.', $from->value, $trigger->value)
            );
        }

        $this->checkDeadlineGuard($trigger, $deadline, $now);

        return self::TRANSITIONS[$from->value][$trigger->value];
    }

    /** Rok kao uvjet nad prijelazom, ne kao stanje. */
    private function checkDeadlineGuard(Trigger $trigger, ?Deadline $deadline, CarbonInterface $now): void
    {
        if ($deadline === null) {
            if ($trigger === Trigger::DEADLINE_ELAPSED) {
                throw new DomainException('Rok nije poceo teci: nemogucnost nije nastupila.');
            }

            return;
        }

        $expired = $deadline->hasExpired($now);

        if ($trigger === Trigger::SENT && $expired) {
            throw new DomainException(
                'Ponovno slanje nije dopusteno: rok iz cl. 49. st. 1. je istrosen.'
            );
        }

        if ($trigger === Trigger::DEADLINE_ELAPSED && ! $expired) {
            throw new DomainException(
                sprintf('Rok jos tece, preostalo %d s.', $deadline->remainingSeconds($now))
            );
        }
    }
}
