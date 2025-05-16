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
        Schema::create('calciatori', function (Blueprint $table) {
            $table->id(); // Chiave primaria della tabella calciatori stessa
            $table->string('id_esterno_giocatore')->unique(); // ID dal CSV
            $table->string('nome_completo');
            $table->enum('ruolo', ['P', 'D', 'C', 'A']);
            $table->string('squadra_serie_a');
            $table->integer('quotazione_iniziale')->default(1);
            $table->string('tag_lista_inserimento')->nullable();
            $table->boolean('attivo')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('calciatori');
    }
};
