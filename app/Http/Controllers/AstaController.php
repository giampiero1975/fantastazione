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
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $tagAttivo = $impostazioniLega->tag_lista_attiva;
        
        $idsCalciatoriGiaAcquistati = GiocatoreAcquistato::query()
        ->when($tagAttivo, function ($query) use ($tagAttivo) {
            $query->whereHas('calciatore', function($q) use ($tagAttivo) {
                $q->where('tag_lista_inserimento', $tagAttivo);
            });
        })
        ->pluck('calciatore_id')->all();
        
        $query = Calciatore::where('attivo', true)
        ->whereNotIn('id', $idsCalciatoriGiaAcquistati);
        
        if ($tagAttivo) {
            $query->where('tag_lista_inserimento', $tagAttivo);
        }
        
        // --- QUESTA È LA LOGICA MANCANTE ---
        if ($request->filled('ruolo')) {
            $query->where('ruolo', $request->input('ruolo'));
        }
        
        if ($request->filled('q')) {
            $searchTerm = strtolower($request->input('q'));
            $query->where(function ($q) use ($searchTerm) {
                $q->whereRaw('LOWER(nome_completo) LIKE ?', ["%{$searchTerm}%"])
                ->orWhereRaw('LOWER(squadra_serie_a) LIKE ?', ["%{$searchTerm}%"]);
            });
        }
        // --- FINE LOGICA MANCANTE ---
        
        $calciatori = $query->orderByRaw("FIELD(ruolo, 'P', 'D', 'C', 'A')")
        ->orderBy('quotazione_iniziale', 'desc')
        ->paginate(50);
        
        $ruoliDisponibili = Calciatore::where('attivo', true)
        ->when($tagAttivo, function ($q) use ($tagAttivo) {
            $q->where('tag_lista_inserimento', $tagAttivo);
        })
        ->select('ruolo')
        ->distinct()
        ->orderByRaw("FIELD(ruolo, 'P', 'D', 'C', 'A')")
        ->pluck('ruolo');
        
        // Se la richiesta è AJAX, restituisci solo la tabella.
        if ($request->ajax()) {
            return view('asta.partials.lista-calciatori', compact('calciatori'))->render();
        }
        
        // Altrimenti, restituisci la pagina completa.
        return view('asta.calciatori-disponibili', compact(
            'calciatori',
            'impostazioniLega',
            'ruoliDisponibili'
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
    
    public function statoAstaTap(Request $request, $idChiamataAstaUrl)
    {
        $idChiamataRichiesta = (int) $idChiamataAstaUrl;
        Log::info("[Polling Server V11.1] Richiesta stato per ID URL: {$idChiamataRichiesta}.");
        
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $tagAttivo = $impostazioniLega->tag_lista_attiva;
        
        $astaLiveCorrenteId = null;
        $astaLiveAttiva = null; // Manteniamo l'oggetto per passarlo a formatChiamataForJsonResponse
        $astaInAttesaAdminId = null;
        $chiamataInAttesa = null; // Manteniamo l'oggetto
        $datiUltimaAstaConclusa = null;
        
        if ($tagAttivo) {
            $astaLiveAttiva = ChiamataAsta::where('stato_chiamata', 'in_asta_tap_live')
            ->where('tag_lista_calciatori', $tagAttivo)
            ->with(['calciatore', 'migliorOfferenteTap', 'utenteChiamante'])
            ->orderBy('updated_at', 'desc')
            ->first();
            if ($astaLiveAttiva) {
                $astaLiveCorrenteId = $astaLiveAttiva->id;
                Log::info("[Polling Server V11.1] Trovata asta LIVE (ID: {$astaLiveCorrenteId}) per tag '{$tagAttivo}'.");
            }
        }
        
        if (!$astaLiveCorrenteId && $impostazioniLega->modalita_asta === 'tap' && $impostazioniLega->asta_tap_approvazione_admin && $tagAttivo) {
            $chiamataInAttesa = ChiamataAsta::where('stato_chiamata', 'in_attesa_admin')
            ->where('tag_lista_calciatori', $tagAttivo)
            ->with(['calciatore:id,nome_completo', 'utenteChiamante:id,name']) // Carica solo ciò che serve per $datiAstaInAttesaAdmin
            ->orderBy('created_at', 'desc')
            ->first(); // Non serve ->select(...) se usi with per campi specifici
            if ($chiamataInAttesa) {
                $astaInAttesaAdminId = $chiamataInAttesa->id;
                // Non prepopolare $datiAstaInAttesaAdmin qui, lo faremo in formatChiamataForJsonResponse
                Log::info("[Polling Server V11.1] Trovata asta IN ATTESA ADMIN (ID: {$astaInAttesaAdminId}) per tag '{$tagAttivo}'.");
            }
        }
        
        if ($idChiamataRichiesta === 0 && !$astaLiveCorrenteId && !$astaInAttesaAdminId && $tagAttivo) {
            $statiFinali = ['conclusa_tap_assegnato', 'conclusa_tap_non_assegnato', 'annullata_admin'];
            $ultimaAstaTerminata = ChiamataAsta::whereIn('stato_chiamata', $statiFinali)
            ->where('tag_lista_calciatori', $tagAttivo)
            ->with(['calciatore', 'migliorOfferenteTap', 'utenteChiamante', 'vincitore'])
            ->orderBy('updated_at', 'desc')
            ->first();
            
            if ($ultimaAstaTerminata) {
                Log::info("[Polling Server V11.2] ID richiesta 0: Trovata ultima asta terminata ID {$ultimaAstaTerminata->id}, stato {$ultimaAstaTerminata->stato_chiamata}.");
                $datiUltimaAstaConclusa = $this->formatChiamataForJsonResponse($ultimaAstaTerminata, $impostazioniLega);
                // IMPORTANTE: Imposta lo status che il JS si aspetta per questa condizione
                $datiUltimaAstaConclusa['status'] = 'ultima_conclusa_da_mostrare'; // <<<<<< ASSICURATI CHE QUESTO SIA FATTO
                $datiUltimaAstaConclusa['status_originale'] = $ultimaAstaTerminata->stato_chiamata; // Mantieni lo stato originale se serve
                $datiUltimaAstaConclusa['asta_live_corrente_id'] = null;
                $datiUltimaAstaConclusa['asta_in_attesa_admin_id'] = null;
                Log::debug("[Polling Server V11.2] ID richiesta 0: Invio dati ultima asta conclusa:", $datiUltimaAstaConclusa);
                return response()->json($datiUltimaAstaConclusa);
            }
        }
        
        $chiamataDaProcessare = null;
        if ($astaLiveCorrenteId && ($idChiamataRichiesta === 0 || $idChiamataRichiesta === $astaLiveCorrenteId)) {
            $chiamataDaProcessare = $astaLiveAttiva;
        } elseif ($astaInAttesaAdminId && ($idChiamataRichiesta === 0 || $idChiamataRichiesta === $astaInAttesaAdminId)) {
            // Ricarica con tutte le relazioni necessarie per formatChiamataForJsonResponse
            $chiamataDaProcessare = ChiamataAsta::with(['calciatore', 'utenteChiamante', 'migliorOfferenteTap', 'vincitore'])->find($astaInAttesaAdminId);
        } elseif ($idChiamataRichiesta !== 0) {
            $chiamataDaProcessare = ChiamataAsta::with(['calciatore', 'migliorOfferenteTap', 'utenteChiamante', 'vincitore'])
            ->find($idChiamataRichiesta);
        }
        
        if ($chiamataDaProcessare) {
            Log::info("[Polling Server V11.1] Processo chiamata ID {$chiamataDaProcessare->id}. Stato DB: {$chiamataDaProcessare->stato_chiamata}, Tag: {$chiamataDaProcessare->tag_lista_calciatori}");
            $responseData = $this->formatChiamataForJsonResponse($chiamataDaProcessare, $impostazioniLega);
            // Sovrascrivi questi con i valori globali calcolati all'inizio
            $responseData['asta_live_corrente_id'] = $astaLiveCorrenteId;
            $responseData['asta_in_attesa_admin_id'] = $astaInAttesaAdminId;
            // Prepara dati_asta_in_attesa_admin solo se effettivamente c'è un'asta in attesa
            if($chiamataInAttesa && $astaInAttesaAdminId) {
                $responseData['dati_asta_in_attesa_admin'] = [
                    'id' => $chiamataInAttesa->id,
                    'calciatore_nome' => $this->sanitizeForJsonResponse(optional($chiamataInAttesa->calciatore)->nome_completo),
                    'chiamante_nome' => $this->sanitizeForJsonResponse(optional($chiamataInAttesa->utenteChiamante)->name),
                ];
            } else {
                $responseData['dati_asta_in_attesa_admin'] = null;
            }
            
            Log::debug("[Polling Server V11.1] Invio dati per chiamata ID {$chiamataDaProcessare->id}: ", $responseData);
            return response()->json($responseData);
        }
        
        Log::warning("[Polling Server V11.1] Nessuna asta trovata per ID {$idChiamataRichiesta} e nessuna live/attesa/ultima conclusa rilevante.");
        $prossimoChiamanteInfo = $this->getProssimoChiamanteInfo($impostazioniLega);
        return response()->json([
            'status' => 'nessuna_asta_attiva_o_recente',
            'chiamata_id' => $idChiamataRichiesta,
            'messaggio_esito' => $this->sanitizeForJsonResponse('Nessuna asta attiva, in attesa o recentemente conclusa da mostrare.'),
            'asta_live_corrente_id' => null,
            'asta_in_attesa_admin_id' => null,
            'dati_asta_in_attesa_admin' => null,
            'crediti_utente_corrente' => Auth::check() ? Auth::user()->crediti_rimanenti : null,
            'prossimo_chiamante_nome' => $prossimoChiamanteInfo['nome'],
            'prossimo_chiamante_id' => $prossimoChiamanteInfo['id'],
        ], 200);
    }
    
    public function mostraAstaLive(Request $request)
    {
        Log::info("--- [ASTA LIVE V11.1] Entrato in AstaController@mostraAstaLive ---");
        $impostazioniLega = ImpostazioneLega::first();
        
        if (!$impostazioniLega) {
            Log::error("[mostraAstaLive V11.1] CRITICO: ImpostazioniLega non trovate.");
            $impostazioniLega = new ImpostazioneLega([
                'modalita_asta' => 'voce', 'tag_lista_attiva' => null,
                'fase_asta_corrente' => 'PRE_ASTA', 'usa_ordine_chiamata' => false,
                'prossimo_turno_chiamata_user_id' => null, 'asta_tap_approvazione_admin' => true,
                // Default base per evitare errori nulli nella vista
                'num_portieri' => 3, 'num_difensori' => 8, 'num_centrocampisti' => 8, 'num_attaccanti' => 6,
            ]);
        }
        
        $utenteLoggato = Auth::user();
        $creditiRimanenti = $utenteLoggato ? $utenteLoggato->crediti_rimanenti : 0;
        
        $idChiamataInizialePerPolling = "0"; // Default a discovery mode
        $isAstaAttualmenteLive = false;
        $timestampFineInizialePerCountdown = '';
        $statoChiamataDaMostrare = 'non_attiva'; // Stato iniziale per la vista
        $calciatoreInAsta_id = null;
        $calciatoreInAsta_nome = null;
        $calciatoreInAsta_ruolo = null;
        $calciatoreInAsta_squadra = null;
        $prezzoPartenzaTapIniziale = null;
        $prezzoAttualeTapIniziale = null;
        $migliorOfferente_nome = null;
        $migliorOfferenteIdIniziale = null;
        $initialDatiAstaInAttesa_calciatoreNome = '';
        $initialDatiAstaInAttesa_chiamanteNome = '';
        $messaggioEsitoIniziale = null;
        // $vincitoreNomeIniziale = null; // Non sembra più usata con la nuova logica
        // $prezzoFinaleIniziale = null;  // Non sembra più usata con la nuova logica
        
        $prossimoChiamanteInfo = $this->getProssimoChiamanteInfo($impostazioniLega);
        $prossimoChiamanteNome = $prossimoChiamanteInfo['nome'];
        $prossimoChiamanteId = $prossimoChiamanteInfo['id'];
        
        
        if ($impostazioniLega->modalita_asta === 'tap' && $impostazioniLega->tag_lista_attiva) {
            $tagAttivo = $impostazioniLega->tag_lista_attiva;
            Log::info("[mostraAstaLive V11.1] Modalità TAP. Cerco chiamate per tag: {$tagAttivo}");
            
            // Cerca prima un'asta LIVE
            $astaLive = ChiamataAsta::where('tag_lista_calciatori', $tagAttivo)
            ->where('stato_chiamata', 'in_asta_tap_live')
            ->with(['calciatore', 'utenteChiamante', 'migliorOfferenteTap'])
            ->orderBy('updated_at', 'desc')
            ->first();
            
            $chiamataDaPopolarePerVista = $astaLive;
            
            if ($astaLive) {
                $idChiamataInizialePerPolling = (string) $astaLive->id;
                $isAstaAttualmenteLive = true;
                $statoChiamataDaMostrare = 'in_asta_tap_live';
                Log::info("[mostraAstaLive V11.1] Trovata asta LIVE ID: {$idChiamataInizialePerPolling}");
            } else {
                // Se non c'è live, cerca IN ATTESA ADMIN
                if ($impostazioniLega->asta_tap_approvazione_admin) {
                    $astaInAttesa = ChiamataAsta::where('tag_lista_calciatori', $tagAttivo)
                    ->where('stato_chiamata', 'in_attesa_admin')
                    ->with(['calciatore', 'utenteChiamante'])
                    ->orderBy('created_at', 'desc')
                    ->first();
                    if ($astaInAttesa) {
                        $chiamataDaPopolarePerVista = $astaInAttesa;
                        $idChiamataInizialePerPolling = (string) $astaInAttesa->id;
                        $statoChiamataDaMostrare = 'in_attesa_admin';
                        Log::info("[mostraAstaLive V11.1] Trovata asta IN ATTESA ADMIN ID: {$idChiamataInizialePerPolling}");
                    }
                }
                // Se ancora nulla, cerca l'ULTIMA CONCLUSA/ANNULLATA da mostrare "congelata"
                if (!$chiamataDaPopolarePerVista) {
                    $statiFinali = ['conclusa_tap_assegnato', 'conclusa_tap_non_assegnato', 'annullata_admin'];
                    $ultimaTerminata = ChiamataAsta::where('tag_lista_calciatori', $tagAttivo)
                    ->whereIn('stato_chiamata', $statiFinali)
                    ->with(['calciatore', 'utenteChiamante', 'migliorOfferenteTap', 'vincitore'])
                    ->orderBy('updated_at', 'desc')
                    ->first();
                    if ($ultimaTerminata) {
                        $chiamataDaPopolarePerVista = $ultimaTerminata;
                        $idChiamataInizialePerPolling = (string) $ultimaTerminata->id; // Anche se conclusa, il JS la userà per il primo fetch
                        $statoChiamataDaMostrare = $ultimaTerminata->stato_chiamata; // Stato effettivo
                        Log::info("[mostraAstaLive V11.1] Trovata ULTIMA TERMINATA ID: {$idChiamataInizialePerPolling}, Stato: {$statoChiamataDaMostrare}");
                    }
                }
            }
            
            
            if ($chiamataDaPopolarePerVista) {
                $calciatore = $chiamataDaPopolarePerVista->calciatore;
                if ($calciatore) {
                    $calciatoreInAsta_id = $calciatore->id;
                    $calciatoreInAsta_nome = $this->sanitizeForJsonResponse($calciatore->nome_completo);
                    $calciatoreInAsta_ruolo = $this->sanitizeForJsonResponse($calciatore->ruolo);
                    $calciatoreInAsta_squadra = $this->sanitizeForJsonResponse($calciatore->squadra_serie_a);
                }
                $prezzoPartenzaTapIniziale = $chiamataDaPopolarePerVista->prezzo_partenza_tap;
                $prezzoAttualeTapIniziale = $chiamataDaPopolarePerVista->prezzo_attuale_tap;
                
                if ($isAstaAttualmenteLive) {
                    $migliorOfferenteIdIniziale = $chiamataDaPopolarePerVista->miglior_offerente_tap_id;
                    if ($migliorOfferenteIdIniziale) {
                        $offerente = User::find($migliorOfferenteIdIniziale);
                        if ($offerente) {
                            $migliorOfferente_nome = $this->sanitizeForJsonResponse($offerente->name);
                            if ($migliorOfferenteIdIniziale == $chiamataDaPopolarePerVista->user_id_chiamante && $prezzoAttualeTapIniziale == $prezzoPartenzaTapIniziale) {
                                $migliorOfferente_nome .= " (chiamata)";
                            }
                        }
                    } elseif($chiamataDaPopolarePerVista->user_id_chiamante && $prezzoAttualeTapIniziale == $prezzoPartenzaTapIniziale) {
                        // Asta appena partita, il chiamante è implicitamente il miglior offerente
                        $chiamanteIniziale = $chiamataDaPopolarePerVista->utenteChiamante;
                        if($chiamanteIniziale) {
                            $migliorOfferente_nome = $this->sanitizeForJsonResponse($chiamanteIniziale->name) . " (chiamata)";
                            $migliorOfferenteIdIniziale = $chiamanteIniziale->id;
                        }
                    }
                    
                    
                    $tsValue = $chiamataDaPopolarePerVista->timestamp_fine_tap_prevista;
                    if ($tsValue) {
                        try {
                            $carbonDate = ($tsValue instanceof Carbon) ? $tsValue : Carbon::parse((string)$tsValue);
                            $timestampFineInizialePerCountdown = (string) $carbonDate->timestamp;
                        } catch (\Throwable $e) {
                            Log::error("[mostraAstaLive V11.1] Errore parsing timestamp: " . $e->getMessage());
                            $timestampFineInizialePerCountdown = 'ErroreParseTS';
                        }
                    }
                } elseif ($statoChiamataDaMostrare === 'in_attesa_admin') {
                    $initialDatiAstaInAttesa_calciatoreNome = $calciatoreInAsta_nome;
                    $chiamante = $chiamataDaPopolarePerVista->utenteChiamante;
                    $initialDatiAstaInAttesa_chiamanteNome = $chiamante ? $this->sanitizeForJsonResponse($chiamante->name) : 'Squadra';
                } elseif (in_array($statoChiamataDaMostrare, ['conclusa_tap_assegnato', 'conclusa_tap_non_assegnato', 'annullata_admin'])) {
                    // Prepopola il messaggio di esito per il primo caricamento, se l'asta è già finita
                    $datiFormattati = $this->formatChiamataForJsonResponse($chiamataDaPopolarePerVista, $impostazioniLega);
                    $messaggioEsitoIniziale = $datiFormattati['messaggio_esito'];
                    $prezzoAttualeTapIniziale = $datiFormattati['prezzo_finale']; // Mostra il prezzo finale
                    $migliorOfferente_nome = $datiFormattati['vincitore_nome'] ? 'vinto da ' . $datiFormattati['vincitore_nome'] : ($datiFormattati['miglior_offerente'] ? 'da ' . $datiFormattati['miglior_offerente'] : ' (Nessun Vincitore)');
                }
            }
        }
        
        Log::info("[mostraAstaLive V11.1] Fine. Passo alla vista: idPolling:{$idChiamataInizialePerPolling}, isLive:{$isAstaAttualmenteLive}, statoMostrare:{$statoChiamataDaMostrare}, tsCountdown:{$timestampFineInizialePerCountdown}");
        
        return view('asta.live', [
            'impostazioniLega' => $impostazioniLega,
            'creditiRimanenti' => $creditiRimanenti,
            'prossimoChiamanteNome' => $prossimoChiamanteNome,
            'prossimoChiamanteId' => $prossimoChiamanteId,
            'idChiamataInizialePerPolling' => $idChiamataInizialePerPolling,
            'isAstaAttualmenteLive' => $isAstaAttualmenteLive,
            'timestampFineInizialePerCountdown' => $timestampFineInizialePerCountdown,
            'statoChiamataDaMostrare' => $statoChiamataDaMostrare,
            'calciatoreInAsta_id' => $calciatoreInAsta_id,
            'calciatoreInAsta_nome' => $calciatoreInAsta_nome,
            'calciatoreInAsta_ruolo' => $calciatoreInAsta_ruolo,
            'calciatoreInAsta_squadra' => $calciatoreInAsta_squadra,
            'prezzoPartenzaTapIniziale' => $prezzoPartenzaTapIniziale,
            'prezzoAttualeTapIniziale' => $prezzoAttualeTapIniziale,
            'migliorOfferente_nome' => $migliorOfferente_nome,
            'migliorOfferenteIdIniziale' => $migliorOfferenteIdIniziale,
            'initialDatiAstaInAttesa_calciatoreNome' => $initialDatiAstaInAttesa_calciatoreNome,
            'initialDatiAstaInAttesa_chiamanteNome' => $initialDatiAstaInAttesa_chiamanteNome,
            'messaggioEsitoIniziale' => $messaggioEsitoIniziale,
        ]);
    }
        
    // NUOVA FUNZIONE HELPER per ottenere info prossimo chiamante
        
    // Metodo helper per formattare la risposta JSON per una chiamataAsta
    private function formatChiamataForJsonResponse(ChiamataAsta $chiamata, ImpostazioneLega $impostazioniLega)
    {
        $calciatore = $chiamata->calciatore;
        $utenteChiamante = $chiamata->utenteChiamante;
        $migliorOfferente = $chiamata->migliorOfferenteTap; // Caricato con with()
        $vincitore = $chiamata->vincitore; // Caricato con with()
        
        $responseData = [
            'status' => $chiamata->stato_chiamata,
            'status_originale' => $chiamata->stato_chiamata, // Per JS se sovrascriviamo status
            'chiamata_id' => $chiamata->id,
            'calciatore_id' => optional($calciatore)->id,
            'calciatore_nome' => $this->sanitizeForJsonResponse(optional($calciatore)->nome_completo),
            'calciatore_ruolo' => optional($calciatore)->ruolo,
            'calciatore_squadra_serie_a' => $this->sanitizeForJsonResponse(optional($calciatore)->squadra_serie_a),
            'prezzo_partenza_tap' => $chiamata->prezzo_partenza_tap,
            'prezzo_attuale_tap' => $chiamata->prezzo_attuale_tap, // Manteniamo questo per coerenza
            'prezzo_attuale' => $chiamata->prezzo_attuale_tap, // Alias per JS se lo usa
            'timestamp_fine_prevista_unix' => null,
            'secondi_rimanenti' => 0,
            'messaggio_esito' => null,
            'vincitore_nome' => null,
            'prezzo_finale' => $chiamata->prezzo_finale_assegnazione,
            'miglior_offerente' => null, // Nome sanificato
            'miglior_offerente_id' => $chiamata->miglior_offerente_tap_id,
            'user_id_chiamante' => $chiamata->user_id_chiamante,
            'chiamante_nome' => $this->sanitizeForJsonResponse(optional($utenteChiamante)->name),
            'crediti_utente_corrente' => Auth::check() ? Auth::user()->crediti_rimanenti : null,
            // Questi verranno sovrascritti/aggiunti dal chiamante se necessario
            // 'asta_live_corrente_id' => null,
            // 'asta_in_attesa_admin_id' => null,
            // 'dati_asta_in_attesa_admin' => null,
        ];
        
        // Logica per popolare i campi dinamici
        if ($chiamata->stato_chiamata === 'in_asta_tap_live') {
            $nomeMigliorOfferente = null;
            if ($migliorOfferente) {
                $nomeMigliorOfferente = $migliorOfferente->name;
                if ($chiamata->miglior_offerente_tap_id == $chiamata->user_id_chiamante &&
                    $chiamata->prezzo_attuale_tap == $chiamata->prezzo_partenza_tap) {
                        $nomeMigliorOfferente .= " (chiamata)";
                    }
            } elseif ($utenteChiamante && $chiamata->prezzo_attuale_tap == $chiamata->prezzo_partenza_tap) {
                $nomeMigliorOfferente = $utenteChiamante->name . " (chiamata)";
            }
            $responseData['miglior_offerente'] = $this->sanitizeForJsonResponse($nomeMigliorOfferente);
            
            if ($chiamata->timestamp_fine_tap_prevista) {
                $tsFineCarbon = ($chiamata->timestamp_fine_tap_prevista instanceof Carbon)
                ? $chiamata->timestamp_fine_tap_prevista
                : Carbon::parse((string)$chiamata->timestamp_fine_tap_prevista);
                $responseData['timestamp_fine_prevista_unix'] = $tsFineCarbon->timestamp;
                $responseData['secondi_rimanenti'] = max(0, Carbon::now()->diffInSeconds($tsFineCarbon, false));
            }
        } elseif ($chiamata->stato_chiamata === 'conclusa_tap_assegnato' && $vincitore) {
            $responseData['vincitore_nome'] = $this->sanitizeForJsonResponse($vincitore->name);
            $responseData['messaggio_esito'] = "Asta conclusa: {$responseData['calciatore_nome']} assegnato a {$responseData['vincitore_nome']} per {$responseData['prezzo_finale']} crediti.";
        } elseif ($chiamata->stato_chiamata === 'conclusa_tap_non_assegnato') {
            $responseData['messaggio_esito'] = "Asta per {$responseData['calciatore_nome']} conclusa senza assegnazione valida.";
        } elseif ($chiamata->stato_chiamata === 'annullata_admin') {
            $responseData['messaggio_esito'] = "Asta per {$responseData['calciatore_nome']} annullata dall'amministratore.";
        } elseif ($chiamata->stato_chiamata === 'in_attesa_admin') {
            $responseData['messaggio_esito'] = "{$responseData['calciatore_nome']} chiamato da {$responseData['chiamante_nome']}. In attesa di avvio da parte dell'admin.";
        }
        
        $prossimoChiamanteInfo = $this->getProssimoChiamanteInfo($impostazioniLega);
        $responseData['prossimo_chiamante_nome'] = $prossimoChiamanteInfo['nome'];
        $responseData['prossimo_chiamante_id'] = $prossimoChiamanteInfo['id'];
        
        return $responseData;
    }
    
    // Metodo helper per ottenere le info sul prossimo chiamante
    private function getProssimoChiamanteInfo(ImpostazioneLega $impostazioniLega)
    {
        $nome = null;
        $id = null;
        if ($impostazioniLega->usa_ordine_chiamata && $impostazioniLega->prossimo_turno_chiamata_user_id) {
            $utenteProssimo = User::find($impostazioniLega->prossimo_turno_chiamata_user_id);
            if ($utenteProssimo) {
                $nome = $this->sanitizeForJsonResponse($utenteProssimo->name);
                $id = $utenteProssimo->id;
            }
        }
        // Se l'ordine è attivo ma non c'è un ID (es. stallo o fine giro), mostra "Da definire"
        if ($impostazioniLega->usa_ordine_chiamata && !$nome) {
            // Traduci 'Da definire' se hai un sistema di localizzazione, altrimenti usa la stringa letterale
            $nome = __('Da definire'); // Assumendo che tu abbia definito questa chiave di traduzione
        }
        return ['nome' => $nome, 'id' => $id];
    }
    
    // ... (sanitizeForJsonResponse e getAstaLiveCorrenteId come prima) ...
    // Assicurati che sanitizeForJsonResponse e getAstaLiveCorrenteId siano definiti come metodi privati.
    private function sanitizeForJsonResponse($string)
    {
        if ($string === null) return null;
        return mb_convert_encoding((string)$string, 'UTF-8', 'UTF-8');
    }
    
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
        ->orderBy('updated_at', 'desc')
        ->first();
        return optional($astaLive)->id;
    }
    
    // ... (finalizzaAstaTapScaduta come l'ultima versione corretta) ...
    public function finalizzaAstaTapScaduta(Request $request, $idChiamataAstaUrlParam)
    {
        $idChiamataOriginale = (int) $idChiamataAstaUrlParam;
        Log::info("--- [FIN FINALIZZA ASTA TAP V10.1] Richiesta per ID Chiamata URL: {$idChiamataOriginale} ---");
        
        if (!$idChiamataOriginale || $idChiamataOriginale <= 0) {
            Log::error("[FIN Finalizza Asta V10.1] ID Chiamata NON VALIDO o mancante dall'URL: " . $idChiamataAstaUrlParam);
            return response()->json([
                'status' => 'errore_id_mancante',
                'chiamata_id' => $idChiamataOriginale,
                'messaggio_esito' => $this->sanitizeForJsonResponse('ID asta non valido o mancante per la finalizzazione.'),
                'asta_live_corrente_id' => $this->getAstaLiveCorrenteId(null)
            ], 400);
        }
        
        try {
            return DB::transaction(function () use ($idChiamataOriginale, $request) {
                $chiamata = ChiamataAsta::where('id', $idChiamataOriginale)
                ->lockForUpdate()
                ->with(['calciatore', 'utenteChiamante', 'migliorOfferenteTap', 'vincitore'])
                ->first();
                
                if (!$chiamata) {
                    Log::error("[FIN Finalizza Asta V10.1] Chiamata ID {$idChiamataOriginale} non trovata con lockForUpdate.");
                    return response()->json([
                        'status' => 'errore_non_trovata_db',
                        'chiamata_id' => $idChiamataOriginale,
                        'messaggio_esito' => $this->sanitizeForJsonResponse('Chiamata asta (ID: '.$idChiamataOriginale.') non più disponibile.'),
                        'asta_live_corrente_id' => $this->getAstaLiveCorrenteId(null)
                    ], 404);
                }
                
                Log::info("[FIN Finalizza Asta V10.1 ID {$chiamata->id}] Caricata con lock. Stato DB: {$chiamata->stato_chiamata}. Miglior Offerente ID DB: {$chiamata->miglior_offerente_tap_id}, Prezzo Attuale DB: {$chiamata->prezzo_attuale_tap}");
                
                $impostazioniLega = ImpostazioneLega::firstOrFail();
                $idUtenteChiamanteOriginario = $chiamata->user_id_chiamante;
                
                if ($chiamata->stato_chiamata !== 'in_asta_tap_live') {
                    Log::warning("[FIN Finalizza Asta V10.1 ID {$chiamata->id}] Asta non più 'in_asta_tap_live' (stato attuale: {$chiamata->stato_chiamata}). Finalizzazione interrotta.");
                    // Prepara la risposta usando formatChiamataForJsonResponse anche per stati già finalizzati
                    $responseData = $this->formatChiamataForJsonResponse($chiamata, $impostazioniLega);
                    $responseData['asta_live_corrente_id'] = $this->getAstaLiveCorrenteId($chiamata->tag_lista_calciatori);
                    // $responseData['asta_in_attesa_admin_id'] // Se necessario, ricalcolalo qui
                    return response()->json($responseData);
                }
                
                $timestampFineCarbon = null;
                if ($chiamata->timestamp_fine_tap_prevista) {
                    if ($chiamata->timestamp_fine_tap_prevista instanceof Carbon) {
                        $timestampFineCarbon = $chiamata->timestamp_fine_tap_prevista;
                    } else {
                        try {
                            $timestampFineCarbon = Carbon::parse((string)$chiamata->timestamp_fine_tap_prevista);
                        } catch (\Exception $carbonEx) {
                            Log::error("[FIN Finalizza Asta V10.1 ID {$chiamata->id}] Errore parsing 'timestamp_fine_tap_prevista': " . $carbonEx->getMessage());
                        }
                    }
                }
                
                if ($timestampFineCarbon && $timestampFineCarbon->isFuture()) {
                    Log::warning("[FIN Finalizza Asta V10.1 ID {$chiamata->id}] Tentativo di finalizzare Asta NON ANCORA SCADUTA. Fine server: " . $timestampFineCarbon->toDateTimeString() . ", Now: " . Carbon::now()->toDateTimeString());
                    $responseData = $this->formatChiamataForJsonResponse($chiamata, $impostazioniLega); // Prepara i dati live
                    $responseData['status'] = 'non_scaduta_ancora'; // Sovrascrivi lo stato per il client
                    $responseData['messaggio_esito'] = $this->sanitizeForJsonResponse('Asta non ancora ufficialmente scaduta.');
                    $responseData['asta_live_corrente_id'] = $chiamata->id;
                    return response()->json($responseData);
                }
                Log::info("[FIN Finalizza Asta V10.1 ID {$chiamata->id}] Asta considerata scaduta/da processare.");
                
                $calciatoreDaAssegnare = $chiamata->calciatore;
                if (!$calciatoreDaAssegnare) {
                    Log::error("[FIN Finalizza Asta V10.1 ID {$chiamata->id}] CRITICO: Calciatore non trovato sulla chiamata.");
                    $chiamata->stato_chiamata = 'annullata_admin';
                    $chiamata->timestamp_fine_tap_prevista = null;
                    $chiamata->save();
                    if ($impostazioniLega->usa_ordine_chiamata && $idUtenteChiamanteOriginario) {
                        $valOld = $impostazioniLega->prossimo_turno_chiamata_user_id;
                        $impostazioniLega->avanzaTurnoChiamata($idUtenteChiamanteOriginario);
                        if ($impostazioniLega->isDirty('prossimo_turno_chiamata_user_id') || $valOld != $impostazioniLega->prossimo_turno_chiamata_user_id) {
                            $impostazioniLega->save();
                        }
                    }
                    $responseData = $this->formatChiamataForJsonResponse($chiamata, $impostazioniLega);
                    $responseData['asta_live_corrente_id'] = $this->getAstaLiveCorrenteId($chiamata->tag_lista_calciatori);
                    return response()->json($responseData, 500);
                }
                $calciatoreNome = $calciatoreDaAssegnare->nome_completo;
                
                $vincitorePotenziale = null;
                if ($chiamata->miglior_offerente_tap_id) {
                    $vincitorePotenziale = User::find($chiamata->miglior_offerente_tap_id);
                }
                
                $assegnazioneValida = true;
                $messaggioFallimentoValidazione = "Condizioni di assegnazione non soddisfatte.";
                
                if (!$vincitorePotenziale) {
                    Log::warning("[FIN Finalizza Asta V10.1 ID {$chiamata->id}] Nessun miglior offerente valido (ID: {$chiamata->miglior_offerente_tap_id}) per {$calciatoreNome}.");
                    $assegnazioneValida = false;
                    $messaggioFallimentoValidazione = "Miglior offerente non valido o non trovato.";
                } else {
                    Log::info("[FIN Finalizza Asta V10.1 ID {$chiamata->id}] Vincitore Potenziale: {$vincitorePotenziale->name} (ID: {$vincitorePotenziale->id}), Crediti: {$vincitorePotenziale->crediti_rimanenti}. Offerta: {$chiamata->prezzo_attuale_tap}");
                    $prezzoFinaleAcquisto = $chiamata->prezzo_attuale_tap;
                    
                    if ($vincitorePotenziale->crediti_rimanenti < $prezzoFinaleAcquisto) {
                        Log::warning("[FIN Validazione V10.1 ID {$chiamata->id}] CREDITI INSUFFICIENTI per {$vincitorePotenziale->name}. Rimanenti: {$vincitorePotenziale->crediti_rimanenti}, Richiesti: {$prezzoFinaleAcquisto}");
                        $assegnazioneValida = false;
                        $messaggioFallimentoValidazione = "Crediti insufficienti per {$vincitorePotenziale->name}.";
                    }
                    
                    if ($assegnazioneValida) {
                        $rosaVincitoreQuery = GiocatoreAcquistato::where('user_id', $vincitorePotenziale->id);
                        if ($chiamata->tag_lista_calciatori) {
                            $rosaVincitoreQuery->whereHas('calciatore', function ($q) use ($chiamata) {
                                $q->where('tag_lista_inserimento', $chiamata->tag_lista_calciatori);
                            });
                        }
                        $rosaVincitore = $rosaVincitoreQuery->with('calciatore:id,ruolo')->get();
                        $numGiocatoriAttualiRosaVincitore = $rosaVincitore->count();
                        $conteggioRuoliVincitore = $rosaVincitore->whereNotNull('calciatore')->groupBy('calciatore.ruolo')->map->count();
                        foreach (['P', 'D', 'C', 'A'] as $r) {
                            if (!$conteggioRuoliVincitore->has($r)) $conteggioRuoliVincitore->put($r, 0);
                        }
                        
                        $limiteGiocatoriSistema = $impostazioniLega->num_portieri + $impostazioniLega->num_difensori + $impostazioniLega->num_centrocampisti + $impostazioniLega->num_attaccanti;
                        $ruoloCalciatoreAsta = $calciatoreDaAssegnare->ruolo;
                        $limitePerRuoloCorrente = match ($ruoloCalciatoreAsta) {
                            'P' => $impostazioniLega->num_portieri, 'D' => $impostazioniLega->num_difensori,
                            'C' => $impostazioniLega->num_centrocampisti, 'A' => $impostazioniLega->num_attaccanti, default => 0,
                        };
                        Log::info("[FIN Validazione V10.1 ID {$chiamata->id}] Rosa per {$vincitorePotenziale->name}: Tot: {$numGiocatoriAttualiRosaVincitore}/{$limiteGiocatoriSistema}. Ruolo {$ruoloCalciatoreAsta}: ".($conteggioRuoliVincitore[$ruoloCalciatoreAsta] ?? 0)."/{$limitePerRuoloCorrente}");
                        
                        if ($limiteGiocatoriSistema > 0 && ($numGiocatoriAttualiRosaVincitore + 1) > $limiteGiocatoriSistema) {
                            $assegnazioneValida = false;
                            $messaggioFallimentoValidazione = "Limite rosa totale superato per {$vincitorePotenziale->name}.";
                        }
                        if ($assegnazioneValida && $limitePerRuoloCorrente > 0 && (($conteggioRuoliVincitore[$ruoloCalciatoreAsta] ?? 0) + 1) > $limitePerRuoloCorrente) {
                            $assegnazioneValida = false;
                            $messaggioFallimentoValidazione = "Limite ruolo {$ruoloCalciatoreAsta} superato per {$vincitorePotenziale->name}.";
                        }
                        if ($assegnazioneValida) {
                            $creditiRimanentiDopoAcquisto = $vincitorePotenziale->crediti_rimanenti - $prezzoFinaleAcquisto;
                            $slotAncoraDaRiempireDopoAcquisto = $limiteGiocatoriSistema - ($numGiocatoriAttualiRosaVincitore + 1);
                            if ($slotAncoraDaRiempireDopoAcquisto > 0 && $creditiRimanentiDopoAcquisto < $slotAncoraDaRiempireDopoAcquisto) {
                                $assegnazioneValida = false;
                                $messaggioFallimentoValidazione = "Regola banco saltato non rispettata per {$vincitorePotenziale->name}.";
                            }
                        }
                    }
                }
                
                $vincitoreSalvatoDB_ID = null;
                if ($assegnazioneValida && $vincitorePotenziale) {
                    GiocatoreAcquistato::create([
                        'user_id' => $vincitorePotenziale->id,
                        'calciatore_id' => $calciatoreDaAssegnare->id,
                        'prezzo_acquisto' => $prezzoFinaleAcquisto,
                        'ruolo_al_momento_acquisto' => $calciatoreDaAssegnare->ruolo,
                    ]);
                    $vincitorePotenziale->decrement('crediti_rimanenti', $prezzoFinaleAcquisto);
                    $chiamata->stato_chiamata = 'conclusa_tap_assegnato';
                    $vincitoreSalvatoDB_ID = $vincitorePotenziale->id;
                } else {
                    $chiamata->stato_chiamata = 'conclusa_tap_non_assegnato';
                }
                $chiamata->vincitore_user_id = $vincitoreSalvatoDB_ID;
                $chiamata->prezzo_finale_assegnazione = $chiamata->prezzo_attuale_tap; // Prezzo al momento della scadenza
                $chiamata->timestamp_fine_tap_prevista = null;
                $chiamata->save();
                
                if ($impostazioniLega->usa_ordine_chiamata && $idUtenteChiamanteOriginario) {
                    Log::info("[FIN FinalizzaAstaTAP V10.1 ID:{$chiamata->id}] Avanzamento turno per utente chiamante originario ID: {$idUtenteChiamanteOriginario}. Prossimo turno in DB prima: " . $impostazioniLega->fresh()->prossimo_turno_chiamata_user_id);
                    $valoreProssimoTurnoAttuale = $impostazioniLega->prossimo_turno_chiamata_user_id;
                    $impostazioniLega->avanzaTurnoChiamata($idUtenteChiamanteOriginario);
                    if ($impostazioniLega->isDirty('prossimo_turno_chiamata_user_id') || $valoreProssimoTurnoAttuale != $impostazioniLega->prossimo_turno_chiamata_user_id) {
                        $impostazioniLega->save();
                        Log::info("[FIN FinalizzaAstaTAP V10.1 ID:{$chiamata->id}] ImpostazioneLega salvata. Nuovo prossimo_turno_chiamata_user_id: " . $impostazioniLega->prossimo_turno_chiamata_user_id);
                    } else {
                        Log::info("[FIN FinalizzaAstaTAP V10.1 ID:{$chiamata->id}] prossimo_turno_chiamata_user_id non cambiato. Nessun salvataggio ImpostazioneLega.");
                    }
                }
                // Ricarica l'oggetto $impostazioniLega dal DB per avere l'ultimo stato del prossimo chiamante
                $impostazioniLegaFresh = $impostazioniLega->fresh();
                $responseData = $this->formatChiamataForJsonResponse($chiamata->fresh(), $impostazioniLegaFresh); // Usa fresh() per avere lo stato aggiornato
                $responseData['asta_live_corrente_id'] = $this->getAstaLiveCorrenteId($chiamata->tag_lista_calciatori);
                // Se c'è un'asta in attesa, sovrascrivi con quei dati per il polling JS
                if ($responseData['asta_live_corrente_id'] == null) { // Solo se non c'è una live
                    $chiamataInAttesaDopoFinalizzazione = ChiamataAsta::where('stato_chiamata', 'in_attesa_admin')
                    ->where('tag_lista_calciatori', $chiamata->tag_lista_calciatori)
                    ->with(['calciatore:id,nome_completo', 'utenteChiamante:id,name'])
                    ->orderBy('created_at', 'desc')->first();
                    if ($chiamataInAttesaDopoFinalizzazione) {
                        $responseData['asta_in_attesa_admin_id'] = $chiamataInAttesaDopoFinalizzazione->id;
                        $responseData['dati_asta_in_attesa_admin'] = [
                            'id' => $chiamataInAttesaDopoFinalizzazione->id,
                            'calciatore_nome' => $this->sanitizeForJsonResponse(optional($chiamataInAttesaDopoFinalizzazione->calciatore)->nome_completo),
                            'chiamante_nome' => $this->sanitizeForJsonResponse(optional($chiamataInAttesaDopoFinalizzazione->utenteChiamante)->name),
                        ];
                    }
                }
                
                Log::info("[FIN Finalizza Asta V10.1 ID {$chiamata->id}] Risposta JSON:", $responseData);
                return response()->json($responseData);
                
            });
        } catch (Exception $e) {
            Log::error("[FIN Finalizza Asta V10.1 Catch Generale] Errore per Chiamata ID {$idChiamataOriginale}: " . $e->getMessage() . " IN FILE: " . $e->getFile() . ":" . $e->getLine() . " Trace: " . $e->getTraceAsString());
            $chiamataFallita = ChiamataAsta::find($idChiamataOriginale);
            $tagListaChiamataFallita = optional($chiamataFallita)->tag_lista_calciatori;
            return response()->json([
                'status' => 'errore_server_finalizzazione',
                'chiamata_id' => $idChiamataOriginale,
                'messaggio_esito' => $this->sanitizeForJsonResponse('Errore server durante la finalizzazione: '. $e->getMessage()),
                'asta_live_corrente_id' => $this->getAstaLiveCorrenteId($tagListaChiamataFallita)
            ], 500);
        }
    }
}