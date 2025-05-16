<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Calciatore;
use App\Models\GiocatoreAcquistato;
use App\Models\ImpostazioneLega;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Exception;
use Illuminate\Support\Facades\Log;
use App\Models\ChiamataAsta; // Assicurati che sia importato
use Carbon\Carbon; // Per il timestamp

class AdminRosterController extends Controller
{
    public function visualizzaRoseSquadre() // <--- CONTROLLA QUESTO NOME
    {
        // $squadre = User::where('is_admin', false) // Vecchia logica se l'admin non partecipa
        $squadre = User::with(['giocatoriAcquistati.calciatore']) // Nuova logica: prendi tutti gli utenti
        ->orderBy('name')
        ->get();
        
        $impostazioniLega = ImpostazioneLega::first();
        $limitiRuoli = null;
        if ($impostazioniLega) {
            $limitiRuoli = [
                'P' => $impostazioniLega->num_portieri,
                'D' => $impostazioniLega->num_difensori,
                'C' => $impostazioniLega->num_centrocampisti,
                'A' => $impostazioniLega->num_attaccanti,
            ];
        }
        
        $squadreConDettagli = $squadre->map(function ($squadra) use ($limitiRuoli) {
            $rosa = $squadra->giocatoriAcquistati;
            $costoTotaleRosa = $rosa->sum('prezzo_acquisto');
            
            $conteggioRuoliEffettivo = collect();
            if($rosa->isNotEmpty() && $rosa->every(fn($acquisto) => $acquisto->relationLoaded('calciatore') && $acquisto->calciatore)){
                $conteggioRuoliEffettivo = $rosa->map(function($acquisto){
                    return $acquisto->calciatore->ruolo; // Assicurati che 'calciatore' sia caricato e abbia 'ruolo'
                })->countBy();
            } else if ($rosa->isNotEmpty()) { // Fallback se la relazione non è caricata come previsto
                $idsCalciatoriInRosa = $rosa->pluck('calciatore_id');
                if ($idsCalciatoriInRosa->isNotEmpty()){
                    $conteggioRuoliEffettivo = Calciatore::whereIn('id', $idsCalciatoriInRosa)->pluck('ruolo')->countBy();
                }
            }
            
            
            return [
                'id' => $squadra->id,
                'name' => $squadra->name,
                'is_admin' => $squadra->is_admin,
                'crediti_iniziali_squadra' => $squadra->crediti_iniziali_squadra,
                'crediti_rimanenti' => $squadra->crediti_rimanenti,
                'costo_totale_rosa' => $costoTotaleRosa,
                'rosa_giocatori' => $rosa,
                'conteggio_ruoli' => $conteggioRuoliEffettivo,
                'limiti_ruoli' => $limitiRuoli,
                'numero_giocatori_in_rosa' => $rosa->count()
            ];
        });
            
            return view('admin.rose.squadre', compact('squadreConDettagli'));
    }
    
