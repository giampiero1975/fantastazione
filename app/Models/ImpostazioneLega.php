<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\GiocatoreAcquistato;

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
        'usa_ordine_chiamata', // Boolean
        'tipo_base_asta',
        'max_sostituzioni_stagionali',
        'percentuale_crediti_svincolo_riparazione',
        'ordine_squadre_personalizzato' // Array (JSON nel DB)
    ];
    
    // Casts per Laravel 9
    protected $casts = [
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
        'ordine_squadre_personalizzato' => 'array',
    ];
    
    public function prossimoUtenteTurno()
    {
        return $this->belongsTo(User::class, 'prossimo_turno_chiamata_user_id');
    }
    
    public function utentePuoChiamare(User $utente): bool
    {
        $authUserId = Auth::id(); // Per debug APP_KEY/Sessione
        Log::debug(
            "[utentePuoChiamare V8] INIZIO - Controllo per Utente: {$utente->name} (ID: {$utente->id}). " .
            "Auth::id(): " . ($authUserId ?? "NULL (Problema APP_KEY/Sessione?)") .
            ". Fase asta: {$this->fase_asta_corrente}. Tag lista: {$this->tag_lista_attiva}"
        );
        
        if (!in_array($this->fase_asta_corrente, ['P', 'D', 'C', 'A'])) {
            Log::debug("[utentePuoChiamare V8] Fase '{$this->fase_asta_corrente}' non di ruolo specifico. Può chiamare = true.");
            return true;
        }
        
        $limitePerRuolo = match ($this->fase_asta_corrente) {
            'P' => (int) $this->num_portieri,
            'D' => (int) $this->num_difensori,
            'C' => (int) $this->num_centrocampisti,
            'A' => (int) $this->num_attaccanti,
            default => 0,
        };
        Log::debug("[utentePuoChiamare V8] Limite per ruolo {$this->fase_asta_corrente}: {$limitePerRuolo}");
        
        if ($limitePerRuolo <= 0) {
            Log::debug("[utentePuoChiamare V8] Limite ruolo non positivo (o 0). Può chiamare = true.");
            return true;
        }
        
        $tagListaPerConteggio = $this->tag_lista_attiva;
        if (empty($tagListaPerConteggio)) {
            Log::warning("[utentePuoChiamare V8] tag_lista_attiva VUOTO o NULL. Il conteggio giocatori per ruolo potrebbe essere impreciso!");
        }
        
        $queryGiocatori = GiocatoreAcquistato::where('user_id', $utente->id)
        ->whereHas('calciatore', function($query) use ($tagListaPerConteggio) {
            $query->where('ruolo', $this->fase_asta_corrente);
            if (!empty($tagListaPerConteggio)) {
                $query->where('tag_lista_inserimento', $tagListaPerConteggio);
            }
        });
            
            $giocatoriAttualiNelRuolo = $queryGiocatori->count();
            $puoChiamare = $giocatoriAttualiNelRuolo < $limitePerRuolo;
            
            Log::info("[utentePuoChiamare ESITO V8] Utente: {$utente->name} (ID: {$utente->id}), Fase: {$this->fase_asta_corrente}, Attuali: {$giocatoriAttualiNelRuolo}, Limite: {$limitePerRuolo}. Può chiamare: " . ($puoChiamare ? 'Sì' : 'No'));
            return $puoChiamare;
    }
    
    public function avanzaTurnoChiamata(?int $userIdChiamanteCheHaCompletatoIlTurno = null): void
    {
        $valoreOriginaleProssimoTurno = $this->prossimo_turno_chiamata_user_id;
        // Assicura che usa_ordine_chiamata sia interpretato come booleano
        $isOrdineAttivo = filter_var($this->usa_ordine_chiamata, FILTER_VALIDATE_BOOLEAN);
        
        Log::info(
            "[AvanzaTurno V8 INIZIO] usa_ordine_chiamata (valore grezzo DB: '{$this->getOriginal('usa_ordine_chiamata')}', castato: " . ($isOrdineAttivo ? 'Attivo' : 'Disattivato') . ")" .
            ". Fase asta: {$this->fase_asta_corrente}. ID chiamante completato: " . ($userIdChiamanteCheHaCompletatoIlTurno ?? 'N/A') .
            ". Prossimo turno attuale (originale in oggetto): " . ($valoreOriginaleProssimoTurno ?? 'null')
        );
        
        if (!$isOrdineAttivo) {
            Log::info("[AvanzaTurno V8] ORDINE CHIAMATA È DISATTIVATO. Imposto prossimo_turno_chiamata_user_id a null.");
            $this->prossimo_turno_chiamata_user_id = null;
        } else {
            // ORDINE CHIAMATA È ATTIVO
            Log::info("[AvanzaTurno V8] ORDINE CHIAMATA È ATTIVO. Cerco prossimo utente idoneo.");
            $listaIdUtentiOrdinati = [];
            
            if (is_array($this->ordine_squadre_personalizzato) && !empty($this->ordine_squadre_personalizzato)) {
                $customOrderArray = $this->ordine_squadre_personalizzato;
                uasort($customOrderArray, fn($a, $b) => (int)$a <=> (int)$b); // Ordina per valore (posizione)
                $listaIdUtentiOrdinati = array_map('intval', array_keys($customOrderArray)); // Assicura che gli ID siano interi
                Log::debug("[AvanzaTurno V8] Uso 'ordine_squadre_personalizzato'. Utenti ordinati (ID): " . (!empty($listaIdUtentiOrdinati) ? implode(', ', $listaIdUtentiOrdinati) : "VUOTO"));
            } else {
                $utentiQuery = User::where('is_admin', false)->whereNotNull('ordine_chiamata')
                ->orderBy('ordine_chiamata', 'asc')->orderBy('id', 'asc');
                $listaIdUtentiOrdinati = $utentiQuery->pluck('id')->map(fn($id) => (int)$id)->toArray();
                Log::debug("[AvanzaTurno V8] Uso 'ordine_chiamata' da User. Utenti ordinati (ID): " . (!empty($listaIdUtentiOrdinati) ? implode(', ', $listaIdUtentiOrdinati) : "VUOTO"));
            }
            
            if (empty($listaIdUtentiOrdinati)) {
                $this->prossimo_turno_chiamata_user_id = null;
                Log::warning("[AvanzaTurno V8] Lista utenti ordinati VUOTA. Prossimo turno impostato a null.");
            } else {
                $numeroUtentiTotali = count($listaIdUtentiOrdinati);
                $idRiferimentoAttuale = $this->prossimo_turno_chiamata_user_id ?? $userIdChiamanteCheHaCompletatoIlTurno;
                
                $indicePartenza = -1;
                if ($idRiferimentoAttuale !== null) {
                    $chiaveTemp = array_search((int)$idRiferimentoAttuale, $listaIdUtentiOrdinati, true);
                    if ($chiaveTemp !== false) {
                        $indicePartenza = $chiaveTemp;
                    } else {
                        Log::warning("[AvanzaTurno V8] ID riferimento {$idRiferimentoAttuale} non trovato in lista: [" . implode(', ',$listaIdUtentiOrdinati) . "]. Inizio ricerca dal primo.");
                    }
                }
                Log::debug("[AvanzaTurno V8] ID Riferimento per avanzamento: " . ($idRiferimentoAttuale ?? 'null') . ". Indice partenza calcolato: {$indicePartenza}. N. Utenti: {$numeroUtentiTotali}");
                
                $prossimoIdTrovato = null;
                for ($i = 1; $i <= $numeroUtentiTotali; $i++) { // Cicla al massimo N volte per controllare tutti una volta
                    $indiceCandidato = ($indicePartenza + $i) % $numeroUtentiTotali;
                    $utenteCandidatoId = $listaIdUtentiOrdinati[$indiceCandidato];
                    $utenteCandidato = User::find($utenteCandidatoId);
                    
                    Log::debug("[AvanzaTurno V8] Tentativo {$i}. Controllo utente ID: {$utenteCandidatoId} (" . optional($utenteCandidato)->name . ") all'indice {$indiceCandidato}.");
                    
                    if ($utenteCandidato && $this->utentePuoChiamare($utenteCandidato)) {
                        $prossimoIdTrovato = $utenteCandidatoId;
                        Log::info("[AvanzaTurno V8] Utente ID: {$utenteCandidatoId} ({$utenteCandidato->name}) PUÒ chiamare.");
                        break; // Trovato utente idoneo
                    } else {
                        Log::info("[AvanzaTurno V8] Utente ID: {$utenteCandidatoId} (" . optional($utenteCandidato)->name . ") NON può chiamare. Salto.");
                    }
                }
                
                $this->prossimo_turno_chiamata_user_id = $prossimoIdTrovato; // Sarà null se nessuno è idoneo
                if ($prossimoIdTrovato) {
                    Log::info("[AvanzaTurno V8] Prossimo turno CALCOLATO: " . User::find($prossimoIdTrovato)->name . " (ID: {$prossimoIdTrovato})");
                } else {
                    Log::warning("[AvanzaTurno V8] Nessun utente idoneo trovato. Prossimo turno CALCOLATO: null (STALLO).");
                }
            }
        }
        
        // Salva solo se il valore è effettivamente cambiato rispetto a quello che c'era all'inizio del metodo
        if ($valoreOriginaleProssimoTurno != $this->prossimo_turno_chiamata_user_id) {
            Log::info("[AvanzaTurno V8] Valore di prossimo_turno_chiamata_user_id è CAMBIATO (Originale: ".($valoreOriginaleProssimoTurno ?? 'null').", Nuovo: ".($this->prossimo_turno_chiamata_user_id ?? 'null')."). Salvo.");
            $this->save();
            Log::info("[AvanzaTurno V8 FINE] Salvataggio eseguito. Valore oggetto attuale: " . ($this->prossimo_turno_chiamata_user_id ?? 'null'));
        } else {
            Log::info("[AvanzaTurno V8 FINE] Nessuna modifica a prossimo_turno_chiamata_user_id. Valore: " . ($this->prossimo_turno_chiamata_user_id ?? 'null'));
        }
    }
}