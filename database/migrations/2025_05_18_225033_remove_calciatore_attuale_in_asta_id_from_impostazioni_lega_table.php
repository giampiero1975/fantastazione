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
    // database/migrations/2025_05_18_225033_remove_calciatore_attuale_in_asta_id_from_impostazioni_lega_table.php
    public function up()
    {
        Schema::table('impostazioni_lega', function (Blueprint $table) {
            // Determina il nome esatto della foreign key constraint.
            // Di solito è qualcosa come 'nomeTabella_nomeColonna_foreign'.
            // Puoi trovarlo ispezionando la struttura della tua tabella con uno strumento DB
            // o guardando la migrazione originale che l'ha creata.
            // Assumiamo sia 'impostazioni_lega_calciatore_attuale_in_asta_id_foreign'
            // Se il tuo DB è SQLite e stai testando, potrebbe comportarsi diversamente
            // con dropForeign se la colonna viene rimossa nella stessa operazione,
            // ma per MySQL/PostgreSQL è meglio essere espliciti.
            
            // Prova prima a droppare la foreign key specificando la colonna.
            // Laravel tenterà di indovinare il nome del vincolo.
            $table->dropForeign(['calciatore_attuale_in_asta_id']);
            $table->dropColumn('calciatore_attuale_in_asta_id');
        });
    }
};
