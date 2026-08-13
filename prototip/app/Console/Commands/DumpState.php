<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\AuditEntry;
use App\Models\Handover;
use App\Models\Invoice;
use Illuminate\Console\Command;

/**
 * Ispis zavrsnog stanja baze nakon scenarija.
 *
 * Ovo je prva polovica dokaznog materijala poglavlja 7; druga je isjecak
 * dnevnika. Ispis se namjerno ne uljepsava: pokazuje redke onako kako stoje,
 * ukljucujuci dogadaje ciji zakljucak nije bio jednoznacan.
 */
final class DumpState extends Command
{
    protected $signature = 'state';

    protected $description = 'Ispisuje zavrsno stanje racuna, predaja i evidencije';

    public function handle(): int
    {
        $this->newLine();

        $this->line('  RACUNI');
        $this->table(
            ['id', 'broj', 'datum', 'vrsta', 'ind. kopije'],
            Invoice::query()->get()->map(fn (Invoice $invoice): array => [
                (string) $invoice->id,
                $invoice->document_number,
                $invoice->issue_date->toDateString(),
                $invoice->document_type,
                $invoice->copy_indicator === null ? '-' : ($invoice->copy_indicator ? 'true' : 'false'),
            ])->all(),
        );

        $this->line('  PREDAJE');
        $this->table(
            ['id', 'racun', 'namjera', 'stanje', 'pok.', 'odgovor', 'ishod poznat'],
            Handover::query()->with('invoice')->get()->map(fn (Handover $handover): array => [
                (string) $handover->id,
                $handover->invoice->document_number,
                $handover->intent->value,
                $handover->state->value,
                (string) $handover->attempt,
                $handover->last_response ?? '-',
                $handover->state->outcomeKnown() ? 'da' : 'ne',
            ])->all(),
        );

        $this->line('  EVIDENCIJA');
        $this->table(
            ['predaja', 'br.', 'dogadaj', 'iz', 'u', 'jednoznacno'],
            AuditEntry::query()->orderBy('handover_id')->orderBy('sequence')->get()
                ->map(fn (AuditEntry $entry): array => [
                    (string) $entry->handover_id,
                    (string) $entry->sequence,
                    $entry->name,
                    $entry->state_before ?? '-',
                    $entry->state_after ?? '-',
                    $entry->unambiguous ? 'da' : 'NE',
                ])->all(),
        );

        $ambiguous = AuditEntry::query()->where('unambiguous', false)->count();

        $this->line(sprintf(
            '  Zapisanih trenutaka u kojima sustav nije znao ishod: %d.',
            $ambiguous,
        ));
        $this->newLine();

        return self::SUCCESS;
    }
}
