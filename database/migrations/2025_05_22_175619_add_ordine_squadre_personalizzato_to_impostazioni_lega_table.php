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
        Schema::table('impostazioni_lega', function (Blueprint $table) {
            // Aggiungi il campo dopo una colonna esistente, ad esempio 'tipo_base_asta'
            // Assicurati che 'tipo_base_asta' esista o scegli un'altra colonna di riferimento
            if (!Schema::hasColumn('impostazioni_lega', 'ordine_squadre_personalizzato')) {
                $table->json('ordine_squadre_personalizzato')->nullable()->after('tipo_base_asta');
            }
        });
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('impostazioni_lega', function (Blueprint $table) {
            if (Schema::hasColumn('impostazioni_lega', 'ordine_squadre_personalizzato')) {
                $table->dropColumn('ordine_squadre_personalizzato');
            }
        });
    }
};
