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
            // Aggiungi dopo quotazione_iniziale o dove preferisci
            $table->integer('quotazione_attuale')->default(0)->after('quotazione_iniziale');
        });
    }
    // Nel down(), rimuovi la colonna
    public function down(): void
    {
        Schema::table('calciatori', function (Blueprint $table) {
            $table->dropColumn('quotazione_attuale');
        });
    }
};
