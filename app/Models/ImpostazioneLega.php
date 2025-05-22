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
    
    // In app/Models/ImpostazioneLega.php
    public function utentePuoChiamare(User $utente): bool
    {
        $authUserId = Auth::id();
        Log::debug(
            "[utentePuoChiamare V9] INIZIO - Controllo per Utente: {$utente->name} (ID: {$utente->id}). " .
            "Auth::id(): " . ($authUserId ?? "NULL") .
            ". Fase asta: {$this->fase_asta_corrente}. Tag lista: {$this->tag_lista_attiva}"
        );
        
        if (!in_array($this->fase_asta_corrente, ['P', 'D', 'C', 'A'])) {
            Log::debug("[utentePuoChiamare V9] Fase '{$this->fase_asta_corrente}' non di ruolo specifico. Può chiamare = true.");
            return true; // Se non siamo in una fase di ruolo, chiunque può chiamare (se è il suo turno e l'asta è TAP)
        }
        
        $limitePerRuolo = match ($this->fase_asta_corrente) {
            'P' => (int) $this->num_portieri,
            'D' => (int) $this->num_difensori,
            'C' => (int) $this->num_centrocampisti,
            'A' => (int) $this->num_attaccanti,
            default => 0,
        };
        Log::debug("[utentePuoChiamare V9] Limite per ruolo {$this->fase_asta_corrente}: {$limitePerRuolo}");
        
        if ($limitePerRuolo <= 0) { // Se il limite per il ruolo è 0 o non definito, si assume che non si possano prendere giocatori di quel ruolo in quella fase
            Log::debug("[utentePuoChiamare V9] Limite ruolo NON POSITIVO (o 0). Può chiamare = false (o true se si intende illimitato, ma di solito 0 è bloccante). Modifico per considerare 0 come bloccante.");
            // Se il limite è 0, l'utente NON può chiamare giocatori di quel ruolo.
            // Se non ci fosse limite (illimitato), si potrebbe gestire diversamente (es. con un valore speciale o non impostando il limite).
            // Per come sono impostati i campi (num_portieri, ecc.), 0 significa che non se ne possono prendere.
            return false;
        }
        
        $tagListaPerConteggio = $this->tag_lista_attiva;
        if (empty($tagListaPerConteggio)) {
            Log::warning("[utentePuoChiamare V9] tag_lista_attiva VUOTO o NULL. Il conteggio giocatori per ruolo potrebbe essere impreciso e basarsi su tutti i tag!");
            // Potresti decidere di restituire false qui se un tag attivo è obbligatorio per operare
            // return false;
        }
        
        $queryGiocatori = GiocatoreAcquistato::where('user_id', $utente->id)
        ->whereHas('calciatore', function($query) use ($tagListaPerConteggio) {
            $query->where('ruolo', $this->fase_asta_corrente); // Fase corrente per il ruolo del calciatore
            if (!empty($tagListaPerConteggio)) {
                $query->where('tag_lista_inserimento', $tagListaPerConteggio);
            }
        });
            
            $giocatoriAttualiNelRuolo = $queryGiocatori->count();
            $puoChiamare = $giocatoriAttualiNelRuolo < $limitePerRuolo;
            
            Log::info("[utentePuoChiamare ESITO V9] Utente: {$utente->name} (ID: {$utente->id}), Fase: {$this->fase_asta_corrente}, Attuali nel ruolo ({$this->fase_asta_corrente}): {$giocatoriAttualiNelRuolo}, Limite: {$limitePerRuolo}. Può chiamare: " . ($puoChiamare ? 'Sì' : 'No'));
            return $puoChiamare;
    }
    // app/Models/ImpostazioneLega.php
    public function avanzaTurnoChiamata($userIdChiamanteCheHaCompletatoIlTurno = null, $forzaReinizializzazioneDaInizioLista = false)
    {
        Log::info("[AvanzaTurno V9.1] Inizio. User Completato: " . ($userIdChiamanteCheHaCompletatoIlTurno ?? 'null') . ". Forza Reinizializzazione: " . ($forzaReinizializzazioneDaInizioLista ? 'true' : 'false') . ". Prossimo Turno Attuale in DB: " . $this->getOriginal('prossimo_turno_chiamata_user_id') . ", Prossimo Turno in Oggetto: " . $this->prossimo_turno_chiamata_user_id);
        
        if (!$this->usa_ordine_chiamata) {
            if ($this->prossimo_turno_chiamata_user_id !== null) {
                $this->prossimo_turno_chiamata_user_id = null;
                Log::info("[AvanzaTurno V9.1] Ordine chiamata NON attivo. Prossimo resettato a null.");
            } else {
                Log::info("[AvanzaTurno V9.1] Ordine chiamata NON attivo e prossimo turno già null. Nessuna modifica.");
            }
            return; // Esce se l'ordine non è attivo
        }
        
        $listaIdUtentiOrdinati = $this->ordine_squadre_personalizzato; // Questo dovrebbe essere un array di ID utente
        
        if (!is_array($listaIdUtentiOrdinati) || empty($listaIdUtentiOrdinati)) {
            Log::warning("[AvanzaTurno V9.1] Ordine chiamata ATTIVO ma ordine_squadre_personalizzato è VUOTO o non è un array. Impossibile determinare il prossimo. Reset a null.");
            if ($this->prossimo_turno_chiamata_user_id !== null) {
                $this->prossimo_turno_chiamata_user_id = null;
            }
            return;
        }
        Log::debug("[AvanzaTurno V9.1] Utilizzo ordine_squadre_personalizzato: [" . implode(',', $listaIdUtentiOrdinati) . "]");
        
        $numeroUtentiTotali = count($listaIdUtentiOrdinati);
        $prossimoIdAttualeInOggetto = $this->prossimo_turno_chiamata_user_id; // Valore corrente nell'oggetto, potrebbe essere stato appena settato dall'admin
        $idRiferimentoPerCiclo = null;
        $indicePartenzaDaCuiCiclare = -1; // Indice (0-based) nella $listaIdUtentiOrdinati
        
        if ($forzaReinizializzazioneDaInizioLista) {
            // L'admin ha forzato un ricalcolo (es. cambio fase, attivazione ordine, reset asta)
            // o ha selezionato un utente specifico nel dropdown "Prossima Squadra a Chiamare".
            $idRiferimentoPerCiclo = $prossimoIdAttualeInOggetto; // Questo è l'ID che l'admin potrebbe aver scelto, o null se non scelto.
            Log::debug("[AvanzaTurno V9.1] ForzaReinizializzazione attivo. idRiferimentoPerCiclo (da oggetto this->prossimo_turno_chiamata_user_id): " . ($idRiferimentoPerCiclo ?? 'null'));
            if ($idRiferimentoPerCiclo !== null) {
                $key = array_search($idRiferimentoPerCiclo, $listaIdUtentiOrdinati);
                if ($key !== false) {
                    $indicePartenzaDaCuiCiclare = $key; // Partiamo controllando QUESTO utente
                } else {
                    Log::warning("[AvanzaTurno V9.1] ID Forzato/Riferimento {$idRiferimentoPerCiclo} non trovato nella lista. Si parte da indice 0.");
                    $indicePartenzaDaCuiCiclare = 0; // Non trovato, partiamo dal primo della lista
                }
            } else {
                $indicePartenzaDaCuiCiclare = 0; // Nessun riferimento, partiamo dal primo della lista
            }
        } elseif ($userIdChiamanteCheHaCompletatoIlTurno !== null) {
            // Un utente ha completato il suo turno (es. asta conclusa, chiamata annullata post-live)
            $idRiferimentoPerCiclo = $userIdChiamanteCheHaCompletatoIlTurno;
            Log::debug("[AvanzaTurno V9.1] User Completato: idRiferimentoPerCiclo = " . $idRiferimentoPerCiclo);
            $key = array_search($idRiferimentoPerCiclo, $listaIdUtentiOrdinati);
            if ($key !== false) {
                $indicePartenzaDaCuiCiclare = ($key + 1) % $numeroUtentiTotali; // Partiamo dal SUCCESSIVO
            } else {
                Log::warning("[AvanzaTurno V9.1] User Completato ID {$idRiferimentoPerCiclo} non trovato nella lista. Si parte da indice 0.");
                $indicePartenzaDaCuiCiclare = 0; // Non trovato, partiamo dal primo
            }
        } else {
            // Nessun utente ha completato, non si forza reinizializzazione.
            // Questo caso si verifica se si chiama avanzaTurnoChiamata(null, false).
            // Si dovrebbe continuare dal prossimo dell'utente attualmente impostato come 'prossimo_turno_chiamata_user_id'.
            $idRiferimentoPerCiclo = $prossimoIdAttualeInOggetto;
            Log::debug("[AvanzaTurno V9.1] Caso standard (né user completato, né reinizializzazione forzata). idRiferimentoPerCiclo (da this->prossimo_turno_chiamata_user_id): " . ($idRiferimentoPerCiclo ?? 'null'));
            if ($idRiferimentoPerCiclo !== null) {
                $key = array_search($idRiferimentoPerCiclo, $listaIdUtentiOrdinati);
                if ($key !== false) {
                    $indicePartenzaDaCuiCiclare = ($key + 1) % $numeroUtentiTotali; // Si cerca il successivo all'attuale "prossimo"
                } else {
                    Log::warning("[AvanzaTurno V9.1] ID prossimo turno attuale {$idRiferimentoPerCiclo} non trovato nella lista. Si parte da indice 0.");
                    $indicePartenzaDaCuiCiclare = 0;
                }
            } else {
                $indicePartenzaDaCuiCiclare = 0; // Nessun prossimo turno impostato, partiamo dal primo
            }
        }
        
        Log::debug("[AvanzaTurno V9.1] Indice di partenza effettivo per il ciclo: {$indicePartenzaDaCuiCiclare}. N. Utenti: {$numeroUtentiTotali}");
        
        $prossimoIdTrovato = null;
        for ($i = 0; $i < $numeroUtentiTotali; $i++) { // Cicla al massimo N volte
            $indiceCandidato = ($indicePartenzaDaCuiCiclare + $i) % $numeroUtentiTotali;
            $utenteCandidatoId = $listaIdUtentiOrdinati[$indiceCandidato];
            $utenteCandidato = User::find($utenteCandidatoId);
            
            Log::debug("[AvanzaTurno V9.1] Tentativo " . ($i + 1) . ". Controllo utente ID: {$utenteCandidatoId} (" . optional($utenteCandidato)->name . ") all'indice {$indiceCandidato}.");
            
            if ($utenteCandidato && $this->utentePuoChiamare($utenteCandidato)) {
                $prossimoIdTrovato = $utenteCandidatoId;
                Log::info("[AvanzaTurno V9.1] Utente ID: {$utenteCandidatoId} ({$utenteCandidato->name}) PUÒ chiamare.");
                break; // Trovato utente idoneo
            } else {
                Log::info("[AvanzaTurno V9.1] Utente ID: {$utenteCandidatoId} (" . optional($utenteCandidato)->name . ") NON può chiamare o non trovato. Salto.");
            }
        }
        
        if ($this->prossimo_turno_chiamata_user_id != $prossimoIdTrovato) {
            Log::info("[AvanzaTurno V9.1] Cambio prossimo turno da " . ($this->prossimo_turno_chiamata_user_id ?? 'null') . " a " . ($prossimoIdTrovato ?? 'null'));
            $this->prossimo_turno_chiamata_user_id = $prossimoIdTrovato;
            // Il salvataggio avviene nel controller
        } else {
            Log::info("[AvanzaTurno V9.1] Prossimo turno NON cambiato. Rimane: " . ($this->prossimo_turno_chiamata_user_id ?? 'null'));
        }
        Log::info("[AvanzaTurno V9.1] Fine. Prossimo turno in oggetto: " . ($this->prossimo_turno_chiamata_user_id ?? 'Nessuno (stallo?)'));
    }
}