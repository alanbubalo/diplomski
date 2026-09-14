<?php

declare(strict_types=1);

namespace App\Domain;

use Carbon\CarbonInterface;
use DomainException;

/**
 * Automat zivotnog ciklusa predaje. Schneiderova karakterizacija (odjeljak 5.3)
 * trazi da izlaze odreduje niz obradenih zahtjeva, pa vrijede dva pravila:
 *
 *   1. Prijelaz pokrece poticaj, nikada protek vremena sam po sebi. Zato je i
 *      DEADLINE_ELAPSED poticaj koji netko mora dostaviti.
 *   2. Rok ulazi kao uvjet nad prijelazom. Dok roka ima, salje se; kad ga nema,
 *      jedini dopusten prijelaz vodi u DEADLINE_EXPIRED.
 *
 * Zaustavljanje slanja nakon roka je granica prototipa, ne zakonska zabrana.
 * Nedopusten prijelaz baca iznimku umjesto da se tiho preskoci.
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
     * $deadline je null dok nemogucnost nije nastupila; tada uvjet roka otpada.
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
            // Granica prototipa, ne zakonska zabrana slanja. Vidi opis razreda.
            throw new DomainException(
                'Prototip ne salje nakon isteka roka iz cl. 49. st. 1.:'
                .' novi pokusaj vise ne bi bio unutar roka.'
            );
        }

        if ($trigger === Trigger::DEADLINE_ELAPSED && ! $expired) {
            throw new DomainException(
                sprintf('Rok jos tece, preostalo %d s.', $deadline->remainingSeconds($now))
            );
        }
    }
}
