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
            // Aggiungiamo la colonna 'is_admin' dopo la colonna 'email' (o dove preferisci)
            // Sar� un booleano, non potr� essere nullo, e di default sar� false.
            $table->boolean('is_admin')->after('email')->default(false);
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Se eseguiamo il rollback della migrazione, rimuoviamo la colonna
            $table->dropColumn('is_admin');
        });
    }
};