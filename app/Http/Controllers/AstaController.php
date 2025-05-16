<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Models\User;
use App\Models\ImpostazioneLega;
use App\Models\Calciatore;
use App\Models\GiocatoreAcquistato;
use App\Models\ChiamataAsta;
use Carbon\Carbon;
use Exception;
// use Illuminate\Validation\Rule; // Potrebbe servire per validazioni più complesse

class AstaController extends Controller
{
    /**
     * Funzione helper per sanificare le stringhe per l'output JSON.
     */
    private function sanitizeForJsonResponse($string)
    {
        if ($string === null) return null;
        $string = (string)$string;
        // Tenta la conversione standard, se fallisce, mb_convert_encoding può restituire false o stringa vuota.
        $converted = @mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        if ($converted === false || !mb_check_encoding($converted, 'UTF-8')) {
            // Se la conversione fallisce o il risultato non è UTF-8 valido, prova a rimuovere i caratteri non validi.
            $sanitized = preg_replace('/[^\x20-\x7E\p{L}\p{N}\p{P}\p{S}\p{Z}]/u', '', $string); // Mantieni stampabili, lettere, numeri, punteggiatura, simboli, spazi
            if (mb_check_encoding($sanitized, 'UTF-8')) {
                return $sanitized;
            }
            Log::warning("Stringa non convertibile a UTF-8 valido (originale in hex): " . bin2hex($string));
            return "Dato non visualizzabile (encoding errato)";
        }
        return $converted;
    }
    
    /**
     * Gestisce la visualizzazione della dashboard (smista tra admin e squadra).
     */
    public function dashboard()
    {
        $user = Auth::user();
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        
        if ($user->is_admin) {
            return redirect()->route('admin.dashboard');
        } else {
            $squadra = $user;
            $rosa = $squadra->giocatoriAcquistati()->with('calciatore')->get();
            $costoTotaleRosa = $rosa->sum('prezzo_acquisto');
            
            $conteggioRuoli = collect();
            if($rosa->isNotEmpty() && $rosa->every(fn($acquisto) => $acquisto->relationLoaded('calciatore') && $acquisto->calciatore)){
                $conteggioRuoli = $rosa->map(function($acquisto){
                    return optional($acquisto->calciatore)->ruolo;
                })->filter()->countBy();
            } else if ($rosa->isNotEmpty()) {
                $idsCalciatoriInRosa = $rosa->pluck('calciatore_id');
                if ($idsCalciatoriInRosa->isNotEmpty()){
                    $conteggioRuoli = Calciatore::whereIn('id', $idsCalciatoriInRosa)->pluck('ruolo')->countBy();
                }
            }
            
            $limitiRuoli = [
                'P' => $impostazioniLega->num_portieri, 'D' => $impostazioniLega->num_difensori,
                'C' => $impostazioniLega->num_centrocampisti, 'A' => $impostazioniLega->num_attaccanti,
            ];
            $numeroGiocatoriInRosa = $rosa->count();
            $limiteGiocatoriTotaliInRosa = $impostazioniLega->num_portieri + $impostazioniLega->num_difensori + $impostazioniLega->num_centrocampisti + $impostazioniLega->num_attaccanti;
            
            $astaTapLiveAttualmente = null;
            if ($impostazioniLega->modalita_asta === 'tap') {
                $astaTapLiveAttualmente = ChiamataAsta::where('stato_chiamata', 'in_asta_tap_live')
                // ->where('tag_lista_calciatori', $impostazioniLega->tag_lista_attiva) // Se filtri per tag
                ->first();
            }
            
            return view('squadra.dashboard', compact(
                'squadra', 'rosa', 'impostazioniLega', 'costoTotaleRosa',
                'conteggioRuoli', 'limitiRuoli', 'numeroGiocatoriInRosa', 'limiteGiocatoriTotaliInRosa',
                'astaTapLiveAttualmente'
                ));
        }
    }
    
