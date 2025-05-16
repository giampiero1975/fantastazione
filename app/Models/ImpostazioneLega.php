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
        'calciatore_attuale_in_asta_id', // Se ancora rilevante qui
        'prossimo_turno_chiamata_user_id', // Se ancora rilevante qui
        'crediti_iniziali_lega',
        'num_portieri',
        'num_difensori',
        'num_centrocampisti',
        'num_attaccanti',
        'tag_lista_attiva',             // NUOVO
        'modalita_asta',                // NUOVO
        'durata_countdown_secondi',     // NUOVO
        'asta_tap_approvazione_admin',  // NUOVO
    ];
    
    protected function casts(): array // O protected $casts = [...]
    {
        return [
            'crediti_iniziali_lega' => 'integer',
            'num_portieri' => 'integer',
            'num_difensori' => 'integer',
            'num_centrocampisti' => 'integer',
            'num_attaccanti' => 'integer',
            'durata_countdown_secondi' => 'integer',    // NUOVO
            'asta_tap_approvazione_admin' => 'boolean', // NUOVO
            // fase_asta_corrente e modalita_asta sono ENUM, Laravel li gestisce come stringhe
        ];
    }
    
    // Relazioni se necessarie (quelle per calciatore_attuale_in_asta_id e prossimo_turno_chiamata_user_id
    // potrebbero essere rimosse da qui se spostiamo quella logica interamente in chiamate_asta per il TAP)
    public function calciatoreAttuale()
    {
        return $this->belongsTo(Calciatore::class, 'calciatore_attuale_in_asta_id');
    }
    
    public function prossimoUtenteTurno()
    {
        return $this->belongsTo(User::class, 'prossimo_turno_chiamata_user_id');
    }
}