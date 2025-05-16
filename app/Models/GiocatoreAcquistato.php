<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GiocatoreAcquistato extends Model
{
    use HasFactory;
    
    /**
     * The table associated with the model.
     * Se il nome della tabella non segue la convenzione plurale del nome del model,
     * puoi specificarlo esplicitamente, ma 'giocatori_acquistati' per 'GiocatoreAcquistato'
     * dovrebbe essere riconosciuto automaticamente.
     */
    protected $table = 'giocatori_acquistati';
    
    protected $fillable = [
        'user_id',
        'calciatore_id',
        'prezzo_acquisto',
        'ruolo_al_momento_acquisto',
    ];
    
    protected $casts = [
        'prezzo_acquisto' => 'integer',
    ];
    
    /**
     * Get the user (squadra) that owns this acquisto.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
    
    /**
     * Get the calciatore that was acquistato.
     */
    public function calciatore()
    {
        return $this->belongsTo(Calciatore::class);
    }
}