    /**
     * Mostra la lista dei calciatori disponibili per l'asta alla squadra.
     */
    public function mostraCalciatoriDisponibili(Request $request)
    {
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $faseAstaCorrente = $impostazioniLega->fase_asta_corrente;
        $tagListaAttiva = $impostazioniLega->tag_lista_attiva;
        
        $calciatori = collect();
        $messaggio = null;
        $squadreSerieAUniche = collect();
        $chiamataTapPossibile = false;
        
        if (!in_array($faseAstaCorrente, ['P', 'D', 'C', 'A']) || !$tagListaAttiva) {
            if (!in_array($faseAstaCorrente, ['P', 'D', 'C', 'A'])) {
                $messaggio = "L'asta è in fase: {$faseAstaCorrente}. Non è possibile visualizzare giocatori per la chiamata.";
            } elseif (!$tagListaAttiva) {
                $messaggio = "Nessuna lista giocatori attiva definita dall'admin per l'asta.";
            }
        } else {
            $query = Calciatore::where('attivo', true)
            ->where('tag_lista_inserimento', $tagListaAttiva)
            ->where('ruolo', $faseAstaCorrente);
            
            // Escludi giocatori già acquistati (questa logica andrà raffinata se hai un sistema multi-asta/stagione)
            $idsCalciatoriAcquistati = GiocatoreAcquistato::pluck('calciatore_id')->all();
            if (!empty($idsCalciatoriAcquistati)) {
                $query->whereNotIn('id', $idsCalciatoriAcquistati);
            }
            
            
            if ($request->filled('nome_calciatore_search')) {
                $query->where('nome_completo', 'like', '%' . $request->input('nome_calciatore_search') . '%');
            }
            if ($request->filled('squadra_serie_a_search')) {
                $query->where('squadra_serie_a', 'like', '%' . $request->input('squadra_serie_a_search') . '%');
            }
            
            $calciatori = $query->orderBy('nome_completo')->paginate(20);
            
            $queryBaseFiltri = Calciatore::where('attivo', true)
            ->where('tag_lista_inserimento', $tagListaAttiva)
            ->where('ruolo', $faseAstaCorrente)
            ->whereNotIn('id', $idsCalciatoriAcquistati); // Applica anche qui
            $squadreSerieAUniche = (clone $queryBaseFiltri)->select('squadra_serie_a')->distinct()->orderBy('squadra_serie_a')->pluck('squadra_serie_a');
            
            if ($impostazioniLega->modalita_asta === 'tap') {
                $chiamataAttivaEsistente = ChiamataAsta::whereIn('stato_chiamata', ['in_attesa_admin', 'in_asta_tap_live'])
                // ->where('tag_lista_calciatori', $tagListaAttiva) // Se filtri per tag
                ->doesntExist();
                $chiamataTapPossibile = $chiamataAttivaEsistente;
            }
        }
        
        return view('asta.calciatori-disponibili', compact(
            'calciatori', 'messaggio', 'faseAstaCorrente', 'tagListaAttiva',
            'squadreSerieAUniche', 'impostazioniLega', 'chiamataTapPossibile'
            ));
    }
    
