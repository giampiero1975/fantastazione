<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Calciatore extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'calciatori';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'id_esterno_giocatore',
        'nome_completo',
        'ruolo',
        'squadra_serie_a',
        'quotazione_iniziale',
        'quotazione_attuale', // Se aggiunta
        'tag_lista_inserimento',
        'attivo'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'attivo' => 'boolean',
        'quotazione_iniziale' => 'integer',
        'quotazione_attuale' => 'integer' // Se aggiunta
    ];

    // Qui in futuro definiremo le RELAZIONI, ad esempio con GiocatoreAcquistato
    public function acquistoAttuale()
    {
        return $this->hasMany(GiocatoreAcquistato::class);
    }
}