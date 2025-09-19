<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ImpostazioneLega;

class ImpostazioneLegaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Cerca una riga di impostazioni.
        // Se non la trova, la crea con questi valori di default.
        ImpostazioneLega::firstOrCreate(
            ['id' => 1], // Cerca la riga con id=1
            [ // Se non la trova, la crea con questi valori
                'nome_lega' => 'Fantastazione',
                'crediti_iniziali_lega' => 500,
                'num_portieri' => 3,
                'num_difensori' => 8,
                'num_centrocampisti' => 8,
                'num_attaccanti' => 6,
                'modalita_asta' => 'tap',
                'fase_asta_corrente' => 'PRE_ASTA',
                'asta_tap_approvazione_admin' => true,
                'durata_countdown_secondi' => 30,
                'tipo_base_asta' => 'quotazione_iniziale',
                'usa_ordine_chiamata' => false,
                'max_sostituzioni_stagionali' => 5,
                'percentuale_crediti_svincolo_riparazione' => 100,
            ]
            );
    }
}