    /**
     * Registra la chiamata di un calciatore per l'asta TAP da parte di una squadra.
     */
    public function registraChiamata(Request $request, Calciatore $calciatore)
    {
        $user = Auth::user();
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        
        // --- VALIDAZIONI PRELIMINARI ---
        // L'admin partecipante PUO' chiamare, quindi il check $user->is_admin è stato rimosso.
        // Se vuoi reintrodurlo per admin che NON partecipano, dovrai avere un modo per distinguerli.
        
        if ($impostazioniLega->modalita_asta !== 'tap') {
            return back()->with('error', 'La modalità asta TAP non è attualmente attiva.');
        }
        if (!in_array($impostazioniLega->fase_asta_corrente, ['P', 'D', 'C', 'A'])) {
            return back()->with('error', "L'asta non è in una fase attiva (P, D, C, A) per la chiamata dei giocatori.");
        }
        if ($calciatore->ruolo !== $impostazioniLega->fase_asta_corrente) {
            return back()->with('error', "Puoi chiamare per l'asta solo giocatori del ruolo della fase corrente ({$impostazioniLega->fase_asta_corrente}). Questo giocatore è un {$calciatore->ruolo}.");
        }
        if (!$calciatore->attivo || $calciatore->tag_lista_inserimento !== $impostazioniLega->tag_lista_attiva) {
            return back()->with('error', "Questo calciatore ({$calciatore->nome_completo}) non è disponibile per l'asta attiva.");
        }
        $giaAcquistato = GiocatoreAcquistato::where('calciatore_id', $calciatore->id)->exists();
        if ($giaAcquistato) {
            return back()->with('error', "{$calciatore->nome_completo} è già stato acquistato in questa lega/asta.");
        }
        $astaTapAttivaEsistente = ChiamataAsta::whereIn('stato_chiamata', ['in_attesa_admin', 'in_asta_tap_live'])->exists();
        if ($astaTapAttivaEsistente) {
            return back()->with('error', 'C\'è già un giocatore chiamato o un\'asta TAP in corso. Attendi il suo completamento.');
        }
        
        try {
            DB::beginTransaction();
            $prezzoPartenza = $calciatore->quotazione_iniziale > 0 ? $calciatore->quotazione_iniziale : 1;
            $statoInizialeChiamata = 'in_attesa_admin';
            $timestampFineAsta = null;
            $migliorOfferenteIniziale = null;
            
            if (!$impostazioniLega->asta_tap_approvazione_admin) {
                $statoInizialeChiamata = 'in_asta_tap_live';
                $timestampFineAsta = Carbon::now()->addSeconds($impostazioniLega->durata_countdown_secondi);
                $migliorOfferenteIniziale = $user->id;
                Log::info("Asta TAP auto-avviata per {$calciatore->nome_completo} da {$user->name}. Fine: {$timestampFineAsta}");
            }
            
            ChiamataAsta::create([
                'calciatore_id' => $calciatore->id, 'user_id_chiamante' => $user->id,
                'tag_lista_calciatori' => $impostazioniLega->tag_lista_attiva,
                'stato_chiamata' => $statoInizialeChiamata,
                'prezzo_partenza_tap' => $prezzoPartenza, 'prezzo_attuale_tap' => $prezzoPartenza,
                'miglior_offerente_tap_id' => $migliorOfferenteIniziale,
                'timestamp_fine_tap_prevista' => $timestampFineAsta,
            ]);
            DB::commit();
            
            $messaggio = $statoInizialeChiamata === 'in_asta_tap_live' ?
            "Asta TAP per {$this->sanitizeForJsonResponse($calciatore->nome_completo)} avviata! Base: {$prezzoPartenza}. Fine tra: {$impostazioniLega->durata_countdown_secondi}s." :
            "{$this->sanitizeForJsonResponse($calciatore->nome_completo)} chiamato per asta TAP! Attesa approvazione admin.";
            
            $routeDestinazione = $statoInizialeChiamata === 'in_asta_tap_live' ? 'asta.live' : 'asta.calciatori.disponibili';
            return redirect()->route($routeDestinazione)->with('success', $messaggio);
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Errore registrazione chiamata per {$calciatore->nome_completo}: " . $e->getMessage());
            return back()->with('error', 'Errore tecnico chiamata: ' . $this->sanitizeForJsonResponse($e->getMessage()));
        }
    }
    
