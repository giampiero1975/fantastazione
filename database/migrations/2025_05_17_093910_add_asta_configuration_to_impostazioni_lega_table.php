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
        Schema::table('impostazioni_lega', function (Blueprint $table) {
            // Assumiamo che 'prossimo_turno_chiamata_user_id' esista già e sia un FK nullable
            // come da migrazione 2025_05_09_130105_create_impostazioni_lega_table.php
            
            // Aggiungiamo i nuovi campi:
            $table->boolean('usa_ordine_chiamata')->default(false)->after('asta_tap_approvazione_admin'); // o un'altra colonna di riferimento
            $table->enum('tipo_base_asta', ['quotazione_iniziale', 'credito_singolo'])
            ->default('quotazione_iniziale') // O 'credito_singolo' se preferisci
            ->after('usa_ordine_chiamata');
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('impostazioni_lega', function (Blueprint $table) {
            $table->dropColumn(['usa_ordine_chiamata', 'tipo_base_asta']);
        });
    }
};