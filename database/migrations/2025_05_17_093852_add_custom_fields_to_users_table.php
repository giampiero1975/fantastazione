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
        Schema::table('users', function (Blueprint $table) {
            // Dopo 'crediti_rimanenti' o dove preferisci
            $table->integer('ordine_chiamata')->nullable()->unique()->after('crediti_rimanenti');
            $table->string('nome_proprietario')->nullable()->after('name');
            $table->string('phone_number')->nullable()->after('email');
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['ordine_chiamata', 'nome_proprietario', 'phone_number']);
        });
    }
};