    public function showAssegnaForm(Request $request)
    {
        $squadre = User::orderBy('name')->get(); // Includi anche l'admin se partecipa
        
        $impostazioniLega = ImpostazioneLega::firstOrFail(); // Assicurati che esista una riga
        $faseAstaCorrente = $impostazioniLega->fase_asta_corrente;
        $tagAttivo = $impostazioniLega->tag_lista_attiva; // Assumendo che tu abbia aggiunto questo campo
        
        $queryCalciatori = Calciatore::where('attivo', true);
        
        if ($tagAttivo) {
            $queryCalciatori->where('tag_lista_inserimento', $tagAttivo);
        }
        
        // Filtra per ruolo IN BASE ALLA FASE ASTA se P, D, C, o A
        $ruoloDaFiltrare = null;
        if (in_array($faseAstaCorrente, ['P', 'D', 'C', 'A'])) {
            $ruoloDaFiltrare = $faseAstaCorrente;
            $queryCalciatori->where('ruolo', $ruoloDaFiltrare);
        }
        // Se la fase è PRE_ASTA o CONCLUSA, o se l'admin vuole comunque poter selezionare un ruolo diverso
        // potremmo ancora usare il filtro dalla request, ma per ora diamo priorità alla fase asta.
        // Se vuoi mantenere il filtro manuale del ruolo quando l'asta non è in una fase P,D,C,A:
        // else if ($request->filled('filtro_ruolo_display')) {
        //     $ruoloDaFiltrare = $request->input('filtro_ruolo_display');
        //     $queryCalciatori->where('ruolo', $ruoloDaFiltrare);
        // }
        
        
        $tuttiICalciatoriPerFiltri = $queryCalciatori->orderBy('nome_completo')->get();
        
        $calciatoriPerJs = [];
        if ($tuttiICalciatoriPerFiltri instanceof \Illuminate\Support\Collection) {
            $calciatoriPerJs = $tuttiICalciatoriPerFiltri->map(function($calciatore) {
                return [
                    'id' => $calciatore->id,
                    'nome_completo' => $calciatore->nome_completo,
                    'ruolo' => $calciatore->ruolo,
                    'squadra_serie_a' => $calciatore->squadra_serie_a,
                    'quotazione_iniziale' => $calciatore->quotazione_iniziale,
                ];
            })->values()->all();
        }
        $idsCalciatoriGiaAcquistati = GiocatoreAcquistato::pluck('calciatore_id')->all(); // Andrebbe filtrato per lega/stagione
        
        // Per il dropdown dei ruoli, mostra tutti i ruoli se l'asta non è in una fase specifica,
        // altrimenti potresti voler mostrare solo il ruolo della fase corrente o nascondere il filtro.
        $ruoliDisponibiliQuery = Calciatore::where('attivo', true);
        if ($tagAttivo) {
            $ruoliDisponibiliQuery->where('tag_lista_inserimento', $tagAttivo);
        }
        $ruoliDisponibili = $ruoliDisponibiliQuery->select('ruolo')->distinct()->orderBy('ruolo')->pluck('ruolo');
        
        
        return view('admin.giocatori.assegna', compact(
            'squadre',
            'ruoliDisponibili', // Per il select del ruolo (potrebbe essere nascosto/modificato nella vista)
            'calciatoriPerJs',
            'idsCalciatoriGiaAcquistati',
            'ruoloDaFiltrare', // Questo è il ruolo effettivamente usato per filtrare (può essere null)
            'tagAttivo',
            'faseAstaCorrente' // PASSA QUESTA VARIABILE ALLA VISTA
            ));
    }
    
