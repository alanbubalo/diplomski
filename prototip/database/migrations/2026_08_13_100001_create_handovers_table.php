<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Odlazni pretinac i zapis namjere, u istoj tablici.
 *
 * Redak nastaje PRIJE predaje i u istoj transakciji kao dokument. Nakon svakog
 * prekida poslovni sustav moze utvrditi sto je namjeravao poslati, pa slucaj E1
 * prestaje biti rupa.
 *
 * sender_key postoji jer profil AS4 duplikate prepoznaje po identifikatoru
 * poruke, koji se pri ponavljanju na ovoj granici mijenja. Potiskivanje se zato
 * mora dogoditi na krajevima -- argument s kraja na kraj.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('handovers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();

            $table->uuid('sender_key')->unique();
            $table->string('intent');
            $table->string('state');
            $table->unsignedInteger('attempt')->default(0);

            // Rok tece od nastupa nemogucnosti, ne od oporavka (cl. 49. st. 1.).
            // Zato se pamti trenutak nastupa, a ne trenutak zadnjeg pokusaja.
            $table->timestamp('impossibility_onset_at')->nullable();
            $table->timestamp('next_attempt_at')->nullable();

            $table->string('last_response')->nullable();
            $table->text('conclusion')->nullable();

            $table->timestamps();

            $table->index(['state', 'next_attempt_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('handovers');
    }
};
