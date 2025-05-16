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
            // Aggiungiamo i nuovi campi dopo una colonna esistente, es. 'num_attaccanti'
            // o alla fine se preferisci (omettendo ->after())
            
            $table->string('tag_lista_attiva')->nullable()->after('num_attaccanti');
            $table->enum('modalita_asta', ['voce', 'tap'])->default('voce')->after('tag_lista_attiva');
            $table->integer('durata_countdown_secondi')->default(60)->after('modalita_asta');
            $table->boolean('asta_tap_approvazione_admin')->default(true)->after('durata_countdown_secondi');
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('impostazioni_lega', function (Blueprint $table) {
            $table->dropColumn([
                'tag_lista_attiva',
                'modalita_asta',
                'durata_countdown_secondi',
                'asta_tap_approvazione_admin'
            ]);
        });
    }
};