<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB; // Per modificare l'enum

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('impostazioni_lega', function (Blueprint $table) {
            // Aggiungi i nuovi campi dopo colonne esistenti per organizzazione
            $table->integer('max_sostituzioni_stagionali')->default(5)->nullable()->after('tipo_base_asta');
            $table->tinyInteger('percentuale_crediti_svincolo_riparazione')->default(50)->comment('Percentuale 0-100 dei crediti restituiti durante asta riparazione')->nullable()->after('max_sostituzioni_stagionali');
        });
            
            // Modifica l'ENUM per fase_asta_corrente per aggiungere le nuove fasi
            // ATTENZIONE: la modifica di ENUM può variare leggermente a seconda del DB (MySQL vs PostgreSQL)
            // Questo è per MySQL. Per altri DB, la sintassi potrebbe essere diversa.
            // È SEMPRE consigliabile fare un backup del DB prima di modificare ENUM in produzione.
            $fasi = ['PRE_ASTA', 'P', 'D', 'C', 'A', 'CONCLUSA', 'SVINCOLI_STAGIONALI', 'ASTA_RIPARAZIONE'];
            $fasiString = "'" . implode("','", $fasi) . "'";
            DB::statement("ALTER TABLE impostazioni_lega MODIFY fase_asta_corrente ENUM({$fasiString}) NOT NULL DEFAULT 'PRE_ASTA'");
    }
    
    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('impostazioni_lega', function (Blueprint $table) {
            $table->dropColumn('max_sostituzioni_stagionali');
            $table->dropColumn('percentuale_crediti_svincolo_riparazione');
        });
            
            // Ripristina l'ENUM precedente
            $fasiVecchie = ['PRE_ASTA', 'P', 'D', 'C', 'A', 'CONCLUSA'];
            $fasiVecchieString = "'" . implode("','", $fasiVecchie) . "'";
            DB::statement("ALTER TABLE impostazioni_lega MODIFY fase_asta_corrente ENUM({$fasiVecchieString}) NOT NULL DEFAULT 'PRE_ASTA'");
    }
};