    public function gestisciRilancioTap(Request $request, ChiamataAsta $chiamataAsta)
    {
        $user = Auth::user();
        Log::info("RILANCIO TAP - Inizio per Asta ID: {$chiamataAsta->id}, Utente: {$user->name} ({$user->id}), Rilancio: " . $request->input('importo_rilancio'));
        
        $validated = $request->validate([
            'importo_rilancio' => ['required', 'integer', 'min:1'],
        ]);
        $incrementoOfferta = $validated['importo_rilancio'];
        
        try {
            // Usiamo una transazione con lock pessimistico
            return DB::transaction(function () use ($chiamataAsta, $user, $incrementoOfferta) {
                // Ricarica con lock e relazioni
                $chiamata = ChiamataAsta::where('id', $chiamataAsta->id)
                ->lockForUpdate()
                ->with('calciatore') // Per accedere a $chiamata->calciatore->ruolo ecc.
                ->firstOrFail();
                
                $impostazioniLega = ImpostazioneLega::firstOrFail();
                $calciatoreInAsta = $chiamata->calciatore; // Già caricato
                
                // --- VALIDAZIONI ---
                if ($chiamata->stato_chiamata !== 'in_asta_tap_live') {
                    return response()->json(['error' => $this->sanitizeForJsonResponse('L\'asta per questo giocatore non è più attiva.')], 400);
                }
                // Controlla se il timestamp di fine è definito e se l'asta è scaduta
                if ($chiamata->timestamp_fine_tap_prevista && Carbon::now()->greaterThanOrEqualTo(Carbon::parse($chiamata->timestamp_fine_tap_prevista))) {
                    return response()->json(['error' => $this->sanitizeForJsonResponse('Il tempo per quest\'asta è scaduto!')], 400);
                }
                if ($user->id == $chiamata->miglior_offerente_tap_id) {
                    return response()->json(['error' => $this->sanitizeForJsonResponse('Sei ancora il miglior offerente.')], 400);
                }
                
                $nuovoPrezzoProposto = $chiamata->prezzo_attuale_tap + $incrementoOfferta;
                
                // 1. Validazione Crediti Utente
                if ($user->crediti_rimanenti < $nuovoPrezzoProposto) {
                    return response()->json(['error' => $this->sanitizeForJsonResponse("Non hai abbastanza crediti (Disponibili: {$user->crediti_rimanenti}, Richiesti: {$nuovoPrezzoProposto}).")], 400);
                }
                
                // 2. Validazioni Rosa (Limite giocatori totali, limite per ruolo, regola portieri, regola ultimo credito)
                // QUESTA PARTE È CRUCIALE E DEVE ESSERE COMPLETA E CORRETTA
                // Prendi spunto da AdminRosterController@handleAssegna e adatta per restituire JSON error
                // Esempio (da espandere!):
                $rosaAttualeQuery = GiocatoreAcquistato::where('user_id', $user->id);
                // Aggiungi filtro per tag/stagione se GiocatoreAcquistato ha tale campo e $chiamata->tag_lista_calciatori è disponibile
                $rosaAttuale = $rosaAttualeQuery->get();
                $idsCalciatoriInRosa = $rosaAttuale->pluck('calciatore_id');
                $ruoliGiocatoriInRosa = collect();
                if($idsCalciatoriInRosa->isNotEmpty()){
                    $ruoliGiocatoriInRosa = Calciatore::whereIn('id', $idsCalciatoriInRosa)->pluck('ruolo');
                }
                $conteggioRuoli = $ruoliGiocatoriInRosa->countBy();
                $numGiocatoriTotaliInRosa = $rosaAttuale->count();
                $limiteGiocatoriSistema = $impostazioniLega->num_portieri + $impostazioniLega->num_difensori + $impostazioniLega->num_centrocampisti + $impostazioniLega->num_attaccanti;
                
                // Limite Totale Giocatori
                if ($numGiocatoriTotaliInRosa >= $limiteGiocatoriSistema) {
                    return response()->json(['error' => $this->sanitizeForJsonResponse("Rosa completa ({$limiteGiocatoriSistema} giocatori).")], 400);
                }
                // Limite per Ruolo Specifico
                $ruoloCalciatoreAsta = optional($calciatoreInAsta)->ruolo;
                if ($ruoloCalciatoreAsta) {
                    $limitePerRuoloCorrente = match ($ruoloCalciatoreAsta) {
                        'P' => $impostazioniLega->num_portieri, 'D' => $impostazioniLega->num_difensori,
                        'C' => $impostazioniLega->num_centrocampisti, 'A' => $impostazioniLega->num_attaccanti,
                        default => 0,
                    };
                    if (($conteggioRuoli[$ruoloCalciatoreAsta] ?? 0) >= $limitePerRuoloCorrente && $limitePerRuoloCorrente > 0) {
                        return response()->json(['error' => $this->sanitizeForJsonResponse("Limite giocatori per ruolo {$ruoloCalciatoreAsta} raggiunto.")], 400);
                    }
                } else { // CalciatoreInAsta o il suo ruolo non è definito, errore
                    return response()->json(['error' => $this->sanitizeForJsonResponse('Dati calciatore per validazione ruolo mancanti.')], 500);
                }
                // TODO: AGGIUNGERE VALIDAZIONE REGOLA PORTIERI E ULTIMO CREDITO QUI
                
                
                // --- OFFERTA VALIDA ---
                // Ad ogni rilancio valido, il countdown riparte dalla durata piena
                $timestampFineAggiornato = Carbon::now()->addSeconds($impostazioniLega->durata_countdown_secondi);
                Log::info("Rilancio valido per asta ID {$chiamata->id}. Timer resettato. Nuova fine: {$timestampFineAggiornato->toDateTimeString()}");
                
                $chiamata->prezzo_attuale_tap = $nuovoPrezzoProposto;
                $chiamata->miglior_offerente_tap_id = $user->id;
                $chiamata->timestamp_fine_tap_prevista = $timestampFineAggiornato;
                $chiamata->save();
                
                Log::info("Dati salvati per asta ID {$chiamata->id}. Nuovo prezzo: {$chiamata->prezzo_attuale_tap}. Miglior offerente: {$user->name}. Nuova fine: {$chiamata->timestamp_fine_tap_prevista->toDateTimeString()}");
                
                // Ricarica il modello per avere il nome del miglior offerente tramite relazione
                $chiamata->loadMissing('migliorOfferenteTap');
                $nomeMigliorOfferente = optional($chiamata->migliorOfferenteTap)->name ?? 'Nessuno';
                
                return response()->json([
                    'success' => true,
                    'message' => $this->sanitizeForJsonResponse('Offerta registrata!'),
                    'nuovo_prezzo' => $chiamata->prezzo_attuale_tap,
                    'miglior_offerente' => $this->sanitizeForJsonResponse($nomeMigliorOfferente),
                    'timestamp_fine_tap_prevista_unix' => $chiamata->timestamp_fine_tap_prevista->timestamp,
                ]);
            }); // Fine DB::transaction
        } catch (Exception $e) {
            Log::error("Errore gestisciRilancioTap per asta ID {$chiamataAsta->id}, utente {$user->id}: " . $e->getMessage() . " Line: " . $e->getLine() . " File: " . $e->getFile());
            return response()->json(['error' => $this->sanitizeForJsonResponse('Errore tecnico durante la registrazione dell\'offerta.')], 500);
        }
    }
    /**
     * Restituisce lo stato attuale di un'asta TAP attiva per il polling AJAX.
     */
    // In app/Http/Controllers/AstaController.php
    public function statoAstaTap(ChiamataAsta $chiamataAsta)
    {
        Log::info("[Polling Server] Richiesta stato per chiamata asta ID: {$chiamataAsta->id}. Stato DB: {$chiamataAsta->stato_chiamata}");
        
        // Ricarica sempre l'entità e le relazioni necessarie per avere i dati più freschi
        $chiamataAsta->refresh()->loadMissing(['calciatore', 'migliorOfferenteTap', 'utenteChiamante', 'vincitore']);
        
        $nomeMigliorOfferente = null;
        if (isset($chiamataAsta->migliorOfferenteTap)) {
            $nomeMigliorOfferente = $chiamataAsta->migliorOfferenteTap->name;
        } elseif ($chiamataAsta->miglior_offerente_tap_id === $chiamataAsta->user_id_chiamante && isset($chiamataAsta->utenteChiamante)) {
            $nomeMigliorOfferente = $chiamataAsta->utenteChiamante->name;
        }
        $nomeMigliorOfferente = $nomeMigliorOfferente ?? 'Nessuno';
        
        $tempoRimanente = 0;
        $timestampFineUnix = null; // Inizializza a null
        
        if ($chiamataAsta->timestamp_fine_tap_prevista) {
            // Il cast a 'datetime' nel modello ChiamataAsta dovrebbe rendere questo un oggetto Carbon
            if ($chiamataAsta->timestamp_fine_tap_prevista instanceof \Carbon\Carbon) {
                $timestampFineUnix = $chiamataAsta->timestamp_fine_tap_prevista->timestamp; // Prendi il timestamp Unix (secondi)
                if ($chiamataAsta->stato_chiamata === 'in_asta_tap_live') {
                    $tempoRimanente = Carbon::now()->diffInSeconds($chiamataAsta->timestamp_fine_tap_prevista, false);
                    if($tempoRimanente < 0) $tempoRimanente = 0;
                }
            } else { // Fallback se non è un oggetto Carbon (improbabile con il cast corretto)
                Log::warning("[Polling Server] timestamp_fine_tap_prevista non è Carbon per asta ID {$chiamataAsta->id}. Valore: " . $chiamataAsta->timestamp_fine_tap_prevista);
                $parsed = strtotime($chiamataAsta->timestamp_fine_tap_prevista);
                if ($parsed !== false) {
                    $timestampFineUnix = $parsed;
                    if ($chiamataAsta->stato_chiamata === 'in_asta_tap_live') {
                        $tempoRimanente = $parsed - Carbon::now()->timestamp;
                        if($tempoRimanente < 0) $tempoRimanente = 0;
                    }
                }
            }
        }
        Log::info("[Polling Server] Timestamp fine calcolato (Unix): {$timestampFineUnix}, Secondi rimanenti: {$tempoRimanente}");
        
        
        $messaggioEsitoFinale = session()->pull('messaggio_esito_asta_' . $chiamataAsta->id);
        if (!$messaggioEsitoFinale && $chiamataAsta->stato_chiamata === 'conclusa_tap_assegnato' && isset($chiamataAsta->vincitore) && isset($chiamataAsta->calciatore)) {
            $messaggioEsitoFinale = "Asta conclusa: {$this->sanitizeForJsonResponse(optional($chiamataAsta->calciatore)->nome_completo)} assegnato a {$this->sanitizeForJsonResponse(optional($chiamataAsta->vincitore)->name)} per {$chiamataAsta->prezzo_finale_assegnazione} crediti.";
        } elseif (!$messaggioEsitoFinale && $chiamataAsta->stato_chiamata === 'conclusa_tap_non_assegnato' && isset($chiamataAsta->calciatore)) {
            $messaggioEsitoFinale = "Asta per {$this->sanitizeForJsonResponse(optional($chiamataAsta->calciatore)->nome_completo)} conclusa senza offerte valide.";
        } // ... altri stati conclusi ...
        
        $responseData = [
            'status' => $chiamataAsta->stato_chiamata,
            'chiamata_id' => $chiamataAsta->id,
            'calciatore_id' => optional($chiamataAsta->calciatore)->id,
            'calciatore_nome' => $this->sanitizeForJsonResponse(optional($chiamataAsta->calciatore)->nome_completo),
            'calciatore_ruolo' => optional($chiamataAsta->calciatore)->ruolo,
            'calciatore_squadra_serie_a' => $this->sanitizeForJsonResponse(optional($chiamataAsta->calciatore)->squadra_serie_a),
            'prezzo_partenza_tap' => $chiamataAsta->prezzo_partenza_tap,
            'prezzo_attuale' => $chiamataAsta->prezzo_attuale_tap,
            'miglior_offerente' => $this->sanitizeForJsonResponse($nomeMigliorOfferente),
            'miglior_offerente_id' => $chiamataAsta->miglior_offerente_tap_id,
            'timestamp_fine_prevista_unix' => $timestampFineUnix, // DEVE ESSERE UN NUMERO O NULL
            'secondi_rimanenti' => $tempoRimanente,
            'messaggio_esito' => $this->sanitizeForJsonResponse($messaggioEsitoFinale),
            'vincitore_nome' => ($chiamataAsta->stato_chiamata === 'conclusa_tap_assegnato' && isset($chiamataAsta->vincitore)) ? $this->sanitizeForJsonResponse(optional($chiamataAsta->vincitore)->name) : null,
            'prezzo_finale' => ($chiamataAsta->stato_chiamata === 'conclusa_tap_assegnato') ? $chiamataAsta->prezzo_finale_assegnazione : null,
        ];
        Log::info("[Polling Server] Dati inviati per asta ID {$chiamataAsta->id}: ", $responseData);
        return response()->json($responseData);
    }
    
