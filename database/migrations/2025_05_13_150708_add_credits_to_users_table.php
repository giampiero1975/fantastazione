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
        Schema::table('users', function (Blueprint $table) {
            // Dopo 'is_admin' o dove preferisci
            $table->integer('crediti_iniziali_squadra')->default(0)->after('is_admin');
            $table->integer('crediti_rimanenti')->default(0)->after('crediti_iniziali_squadra');
        });
    }
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['crediti_iniziali_squadra', 'crediti_rimanenti']);
        });
    }
};
