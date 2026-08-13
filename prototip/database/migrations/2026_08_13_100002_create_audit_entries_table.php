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
        Schema::create('audit_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('handover_id')->constrained()->cascadeOnDelete();

            $table->unsignedInteger('sequence');
            $table->string('name');
            $table->string('state_before')->nullable();
            $table->string('state_after')->nullable();
            $table->boolean('unambiguous')->default(true);
            $table->text('reason')->nullable();
            $table->timestamp('occurred_at');

            $table->unique(['handover_id', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_entries');
    }
};
