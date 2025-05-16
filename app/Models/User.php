<?php
namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable // Potrebbe implementare MustVerifyEmail se lo hai abilitato in Breeze
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'is_admin',
        'crediti_iniziali_squadra',
        'crediti_rimanenti'
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token'
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array // Se usi Laravel 10+ la sintassi è questa
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_admin' => 'boolean',
            'crediti_iniziali_squadra' => 'integer', // <-- NUOVO
            'crediti_rimanenti' => 'integer' // <-- NUOVO
        ];
    }

    public function giocatoriAcquistati()
    {
        return $this->hasMany(GiocatoreAcquistato::class);
    }
}