    /**
     * Finalizza un'asta TAP quando il tempo è scaduto.
     */
    public function finalizzaAstaTapScaduta(Request $request, ChiamataAsta $chiamataAsta)
    {
        Log::info("--- RICHIESTA FINALIZZAZIONE ASTA TAP ID: {$chiamataAsta->id} ---");
        Log::info("Stato chiamata ricevuto: {$chiamataAsta->stato_chiamata}");
        Log::info("Timestamp fine previsto (DB): " . (optional($chiamataAsta->timestamp_fine_tap_prevista)->toDateTimeString() ?? 'N/D'));
        Log::info("Ora server attuale: " . Carbon::now()->toDateTimeString());
        
        try {
            return DB::transaction(function () use ($chiamataAsta, $request) {
                $chiamata = ChiamataAsta::where('id', $chiamataAsta->id)
                ->lockForUpdate()
                ->with(['calciatore', 'utenteChiamante', 'migliorOfferenteTap'])
                ->first();
                
                if (!$chiamata) {
                    Log::error("Finalizzazione: Chiamata ID {$chiamataAsta->id} non trovata.");
                    return response()->json(['status' => 'errore_non_trovata', 'message' => $this->sanitizeForJsonResponse('Chiamata asta non trovata.')], 404);
                }
                if ($chiamata->stato_chiamata !== 'in_asta_tap_live') {
                    Log::warning("Finalizzazione: Asta ID {$chiamata->id} non più live (stato: {$chiamata->stato_chiamata}). Richiesta ridondante.");
                    return response()->json(['status' => 'non_finalizzata_gia_conclusa', 'message' => $this->sanitizeForJsonResponse('Asta non più live o già finalizzata.')]);
                }
                if ($chiamata->timestamp_fine_tap_prevista && Carbon::parse($chiamata->timestamp_fine_tap_prevista)->isFuture()) {
                    Log::warning("Finalizzazione: Asta ID {$chiamata->id} non ancora scaduta. Ora server: ".Carbon::now()->toDateTimeString().", Fine: ".$chiamata->timestamp_fine_tap_prevista->toDateTimeString());
                    return response()->json(['status' => 'non_scaduta', 'message' => $this->sanitizeForJsonResponse('Asta non ancora ufficialmente scaduta.')]);
                }
                
                $calciatoreNome = optional($chiamata->calciatore)->nome_completo ?? 'Sconosciuto';
                $messaggioEsito = '';
                
                if ($chiamata->miglior_offerente_tap_id && $chiamata->prezzo_attuale_tap >= ($chiamata->prezzo_partenza_tap > 0 ? $chiamata->prezzo_partenza_tap : 1)) {
                    $vincitore = User::find($chiamata->miglior_offerente_tap_id);
                    if ($vincitore && $vincitore->crediti_rimanenti >= $chiamata->prezzo_attuale_tap) {
                        GiocatoreAcquistato::create([ /* ... */
                            'user_id' => $vincitore->id, 'calciatore_id' => $chiamata->calciatore_id,
                            'prezzo_acquisto' => $chiamata->prezzo_attuale_tap,
                            'ruolo_al_momento_acquisto' => optional($chiamata->calciatore)->ruolo,
                            // TODO: Aggiungi 'tag_lista_acquisto' qui se la tabella giocatori_acquistati lo ha
                        ]);
                        $vincitore->decrement('crediti_rimanenti', $chiamata->prezzo_attuale_tap);
                        $chiamata->stato_chiamata = 'conclusa_tap_assegnato';
                        $chiamata->vincitore_user_id = $vincitore->id;
                        $chiamata->prezzo_finale_assegnazione = $chiamata->prezzo_attuale_tap;
                        $chiamata->save();
                        Log::info("Asta TAP ID {$chiamata->id} ASSEGNATA. {$calciatoreNome} a {$vincitore->name} per {$chiamata->prezzo_attuale_tap}.");
                        $messaggioEsito = "Calciatore {$this->sanitizeForJsonResponse($calciatoreNome)} assegnato a {$this->sanitizeForJsonResponse($vincitore->name)} per {$chiamata->prezzo_attuale_tap} crediti!";
                        $request->session()->flash('messaggio_esito_asta_' . $chiamata->id, $messaggioEsito);
                        return response()->json(['status' => 'assegnato', 'message' => $messaggioEsito, 'vincitore' => $this->sanitizeForJsonResponse(optional($vincitore)->name), 'calciatore' => $this->sanitizeForJsonResponse($calciatoreNome), 'prezzo' => $chiamata->prezzo_attuale_tap]);
                    } else { /* ... gestione errore crediti/vincitore non trovato ... */
                        $chiamata->stato_chiamata = 'conclusa_tap_non_assegnato'; $chiamata->save();
                        Log::error("Asta TAP ID {$chiamata->id}: Errore crediti/vincitore.");
                        $messaggioEsito = "Errore assegnazione per asta di {$this->sanitizeForJsonResponse($calciatoreNome)}.";
                        $request->session()->flash('messaggio_esito_asta_' . $chiamata->id, $messaggioEsito);
                        return response()->json(['status' => 'errore_assegnazione', 'message' => $messaggioEsito], 422);
                    }
                } else { /* ... gestione nessuna offerta valida ... */
                    $chiamata->stato_chiamata = 'conclusa_tap_non_assegnato'; $chiamata->save();
                    Log::info("Asta TAP ID {$chiamata->id} per {$calciatoreNome} conclusa senza assegnazione.");
                    $messaggioEsito = "Asta per {$this->sanitizeForJsonResponse($calciatoreNome)} conclusa senza offerte.";
                    $request->session()->flash('messaggio_esito_asta_' . $chiamata->id, $messaggioEsito);
                    return response()->json(['status' => 'non_assegnato', 'message' => $messaggioEsito]);
                }
            });
        } catch (Exception $e) {
            Log::error("Catch generale finalizzazione asta ID {$chiamataAsta->id}: " . $e->getMessage());
            return response()->json(['status' => 'errore_server', 'message' => $this->sanitizeForJsonResponse('Errore server finalizzazione.')], 500);
        }
    }
    
