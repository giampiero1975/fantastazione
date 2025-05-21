<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

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
        'usa_ordine_chiamata',
        'tipo_base_asta',
        'max_sostituzioni_stagionali',
        'percentuale_crediti_svincolo_riparazione',
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
            'usa_ordine_chiamata' => 'boolean',
            'max_sostituzioni_stagionali' => 'integer',
            'percentuale_crediti_svincolo_riparazione' => 'integer',
        ];
    }
    
    public function calciatoreAttuale()
    {
        return $this->belongsTo(Calciatore::class, 'calciatore_attuale_in_asta_id');
    }
    
    public function prossimoUtenteTurno()
    {
        return $this->belongsTo(User::class, 'prossimo_turno_chiamata_user_id');
    }
    
    /**
     * Avanza il turno di chiamata all'utente successivo secondo l'ordine definito.
     * Se $userIdChiamanteAttuale è null, imposta il primo utente dell'ordine.
     *
     * @param int|null $userIdChiamanteAttuale L'ID dell'utente che ha appena chiamato (o null per iniziare).
     * @return void
     */
    public function avanzaTurnoChiamata(?int $userIdChiamanteCheHaCompletatoIlTurno = null): void
    {
        if (!$this->usa_ordine_chiamata) {
            // Se l'ordine di chiamata non è attivo, non fare nulla o assicurati che prossimo_turno_chiamata_user_id sia null
            if ($this->prossimo_turno_chiamata_user_id !== null) {
                $this->prossimo_turno_chiamata_user_id = null;
                $this->save();
                Log::info("[AvanzaTurno] Ordine chiamata disattivato. Prossimo turno resettato a null.");
            }
            return;
        }
        
        // Recupera tutti gli utenti NON admin che hanno un ordine_chiamata definito,
        // ordinati per il loro ordine di chiamata e poi per ID come fallback.
        $utentiOrdinati = User::where('is_admin', false)
        ->whereNotNull('ordine_chiamata')
        ->orderBy('ordine_chiamata', 'asc')
        ->orderBy('id', 'asc') // Fallback per parità di ordine_chiamata
        ->pluck('id')
        ->toArray();
        
        if (empty($utentiOrdinati)) {
            $this->prossimo_turno_chiamata_user_id = null;
            $this->save();
            Log::warning("[AvanzaTurno] Nessun utente partecipante (non admin) con 'ordine_chiamata' definito trovato. Impossibile determinare il prossimo turno.");
            return;
        }
        
        $prossimoIdDaImpostare = null;
        $idRiferimentoPerAvanzamento = $userIdChiamanteCheHaCompletatoIlTurno ?: $this->prossimo_turno_chiamata_user_id;
        
        if ($idRiferimentoPerAvanzamento === null) {
            // Caso di inizio asta/reset: il prossimo è il primo della lista
            $prossimoIdDaImpostare = $utentiOrdinati[0];
            Log::info("[AvanzaTurno] Inizio/Reset: prossimo turno impostato al primo utente (ID: {$prossimoIdDaImpostare}).");
        } else {
            $indiceAttuale = array_search($idRiferimentoPerAvanzamento, $utentiOrdinati);
            
            if ($indiceAttuale !== false) { // L'utente di riferimento è nella lista ordinata
                if (isset($utentiOrdinati[$indiceAttuale + 1])) {
                    // C'è un utente successivo nella lista
                    $prossimoIdDaImpostare = $utentiOrdinati[$indiceAttuale + 1];
                    Log::info("[AvanzaTurno] Utente riferimento (ID: {$idRiferimentoPerAvanzamento}) trovato a indice {$indiceAttuale}. Prossimo è l'utente successivo (ID: {$prossimoIdDaImpostare}).");
                } else {
                    // L'utente di riferimento era l'ultimo, si torna al primo (giro completo)
                    $prossimoIdDaImpostare = $utentiOrdinati[0];
                    Log::info("[AvanzaTurno] Utente riferimento (ID: {$idRiferimentoPerAvanzamento}) era l'ultimo. Si torna al primo utente (ID: {$prossimoIdDaImpostare}).");
                }
            } else {
                // L'utente di riferimento non è (più) nella lista o il suo ordine_chiamata è stato rimosso.
                // Per sicurezza, si imposta il primo della lista attuale.
                $prossimoIdDaImpostare = $utentiOrdinati[0];
                Log::warning("[AvanzaTurno] Utente riferimento (ID: {$idRiferimentoPerAvanzamento}) non trovato nella lista ordinata. Reimpostato al primo utente disponibile (ID: {$prossimoIdDaImpostare}).");
            }
        }
        
        if ($this->prossimo_turno_chiamata_user_id != $prossimoIdDaImpostare) {
            $this->prossimo_turno_chiamata_user_id = $prossimoIdDaImpostare;
            $this->save();
            Log::info("[AvanzaTurno] Salvataggio DB: prossimo_turno_chiamata_user_id aggiornato a: " . ($prossimoIdDaImpostare ?? 'null'));
        } else {
            Log::info("[AvanzaTurno] Nessun cambiamento a prossimo_turno_chiamata_user_id (era già {$this->prossimo_turno_chiamata_user_id}, nuovo calcolato {$prossimoIdDaImpostare}).");
        }
    }
}