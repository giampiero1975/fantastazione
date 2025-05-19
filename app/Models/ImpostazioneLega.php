<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ImpostazioneLega extends Model
{
    use HasFactory;
    
    protected $table = 'impostazioni_lega';
    
    protected $fillable = [
        'fase_asta_corrente',
        'prossimo_turno_chiamata_user_id',
        'crediti_iniziali_lega',
        'num_portieri',
        'num_difensori',
        'num_centrocampisti',
        'num_attaccanti',
        'tag_lista_attiva',
        'modalita_asta',
        'durata_countdown_secondi',
        'asta_tap_approvazione_admin',
        'usa_ordine_chiamata',          // NUOVO
        'tipo_base_asta',               // NUOVO
    ];
    
    protected function casts(): array
    {
        return [
            'crediti_iniziali_lega' => 'integer',
            'num_portieri' => 'integer',
            'num_difensori' => 'integer',
            'num_centrocampisti' => 'integer',
            'num_attaccanti' => 'integer',
            'durata_countdown_secondi' => 'integer',
            'asta_tap_approvazione_admin' => 'boolean',
            'usa_ordine_chiamata' => 'boolean', // NUOVO
            // 'tipo_base_asta' è un ENUM, Laravel lo gestisce come stringa
        ];
    }
    // Vecchia sintassi per $casts:
    // protected $casts = [
    //     'crediti_iniziali_lega' => 'integer',
    //     'num_portieri' => 'integer',
    //     // ... altri cast esistenti ...
    //     'asta_tap_approvazione_admin' => 'boolean',
    //     'usa_ordine_chiamata' => 'boolean', // NUOVO
    // ];
    
    public function calciatoreAttuale()
    {
        return $this->belongsTo(Calciatore::class, 'calciatore_attuale_in_asta_id');
    }
    
    public function prossimoUtenteTurno()
    {
        return $this->belongsTo(User::class, 'prossimo_turno_chiamata_user_id');
    }
}