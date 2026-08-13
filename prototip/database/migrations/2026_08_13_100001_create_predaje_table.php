<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odlazni pretinac i zapis namjere, u istoj tablici.
 *
 * Redak nastaje PRIJE predaje i u istoj transakciji kao dokument. Nakon svakog
 * prekida poslovni sustav moze utvrditi sto je namjeravao poslati, pa slucaj
 * E1 prestaje biti rupa.
 *
 * kljuc_posiljatelja postoji jer profil AS4 duplikate prepoznaje po
 * identifikatoru poruke, koji se pri ponavljanju na ovoj granici mijenja.
 * Potiskivanje se zato mora dogoditi na krajevima -- argument s kraja na kraj.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('predaje', function (Blueprint $tablica): void {
            $tablica->id();
            $tablica->foreignId('racun_id')->constrained('racuni')->cascadeOnDelete();

            $tablica->uuid('kljuc_posiljatelja')->unique();
            $tablica->string('namjera');
            $tablica->string('stanje');
            $tablica->unsignedInteger('pokusaj')->default(0);

            // Rok tece od nastupa nemogucnosti, ne od oporavka (cl. 49. st. 1.).
            // Zato se pamti trenutak nastupa, a ne trenutak zadnjeg pokusaja.
            $tablica->timestamp('nastanak_nemogucnosti')->nullable();
            $tablica->timestamp('sljedeci_pokusaj')->nullable();

            $tablica->string('zadnji_odgovor')->nullable();
            $tablica->text('zakljucak')->nullable();

            $tablica->timestamps();

            $tablica->index(['stanje', 'sljedeci_pokusaj']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('predaje');
    }
};
