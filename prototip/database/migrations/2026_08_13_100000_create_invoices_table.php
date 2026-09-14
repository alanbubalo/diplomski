<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Racun kao prvi entitet. Helland (odjeljak 5.1) trazi podjelu na entitete bez
 * transakcije preko njihove granice; ovdje su to dokument i njegova predaja,
 * pa dvije tablice.
 *
 * Dvije tablice ne znace dvije transakcijske granice: dokument i pocetni zapis
 * predaje nastaju u jednoj lokalnoj transakciji (HandoverService).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table): void {
            $table->id();

            $table->string('document_number');   // BT-1
            $table->date('issue_date');          // BT-2
            $table->string('document_type');     // BT-3
            $table->string('issuer_oib');        // BT-31
            $table->string('einvoice_type');

            // HR-CIUS drzi indikator kopije na 0..1, a cl. 43. st. 1. ga cini
            // obveznim (sukob iz 6.2). Stupac slijedi specifikaciju.
            $table->boolean('copy_indicator')->nullable();

            $table->timestamps();

            // Namjerno bez jedinstvenog indeksa nad slozenim identifikatorom:
            // sudar se dogada kod primatelja, pa prototip mora moci poslati
            // poruku koja ce biti odbijena sifrom S008.
            $table->index(['issuer_oib', 'document_number', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
