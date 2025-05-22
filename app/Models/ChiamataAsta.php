<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon; // Importa Carbon

class ChiamataAsta extends Model
{
    use HasFactory;
    
    protected $table = 'chiamate_asta';
    
    protected $fillable = [
        'calciatore_id',
        'user_id_chiamante',
        'tag_lista_calciatori',
        'stato_chiamata',
        'prezzo_partenza_tap',
        'prezzo_attuale_tap',
        'miglior_offerente_tap_id',
        'timestamp_fine_tap_prevista',
        'vincitore_user_id',
        'prezzo_finale_assegnazione',
    ];
    
    // Corretto per Laravel 9
    protected $casts = [
        'timestamp_fine_tap_prevista' => 'datetime',
        'prezzo_partenza_tap' => 'integer',
        'prezzo_attuale_tap' => 'integer',
        'prezzo_finale_assegnazione' => 'integer',
    ];
    
    public function calciatore()
    {
        return $this->belongsTo(Calciatore::class);
    }
    
    public function utenteChiamante()
    {
        return $this->belongsTo(User::class, 'user_id_chiamante');
    }
    
    public function migliorOfferenteTap()
    {
        return $this->belongsTo(User::class, 'miglior_offerente_tap_id');
    }
    
    public function vincitore()
    {
        return $this->belongsTo(User::class, 'vincitore_user_id');
    }
}