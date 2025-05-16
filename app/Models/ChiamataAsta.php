<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChiamataAsta extends Model
{
    use HasFactory;
    
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'chiamate_asta'; // Specifica il nome della tabella se non segue la convenzione plurale standard
    
    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'calciatore_id',
        'user_id_chiamante',
        'tag_lista_calciatori', // Assicurati che questo campo esista nella tua migrazione per 'chiamate_asta'
        'stato_chiamata',
        'prezzo_partenza_tap',
        'prezzo_attuale_tap',
        'miglior_offerente_tap_id',
        'timestamp_fine_tap_prevista',
        'vincitore_user_id',
        'prezzo_finale_assegnazione',
    ];
    
    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected function casts(): array // Per Laravel 10+
    {
        return [
            'timestamp_fine_tap_prevista' => 'datetime',
            'prezzo_partenza_tap' => 'integer',
            'prezzo_attuale_tap' => 'integer',
            'prezzo_finale_assegnazione' => 'integer',
        ];
    }
    
    // Se usi una versione di Laravel precedente alla 10, la sintassi per $casts è:
    // protected $casts = [
    //     'timestamp_fine_tap_prevista' => 'datetime',
    //     'prezzo_partenza_tap' => 'integer',
    //     'prezzo_attuale_tap' => 'integer',
    //     'prezzo_finale_assegnazione' => 'integer',
    // ];
    
    /**
     * Get the calciatore associated with this chiamata.
     */
    public function calciatore()
    {
        return $this->belongsTo(Calciatore::class);
    }
    
    /**
     * Get the user (squadra) who made the call.
     */
    public function utenteChiamante()
    {
        return $this->belongsTo(User::class, 'user_id_chiamante');
    }
    
    /**
     * Get the user (squadra) who is currently the highest bidder for the TAP auction.
     */
    public function migliorOfferenteTap()
    {
        return $this->belongsTo(User::class, 'miglior_offerente_tap_id');
    }
    
    /**
     * Get the user (squadra) who won this TAP auction.
     */
    public function vincitore()
    {
        return $this->belongsTo(User::class, 'vincitore_user_id');
    }
}