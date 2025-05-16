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
        Schema::table('calciatori', function (Blueprint $table) {
            // 1. Rimuovi il vecchio indice univoco
            $table->dropUnique('calciatori_id_esterno_giocatore_unique'); // Usa il nome esatto del tuo indice
            
            // 2. Aggiungi il nuovo indice univoco sulla combinazione delle due colonne
            $table->unique(['id_esterno_giocatore', 'tag_lista_inserimento'], 'calciatori_id_esterno_tag_unique'); // Nome nuovo indice
        });
    }
    // Nel down(), fai l'inverso (rimuovi il nuovo, riaggiungi il vecchio)
    public function down(): void
    {
        Schema::table('calciatori', function (Blueprint $table) {
            $table->dropUnique('calciatori_id_esterno_tag_unique');
            $table->unique('id_esterno_giocatore', 'calciatori_id_esterno_giocatore_unique');
        });
    }
};
