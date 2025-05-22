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
    
    public function finalizzaAstaTapScaduta(Request $request, ChiamataAsta $chiamataAstaRouteParam) // Rinominato per chiarezza
    {
        $idChiamataOriginale = $chiamataAstaRouteParam->id;
        Log::info("--- RICHIESTA FINALIZZAZIONE ASTA TAP ID: {$idChiamataOriginale} (Route Model Bound) ---");
        
        try {
            return DB::transaction(function () use ($idChiamataOriginale, $request) { // Passa l'ID, non l'oggetto Eloquent intero
                $chiamata = ChiamataAsta::where('id', $idChiamataOriginale)
                ->lockForUpdate()
                ->with(['calciatore', 'utenteChiamante', 'migliorOfferenteTap'])
                ->first();
                
                if (!$chiamata) {
                    Log::error("[Finalizza Asta ID {$idChiamataOriginale}] Chiamata non trovata con lockForUpdate.");
                    return response()->json([
                        'status' => 'errore_non_trovata',
                        'chiamata_id' => $idChiamataOriginale,
                        'messaggio_esito' => $this->sanitizeForJsonResponse('Chiamata asta non trovata durante la finalizzazione critica.'),
                        'asta_live_corrente_id' => $this->getAstaLiveCorrenteId(null)
                    ], 404);
                }
                
                Log::info("[Finalizza Asta ID {$chiamata->id}] Caricata con lock. Stato DB: {$chiamata->stato_chiamata}. Miglior Offerente ID DB: {$chiamata->miglior_offerente_tap_id}, Prezzo Attuale DB: {$chiamata->prezzo_attuale_tap}");
                
                $impostazioniLega = ImpostazioneLega::firstOrFail();
                $idUtenteChiamanteOriginario = $chiamata->user_id_chiamante;
                
                if ($chiamata->stato_chiamata !== 'in_asta_tap_live') {
                    Log::warning("[Finalizza Asta ID {$chiamata->id}] Asta non più 'in_asta_tap_live' (stato attuale: {$chiamata->stato_chiamata}). Finalizzazione interrotta.");
                    return response()->json([
                        'status' => $chiamata->stato_chiamata,
                        'chiamata_id' => $chiamata->id,
                        'messaggio_esito' => $this->sanitizeForJsonResponse('Asta non più live o già finalizzata.'),
                        'asta_live_corrente_id' => $this->getAstaLiveCorrenteId($chiamata->tag_lista_calciatori)
                    ]);
                }
                
                $timestampFineCarbon = null;
                if ($chiamata->timestamp_fine_tap_prevista) {
                    try {
                        $timestampFineCarbon = Carbon::parse((string)$chiamata->timestamp_fine_tap_prevista);
                    } catch (\Exception $carbonEx) {
                        Log::error("[Finalizza Asta ID {$chiamata->id}] Errore parsing 'timestamp_fine_tap_prevista': " . $carbonEx->getMessage());
                    }
                }
                
                $scadutaEffettivamente = true;
                if ($timestampFineCarbon) {
                    if ($timestampFineCarbon->addSeconds(3)->isFuture()) { // Tolleranza 3 secondi
                        $scadutaEffettivamente = false;
                    }
                } else {
                    Log::error("[Finalizza Asta ID {$chiamata->id}] Timestamp di fine non impostato. Impossibile verificare scadenza.");
                    $scadutaEffettivamente = false;
                }
                
                if (!$scadutaEffettivamente) {
                    Log::warning("[Finalizza Asta ID {$chiamata->id}] Tentativo di finalizzare Asta non ancora considerata scaduta dal server. Fine prevista: " . optional($timestampFineCarbon)->toDateTimeString());
                    return response()->json([
                        'status' => 'non_scaduta_ancora',
                        'chiamata_id' => $chiamata->id,
                        'messaggio_esito' => $this->sanitizeForJsonResponse('Asta non ancora ufficialmente scaduta secondo il server.'),
                        'asta_live_corrente_id' => $chiamata->id
                    ]);
                }
                
                Log::info("[Finalizza Asta ID {$chiamata->id}] Asta considerata scaduta. Procedo con logica di assegnazione.");
                Log::info("[Finalizza Asta ID {$chiamata->id}] Dati Chiamata: Calciatore ID: {$chiamata->calciatore_id}, Prezzo Attuale: {$chiamata->prezzo_attuale_tap}, Miglior Offerente ID: {$chiamata->miglior_offerente_tap_id}");
                
                $calciatoreDaAssegnare = $chiamata->calciatore;
                if (!$calciatoreDaAssegnare) {
                    Log::error("[Finalizza Asta ID {$chiamata->id}] ERRORE CRITICO: Calciatore non trovato sulla chiamata.");
                    $chiamata->stato_chiamata = 'annullata_admin';
                    $chiamata->timestamp_fine_tap_prevista = null;
                    $chiamata->save();
                    return response()->json([
                        'status' => $chiamata->stato_chiamata,
                        'chiamata_id' => $chiamata->id,
                        'messaggio_esito' => $this->sanitizeForJsonResponse('Errore critico: calciatore associato all\'asta non trovato.'),
                        'asta_live_corrente_id' => $this->getAstaLiveCorrenteId($chiamata->tag_lista_calciatori)
                    ], 500);
                }
                $calciatoreNome = $calciatoreDaAssegnare->nome_completo;
                
                $vincitorePotenziale = null;
                if ($chiamata->miglior_offerente_tap_id) {
                    $vincitorePotenziale = User::find($chiamata->miglior_offerente_tap_id);
                }
                
                $assegnazioneValida = true;
                $messaggioFallimentoValidazione = "Condizioni di assegnazione non soddisfatte."; // Default generico
                
                if (!$vincitorePotenziale) {
                    Log::warning("[Finalizza Asta ID {$chiamata->id}] Nessun miglior offerente valido (ID: {$chiamata->miglior_offerente_tap_id}) per {$calciatoreNome}.");
                    $assegnazioneValida = false;
                    $messaggioFallimentoValidazione = "Miglior offerente non trovato o non valido.";
                } else {
                    Log::info("[Finalizza Asta ID {$chiamata->id}] Vincitore Potenziale: {$vincitorePotenziale->name} (ID: {$vincitorePotenziale->id}), Crediti Attuali: {$vincitorePotenziale->crediti_rimanenti}. Offerta da pagare: {$chiamata->prezzo_attuale_tap}");
                    $prezzoFinaleAcquisto = $chiamata->prezzo_attuale_tap;
                    
                    if ($vincitorePotenziale->crediti_rimanenti < $prezzoFinaleAcquisto) {
                        Log::warning("[Finalizza Validazione ID {$chiamata->id}] CREDITI INSUFFICIENTI per {$vincitorePotenziale->name}. Rimanenti: {$vincitorePotenziale->crediti_rimanenti}, Richiesti: {$prezzoFinaleAcquisto}");
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
                            if (!isset($conteggioRuoliVincitore[$r])) $conteggioRuoliVincitore[$r] = 0;
                        }
                        
                        $limiteGiocatoriSistema = $impostazioniLega->num_portieri + $impostazioniLega->num_difensori + $impostazioniLega->num_centrocampisti + $impostazioniLega->num_attaccanti;
                        $ruoloCalciatoreAsta = $calciatoreDaAssegnare->ruolo;
                        $limitePerRuoloCorrente = match ($ruoloCalciatoreAsta) {
                            'P' => $impostazioniLega->num_portieri, 'D' => $impostazioniLega->num_difensori,
                            'C' => $impostazioniLega->num_centrocampisti, 'A' => $impostazioniLega->num_attaccanti, default => 0,
                        };
                        Log::info("[Finalizza Validazione ID {$chiamata->id}] Rosa per {$vincitorePotenziale->name}: Totali: {$numGiocatoriAttualiRosaVincitore}/{$limiteGiocatoriSistema}. Ruolo {$ruoloCalciatoreAsta}: ".($conteggioRuoliVincitore[$ruoloCalciatoreAsta] ?? 0)."/{$limitePerRuoloCorrente}");
                        
                        if ($limiteGiocatoriSistema > 0 && ($numGiocatoriAttualiRosaVincitore + 1) > $limiteGiocatoriSistema) {
                            Log::warning("[Finalizza Validazione ID {$chiamata->id}] LIMITE ROSA TOTALE SUPERATO per {$vincitorePotenziale->name}.");
                            $assegnazioneValida = false;
                            $messaggioFallimentoValidazione = "Limite rosa totale superato per {$vincitorePotenziale->name}.";
                        }
                        if ($assegnazioneValida && $limitePerRuoloCorrente > 0 && (($conteggioRuoliVincitore[$ruoloCalciatoreAsta] ?? 0) + 1) > $limitePerRuoloCorrente) {
                            Log::warning("[Finalizza Validazione ID {$chiamata->id}] LIMITE RUOLO {$ruoloCalciatoreAsta} SUPERATO per {$vincitorePotenziale->name}.");
                            $assegnazioneValida = false;
                            $messaggioFallimentoValidazione = "Limite ruolo {$ruoloCalciatoreAsta} superato per {$vincitorePotenziale->name}.";
                        }
                    }
                    
                    if ($assegnazioneValida) {
                        $creditiRimanentiDopoAcquisto = $vincitorePotenziale->crediti_rimanenti - $prezzoFinaleAcquisto;
                        $slotAncoraDaRiempireDopoAcquisto = $limiteGiocatoriSistema - ($numGiocatoriAttualiRosaVincitore + 1);
                        if ($slotAncoraDaRiempireDopoAcquisto > 0 && $creditiRimanentiDopoAcquisto < $slotAncoraDaRiempireDopoAcquisto) {
                            Log::warning("[Finalizza Validazione ID {$chiamata->id}] BANCO SALTATO per {$vincitorePotenziale->name}. Crediti dopo: {$creditiRimanentiDopoAcquisto}, Slot da riempire: {$slotAncoraDaRiempireDopoAcquisto}.");
                            $assegnazioneValida = false;
                            $messaggioFallimentoValidazione = "Regola banco saltato non rispettata per {$vincitorePotenziale->name}.";
                        }
                    }
                }
                
                $esitoFinaleChiamata = 'conclusa_tap_non_assegnato';
                $vincitoreSalvatoDB = null;
                $messaggioEsitoFinalePerJson = "";
                $prezzoDaRegistrareComeFinale = $chiamata->prezzo_attuale_tap; // Prezzo a cui si è chiusa l'asta
                
                if ($assegnazioneValida && $vincitorePotenziale) {
                    GiocatoreAcquistato::create([
                        'user_id' => $vincitorePotenziale->id,
                        'calciatore_id' => $calciatoreDaAssegnare->id,
                        'prezzo_acquisto' => $prezzoFinaleAcquisto,
                        'ruolo_al_momento_acquisto' => $calciatoreDaAssegnare->ruolo,
                    ]);
                    $vincitorePotenziale->decrement('crediti_rimanenti', $prezzoFinaleAcquisto);
                    $esitoFinaleChiamata = 'conclusa_tap_assegnato';
                    $vincitoreSalvatoDB = $vincitorePotenziale->id;
                    $messaggioEsitoFinalePerJson = "Asta conclusa: {$this->sanitizeForJsonResponse($calciatoreNome)} assegnato a {$this->sanitizeForJsonResponse($vincitorePotenziale->name)} per {$prezzoFinaleAcquisto} crediti!";
                    Log::info("[Finalizza Asta ID {$chiamata->id}] ASSEGNATA: {$calciatoreNome} a {$vincitorePotenziale->name} per {$prezzoFinaleAcquisto}. Crediti rimanenti vincitore: {$vincitorePotenziale->fresh()->crediti_rimanenti}");
                } else {
                    $nomeMigliorOfferente = $vincitorePotenziale ? $vincitorePotenziale->name : ($chiamata->miglior_offerente_tap_id ? 'ID:'.$chiamata->miglior_offerente_tap_id : 'sconosciuto');
                    $messaggioEsitoFinalePerJson = "Asta per {$this->sanitizeForJsonResponse($calciatoreNome)} conclusa senza assegnazione valida. (Miglior offerente: {$this->sanitizeForJsonResponse($nomeMigliorOfferente)}). Motivo: {$this->sanitizeForJsonResponse($messaggioFallimentoValidazione)}";
                    Log::info("[Finalizza Asta ID {$chiamata->id}] NON ASSEGNATA: {$calciatoreNome}. Miglior offerente: {$nomeMigliorOfferente}. Motivo: {$messaggioFallimentoValidazione}");
                }
                
                $chiamata->stato_chiamata = $esitoFinaleChiamata;
                $chiamata->vincitore_user_id = $vincitoreSalvatoDB;
                $chiamata->prezzo_finale_assegnazione = $prezzoDaRegistrareComeFinale; // Registra il prezzo di chiusura
                $chiamata->timestamp_fine_tap_prevista = null;
                $chiamata->save();
                
                if ($impostazioniLega->usa_ordine_chiamata && $idUtenteChiamanteOriginario) {
                    Log::info("[FinalizzaAstaTAP ID:{$chiamata->id}] Avanzamento turno per utente chiamante originario ID: {$idUtenteChiamanteOriginario}");
                    $impostazioniLega->avanzaTurnoChiamata($idUtenteChiamanteOriginario);
                }
                
                Log::info("[Finalizza Asta ID {$chiamata->id}] Conclusa. Stato DB salvato: {$chiamata->stato_chiamata}");
                $chiamata->loadMissing(['vincitore:id,name']); // Ricarica la relazione per il nome aggiornato
                
                return response()->json([
                    'status' => $chiamata->stato_chiamata,
                    'chiamata_id' => $chiamata->id,
                    'messaggio_esito' => $messaggioEsitoFinalePerJson,
                    'vincitore_nome' => $this->sanitizeForJsonResponse(optional($chiamata->vincitore)->name),
                    'calciatore_nome' => $this->sanitizeForJsonResponse($calciatoreNome),
                    'prezzo_finale' => $chiamata->prezzo_finale_assegnazione,
                    'asta_live_corrente_id' => $this->getAstaLiveCorrenteId($chiamata->tag_lista_calciatori)
                ]);
            });
        } catch (Exception $e) {
            Log::error("[Finalizza Asta Catch Generale] Errore per Chiamata ID {$idChiamataOriginale}: " . $e->getMessage() . " IN FILE: " . $e->getFile() . ":" . $e->getLine() . " Trace: " . $e->getTraceAsString());
            $tagListaChiamataFallita = optional(ChiamataAsta::find($idChiamataOriginale))->tag_lista_calciatori;
            return response()->json([
                'status' => 'errore_server_finalizzazione',
                'chiamata_id' => $idChiamataOriginale,
                'messaggio_esito' => $this->sanitizeForJsonResponse('Errore server durante la finalizzazione dell\'asta: '. $e->getMessage()),
                'asta_live_corrente_id' => $this->getAstaLiveCorrenteId($tagListaChiamataFallita)
            ], 500);
        }
    }
    
    /**
     * Funzione helper per sanificare le stringhe per l'output JSON.
     */
    private function sanitizeForJsonResponse($string)
    {
        if ($string === null) return null;
        // Se la stringa è già UTF-8 e pulita, il cast a stringa potrebbe essere sufficiente.
        // mb_convert_encoding può aiutare a normalizzare.
        return mb_convert_encoding((string)$string, 'UTF-8', 'UTF-8');
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
    
    public function statoAstaTap(Request $request, $idChiamataAstaUrl)
    {
        $idChiamataRichiesta = (int) $idChiamataAstaUrl;
        Log::info("[Polling Server V4] Richiesta stato per chiamata ID URL: {$idChiamataRichiesta}.");
        
        $chiamataRichiesta = ChiamataAsta::with([
            'calciatore', 'migliorOfferenteTap', 'utenteChiamante', 'vincitore'
        ])->find($idChiamataRichiesta);
        
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $tagAttivo = $impostazioniLega->tag_lista_attiva;
        
        $astaLiveCorrenteId = $this->getAstaLiveCorrenteId($tagAttivo);
        $astaInAttesaAdminId = null;
        $datiAstaInAttesaAdmin = null;
        
        if ($impostazioniLega->modalita_asta === 'tap' && $impostazioniLega->asta_tap_approvazione_admin) {
            $chiamataInAttesa = ChiamataAsta::where('stato_chiamata', 'in_attesa_admin')
            ->where('tag_lista_calciatori', $tagAttivo)
            ->with(['calciatore:id,nome_completo', 'utenteChiamante:id,name'])
            ->orderBy('created_at', 'desc')
            ->select('id','calciatore_id','user_id_chiamante') // Seleziona solo ciò che serve
            ->first();
            
            if ($chiamataInAttesa) {
                $astaInAttesaAdminId = $chiamataInAttesa->id;
                $datiAstaInAttesaAdmin = [
                    'id' => $chiamataInAttesa->id,
                    'calciatore_nome' => $this->sanitizeForJsonResponse(optional($chiamataInAttesa->calciatore)->nome_completo),
                    'chiamante_nome' => $this->sanitizeForJsonResponse(optional($chiamataInAttesa->utenteChiamante)->name),
                ];
                Log::info("[Polling Server V4] Trovata asta IN ATTESA ADMIN (ID: {$astaInAttesaAdminId}) per tag '{$tagAttivo}'.");
            }
        }
        
        if (!$chiamataRichiesta) {
            Log::warning("[Polling Server V4] Chiamata ID {$idChiamataRichiesta} NON TROVATA.");
            return response()->json([
                'status' => 'non_trovata',
                'chiamata_id' => $idChiamataRichiesta,
                'messaggio_esito' => $this->sanitizeForJsonResponse('Asta richiesta (ID: '.$idChiamataRichiesta.') non più disponibile o terminata.'),
                'asta_live_corrente_id' => $astaLiveCorrenteId,
                'asta_in_attesa_admin_id' => $astaInAttesaAdminId,
                'dati_asta_in_attesa_admin' => $datiAstaInAttesaAdmin,
                'crediti_utente_corrente' => Auth::check() ? Auth::user()->crediti_rimanenti : null,
            ], 200);
        }
        
        Log::info("[Polling Server V4] Chiamata ID {$chiamataRichiesta->id} CARICATA. Stato DB: {$chiamataRichiesta->stato_chiamata}, Tag Lista: {$chiamataRichiesta->tag_lista_calciatori}");
        
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
            'prezzo_finale' => $chiamataRichiesta->prezzo_finale_assegnazione, // Invia sempre il prezzo finale
            'miglior_offerente' => null,
            'miglior_offerente_id' => $chiamataRichiesta->miglior_offerente_tap_id,
            'asta_live_corrente_id' => $astaLiveCorrenteId,
            'asta_in_attesa_admin_id' => $astaInAttesaAdminId,
            'dati_asta_in_attesa_admin' => $datiAstaInAttesaAdmin,
            'crediti_utente_corrente' => Auth::check() ? Auth::user()->crediti_rimanenti : null,
        ];
        
        if ($chiamataRichiesta->stato_chiamata === 'in_asta_tap_live') {
            $nomeMigliorOfferente = optional($chiamataRichiesta->migliorOfferenteTap)->name ?? optional($chiamataRichiesta->utenteChiamante)->name;
            $responseData['miglior_offerente'] = $this->sanitizeForJsonResponse($nomeMigliorOfferente);
            
            if ($chiamataRichiesta->timestamp_fine_tap_prevista) {
                try {
                    $tsFineCarbon = Carbon::parse($chiamataRichiesta->timestamp_fine_tap_prevista);
                    $responseData['timestamp_fine_prevista_unix'] = $tsFineCarbon->timestamp;
                    $responseData['secondi_rimanenti'] = max(0, Carbon::now()->diffInSeconds($tsFineCarbon, false));
                } catch (\Exception $e) {
                    Log::error("Errore parsing timestamp_fine_tap_prevista per Chiamata ID {$chiamataRichiesta->id}: " . $e->getMessage());
                    $responseData['secondi_rimanenti'] = -1;
                }
            }
        } elseif ($chiamataRichiesta->stato_chiamata === 'conclusa_tap_assegnato') {
            $responseData['messaggio_esito'] = "Asta conclusa: {$this->sanitizeForJsonResponse(optional($chiamataRichiesta->calciatore)->nome_completo)} assegnato a {$this->sanitizeForJsonResponse(optional($chiamataRichiesta->vincitore)->name)} per {$chiamataRichiesta->prezzo_finale_assegnazione} crediti.";
            $responseData['vincitore_nome'] = $this->sanitizeForJsonResponse(optional($chiamataRichiesta->vincitore)->name);
        } elseif ($chiamataRichiesta->stato_chiamata === 'conclusa_tap_non_assegnato') {
            $responseData['messaggio_esito'] = "Asta per {$this->sanitizeForJsonResponse(optional($chiamataRichiesta->calciatore)->nome_completo)} conclusa senza assegnazione valida.";
        } elseif ($chiamataRichiesta->stato_chiamata === 'annullata_admin') {
            $responseData['messaggio_esito'] = "Asta per {$this->sanitizeForJsonResponse(optional($chiamataRichiesta->calciatore)->nome_completo)} annullata dall'amministratore.";
        } elseif ($chiamataRichiesta->stato_chiamata === 'in_attesa_admin') {
            // Questo messaggio verrà sovrascritto nel JS se $data.dati_asta_in_attesa_admin è presente,
            // ma è bene averlo come fallback se l'ID richiesto è proprio quello in attesa.
            $responseData['messaggio_esito'] = "{$this->sanitizeForJsonResponse(optional($chiamataRichiesta->calciatore)->nome_completo)} chiamato da {$this->sanitizeForJsonResponse(optional($chiamataRichiesta->utenteChiamante)->name)}. In attesa di avvio da parte dell'admin.";
        }
        
        Log::debug("[Polling Server V4] Dati inviati per asta ID {$chiamataRichiesta->id} (richiesta per ID URL: {$idChiamataRichiesta}): ", $responseData);
        return response()->json($responseData);
    }
    /**
     * Funzione helper per ottenere l'ID dell'asta live corrente.
     */
    private function getAstaLiveCorrenteId($tagListaRiferimento = null)
    {
        $impostazioniLega = ImpostazioneLega::first(); // Potrebbe essere passato come argomento per efficienza
        $tagDaUsare = $tagListaRiferimento ?: optional($impostazioniLega)->tag_lista_attiva;
        
        if (!$impostazioniLega || $impostazioniLega->modalita_asta !== 'tap' || !$tagDaUsare) {
            return null;
        }
        
        $astaLive = ChiamataAsta::where('stato_chiamata', 'in_asta_tap_live')
        ->where('tag_lista_calciatori', $tagDaUsare)
        ->select('id') // Seleziona solo l'ID
        ->orderBy('updated_at', 'desc') // Prendi la più recente se ce ne fossero > 1 (improbabile)
        ->first();
        return optional($astaLive)->id;
    }
    
    public function mostraAstaLive(Request $request)
    {
        Log::info("--- Entrato in AstaController@mostraAstaLive (Versione con oggetto impostazioniLega) ---");
        $impostazioniLega = ImpostazioneLega::first(); // Oggetto Eloquent
        
        if (!$impostazioniLega) {
            Log::error("[mostraAstaLive] CRITICO: ImpostazioniLega non trovate.");
            // Crea un oggetto fittizio con i valori attesi dalla vista per evitare errori nulli
            $impostazioniLega = new ImpostazioneLega([
                'modalita_asta' => 'voce', 'tag_lista_attiva' => null,
                'fase_asta_corrente' => 'PRE_ASTA', 'usa_ordine_chiamata' => false,
                'prossimo_turno_chiamata_user_id' => null, 'asta_tap_approvazione_admin' => true,
                'num_portieri' => 3, 'num_difensori' => 8, 'num_centrocampisti' => 8, 'num_attaccanti' => 6, // Valori di default
            ]);
        }
        
        $utenteLoggato = Auth::user();
        $creditiRimanenti = $utenteLoggato ? $utenteLoggato->crediti_rimanenti : 0;
        
        // Inizializza le variabili per la vista
        $idChiamataInizialePerPolling = null;
        $isAstaAttualmenteLive = false;
        $timestampFineInizialePerCountdown = '';
        $statoChiamataDaMostrare = 'non_attiva';
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
        $vincitoreNomeIniziale = null;
        $prezzoFinaleIniziale = null;
        $prossimoChiamanteNome = __('Da definire');
        $prossimoChiamanteId = null;
        
        
        if ($impostazioniLega->modalita_asta === 'tap') {
            $tagAttivo = $impostazioniLega->tag_lista_attiva;
            Log::info("[mostraAstaLive] Modalità TAP. Cerco chiamate per tag: " . ($tagAttivo ?? 'Nessuno'));
            
            $chiamataDaMostrareOggettoElq = null;
            
            if ($tagAttivo) {
                $chiamataQuery = ChiamataAsta::where('tag_lista_calciatori', $tagAttivo);
                $astaLive = (clone $chiamataQuery)->where('stato_chiamata', 'in_asta_tap_live')->orderBy('updated_at', 'desc')->first();
                
                if ($astaLive) {
                    $chiamataDaMostrareOggettoElq = $astaLive;
                } else {
                    if ($impostazioniLega->asta_tap_approvazione_admin) {
                        $astaInAttesa = (clone $chiamataQuery)->where('stato_chiamata', 'in_attesa_admin')->orderBy('created_at', 'desc')->first();
                        if ($astaInAttesa) $chiamataDaMostrareOggettoElq = $astaInAttesa;
                    }
                    if (!$chiamataDaMostrareOggettoElq) {
                        $statiConclusi = ['conclusa_tap_assegnato', 'conclusa_tap_non_assegnato', 'annullata_admin'];
                        $ultimaChiamataProcessata = (clone $chiamataQuery)->whereIn('stato_chiamata', $statiConclusi)->orderBy('updated_at', 'desc')->first();
                        if ($ultimaChiamataProcessata) $chiamataDaMostrareOggettoElq = $ultimaChiamataProcessata;
                    }
                }
                
                if ($chiamataDaMostrareOggettoElq) {
                    Log::info("[mostraAstaLive] Chiamata da processare: ID {$chiamataDaMostrareOggettoElq->id}, Stato {$chiamataDaMostrareOggettoElq->stato_chiamata}");
                    $idChiamataInizialePerPolling = $chiamataDaMostrareOggettoElq->id;
                    $statoChiamataDaMostrare = $chiamataDaMostrareOggettoElq->stato_chiamata;
                    
                    $calciatore = Calciatore::find($chiamataDaMostrareOggettoElq->calciatore_id);
                    if ($calciatore) {
                        $calciatoreInAsta_id = $calciatore->id;
                        $calciatoreInAsta_nome = $this->sanitizeForJsonResponse($calciatore->nome_completo);
                        $calciatoreInAsta_ruolo = $this->sanitizeForJsonResponse($calciatore->ruolo);
                        $calciatoreInAsta_squadra = $this->sanitizeForJsonResponse($calciatore->squadra_serie_a);
                    }
                    
                    if ($chiamataDaMostrareOggettoElq->stato_chiamata === 'in_asta_tap_live') {
                        $isAstaAttualmenteLive = true;
                        $prezzoPartenzaTapIniziale = $chiamataDaMostrareOggettoElq->prezzo_partenza_tap;
                        $prezzoAttualeTapIniziale = $chiamataDaMostrareOggettoElq->prezzo_attuale_tap;
                        $migliorOfferenteIdIniziale = $chiamataDaMostrareOggettoElq->miglior_offerente_tap_id;
                        
                        if ($migliorOfferenteIdIniziale) {
                            $offerente = User::find($migliorOfferenteIdIniziale);
                            if ($offerente) {
                                $migliorOfferente_nome = $this->sanitizeForJsonResponse($offerente->name);
                                if ($migliorOfferenteIdIniziale == $chiamataDaMostrareOggettoElq->user_id_chiamante) {
                                    $migliorOfferente_nome .= " (chiamata)";
                                }
                            }
                        }
                        
                        // Gestione di timestamp_fine_tap_prevista (che ora è una stringa se i cast sono commentati nel modello)
                        // Se il casting nel modello ChiamataAsta FUNZIONA, $chiamataDaMostrareOggettoElq->timestamp_fine_tap_prevista sarà Carbon.
                        // Se il casting FALLISCE (perché ChiamataAsta non trova Carbon), sarà una stringa.
                        $timestampValue = $chiamataDaMostrareOggettoElq->timestamp_fine_tap_prevista;
                        Log::info("[mostraAstaLive] Valore per timestamp_fine_tap_prevista (tipo: ".gettype($timestampValue)."): " . print_r($timestampValue, true));
                        
                        if (!empty($timestampValue)) {
                            try {
                                if ($timestampValue instanceof \Carbon\Carbon) {
                                    $carbonDate = $timestampValue;
                                    Log::info("[mostraAstaLive] timestamp_fine_tap_prevista ERA un oggetto Carbon.");
                                } else {
                                    Log::warning("[mostraAstaLive] timestamp_fine_tap_prevista NON era un oggetto Carbon, ma ".gettype($timestampValue).". Tentando parse manuale.");
                                    $carbonDate = \Carbon\Carbon::parse((string)$timestampValue); // Parse esplicito
                                }
                                $timestampFineInizialePerCountdown = (string) $carbonDate->timestamp;
                                Log::info("[mostraAstaLive] Timestamp Unix per countdown: " . $timestampFineInizialePerCountdown);
                            } catch (\Throwable $e) {
                                Log::error("[mostraAstaLive] ECCEZIONE PARSING/USO timestamp: " . $e->getMessage() . " | File: " . $e->getFile() . " | Line: " . $e->getLine());
                                $timestampFineInizialePerCountdown = 'ErroreCarbonEx';
                            }
                        } else {
                            Log::warning("[mostraAstaLive] Asta live ID {$chiamataDaMostrareOggettoElq->id} ha timestamp_fine_tap_prevista vuoto/nullo.");
                            $timestampFineInizialePerCountdown = '';
                        }
                    } elseif ($chiamataDaMostrareOggettoElq->stato_chiamata === 'in_attesa_admin') {
                        $initialDatiAstaInAttesa_calciatoreNome = $calciatoreInAsta_nome; // Già sanificato
                        $chiamante = User::find($chiamataDaMostrareOggettoElq->user_id_chiamante);
                        $initialDatiAstaInAttesa_chiamanteNome = $chiamante ? $this->sanitizeForJsonResponse($chiamante->name) : 'Squadra';
                    } elseif (in_array($chiamataDaMostrareOggettoElq->stato_chiamata, ['conclusa_tap_assegnato', 'conclusa_tap_non_assegnato', 'annullata_admin'])) {
                        $prezzoFinaleIniziale = $chiamataDaMostrareOggettoElq->prezzo_finale_assegnazione;
                        if ($chiamataDaMostrareOggettoElq->stato_chiamata === 'conclusa_tap_assegnato' && $chiamataDaMostrareOggettoElq->vincitore_user_id) {
                            $vincitore = User::find($chiamataDaMostrareOggettoElq->vincitore_user_id);
                            $vincitoreNomeIniziale = $vincitore ? $this->sanitizeForJsonResponse($vincitore->name) : null;
                            $messaggioEsitoIniziale = "Asta conclusa: {$calciatoreInAsta_nome} assegnato a {$vincitoreNomeIniziale} per {$prezzoFinaleIniziale} crediti.";
                        } // ... altri messaggi di esito per stati conclusi
                    }
                }
            }
        }
        
        if ($impostazioniLega->usa_ordine_chiamata && $impostazioniLega->prossimo_turno_chiamata_user_id) {
            $prossimoChiamanteId = $impostazioniLega->prossimo_turno_chiamata_user_id;
            $utenteProssimo = User::find($prossimoChiamanteId);
            $prossimoChiamanteNome = $utenteProssimo ? $this->sanitizeForJsonResponse($utenteProssimo->name) : __('Da definire');
        } elseif($impostazioniLega->usa_ordine_chiamata) {
            $prossimoChiamanteNome = __('Da definire');
        }
        
        Log::info("--- Fine AstaController@mostraAstaLive. Passo alla vista i seguenti dati principali: " .
            "idChiamataPolling: " . ($idChiamataInizialePerPolling ?? 'N/A') .
            ", statoChiamata: " . $statoChiamataDaMostrare .
            ", isAstaLive: " . ($isAstaAttualmenteLive ? 'Sì' : 'No') .
            ", tsCountdown: '" . $timestampFineInizialePerCountdown . "'"
            );
        
        return view('asta.live', [
            'impostazioniLega' => $impostazioniLega, // PASSA L'OGGETTO INTERO
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
            'vincitoreNomeIniziale' => $vincitoreNomeIniziale,
            'prezzoFinaleIniziale' => $prezzoFinaleIniziale,
        ]);
    }
}