    public function mostraAstaLive(Request $request) // Aggiunto Request se vuoi usarlo per qualcosa in futuro
    {
        $impostazioniLega = ImpostazioneLega::firstOrFail(); // Assicura che esista sempre una riga
        $astaTapAttiva = null;
        $calciatoreInAsta = null;
        $migliorOfferente = null;
        $utenteLoggato = Auth::user();
        $creditiRimanenti = $utenteLoggato ? $utenteLoggato->crediti_rimanenti : 0; // Crediti dell'utente attuale
        
        if ($impostazioniLega->modalita_asta === 'tap') {
            $astaTapAttiva = ChiamataAsta::where('stato_chiamata', 'in_asta_tap_live')
            // Se vuoi filtrare per il tag lista attivo nell'asta TAP:
            // ->where('tag_lista_calciatori', $impostazioniLega->tag_lista_attiva)
            ->with(['calciatore', 'migliorOfferenteTap', 'utenteChiamante']) // Eager load
            ->first(); // Ci aspettiamo al massimo una TAP live alla volta
            
            if ($astaTapAttiva) {
                $calciatoreInAsta = $astaTapAttiva->calciatore;
                if ($astaTapAttiva->migliorOfferenteTap) {
                    $migliorOfferente = $astaTapAttiva->migliorOfferenteTap;
                } elseif (isset($astaTapAttiva->utenteChiamante) && $astaTapAttiva->miglior_offerente_tap_id == $astaTapAttiva->user_id_chiamante) {
                    // Se il miglior offerente (ID) è chi ha chiamato, e il prezzo è quello di partenza
                    // Questo assicura che $migliorOfferente (l'oggetto User) sia popolato.
                    $migliorOfferente = $astaTapAttiva->utenteChiamante;
                }
            }
        }
        // Se la modalità è 'voce', $astaTapAttiva e $calciatoreInAsta rimarranno null.
        // La vista 'asta.live.blade.php' che ti ho fornito gestisce la visualizzazione appropriata
        // mostrando un messaggio per l'asta a voce.
        
        return view('asta.live', compact(
            'impostazioniLega',
            'astaTapAttiva',        // Questo è l'oggetto ChiamataAsta, o null
            'calciatoreInAsta',   // L'oggetto Calciatore, o null
            'migliorOfferente',   // L'oggetto User del miglior offerente, o null
            'creditiRimanenti'    // Crediti dell'utente che sta visualizzando
            ));
    }
}