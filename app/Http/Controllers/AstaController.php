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
    // ... (altri metodi come dashboard, mostraCalciatoriDisponibili, registraChiamata, gestisciRilancioTap, statoAstaTap) ...
    
    public function finalizzaAstaTapScaduta(Request $request, ChiamataAsta $chiamataAsta)
    {
        Log::info("--- RICHIESTA FINALIZZAZIONE ASTA TAP ID: {$chiamataAsta->id} (Route Model Bound) ---");
        
        // ... (logica esistente per logging iniziale timestamp, ecc.) ...
        
        try {
            return DB::transaction(function () use ($chiamataAsta, $request) {
                $chiamata = ChiamataAsta::where('id', $chiamataAsta->id)
                ->lockForUpdate()
                ->with(['calciatore', 'utenteChiamante', 'migliorOfferenteTap', 'vincitore'])
                ->first();
                
                if (!$chiamata) {
                    Log::error("[Finalizza Asta] Chiamata ID {$chiamataAsta->id} non trovata con lockForUpdate.");
                    return response()->json([
                        'status' => 'errore_non_trovata',
                        'chiamata_id' => $chiamataAsta->id,
                        'messaggio_esito' => $this->sanitizeForJsonResponse('Chiamata asta non trovata durante la finalizzazione critica.'),
                        'asta_live_corrente_id' => $this->getAstaLiveCorrenteId(optional($chiamataAsta)->tag_lista_calciatori)
                    ], 404);
                }
                
                // Salva l'ID dell'utente che ha originariamente chiamato l'asta
                $idUtenteChiamanteOriginario = $chiamata->user_id_chiamante;
                $impostazioniLega = ImpostazioneLega::firstOrFail(); // Carica le impostazioni
                
                // ... (Logica per parsare $timestampFineCarbon) ...
                $timestampFineCarbon = null;
                if ($chiamata->timestamp_fine_tap_prevista) {
                    if ($chiamata->timestamp_fine_tap_prevista instanceof \Carbon\Carbon) {
                        $timestampFineCarbon = $chiamata->timestamp_fine_tap_prevista;
                    } else {
                        try {
                            $timestampFineCarbon = Carbon::parse((string)$chiamata->timestamp_fine_tap_prevista);
                        } catch (\Exception $carbonEx) {
                            Log::error("[Finalizza Asta DB Lock] Errore parsing manuale di 'timestamp_fine_tap_prevista': " . $carbonEx->getMessage());
                        }
                    }
                }
                
                if ($chiamata->stato_chiamata !== 'in_asta_tap_live') {
                    Log::warning("[Finalizza Asta] Asta ID {$chiamata->id} non più 'in_asta_tap_live' (stato attuale: {$chiamata->stato_chiamata}).");
                    return response()->json([
                        'status' => $chiamata->stato_chiamata,
                        'chiamata_id' => $chiamata->id,
                        'messaggio_esito' => $this->sanitizeForJsonResponse('Asta non più live o già finalizzata.'),
                        'asta_live_corrente_id' => $this->getAstaLiveCorrenteId($chiamata->tag_lista_calciatori)
                    ]);
                }
                
                $scadutaEffettivamente = true;
                if ($timestampFineCarbon) {
                    if ($timestampFineCarbon->addSeconds(2)->isFuture()) { // Tolleranza
                        $scadutaEffettivamente = false;
                    }
                }
                
                if (!$scadutaEffettivamente) {
                    Log::warning("[Finalizza Asta] Tentativo di finalizzare Asta ID {$chiamata->id} non ancora scaduta.");
                    return response()->json([
                        'status' => 'non_scaduta_ancora',
                        'chiamata_id' => $chiamata->id,
                        'messaggio_esito' => $this->sanitizeForJsonResponse('Asta non ancora ufficialmente scaduta.'),
                        'asta_live_corrente_id' => $chiamata->id
                    ]);
                }
                
                // ... (TUTTA la tua logica esistente per determinare vincitore, fare controlli crediti, limiti rosa, banco saltato, ecc.) ...
                // Questa logica dovrebbe determinare $esitoFinaleChiamata, $vincitoreSalvato, $prezzoFinaleSalvato, $messaggioEsito
                
                // Esempio di come potrebbe essere la fine della tua logica di assegnazione:
                $calciatoreDaAssegnare = $chiamata->calciatore;
                $calciatoreNome = optional($calciatoreDaAssegnare)->nome_completo ?? 'Sconosciuto';
                $messaggioEsito = ''; // Verrà popolato dalla logica di assegnazione
                
                $vincitorePotenziale = null;
                // ... (tua logica per trovare $vincitorePotenziale) ...
                
                $esitoFinaleChiamata = 'conclusa_tap_non_assegnato'; // Default
                $vincitoreSalvato = null;
                $prezzoFinaleSalvato = null;
                
                if ($vincitorePotenziale /* && altre condizioni valide */) {
                    // ... (logica di assegnazione) ...
                    // GiocatoreAcquistato::create(...);
                    // $vincitorePotenziale->decrement(...);
                    $esitoFinaleChiamata = 'conclusa_tap_assegnato';
                    $vincitoreSalvato = $vincitorePotenziale->id;
                    $prezzoFinaleSalvato = $chiamata->prezzo_attuale_tap;
                    $messaggioEsito = "Asta conclusa: {$this->sanitizeForJsonResponse($calciatoreNome)} assegnato a {$this->sanitizeForJsonResponse($vincitorePotenziale->name)} per {$prezzoFinaleSalvato} crediti!";
                    Log::info("[Finalizza Asta] ASSEGNATA: {$calciatoreNome} a {$vincitorePotenziale->name} per {$prezzoFinaleSalvato}.");
                } else {
                    // ... (logica per non assegnato) ...
                    $messaggioEsito = "Asta per {$this->sanitizeForJsonResponse($calciatoreNome)} conclusa senza assegnazione valida.";
                    Log::info("[Finalizza Asta] NON ASSEGNATA: {$calciatoreNome}.");
                }
                // Fine logica di assegnazione
                
                $chiamata->stato_chiamata = $esitoFinaleChiamata;
                $chiamata->vincitore_user_id = $vincitoreSalvato;
                $chiamata->prezzo_finale_assegnazione = $prezzoFinaleSalvato;
                $chiamata->timestamp_fine_tap_prevista = null; // Asta conclusa
                $chiamata->save();
                
                // === PUNTO CHIAVE: AVANZA IL TURNO DOPO CHE L'ASTA È CONCLUSA ===
                if ($impostazioniLega->usa_ordine_chiamata && $idUtenteChiamanteOriginario) {
                    Log::info("[FinalizzaAstaTAP ID:{$chiamata->id}] Avanzamento turno per utente chiamante originario ID: {$idUtenteChiamanteOriginario}");
                    $impostazioniLega->avanzaTurnoChiamata($idUtenteChiamanteOriginario);
                }
                // ==============================================================
                
                Log::info("[Finalizza Asta] Asta TAP ID {$chiamata->id} conclusa. Stato: {$chiamata->stato_chiamata}");
                
                return response()->json([
                    'status' => $chiamata->stato_chiamata,
                    'chiamata_id' => $chiamata->id,
                    'messaggio_esito' => $this->sanitizeForJsonResponse($messaggioEsito),
                    'vincitore_nome' => $this->sanitizeForJsonResponse(optional($chiamata->vincitore)->name),
                    'calciatore_nome' => $this->sanitizeForJsonResponse($calciatoreNome),
                    'prezzo_finale' => $chiamata->prezzo_finale_assegnazione,
                    'asta_live_corrente_id' => $this->getAstaLiveCorrenteId($chiamata->tag_lista_calciatori)
                ]);
            });
        } catch (Exception $e) {
            Log::error("[Finalizza Asta Catch Generale] Errore per Chiamata ID {$chiamataAsta->id}: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            // ... (gestione eccezioni response()->json) ...
            return response()->json([
                'status' => 'errore_server_finalizzazione',
                'chiamata_id' => $chiamataAsta->id,
                'messaggio_esito' => $this->sanitizeForJsonResponse('Errore server durante la finalizzazione dell\'asta: '. $e->getMessage()),
                'asta_live_corrente_id' => $this->getAstaLiveCorrenteId(optional(ChiamataAsta::find($chiamataAsta->id))->tag_lista_calciatori)
            ], 500);
        }
    }
    
    /**
     * Funzione helper per sanificare le stringhe per l'output JSON.
     */
    private function sanitizeForJsonResponse($string)
    {
        if ($string === null) return null;
        $string = (string)$string;
        $converted = @mb_convert_encoding($string, 'UTF-8', 'UTF-8');
        if ($converted === false || !mb_check_encoding($converted, 'UTF-8')) {
            $sanitized = preg_replace('/[^\x20-\x7E\p{L}\p{N}\p{P}\p{S}\p{Z}]/u', '', $string);
            if (mb_check_encoding($sanitized, 'UTF-8')) {
                return $sanitized;
            }
            Log::warning("[Sanitize JSON] Stringa non convertibile a UTF-8 valido (originale in hex): " . bin2hex($string));
            return "Dato non visualizzabile (encoding errato)";
        }
        return $converted;
    }

    /**
     * Gestisce la visualizzazione della dashboard (smista tra admin e squadra).
     */
    public function dashboard()
    {
        Log::info("--- Dashboard Utente Invocata ---");
        $user = Auth::user();
        $impostazioniLega = ImpostazioneLega::first();
        
        if (!$impostazioniLega) {
            Log::error("Impostazioni Lega non trovate!");
            if ($user->is_admin) {
                return redirect()->route('admin.dashboard')->with('warning', 'Configurare prima le Impostazioni Lega.');
            } else {
                return back()->with('error', 'L\'amministratore deve ancora configurare la lega. Riprova più tardi.');
            }
        }
        
        if ($user->is_admin) {
            return redirect()->route('admin.dashboard');
        } else {
            $squadra = $user;
            $tagListaAttiva = $impostazioniLega->tag_lista_attiva;
            Log::info("Dashboard per Squadra: {$squadra->name}, Tag Lista Attiva: {$tagListaAttiva}");
            
            $rosaQuery = $squadra->giocatoriAcquistati()->with('calciatore');
            if ($tagListaAttiva) {
                $rosaQuery->whereHas('calciatore', function ($q) use ($tagListaAttiva) {
                    $q->where('tag_lista_inserimento', $tagListaAttiva);
                });
            }
            $rosa = $rosaQuery->get();
            Log::info("Numero giocatori in rosa (filtrati per tag se attivo): " . $rosa->count());
            
            $costoTotaleRosa = $rosa->sum('prezzo_acquisto');
            Log::info("Costo totale rosa: {$costoTotaleRosa}");
            
            $conteggioRuoli = collect(['P' => 0, 'D' => 0, 'C' => 0, 'A' => 0]);
            $costiPerRuolo = collect(['P' => 0, 'D' => 0, 'C' => 0, 'A' => 0]);
            
            if ($rosa->isNotEmpty()) {
                Log::info("La rosa non è vuota, calcolo conteggi e costi...");
                foreach ($rosa as $acquisto) {
                    if ($acquisto->calciatore) {
                        $ruolo = $acquisto->calciatore->ruolo;
                        Log::info("Processing acquisto: Calciatore {$acquisto->calciatore->nome_completo}, Ruolo: {$ruolo}, Prezzo: {$acquisto->prezzo_acquisto}");
                        if ($conteggioRuoli->has($ruolo)) {
                            $conteggioRuoli->put($ruolo, $conteggioRuoli->get($ruolo) + 1);
                            $costiPerRuolo->put($ruolo, $costiPerRuolo->get($ruolo) + $acquisto->prezzo_acquisto);
                        } else {
                            Log::warning("Ruolo non atteso trovato: {$ruolo} per calciatore ID {$acquisto->calciatore_id}");
                        }
                    } else {
                        Log::warning("Acquisto ID {$acquisto->id} non ha un calciatore associato o caricato.");
                    }
                }
            } else {
                Log::info("La rosa è vuota.");
            }
            
            Log::info("Conteggio Ruoli Finale: ", $conteggioRuoli->all());
            Log::info("Costi Per Ruolo Finale: ", $costiPerRuolo->all()); // Log importante
            
            // PRIMO PUNTO DI DEBUG CON dd()
            // dd($costiPerRuolo, $conteggioRuoli); // Decommenta questa riga per il primo test
            
            $limitiRuoli = [
                'P' => $impostazioniLega->num_portieri,
                'D' => $impostazioniLega->num_difensori,
                'C' => $impostazioniLega->num_centrocampisti,
                'A' => $impostazioniLega->num_attaccanti,
            ];
            $numeroGiocatoriInRosa = $rosa->count();
            $limiteGiocatoriTotaliInRosa = array_sum($limitiRuoli);
            
            $dataForView = [
                'squadra' => $squadra,
                'rosa' => $rosa,
                'impostazioniLega' => $impostazioniLega,
                'costoTotaleRosa' => $costoTotaleRosa,
                'conteggioRuoli' => $conteggioRuoli,
                'costiPerRuolo' => $costiPerRuolo, // Assicurati che sia qui
                'limitiRuoli' => $limitiRuoli,
                'numeroGiocatoriInRosa' => $numeroGiocatoriInRosa,
                'limiteGiocatoriTotaliInRosa' => $limiteGiocatoriTotaliInRosa
            ];
            
            // SECONDO PUNTO DI DEBUG CON dd()
            Log::info("Dati pronti per la vista: ", $dataForView);
            // dd($dataForView); // Decommenta questa riga per il secondo test
            
            return view('squadra.dashboard', $dataForView); // Passa l'array direttamente
        }
    }
    
    /**
     * Mostra la lista dei calciatori disponibili per l'asta alla squadra.
     */
    public function mostraCalciatoriDisponibili(Request $request)
    {
        $user = Auth::user();
        $impostazioniLega = ImpostazioneLega::firstOrFail(); // Assume che le impostazioni esistano sempre qui
        $faseAstaCorrente = $impostazioniLega->fase_asta_corrente;
        $tagListaAttiva = $impostazioniLega->tag_lista_attiva;
        
        $calciatori = collect();
        $messaggio = null;
        $squadreSerieAUniche = collect();
        $chiamataTapPossibile = false;
        $prossimoChiamanteInfo = null;
        
        
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
            
            $idsCalciatoriAcquistatiQuery = GiocatoreAcquistato::query();
            if ($tagListaAttiva) {
                $idsCalciatoriAcquistatiQuery->whereHas('calciatore', function ($q) use ($tagListaAttiva) {
                    $q->where('tag_lista_inserimento', $tagListaAttiva);
                });
            }
            $idsCalciatoriAcquistati = $idsCalciatoriAcquistatiQuery->pluck('calciatore_id')->all();
            
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
            ->where('ruolo', $faseAstaCorrente);
            if (!empty($idsCalciatoriAcquistati)) {
                $queryBaseFiltri->whereNotIn('id', $idsCalciatoriAcquistati);
            }
            $squadreSerieAUniche = (clone $queryBaseFiltri)->select('squadra_serie_a')->distinct()->orderBy('squadra_serie_a')->pluck('squadra_serie_a');
            
            if ($impostazioniLega->modalita_asta === 'tap') {
                $astaInCorso = ChiamataAsta::whereIn('stato_chiamata', ['in_attesa_admin', 'in_asta_tap_live'])
                // ->where('tag_lista_calciatori', $tagListaAttiva) // Opzionale, se le chiamate sono taggate
                ->exists();
                $chiamataTapPossibile = !$astaInCorso;
                
                if ($chiamataTapPossibile && $impostazioniLega->usa_ordine_chiamata) {
                    if (Auth::id() != $impostazioniLega->prossimo_turno_chiamata_user_id) {
                        $chiamataTapPossibile = false;
                        $prossimoChiamante = User::find($impostazioniLega->prossimo_turno_chiamata_user_id);
                        $prossimoChiamanteInfo = $prossimoChiamante ? $prossimoChiamante->name : 'Non definito';
                        $messaggio = ($messaggio ? $messaggio . ' ' : ''); // Concatena se già esiste un messaggio
                        $messaggio .= 'Non è il tuo turno di chiamata. Prossimo a chiamare: ' . $prossimoChiamanteInfo;
                    }
                }
            }
        }
        
        return view('asta.calciatori-disponibili', compact(
            'calciatori', 'messaggio', 'faseAstaCorrente', 'tagListaAttiva',
            'squadreSerieAUniche', 'impostazioniLega', 'chiamataTapPossibile', 'prossimoChiamanteInfo'
            ));
    }
    
    
    public function registraChiamata(Request $request, Calciatore $calciatore)
    {
        $user = Auth::user();
        // --- NUOVA VALIDAZIONE LIMITI ROSA PER IL CHIAMANTE ---
        $impostazioniLega = ImpostazioneLega::firstOrFail(); // Assicurati sia caricata
        $ruoloCalciatoreDaChiamare = $calciatore->ruolo;
        $limitePerRuolo = 0;
        switch ($ruoloCalciatoreDaChiamare) {
            case 'P': $limitePerRuolo = $impostazioniLega->num_portieri; break;
            case 'D': $limitePerRuolo = $impostazioniLega->num_difensori; break;
            case 'C': $limitePerRuolo = $impostazioniLega->num_centrocampisti; break;
            case 'A': $limitePerRuolo = $impostazioniLega->num_attaccanti; break;
        }
        
        if ($limitePerRuolo > 0) {
            $giocatoriAttualiNelRuolo = GiocatoreAcquistato::where('user_id', $user->id)
            ->whereHas('calciatore', function($query) use ($ruoloCalciatoreDaChiamare, $impostazioniLega) {
                $query->where('ruolo', $ruoloCalciatoreDaChiamare);
                // Assicurati di filtrare per il tag_lista_attiva se i tuoi GiocatoreAcquistato
                // sono specifici per stagione/tag.
                if ($impostazioniLega->tag_lista_attiva) {
                    $query->where('tag_lista_inserimento', $impostazioniLega->tag_lista_attiva);
                }
            })->count();
            
            Log::info("[Registra Chiamata Validazione] Utente: {$user->name}, Calciatore: {$calciatore->nome_completo} ({$ruoloCalciatoreDaChiamare}), Attuali nel ruolo: {$giocatoriAttualiNelRuolo}, Limite: {$limitePerRuolo}");
            
            if ($giocatoriAttualiNelRuolo >= $limitePerRuolo) {
                $messaggioErrore = "Hai già raggiunto il limite di {$limitePerRuolo} giocatori per il ruolo {$ruoloCalciatoreDaChiamare}. Non puoi chiamare {$calciatore->nome_completo}.";
                Log::warning("[Registra Chiamata Bloccata per LIMITE] Utente: {$user->name} ({$user->id}) ha tentato di chiamare {$calciatore->nome_completo} (ID: {$calciatore->id}) per il ruolo {$ruoloCalciatoreDaChiamare}. Rosa attuale per ruolo: {$giocatoriAttualiNelRuolo}, Limite: {$limitePerRuolo}. Messaggio: " . $messaggioErrore);
                
                // USA UN REDIRECT ESPLICITO ALLA ROTTA DEI CALCIATORI DISPONIBILI
                return redirect()->route('asta.calciatori.disponibili')
                ->with('error', $messaggioErrore)
                ->withInput(); // withInput() è utile se hai filtri o altri input nella pagina precedente
            }
        }
        // --- FINE NUOVA VALIDAZIONE LIMITI ROSA ---
        
        // Validazioni Preliminari
        if ($impostazioniLega->modalita_asta !== 'tap') {
            return back()->with('error', 'La modalità asta TAP non è attualmente attiva.');
        }
        if (!in_array($impostazioniLega->fase_asta_corrente, ['P', 'D', 'C', 'A'])) {
            return back()->with('error', "L'asta non è in una fase attiva (P, D, C, A) per la chiamata dei giocatori.");
        }
        if ($calciatore->ruolo !== $impostazioniLega->fase_asta_corrente) {
            return back()->with('error', "Puoi chiamare solo giocatori del ruolo della fase corrente ({$impostazioniLega->fase_asta_corrente}). Questo giocatore è un {$calciatore->ruolo}.");
        }
        if (!$calciatore->attivo || $calciatore->tag_lista_inserimento !== $impostazioniLega->tag_lista_attiva) {
            return back()->with('error', "Questo calciatore ({$calciatore->nome_completo}) non è disponibile per l'asta attiva.");
        }
        
        $giaAcquistatoQuery = GiocatoreAcquistato::where('calciatore_id', $calciatore->id);
        if ($impostazioniLega->tag_lista_attiva) {
            $giaAcquistatoQuery->whereHas('calciatore', function ($q) use ($impostazioniLega) {
                $q->where('tag_lista_inserimento', $impostazioniLega->tag_lista_attiva);
            });
        }
        if ($giaAcquistatoQuery->exists()) {
            return back()->with('error', "{$calciatore->nome_completo} è già stato acquistato in questa lega/asta.");
        }
        
        $astaTapAttivaEsistente = ChiamataAsta::whereIn('stato_chiamata', ['in_attesa_admin', 'in_asta_tap_live'])
        // ->where('tag_lista_calciatori', $impostazioniLega->tag_lista_attiva)
        ->exists();
        if ($astaTapAttivaEsistente) {
            return back()->with('error', 'C\'è già un giocatore chiamato o un\'asta TAP in corso. Attendi il suo completamento.');
        }
        
        if ($impostazioniLega->usa_ordine_chiamata && $user->id != $impostazioniLega->prossimo_turno_chiamata_user_id) {
            $prossimoChiamante = User::find($impostazioniLega->prossimo_turno_chiamata_user_id);
            $nomeProssimo = $prossimoChiamante ? $prossimoChiamante->name : 'Non definito';
            return back()->with('error', "Non è il tuo turno di chiamata. Prossimo a chiamare: {$nomeProssimo}.");
        }
        
        $baseAstaEffettiva = ($impostazioniLega->tipo_base_asta === 'credito_singolo') ? 1 : ($calciatore->quotazione_iniziale > 0 ? $calciatore->quotazione_iniziale : 1);
        if ($user->crediti_rimanenti < $baseAstaEffettiva) {
            return back()->with('error', "Non hai abbastanza crediti ({$user->crediti_rimanenti}) per chiamare {$calciatore->nome_completo} (base d'asta: {$baseAstaEffettiva}).");
        }
        
        try {
            DB::beginTransaction();
            
            $statoInizialeChiamata = $impostazioniLega->asta_tap_approvazione_admin ? 'in_attesa_admin' : 'in_asta_tap_live';
            $timestampFineAsta = null;
            $migliorOfferenteIniziale = null;
            
            if ($statoInizialeChiamata === 'in_asta_tap_live') {
                $timestampFineAsta = Carbon::now()->addSeconds($impostazioniLega->durata_countdown_secondi);
                $migliorOfferenteIniziale = $user->id;
                Log::info("Asta TAP auto-avviata per {$calciatore->nome_completo} da {$user->name}. Base: {$baseAstaEffettiva}. Fine: {$timestampFineAsta}");
            }
            
            ChiamataAsta::create([
                'calciatore_id' => $calciatore->id,
                'user_id_chiamante' => $user->id,
                'tag_lista_calciatori' => $impostazioniLega->tag_lista_attiva, // Fondamentale!
                'stato_chiamata' => $statoInizialeChiamata,
                'prezzo_partenza_tap' => $baseAstaEffettiva,
                'prezzo_attuale_tap' => $baseAstaEffettiva,
                'miglior_offerente_tap_id' => $migliorOfferenteIniziale,
                'timestamp_fine_tap_prevista' => $timestampFineAsta,
            ]);
            
            // TODO: ORDINE CHIAMATA: Se l'ordine è attivo e l'asta parte automaticamente (o anche se va in attesa admin, il turno è "usato")
            // if ($impostazioniLega->usa_ordine_chiamata) {
            //     // Qui dovresti avere un metodo sul modello ImpostazioneLega o un service per avanzare il turno
            //     // Esempio: $impostazioniLega->avanzaTurnoChiamata($user->id);
            //     // Il metodo dovrebbe trovare l'utente successivo nell'ordine e aggiornare 'prossimo_turno_chiamata_user_id'
            // }
            
            DB::commit();
            
            $messaggio = $statoInizialeChiamata === 'in_asta_tap_live' ?
            "Asta TAP per {$this->sanitizeForJsonResponse($calciatore->nome_completo)} avviata! Base: {$baseAstaEffettiva}. Fine tra: {$impostazioniLega->durata_countdown_secondi}s." :
            "{$this->sanitizeForJsonResponse($calciatore->nome_completo)} chiamato per asta TAP (Base: {$baseAstaEffettiva})! Attesa approvazione admin.";
            
            $routeDestinazione = ($statoInizialeChiamata === 'in_asta_tap_live') ? 'asta.live' : 'asta.calciatori.disponibili';
            return redirect()->route($routeDestinazione)->with('success', $messaggio);
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Errore registrazione chiamata per {$calciatore->nome_completo} da utente {$user->id}: " . $e->getMessage());
            return back()->with('error', 'Errore tecnico durante la chiamata del giocatore: ' . $this->sanitizeForJsonResponse($e->getMessage()));
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
            return DB::transaction(function () use ($chiamataAsta, $user, $incrementoOfferta) {
                $chiamata = ChiamataAsta::where('id', $chiamataAsta->id)
                ->lockForUpdate()
                ->with('calciatore')
                ->firstOrFail();
                
                $impostazioniLega = ImpostazioneLega::firstOrFail();
                $calciatoreInAsta = $chiamata->calciatore;
                
                if (!$calciatoreInAsta) { // Controllo aggiuntivo
                    Log::error("Tentativo di rilancio su chiamata asta ID {$chiamata->id} senza calciatore associato.");
                    return response()->json(['error' => $this->sanitizeForJsonResponse('Errore: calciatore non trovato per questa asta.')], 500);
                }
                
                
                // --- VALIDAZIONI PRELIMINARI ASTA ---
                if ($chiamata->stato_chiamata !== 'in_asta_tap_live') {
                    return response()->json(['error' => $this->sanitizeForJsonResponse('L\'asta per questo giocatore non è più attiva.')], 400);
                }
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
                
                // 2. Validazioni Limiti Rosa
                $rosaAttualeQuery = GiocatoreAcquistato::where('user_id', $user->id);
                if ($chiamata->tag_lista_calciatori) {
                    $rosaAttualeQuery->whereHas('calciatore', function($q) use ($chiamata) {
                        $q->where('tag_lista_inserimento', $chiamata->tag_lista_calciatori);
                    });
                }
                $rosaAttuale = $rosaAttualeQuery->get();
                
                $conteggioRuoli = collect();
                if ($rosaAttuale->isNotEmpty()) {
                    $idsCalciatoriInRosa = $rosaAttuale->pluck('calciatore_id')->unique()->toArray();
                    if(!empty($idsCalciatoriInRosa)){
                        $queryRuoli = Calciatore::whereIn('id', $idsCalciatoriInRosa);
                        if ($chiamata->tag_lista_calciatori) {
                            $queryRuoli->where('tag_lista_inserimento', $chiamata->tag_lista_calciatori);
                        }
                        $ruoliGiocatoriInRosa = $queryRuoli->pluck('ruolo');
                        $conteggioRuoli = $ruoliGiocatoriInRosa->countBy();
                    }
                }
                $numGiocatoriAttualiInRosa = $rosaAttuale->count();
                
                $limiteGiocatoriSistema = $impostazioniLega->num_portieri + $impostazioniLega->num_difensori + $impostazioniLega->num_centrocampisti + $impostazioniLega->num_attaccanti;
                $ruoloCalciatoreAsta = $calciatoreInAsta->ruolo;
                $limitePerRuoloCorrente = match ($ruoloCalciatoreAsta) {
                    'P' => $impostazioniLega->num_portieri, 'D' => $impostazioniLega->num_difensori,
                    'C' => $impostazioniLega->num_centrocampisti, 'A' => $impostazioniLega->num_attaccanti,
                    default => 0,
                };
                
                Log::info("Validazione Limiti per {$user->name}: Ruolo Asta: {$ruoloCalciatoreAsta}, Limite per Ruolo: {$limitePerRuoloCorrente}, Attuali nel ruolo: " . ($conteggioRuoli[$ruoloCalciatoreAsta] ?? 0));
                Log::info("Conteggio Ruoli completo per {$user->name}: ", $conteggioRuoli->toArray());
                Log::info("Num Giocatori Totali in Rosa (per tag {$chiamata->tag_lista_calciatori}): {$numGiocatoriAttualiInRosa}, Limite Sistema: {$limiteGiocatoriSistema}");
                
                if (($numGiocatoriAttualiInRosa + 1) > $limiteGiocatoriSistema && $limiteGiocatoriSistema > 0) {
                    Log::warning("Blocco offerta per {$user->name}: Superamento limite totale. Attuali: {$numGiocatoriAttualiInRosa}, Limite: {$limiteGiocatoriSistema}");
                    return response()->json(['error' => $this->sanitizeForJsonResponse("Acquistando questo giocatore supereresti il limite massimo di {$limiteGiocatoriSistema} giocatori in rosa.")], 400);
                }
                $giocatoriNelRuoloDopoAcquisto = ($conteggioRuoli[$ruoloCalciatoreAsta] ?? 0) + 1;
                if ($giocatoriNelRuoloDopoAcquisto > $limitePerRuoloCorrente && $limitePerRuoloCorrente > 0) {
                    $giaInRosaPerRuolo = $conteggioRuoli[$ruoloCalciatoreAsta] ?? 0;
                    Log::warning("Blocco offerta per {$user->name}: Superamento limite {$ruoloCalciatoreAsta}. Attuali: {$giaInRosaPerRuolo}, Tentativo: {$giocatoriNelRuoloDopoAcquisto}, Limite: {$limitePerRuoloCorrente}");
                    return response()->json(['error' => $this->sanitizeForJsonResponse("Acquistando questo giocatore supereresti il limite di {$limitePerRuoloCorrente} {$ruoloCalciatoreAsta}. Ne hai già {$giaInRosaPerRuolo}.")], 400);
                }
                
                // 3. Validazione "Regola Banco Saltato"
                $creditiRimanentiDopoOfferta = $user->crediti_rimanenti - $nuovoPrezzoProposto;
                $slotAncoraDaRiempireDopoAcquisto = $limiteGiocatoriSistema - ($numGiocatoriAttualiInRosa + 1);
                if ($slotAncoraDaRiempireDopoAcquisto > 0 && $creditiRimanentiDopoOfferta < $slotAncoraDaRiempireDopoAcquisto) {
                    $messaggioErrore = "Offerta non valida. Dopo questa offerta ti rimarrebbero {$creditiRimanentiDopoOfferta} crediti, ";
                    $messaggioErrore .= "ma dovresti ancora acquistare {$slotAncoraDaRiempireDopoAcquisto} giocatori (almeno 1 credito ciascuno).";
                    Log::warning("Utente {$user->name} bloccato da regola banco saltato: offre {$nuovoPrezzoProposto}, crediti dopo {$creditiRimanentiDopoOfferta}, slot da riempire {$slotAncoraDaRiempireDopoAcquisto}.");
                    return response()->json(['error' => $this->sanitizeForJsonResponse($messaggioErrore)], 400);
                }
                
                // --- OFFERTA VALIDA ---
                $timestampFineAggiornato = Carbon::now()->addSeconds($impostazioniLega->durata_countdown_secondi);
                $chiamata->prezzo_attuale_tap = $nuovoPrezzoProposto;
                $chiamata->miglior_offerente_tap_id = $user->id;
                $chiamata->timestamp_fine_tap_prevista = $timestampFineAggiornato;
                $chiamata->save();
                
                Log::info("Dati salvati per asta ID {$chiamata->id}. Nuovo prezzo: {$chiamata->prezzo_attuale_tap}. Miglior offerente: {$user->name}. Nuova fine: {$chiamata->timestamp_fine_tap_prevista->toDateTimeString()}");
                
                $chiamata->loadMissing('migliorOfferenteTap');
                $nomeMigliorOfferente = $this->sanitizeForJsonResponse(optional($chiamata->migliorOfferenteTap)->name ?? $user->name);
                
                return response()->json([
                    'success' => true,
                    'message' => $this->sanitizeForJsonResponse('Offerta registrata!'),
                    'nuovo_prezzo' => $chiamata->prezzo_attuale_tap,
                    'miglior_offerente' => $nomeMigliorOfferente,
                    'miglior_offerente_id' => $chiamata->miglior_offerente_tap_id,
                    'timestamp_fine_tap_prevista_unix' => $chiamata->timestamp_fine_tap_prevista->timestamp,
                ]);
            });
        } catch (Exception $e) {
            Log::error("Errore gestisciRilancioTap per asta ID {$chiamataAsta->id}, utente {$user->id}: " . $e->getMessage() . " Line: " . $e->getLine() . " File: " . $e->getFile());
            return response()->json(['error' => $this->sanitizeForJsonResponse('Errore tecnico durante la registrazione dell\'offerta.')], 500);
        }
    }
    
    // In app/Http/Controllers/AstaController.php
    // In app/Http/Controllers/AstaController.php
    // In app/Http/Controllers/AstaController.php
    // In app/Http/Controllers/AstaController.php
    
    public function statoAstaTap(Request $request, $idChiamataAsta) // Riceve l'ID come parametro
    {
        Log::info("[Polling Server V3] Richiesta stato per chiamata ID URL: {$idChiamataAsta}.");
        
        $chiamataRichiesta = ChiamataAsta::with([
            'calciatore',
            'migliorOfferenteTap', // Relazione al modello User
            'utenteChiamante',     // Relazione al modello User
            'vincitore'            // Relazione al modello User
        ])->find($idChiamataAsta);
        
        $impostazioniLega = ImpostazioneLega::first(); // Necessario per logica asta_live_corrente_id e crediti
        $astaLiveAttualmente = null;
        
        if ($impostazioniLega && $impostazioniLega->modalita_asta === 'tap') {
            $queryAstaLive = ChiamataAsta::where('stato_chiamata', 'in_asta_tap_live');
            if ($impostazioniLega->tag_lista_attiva) {
                $queryAstaLive->where('tag_lista_calciatori', $impostazioniLega->tag_lista_attiva);
            }
            $astaLiveAttualmente = $queryAstaLive->select('id')->first();
            if ($astaLiveAttualmente) {
                Log::info("[Polling Server V3] Trovata asta LIVE CORRENTE (ID: {$astaLiveAttualmente->id}) per tag '{$impostazioniLega->tag_lista_attiva}'.");
            } else {
                Log::info("[Polling Server V3] Nessuna asta LIVE CORRENTE trovata per tag '{$impostazioniLega->tag_lista_attiva}'.");
            }
        }
        
        if (!$chiamataRichiesta) {
            Log::error("[Polling Server V3] Chiamata ID {$idChiamataAsta} NON TROVATA CON FIND().");
            return response()->json([
                'status' => 'non_trovata',
                'chiamata_id' => (int)$idChiamataAsta,
                'messaggio_esito' => 'Asta richiesta (ID: '.$idChiamataAsta.') non trovata.',
                'asta_live_corrente_id' => $astaLiveAttualmente ? $astaLiveAttualmente->id : null
            ], 404); // Importante restituire 404 se l'asta specifica non c'è
        }
        
        Log::info("[Polling Server V3] Chiamata ID {$chiamataRichiesta->id} CARICATA (via find). Stato DB: {$chiamataRichiesta->stato_chiamata}, Tag Lista: {$chiamataRichiesta->tag_lista_calciatori}");
        
        $responseData = [
            'status' => $chiamataRichiesta->stato_chiamata,
            'chiamata_id' => $chiamataRichiesta->id,
            'calciatore_id' => optional($chiamataRichiesta->calciatore)->id,
            'calciatore_nome' => $this->sanitizeForJsonResponse(optional($chiamataRichiesta->calciatore)->nome_completo),
            'calciatore_ruolo' => optional($chiamataRichiesta->calciatore)->ruolo,
            'calciatore_squadra_serie_a' => $this->sanitizeForJsonResponse(optional($chiamataRichiesta->calciatore)->squadra_serie_a),
            'prezzo_partenza_tap' => $chiamataRichiesta->prezzo_partenza_tap,
            'prezzo_attuale' => $chiamataRichiesta->prezzo_attuale_tap,
            'timestamp_fine_prevista_unix' => null,
            'secondi_rimanenti' => 0,
            'messaggio_esito' => null,
            'vincitore_nome' => null,
            'prezzo_finale' => null,
            'miglior_offerente' => null,
            'miglior_offerente_id' => $chiamataRichiesta->miglior_offerente_tap_id,
            'asta_live_corrente_id' => $astaLiveAttualmente ? $astaLiveAttualmente->id : null,
            'crediti_utente_corrente' => Auth::check() ? Auth::user()->crediti_rimanenti : null,
        ];
        
        if ($chiamataRichiesta->stato_chiamata === 'in_asta_tap_live') {
            $nomeMigliorOfferente = null;
            if (isset($chiamataRichiesta->migliorOfferenteTap)) {
                $nomeMigliorOfferente = $chiamataRichiesta->migliorOfferenteTap->name;
            } elseif ($chiamataRichiesta->miglior_offerente_tap_id === $chiamataRichiesta->user_id_chiamante && isset($chiamataRichiesta->utenteChiamante)) {
                $nomeMigliorOfferente = $chiamataRichiesta->utenteChiamante->name;
            }
            $responseData['miglior_offerente'] = $this->sanitizeForJsonResponse($nomeMigliorOfferente ?? 'Nessuno');
            
            if ($chiamataRichiesta->timestamp_fine_tap_prevista) {
                try {
                    $tsFineCarbon = Carbon::parse($chiamataRichiesta->timestamp_fine_tap_prevista);
                    $responseData['timestamp_fine_prevista_unix'] = $tsFineCarbon->timestamp;
                    $responseData['secondi_rimanenti'] = max(0, Carbon::now()->diffInSeconds($tsFineCarbon, false));
                } catch (\Exception $e) {
                    Log::error("Errore parsing timestamp_fine_tap_prevista per Chiamata ID {$chiamataRichiesta->id} (val: '{$chiamataRichiesta->timestamp_fine_tap_prevista}'): " . $e->getMessage());
                    $responseData['timestamp_fine_prevista_unix'] = null; // o un valore che indica errore
                    $responseData['secondi_rimanenti'] = -1; // o un valore che indica errore
                }
            }
        } elseif ($chiamataRichiesta->stato_chiamata === 'conclusa_tap_assegnato' && isset($chiamataRichiesta->vincitore) && isset($chiamataRichiesta->calciatore)) {
            $responseData['messaggio_esito'] = "Asta conclusa: {$this->sanitizeForJsonResponse($chiamataRichiesta->calciatore->nome_completo)} assegnato a {$this->sanitizeForJsonResponse($chiamataRichiesta->vincitore->name)} per {$chiamataRichiesta->prezzo_finale_assegnazione} crediti.";
            $responseData['vincitore_nome'] = $this->sanitizeForJsonResponse($chiamataRichiesta->vincitore->name);
            $responseData['prezzo_finale'] = $chiamataRichiesta->prezzo_finale_assegnazione;
        }
        elseif ($chiamataRichiesta->stato_chiamata === 'conclusa_tap_non_assegnato') {
            $responseData['messaggio_esito'] = "Asta per {$this->sanitizeForJsonResponse(optional($chiamataRichiesta->calciatore)->nome_completo)} conclusa senza assegnazione valida.";
        }
        elseif ($chiamataRichiesta->stato_chiamata === 'annullata_admin' && isset($chiamataRichiesta->calciatore)) {
            $responseData['messaggio_esito'] = "Asta per {$this->sanitizeForJsonResponse($chiamataRichiesta->calciatore->nome_completo)} annullata dall'amministratore.";
        }
        elseif ($chiamataRichiesta->stato_chiamata === 'in_attesa_admin' && isset($chiamataRichiesta->calciatore) && isset($chiamataRichiesta->utenteChiamante)) {
            $responseData['messaggio_esito'] = "{$this->sanitizeForJsonResponse($chiamataRichiesta->calciatore->nome_completo)} chiamato da {$this->sanitizeForJsonResponse($chiamataRichiesta->utenteChiamante->name)}. In attesa di avvio da parte dell'admin.";
        }
        
        // La riga 511 (o giù di lì) problematica era probabilmente il log qui sotto.
        // Ho corretto $chiamataAstaUrl->id con $idChiamataAsta che è il parametro ricevuto.
        Log::debug("[Polling Server V3] Dati inviati per asta ID {$chiamataRichiesta->id} (richiesta per ID URL: {$idChiamataAsta}): ", $responseData);
        return response()->json($responseData);
    }
    
    /**
     * Funzione helper per ottenere l'ID dell'asta live corrente.
     */
    private function getAstaLiveCorrenteId($tagListaRiferimento = null)
    {
        $impostazioniLega = ImpostazioneLega::first();
        $tagDaUsare = $tagListaRiferimento ?: optional($impostazioniLega)->tag_lista_attiva;
        
        if (!$impostazioniLega || $impostazioniLega->modalita_asta !== 'tap' || !$tagDaUsare) {
            return null;
        }
        
        $astaLive = ChiamataAsta::where('stato_chiamata', 'in_asta_tap_live')
        ->where('tag_lista_calciatori', $tagDaUsare)
        ->select('id')
        ->orderBy('updated_at', 'desc') // Prende la più recente se ce ne fossero multiple per errore
        ->first();
        return optional($astaLive)->id;
    }
    
    public function mostraAstaLive(Request $request)
    {
        Log::info("--- Entrato in AstaController@mostraAstaLive ---");
        $impostazioniLega = ImpostazioneLega::first(); // Usiamo first() per evitare errore se non esiste
        
        if (!$impostazioniLega) {
            Log::error("CRITICO: ImpostazioniLega non trovate in mostraAstaLive. L'admin deve configurarle.");
            // Potresti voler reindirizzare o mostrare una vista di errore specifica
            // return redirect()->route('qualche.rotta.errore')->with('error', 'Impostazioni della lega non configurate.');
            // Per ora, per debug, continuiamo ma ci aspettiamo problemi
            $impostazioniLega = new ImpostazioneLega(); // Oggetto vuoto per evitare errori successivi, ma non ideale
            $impostazioniLega->modalita_asta = 'voce'; // Default sicuro
            $impostazioniLega->tag_lista_attiva = null;
            $impostazioniLega->usa_ordine_chiamata = false;
            $impostazioniLega->prossimo_turno_chiamata_user_id = null;
        }
        
        Log::info("ImpostazioniLega caricate. Modalita asta: " . $impostazioniLega->modalita_asta . ", Tag attivo: " . ($impostazioniLega->tag_lista_attiva ?? 'NON IMPOSTATO'));
        Log::info("Usa ordine chiamata: " . ($impostazioniLega->usa_ordine_chiamata ? 'Sì' : 'No') . ", Prossimo turno User ID: " . ($impostazioniLega->prossimo_turno_chiamata_user_id ?? 'N/D'));
        
        
        $utenteLoggato = Auth::user();
        $creditiRimanenti = $utenteLoggato ? $utenteLoggato->crediti_rimanenti : 0;
        Log::info("Utente loggato ID: " . ($utenteLoggato ? $utenteLoggato->id : 'Guest') . ", Crediti: " . $creditiRimanenti);
        
        
        $chiamataDaMostrare = null;
        $calciatoreInAsta = null;
        $migliorOfferente = null;
        $prossimoChiamanteNome = null;
        
        if ($impostazioniLega->modalita_asta === 'tap') {
            Log::info("Modalità TAP attiva, cerco chiamata 'in_asta_tap_live'...");
            
            $queryAstaLive = ChiamataAsta::where('stato_chiamata', 'in_asta_tap_live');
            
            // IMPORTANTE: Filtro per tag_lista_calciatori se è impostato nelle impostazioni lega
            // Questo assicura che stiamo guardando l'asta per la stagione/lista corretta.
            if ($impostazioniLega->tag_lista_attiva) {
                Log::info("Applico filtro per tag_lista_calciatori: " . $impostazioniLega->tag_lista_attiva);
                $queryAstaLive->where('tag_lista_calciatori', $impostazioniLega->tag_lista_attiva);
            } else {
                Log::warning("Nessun tag_lista_attiva impostato in ImpostazioneLega. La ricerca di aste live potrebbe non essere accurata se ci sono più aste storiche.");
            }
            
            $chiamataDaMostrare = $queryAstaLive->with(['calciatore', 'migliorOfferenteTap', 'utenteChiamante'])->first();
            
            if ($chiamataDaMostrare) {
                Log::info("TROVATA chiamata 'in_asta_tap_live' (ID: {$chiamataDaMostrare->id}), Calciatore ID: " . (optional($chiamataDaMostrare->calciatore)->id ?? 'N/D'));
            } else {
                Log::info("NESSUNA chiamata 'in_asta_tap_live' trovata. Cerco l'ultima chiamata processata/in attesa...");
                $statiDaCercare = ['conclusa_tap_assegnato', 'conclusa_tap_non_assegnato', 'annullata_admin'];
                if ($impostazioniLega->asta_tap_approvazione_admin) {
                    $statiDaCercare[] = 'in_attesa_admin';
                }
                
                $queryUltimaChiamata = ChiamataAsta::whereIn('stato_chiamata', $statiDaCercare);
                if ($impostazioniLega->tag_lista_attiva) {
                    Log::info("Applico filtro tag_lista_calciatori anche per ultima chiamata processata: " . $impostazioniLega->tag_lista_attiva);
                    $queryUltimaChiamata->where('tag_lista_calciatori', $impostazioniLega->tag_lista_attiva);
                }
                $chiamataDaMostrare = $queryUltimaChiamata->with(['calciatore', 'migliorOfferenteTap', 'utenteChiamante', 'vincitore'])
                ->orderBy('updated_at', 'desc')
                ->first();
                if ($chiamataDaMostrare) {
                    Log::info("TROVATA ultima chiamata processata/attesa (ID: {$chiamataDaMostrare->id}), Stato: {$chiamataDaMostrare->stato_chiamata}");
                } else {
                    Log::info("NESSUNA ultima chiamata processata/attesa trovata.");
                }
            }
            
            if ($chiamataDaMostrare) {
                $calciatoreInAsta = $chiamataDaMostrare->calciatore; // Può essere null se l'asta non è live ma è solo un esito
                if ($chiamataDaMostrare->stato_chiamata === 'in_asta_tap_live') {
                    if ($chiamataDaMostrare->migliorOfferenteTap) {
                        $migliorOfferente = $chiamataDaMostrare->migliorOfferenteTap;
                    } elseif (isset($chiamataDaMostrare->utenteChiamante) && $chiamataDaMostrare->miglior_offerente_tap_id == $chiamataDaMostrare->user_id_chiamante) {
                        $migliorOfferente = $chiamataDaMostrare->utenteChiamante;
                    }
                    Log::info("Calciatore per asta live: " . (optional($calciatoreInAsta)->nome_completo ?? 'N/D') . ", Miglior offerente: " . (optional($migliorOfferente)->name ?? 'N/D'));
                }
            }
        } else {
            Log::info("Modalità asta NON è TAP ('" . $impostazioniLega->modalita_asta . "'), la pagina asta.live mostrerà info per asta a voce.");
        }
        
        if ($impostazioniLega->usa_ordine_chiamata && $impostazioniLega->prossimo_turno_chiamata_user_id) {
            $utenteProssimo = User::find($impostazioniLega->prossimo_turno_chiamata_user_id);
            if ($utenteProssimo) {
                $prossimoChiamanteNome = $utenteProssimo->name;
                Log::info("Prossimo a chiamare (se ordine attivo): " . $prossimoChiamanteNome);
            } else {
                Log::warning("ID prossimo chiamante impostato ({$impostazioniLega->prossimo_turno_chiamata_user_id}) ma utente non trovato.");
            }
        }
        
        Log::info("--- Fine AstaController@mostraAstaLive. Passo alla vista: " . ($chiamataDaMostrare ? "Chiamata ID ".$chiamataDaMostrare->id : "Nessuna chiamata specifica") . " ---");
        
        return view('asta.live', compact(
            'impostazioniLega',
            'chiamataDaMostrare',
            'calciatoreInAsta',
            'migliorOfferente',
            'creditiRimanenti',
            'prossimoChiamanteNome'
            ));
    }
}