    public function handleAssegna(Request $request)
    {
        $validatedData = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'calciatore_id' => ['required', 'exists:calciatori,id'],
            'prezzo_acquisto' => ['required', 'integer', 'min:0'],
        ]);
        
        $squadra = User::findOrFail($validatedData['user_id']);
        $calciatore = Calciatore::findOrFail($validatedData['calciatore_id']);
        $prezzoAcquisto = (int)$validatedData['prezzo_acquisto'];
        
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $faseAstaCorrente = $impostazioniLega->fase_asta_corrente;
        $tagAttivo = $impostazioniLega->tag_lista_attiva; // Assumi che questo sia il tag dell'asta corrente
        
        DB::beginTransaction();
        try {
            // CONTROLLO FASE ASTA E RUOLO CALCIATORE
            if (in_array($faseAstaCorrente, ['P', 'D', 'C', 'A'])) {
                if ($calciatore->ruolo !== $faseAstaCorrente) {
                    throw new Exception("Non puoi assegnare un giocatore di ruolo {$calciatore->ruolo} durante la fase dedicata al ruolo {$faseAstaCorrente}.");
                }
                // Qui dovresti aggiungere il controllo se la squadra ha completato il reparto precedente
                // Esempio per fase Difensori:
                if ($faseAstaCorrente === 'D') {
                    $numPortieriSquadra = GiocatoreAcquistato::where('user_id', $squadra->id)
                    ->whereHas('calciatore', function($q) use ($tagAttivo) {
                        $q->where('ruolo', 'P');
                        if ($tagAttivo) $q->where('tag_lista_inserimento', $tagAttivo);
                    })->count();
                    if ($numPortieriSquadra < $impostazioniLega->num_portieri) {
                        throw new Exception("La squadra {$squadra->name} deve prima completare il reparto Portieri (attuali: $numPortieriSquadra/{$impostazioniLega->num_portieri}).");
                    }
                }
                // Aggiungere logica simile per fase C (controllo P, D) e fase A (controllo P, D, C)
            } elseif (in_array($faseAstaCorrente, ['PRE_ASTA', 'CONCLUSA'])) {
                // Se l'asta non è attiva, l'admin può fare assegnazioni "fuori fase" (per ora),
                // ma potresti voler bloccare anche questo o avere un flag "forza assegnazione".
                // Per ora, lo permettiamo ma stampiamo un log.
                Log::info("Assegnazione manuale admin fuori fase: Fase attuale {$faseAstaCorrente}. Admin ID: " . auth()->id());
            }
            
            
            // 1. Verifica Crediti Squadra (come prima)
            // 2. Verifica Limiti Rosa e Ruoli (come prima, ma usa $tagAttivo per contare la rosa)
            // ... (le validazioni su crediti e limiti rosa che avevamo prima, assicurati di usare $tagAttivo
            //      per contare i giocatori della rosa per la stagione corretta se GiocatoreAcquistato non ha un tag stagione)
            
            // Per il conteggio rosa e ruoli, è FONDAMENTALE considerare la stagione/tag attivo
            // se GiocatoreAcquistato non lo fa già.
            // Esempio per $rosaAttuale (DEVE essere per il tag/stagione corrente)
            $rosaAttualeQuery = GiocatoreAcquistato::where('user_id', $squadra->id)
            ->whereHas('calciatore', function($q) use ($tagAttivo) {
                if ($tagAttivo) {
                    $q->where('tag_lista_inserimento', $tagAttivo);
                }
            });
                $rosaAttuale = $rosaAttualeQuery->get();
                // E poi ricalcola $conteggioRuoli, $numGiocatoriTotali basandoti su questa rosa filtrata.
                
                
                // ... (TUTTE LE ALTRE VALIDAZIONI: crediti, limiti, ultimo credito, portieri, giocatore già acquistato...)
                // Assicurati che queste validazioni siano robuste e considerino il contesto dell'asta attiva.
                
                
                // Se tutte le validazioni passano:
                GiocatoreAcquistato::create([
                    'user_id' => $squadra->id,
                    'calciatore_id' => $calciatore->id,
                    'prezzo_acquisto' => $prezzoAcquisto,
                    'ruolo_al_momento_acquisto' => $calciatore->ruolo,
                    // Aggiungi qui 'tag_lista_inserimento' o 'stagione' se la tabella giocatori_acquistati ha questo campo
                    // per legare l'acquisto alla stagione/tag corretto. Questo è IMPORTANTE.
                ]);
                
                $squadra->crediti_rimanenti -= $prezzoAcquisto;
                $squadra->save();
                
                DB::commit();
                
                return redirect()->route('admin.giocatori.assegna.show')->with('success', "Calciatore {$calciatore->nome_completo} assegnato a {$squadra->name} per {$prezzoAcquisto} crediti.");
                
        } catch (Exception $e) {
            DB::rollBack();
            return redirect()->route('admin.giocatori.assegna.show')->withInput()->with('error', $e->getMessage());
        }
    }
    
    public function autocompleteGiocatori(Request $request) // Per TomSelect
    {
        $searchTerm = $request->input('q', '');
        $ruoloFiltroSpecifico = $request->input('ruolo', ''); // Ruolo dal select del form assegna
        
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $faseAstaCorrente = $impostazioniLega->fase_asta_corrente;
        $tagAttivo = $impostazioniLega->tag_lista_attiva;
        
        $query = Calciatore::where('attivo', true);
        
        if ($tagAttivo) {
            $query->where('tag_lista_inserimento', $tagAttivo);
        }
        
        // Determina il ruolo da usare per la query dell'autocomplete
        $ruoloQuery = null;
        if (in_array($faseAstaCorrente, ['P', 'D', 'C', 'A'])) {
            $ruoloQuery = $faseAstaCorrente; // Priorità alla fase asta
        } elseif ($ruoloFiltroSpecifico) {
            $ruoloQuery = $ruoloFiltroSpecifico; // Altrimenti usa il filtro manuale
        }
        
        if ($ruoloQuery) {
            $query->where('ruolo', $ruoloQuery);
        }
        
        if (!empty($searchTerm)) {
            $query->where(function ($q) use ($searchTerm) {
                $searchTermLower = strtolower($searchTerm);
                $q->whereRaw('LOWER(nome_completo) LIKE ?', ["%{$searchTermLower}%"])
                ->orWhereRaw('LOWER(squadra_serie_a) LIKE ?', ["%{$searchTermLower}%"]);
            });
        }
        
        $idsCalciatoriAcquistati = GiocatoreAcquistato::pluck('calciatore_id')->all(); // Andrebbe filtrato per lega/stagione
        $query->whereNotIn('calciatori.id', $idsCalciatoriAcquistati);
        
        
        $calciatori = $query->select('calciatori.id', 'nome_completo', 'ruolo', 'squadra_serie_a', 'quotazione_iniziale')
        ->orderBy('nome_completo')
        ->limit(15)
        ->get();
        
        $formattedCalciatori = $calciatori->map(function ($calciatore) {
            return [
                'value' => $calciatore->id,
                'text' => "{$calciatore->nome_completo} ({$calciatore->ruolo} - {$calciatore->squadra_serie_a} - Qt.I: {$calciatore->quotazione_iniziale})"
                ];
        });
            return response()->json($formattedCalciatori);
    }
    
    public function gestioneChiamateAsta()
    {
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        
        // Se la modalità non è TAP, o l'approvazione non è richiesta, questa pagina potrebbe non avere molto senso
        // o potrebbe mostrare un messaggio diverso. Per ora, mostriamo le chiamate in attesa.
        if ($impostazioniLega->modalita_asta !== 'tap' || !$impostazioniLega->asta_tap_approvazione_admin) {
            // Potresti reindirizzare o mostrare un avviso che l'approvazione non è attiva/necessaria
            // Per ora, mostriamo comunque la lista, ma non ci dovrebbero essere chiamate in stato 'in_attesa_admin'
            // se l'avvio è automatico.
        }
        
        $chiamateInAttesa = ChiamataAsta::where('stato_chiamata', 'in_attesa_admin')
        ->with(['calciatore', 'utenteChiamante']) // Eager load per dettagli
        ->orderBy('created_at', 'asc') // Mostra le più vecchie prima
        ->get();
        
        // Potremmo anche voler vedere se c'è già un'asta TAP live per disabilitare l'avvio di altre
        $astaTapLiveEsistente = ChiamataAsta::where('stato_chiamata', 'in_asta_tap_live')->exists();
        
        
        return view('admin.asta.chiamate', compact(
            'chiamateInAttesa',
            'impostazioniLega',
            'astaTapLiveEsistente'
            ));
    }
    
    public function avviaAstaTap(Request $request, ChiamataAsta $chiamataAsta) // Route Model Binding per $chiamataAsta
    {
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        
        // --- VALIDAZIONI PRELIMINARI ---
        // 1. L'asta deve essere in modalità TAP e richiedere approvazione admin
        if ($impostazioniLega->modalita_asta !== 'tap') {
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'La modalità asta TAP non è attiva.');
        }
        if (!$impostazioniLega->asta_tap_approvazione_admin) {
            // Se l'approvazione non è richiesta, questa rotta non dovrebbe essere raggiungibile
            // perché l'asta sarebbe partita automaticamente. Ma è un buon controllo.
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'L\'approvazione admin per l\'asta TAP non è richiesta, le aste dovrebbero partire automaticamente.');
        }
        
        // 2. La chiamata deve essere in stato 'in_attesa_admin'
        if ($chiamataAsta->stato_chiamata !== 'in_attesa_admin') {
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "Questa chiamata per {$chiamataAsta->calciatore->nome_completo} non è più in attesa di approvazione (Stato: {$chiamataAsta->stato_chiamata}).");
        }
        
        // 3. Non ci deve essere un'altra asta TAP già attiva
        //    (per la stessa lista/tag, se implementato)
        $astaTapLiveEsistente = ChiamataAsta::where('stato_chiamata', 'in_asta_tap_live')
        // ->where('tag_lista_calciatori', $chiamataAsta->tag_lista_calciatori) // Scommenta se filtri per tag
        ->exists();
        if ($astaTapLiveEsistente) {
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'C\'è già un\'altra asta TAP in corso. Concludila prima di avviarne una nuova.');
        }
        
        // 4. Il calciatore deve essere del ruolo della fase corrente
        if ($chiamataAsta->calciatore->ruolo !== $impostazioniLega->fase_asta_corrente) {
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "Impossibile avviare l'asta. Il giocatore {$chiamataAsta->calciatore->nome_completo} è un {$chiamataAsta->calciatore->ruolo} ma la fase attuale è {$impostazioniLega->fase_asta_corrente}.");
        }
        
        
        // --- AVVIO ASTA TAP ---
        try {
            DB::beginTransaction();
            
            $prezzoPartenza = $chiamataAsta->calciatore->quotazione_iniziale > 0 ? $chiamataAsta->calciatore->quotazione_iniziale : 1;
            
            $chiamataAsta->update([
                'stato_chiamata' => 'in_asta_tap_live',
                'prezzo_partenza_tap' => $prezzoPartenza,
                'prezzo_attuale_tap' => $prezzoPartenza,
                'miglior_offerente_tap_id' => $chiamataAsta->user_id_chiamante, // <-- IMPOSTA IL CHIAMANTE COME PRIMO OFFERENTE
                'timestamp_fine_tap_prevista' => Carbon::now()->addSeconds($impostazioniLega->durata_countdown_secondi),
            ]);
            
            // Potremmo anche voler impostare un riferimento globale al calciatore attualmente all'asta
            // in 'impostazioni_lega' se serve per una visualizzazione rapida, ma lo stato principale è in 'chiamate_asta'.
            // Esempio: $impostazioniLega->update(['calciatore_attuale_in_asta_id' => $chiamataAsta->calciatore_id]);
            
            DB::commit();
            
            Log::info("Admin (ID: ".auth()->id().") ha avviato Asta TAP per {$chiamataAsta->calciatore->nome_completo} (Chiamata ID: {$chiamataAsta->id}). Prezzo base: {$prezzoPartenza}. Fine: {$chiamataAsta->timestamp_fine_tap_prevista}");
            
            // Reindirizza a una pagina "Asta Live" (che mostrerà il countdown e i rilanci)
            // Per ora, reindirizziamo alla gestione chiamate con un messaggio di successo.
            // TODO: Creare la rotta e la vista per 'asta.live'
            // return redirect()->route('asta.live', ['chiamataAsta' => $chiamataAsta->id])->with('success', "Asta TAP per {$chiamataAsta->calciatore->nome_completo} avviata!");
            return redirect()->route('admin.asta.chiamate.gestione')->with('success', "Asta TAP per {$chiamataAsta->calciatore->nome_completo} avviata! Prezzo base: {$prezzoPartenza}.");
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Errore durante l'avvio dell'asta TAP da parte dell'admin per Chiamata ID {$chiamataAsta->id}: " . $e->getMessage());
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Errore tecnico durante l\'avvio dell\'asta TAP: ' . $e->getMessage());
        }
    }
}