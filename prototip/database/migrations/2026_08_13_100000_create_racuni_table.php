<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Racun kao prvi entitet.
 *
 * Helland (odjeljak 5.1) trazi da se sustav podijeli na entitete, svaki sa
 * svojim opsegom serijalizacije, a da transakcije preko granice nema. Ovdje
 * su entiteti dva: sam dokument i njegova predaja. Zato dvije tablice, a ne
 * jedna sira.
 *
 * Cetiri polja slozenog identifikatora nose oznake iz norme, jer se S008
 * provjerava tocno po njima uz vrstu eRacuna.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('racuni', function (Blueprint $tablica): void {
            $tablica->id();

            $tablica->string('broj_dokumenta');          // BT-1
            $tablica->date('datum_izdavanja');           // BT-2
            $tablica->string('vrsta_dokumenta');         // BT-3
            $tablica->string('oib_izdavatelja');         // BT-31
            $tablica->string('vrsta_eracuna');

            // HR-CIUS drzi indikator kopije na 0..1, a cl. 43. st. 1. ga cini
            // obveznim. Sukob je opisan u 6.2. Stupac je nullable jer slijedi
            // specifikaciju, i time prototip stoji na jednoj strani sukoba.
            $tablica->boolean('indikator_kopije')->nullable();

            $tablica->timestamps();

            // NAMJERNO BEZ jedinstvenog indeksa nad slozenim identifikatorom.
            // Sudar se dogada kod primatelja, i prototip mora moci poslati
            // poruku koja ce biti odbijena sifrom S008.
            $tablica->index(['oib_izdavatelja', 'broj_dokumenta', 'datum_izdavanja']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('racuni');
    }
};
