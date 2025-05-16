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
        Schema::create('users', function (Blueprint $table) {
            $table->id(); // Chiave primaria della tabella users
            $table->string('name'); // Nome dell'utente o della squadra
            $table->string('email')->unique(); // Email per il login, deve essere unica
            $table->timestamp('email_verified_at')->nullable(); // Per la verifica dell'email
            $table->string('password'); // Password (verrà hashata)
            $table->rememberToken(); // Per la funzionalità "Ricordami"
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
        Schema::dropIfExists('users');
    }
};
