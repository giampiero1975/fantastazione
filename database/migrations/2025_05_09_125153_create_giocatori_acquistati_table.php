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
        Schema::create('giocatori_acquistati', function (Blueprint $table) {
            $table->id(); // Chiave primaria auto-incrementante per la tabella giocatori_acquistati
            
            // --- Definizione della Foreign Key per l'utente/squadra ---
            $table->unsignedBigInteger('user_id'); // La colonna per la chiave esterna
            $table->foreign('user_id')              // Questa colonna ('user_id')...
            ->references('id')->on('users')  // ...fa riferimento alla colonna 'id' della tabella 'users'.
            ->onDelete('cascade');           // Azione se l'utente (squadra) referenziato viene cancellato:
            // cancella anche tutti i record di acquisto di quella squadra.
            
            // --- Definizione della Foreign Key per il calciatore ---
            $table->unsignedBigInteger('calciatore_id'); // La colonna per la chiave esterna
            $table->foreign('calciatore_id')             // Questa colonna ('calciatore_id')...
            ->references('id')->on('calciatori') // ...fa riferimento alla colonna 'id' della tabella 'calciatori'.
            ->onDelete('cascade');              // Azione se il calciatore referenziato viene cancellato:
            // cancella anche tutti i record di acquisto relativi a quel calciatore
            // (ad esempio, se un calciatore viene rimosso dal database del gioco).
            
            $table->integer('prezzo_acquisto');
            $table->enum('ruolo_al_momento_acquisto', ['P', 'D', 'C', 'A'])->nullable(); // Ruolo con cui è stato preso, opzionale
            
            $table->timestamps(); // created_at e updated_at
            
            // --- Vincolo di unicità ---
            // Per assicurarsi che una squadra non possa acquistare lo stesso giocatore più volte.
            $table->unique(['user_id', 'calciatore_id'], 'user_calciatore_unique');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('giocatori_acquistati');
    }
};
