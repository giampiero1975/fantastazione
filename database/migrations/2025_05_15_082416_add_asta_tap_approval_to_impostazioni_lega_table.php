<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up(): void
    {
        // Schema::table('impostazioni_lega', function (Blueprint $table) {
        //     // Lascia commentato o vuoto se le colonne esistono già nel DB
        //     // e questa migrazione è stata segnata come eseguita.
        // });
    }
    
    public function down(): void
    {
        Schema::table('impostazioni_lega', function (Blueprint $table) {
            // Assicurati che questo sia corretto per rimuovere i campi se necessario
            $table->dropColumn([
                'tag_lista_attiva',
                'modalita_asta',
                'durata_countdown_secondi',
                'asta_tap_approvazione_admin'
            ]);
        });
    }
};
