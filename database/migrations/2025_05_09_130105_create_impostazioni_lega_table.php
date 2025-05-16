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
        Schema::create('impostazioni_lega', function (Blueprint $table) {
            $table->id(); // Chiave primaria per la tabella impostazioni_lega
            $table->enum('fase_asta_corrente', ['PRE_ASTA', 'P', 'D', 'C', 'A', 'CONCLUSA'])->default('PRE_ASTA');
            // ... altre colonne non-foreign key come crediti_iniziali_lega, num_portieri, etc. ...
            
            // --- Definizione della Foreign Key per il calciatore in asta ---
            $table->unsignedBigInteger('calciatore_attuale_in_asta_id')->nullable(); // La colonna DEVE essere nullable se onDelete è 'set null'
            $table->foreign('calciatore_attuale_in_asta_id')  // Questa colonna ('calciatore_attuale_in_asta_id')...
            ->references('id')->on('calciatori')      // ...fa riferimento alla colonna 'id' della tabella 'calciatori'.
            ->onDelete('set null');                   // Azione se il calciatore referenziato viene cancellato: imposta 'calciatore_attuale_in_asta_id' a NULL.
            
            // --- Definizione della Foreign Key per l'utente del prossimo turno ---
            $table->unsignedBigInteger('prossimo_turno_chiamata_user_id')->nullable(); // La colonna DEVE essere nullable se onDelete è 'set null'
            $table->foreign('prossimo_turno_chiamata_user_id') // Questa colonna ('prossimo_turno_chiamata_user_id')...
            ->references('id')->on('users')            // ...fa riferimento alla colonna 'id' della tabella 'users'.
            ->onDelete('set null');                     // Azione se l'utente referenziato viene cancellato: imposta 'prossimo_turno_chiamata_user_id' a NULL.
            
            // Colonne per le regole della lega che abbiamo discusso precedentemente
            $table->integer('crediti_iniziali_lega')->default(500);
            $table->tinyInteger('num_portieri')->default(6);
            $table->tinyInteger('num_difensori')->default(8);
            $table->tinyInteger('num_centrocampisti')->default(8);
            $table->tinyInteger('num_attaccanti')->default(6);
            
            $table->timestamps(); // created_at e updated_at
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('impostazioni_lega');
    }
};
