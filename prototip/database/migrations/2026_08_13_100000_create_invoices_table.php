<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Racun kao prvi entitet.
 *
 * Helland (odjeljak 5.1) trazi da se sustav podijeli na entitete, svaki sa
 * svojim opsegom serijalizacije, a da transakcije preko granice nema. Ovdje su
 * entiteti dva: sam dokument i njegova predaja. Zato dvije tablice, a ne jedna
 * sira.
 *
 * Cetiri polja slozenog identifikatora nose oznake iz norme, jer se S008
 * provjerava tocno po njima uz vrstu eRacuna.
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
            // obveznim. Sukob je opisan u 6.2. Stupac je nullable jer slijedi
            // specifikaciju, i time prototip stoji na jednoj strani sukoba.
            $table->boolean('copy_indicator')->nullable();

            $table->timestamps();

            // NAMJERNO BEZ jedinstvenog indeksa nad slozenim identifikatorom.
            // Sudar se dogada kod primatelja, i prototip mora moci poslati
            // poruku koja ce biti odbijena sifrom S008.
            $table->index(['issuer_oib', 'document_number', 'issue_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
