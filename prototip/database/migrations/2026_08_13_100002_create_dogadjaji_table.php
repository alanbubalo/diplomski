<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidencija kao ostatak.
 *
 * Kada se dio slucajeva ne moze ukloniti, preostaje da se stanje uoci i
 * zabiljezi. Ova tablica je taj ostatak: niz dogadaja koji objasnjava kako je
 * predaja dosla u zavrsno stanje, ukljucujuci trenutke u kojima sustav nije
 * znao sto se dogodilo.
 *
 * Zapisuje se i tumacenje koje NIJE bilo jednoznacno. Presucen trenutak
 * neznanja je upravo ono sto evidencija treba sacuvati.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dogadjaji', function (Blueprint $tablica): void {
            $tablica->id();
            $tablica->foreignId('predaja_id')->constrained('predaje')->cascadeOnDelete();

            $tablica->unsignedInteger('redni_broj');
            $tablica->string('naziv');
            $tablica->string('stanje_prije')->nullable();
            $tablica->string('stanje_poslije')->nullable();
            $tablica->boolean('jednoznacno')->default(true);
            $tablica->text('obrazlozenje')->nullable();
            $tablica->timestamp('nastao_u');

            $tablica->unique(['predaja_id', 'redni_broj']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dogadjaji');
    }
};
