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
    public function up()
    {
        Schema::table('impostazioni_lega', function (Blueprint $table) {
            // Rimuovi prima la foreign key se esiste ancora formalmente
            // Il nome della foreign key potrebbe variare, controlla la tua migrazione originale
            // $table->dropForeign(['calciatore_attuale_in_asta_id']); // Adatta il nome se necessario
            // Per sicurezza, potresti dover specificare il nome del vincolo esatto:
            // $table->dropForeign('impostazioni_lega_calciatore_attuale_in_asta_id_foreign');
            $table->dropColumn('calciatore_attuale_in_asta_id');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('impostazioni_lega', function (Blueprint $table) {
            $table->unsignedBigInteger('calciatore_attuale_in_asta_id')->nullable();
            $table->foreign('calciatore_attuale_in_asta_id')
            ->references('id')->on('calciatori')
            ->onDelete('set null');
        });
    }
};
