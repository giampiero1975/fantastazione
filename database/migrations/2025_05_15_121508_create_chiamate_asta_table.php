<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('chiamate_asta', function (Blueprint $table) {
            $table->id(); // Chiave primaria auto-incrementante
            
            $table->foreignId('calciatore_id')
            ->constrained('calciatori') // Fa riferimento alla tabella 'calciatori', colonna 'id'
            ->onDelete('cascade'); // Se il calciatore viene cancellato, cancella anche la chiamata
            
            $table->foreignId('user_id_chiamante')
            ->constrained('users') // Fa riferimento alla tabella 'users', colonna 'id'
            ->onDelete('cascade'); // Se l'utente chiamante viene cancellato, cancella la chiamata
            
            // Potremmo voler legare la chiamata a una specifica "sessione d'asta" o a un "tag_lista"
            // per chiarezza e per gestire aste storiche.
            $table->string('tag_lista_calciatori'); // Es. "Stagione_2024_25"
            
            $table->enum('stato_chiamata', [
                'in_attesa_admin',          // Chiamato dalla squadra, aspetta l'ok dell'admin
                'in_asta_tap_live',         // L'asta tap è attiva per questo giocatore
                'conclusa_tap_assegnato',   // Asta tap conclusa, giocatore assegnato
                'conclusa_tap_non_assegnato',// Asta tap conclusa, nessuno ha offerto/vinto
                'annullata_admin'           // L'admin ha annullato la chiamata/asta
            ])->default('in_attesa_admin');
            
            // Campi specifici per lo stato dell'asta TAP quando è 'in_asta_tap_live'
            $table->integer('prezzo_partenza_tap')->nullable();
            $table->integer('prezzo_attuale_tap')->nullable();
            
            $table->unsignedBigInteger('miglior_offerente_tap_id')->nullable();
            $table->foreign('miglior_offerente_tap_id')
            ->references('id')->on('users')
            ->onDelete('set null'); // Se l'utente miglior offerente viene cancellato, l'offerta rimane ma senza riferimento diretto
            
            $table->timestamp('timestamp_fine_tap_prevista')->nullable(); // Quando scade il countdown
            
            // Campi per registrare l'esito finale dell'asta TAP
            $table->unsignedBigInteger('vincitore_user_id')->nullable();
            $table->foreign('vincitore_user_id')
            ->references('id')->on('users')
            ->onDelete('set null'); // Se l'utente vincitore viene cancellato
            
            $table->integer('prezzo_finale_assegnazione')->nullable();
            
            $table->timestamps(); // created_at (quando la chiamata è stata fatta) e updated_at
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('chiamate_asta');
    }
};