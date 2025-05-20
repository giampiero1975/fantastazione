<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Calciatore;
use App\Models\GiocatoreAcquistato;
use App\Models\ImpostazioneLega;
// use App\Models\ChiamataAsta; // Assicurati che sia importato
use Carbon\Carbon; // Per il timestamp
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // Aggiunto per Auth::id()
use Exception;

class AdminRosterController extends Controller
{
    private function getFasiRuoloOrdinate()
    {
        return ['P', 'D', 'C', 'A'];
    }
    
    private function getNomeFaseCompleto($codiceFase)
    {
        $mappaNomi = [
            'P' => 'Portieri', 'D' => 'Difensori', 'C' => 'Centrocampisti', 'A' => 'Attaccanti',
            'PRE_ASTA' => 'Pre-Asta', 'CONCLUSA' => 'Asta Conclusa'
        ];
        return $mappaNomi[$codiceFase] ?? $codiceFase;
    }
    
    public function handleAssegna(Request $request)
    {
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $calciatoreDaAssegnare = Calciatore::find($request->input('calciatore_id'));
        
        if (!$calciatoreDaAssegnare) {
            Log::error("[AdminRosterController Assegna ERRORE] Calciatore con ID {$request->input('calciatore_id')} non trovato.");
            return redirect()->route('admin.giocatori.assegna.show')->withInput()->with('error', 'Calciatore selezionato non valido o non trovato.');
        }
        
        $baseAstaMinima = 1;
        if ($impostazioniLega->tipo_base_asta === 'quotazione_iniziale') {
            $baseAstaMinima = $calciatoreDaAssegnare->quotazione_iniziale > 0 ? $calciatoreDaAssegnare->quotazione_iniziale : 1;
        }
        
        $validatedData = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'calciatore_id' => ['required', 'exists:calciatori,id'],
            'prezzo_acquisto' => ['required', 'integer', 'min:' . $baseAstaMinima],
        ]);
        
        $squadra = User::findOrFail($validatedData['user_id']);
        $prezzoAcquisto = (int)$validatedData['prezzo_acquisto'];
        $faseAstaCorrenteDB = $impostazioniLega->fase_asta_corrente;
        $tagAttivo = $impostazioniLega->tag_lista_attiva;
        
        DB::beginTransaction();
        try {
            Log::info("=== INIZIO ASSEGNAZIONE MANUALE ADMIN (AdminRosterController) ===");
            Log::info("Admin ID: " . Auth::id());
            Log::info("Squadra Target: {$squadra->name} (ID: {$squadra->id})");
            Log::info("Calciatore da Assegnare: {$calciatoreDaAssegnare->nome_completo} (ID: {$calciatoreDaAssegnare->id}), Ruolo: {$calciatoreDaAssegnare->ruolo}");
            Log::info("Prezzo Acquisto Proposto: {$prezzoAcquisto}");
            Log::info("Tag Lista Attiva: {$tagAttivo}");
            Log::info("Fase Asta Corrente nel DB: {$faseAstaCorrenteDB}");
            
            // 1. Controllo Fase Asta e Ruolo Calciatore
            if (in_array($faseAstaCorrenteDB, $this->getFasiRuoloOrdinate())) {
                if ($calciatoreDaAssegnare->ruolo !== $faseAstaCorrenteDB) {
                    throw new Exception("Non puoi assegnare un giocatore di ruolo {$calciatoreDaAssegnare->ruolo} durante la fase dedicata al ruolo {$this->getNomeFaseCompleto($faseAstaCorrenteDB)} ({$faseAstaCorrenteDB}).");
                }
            }
            
            // 2. Controllo se il calciatore è già stato acquistato
            $giaAcquistatoQuery = GiocatoreAcquistato::where('calciatore_id', $calciatoreDaAssegnare->id);
            if ($tagAttivo) {
                $giaAcquistatoQuery->whereHas('calciatore', function($q) use ($tagAttivo){
                    $q->where('tag_lista_inserimento', $tagAttivo);
                });
            }
            $acquistoEsistente = $giaAcquistatoQuery->first();
            if ($acquistoEsistente) {
                throw new Exception("{$calciatoreDaAssegnare->nome_completo} (ID: {$calciatoreDaAssegnare->id}) è già stato acquistato da '{$acquistoEsistente->user->name}' per la lista/asta '{$tagAttivo}'.");
            }
            
            // 3. Verifica Crediti Squadra
            $creditiRimanentiPrimaDellAcquisto = $squadra->crediti_rimanenti;
            if ($creditiRimanentiPrimaDellAcquisto < $prezzoAcquisto) {
                throw new Exception("La squadra {$squadra->name} non ha crediti sufficienti (Disponibili: {$creditiRimanentiPrimaDellAcquisto}, Richiesti: {$prezzoAcquisto}).");
            }
            
            // 4. Calcolo Limiti e Conteggi Rosa
            $rosaAttualeQuery = GiocatoreAcquistato::where('user_id', $squadra->id);
            if ($tagAttivo) {
                $rosaAttualeQuery->whereHas('calciatore', fn($q) => $q->where('tag_lista_inserimento', $tagAttivo));
            }
            $rosaAttuale = $rosaAttualeQuery->with('calciatore:id,ruolo')->get();
            $numGiocatoriAttualiInRosa = $rosaAttuale->count();
            
            $conteggioRuoliAttuali = $rosaAttuale->mapToGroups(function ($item) {
                if ($item->calciatore) { return [$item->calciatore->ruolo => 1]; }
                return [];
            })->map(function ($items) { return $items->count(); });
            foreach (['P', 'D', 'C', 'A'] as $r) {
                if (!$conteggioRuoliAttuali->has($r)) { $conteggioRuoliAttuali->put($r, 0); }
            }
            
            $limitePortieri = $impostazioniLega->num_portieri;
            $limiteDifensori = $impostazioniLega->num_difensori;
            $limiteCentrocampisti = $impostazioniLega->num_centrocampisti;
            $limiteAttaccanti = $impostazioniLega->num_attaccanti;
            $limiteGiocatoriTotaliSistema = $limitePortieri + $limiteDifensori + $limiteCentrocampisti + $limiteAttaccanti;
            
            // 5. Verifica Limite Giocatori Totali
            if (($numGiocatoriAttualiInRosa + 1) > $limiteGiocatoriTotaliSistema && $limiteGiocatoriTotaliSistema > 0) {
                throw new Exception("La squadra {$squadra->name} supererebbe il limite massimo di {$limiteGiocatoriTotaliSistema} giocatori.");
            }
            
            // 6. Verifica Limite per Ruolo Specifico
            $ruoloDelCalciatoreDaAssegnare = $calciatoreDaAssegnare->ruolo;
            $limitePerRuoloSpecifico = match ($ruoloDelCalciatoreDaAssegnare) {
                'P' => $limitePortieri, 'D' => $limiteDifensori,
                'C' => $limiteCentrocampisti, 'A' => $limiteAttaccanti, default => 0,
            };
            if ($limitePerRuoloSpecifico > 0 && (($conteggioRuoliAttuali->get($ruoloDelCalciatoreDaAssegnare, 0) + 1) > $limitePerRuoloSpecifico)) {
                throw new Exception("La squadra {$squadra->name} supererebbe il limite di {$limitePerRuoloSpecifico} giocatori per il ruolo {$ruoloDelCalciatoreDaAssegnare}.");
            }
            
            // 7. REGOLA DEL BANCO SALTATO
            $creditiCheResterebbero = $creditiRimanentiPrimaDellAcquisto - $prezzoAcquisto;
            $numGiocatoriInRosaDopoQuestoAcquisto = $numGiocatoriAttualiInRosa + 1;
            $slotAncoraDaRiempireEffettivamente = $limiteGiocatoriTotaliSistema - $numGiocatoriInRosaDopoQuestoAcquisto;
            
            Log::info("---------------- BANCO SALTATO CHECK (MANUALE - AdminRosterController) ----------------");
            Log::info("Squadra: {$squadra->name} (ID:{$squadra->id})");
            Log::info("Calciatore da Assegnare: {$calciatoreDaAssegnare->nome_completo} (ID: {$calciatoreDaAssegnare->id})");
            Log::info("Prezzo Acquisto Proposto: {$prezzoAcquisto}");
            Log::info("Crediti Squadra (Prima dell'acquisto corrente): {$creditiRimanentiPrimaDellAcquisto}");
            Log::info("Crediti Squadra (Che Resterebbero Dopo questo acquisto): {$creditiCheResterebbero}");
            Log::info("Num Giocatori in Rosa (Attuali, prima di questo acquisto, per tag '{$tagAttivo}'): {$numGiocatoriAttualiInRosa}");
            Log::info("Limite Giocatori Totali Sistema: {$limiteGiocatoriTotaliSistema}");
            Log::info("Num Giocatori in Rosa (Conteggiati DOPO questo acquisto): {$numGiocatoriInRosaDopoQuestoAcquisto}");
            Log::info("Slot Ancora da Riempire Effettivamente (DOPO questo acquisto): {$slotAncoraDaRiempireEffettivamente}");
            Log::info("Condizione Banco Saltato: ($slotAncoraDaRiempireEffettivamente > 0 && $creditiCheResterebbero < $slotAncoraDaRiempireEffettivamente) --> (" . ($slotAncoraDaRiempireEffettivamente > 0 ? 'VERO' : 'FALSO') . " && " . ($creditiCheResterebbero < $slotAncoraDaRiempireEffettivamente ? 'VERO' : 'FALSO') . ")");
            Log::info("------------------------------------------------------------------------------------");
            
            if ($slotAncoraDaRiempireEffettivamente > 0 && $creditiCheResterebbero < $slotAncoraDaRiempireEffettivamente) {
                Log::warning("BANCO SALTATO - ECCEZIONE LANCIATA (AdminRosterController)! Per {$squadra->name}. Crediti post: {$creditiCheResterebbero}, Slot post: {$slotAncoraDaRiempireEffettivamente}");
                throw new Exception("BANCO SALTATO! Per {$squadra->name}: dopo aver speso {$prezzoAcquisto} per {$calciatoreDaAssegnare->nome_completo}, resterebbero {$creditiCheResterebbero} crediti con {$slotAncoraDaRiempireEffettivamente} slot ancora da riempire (min. 1 crd l'uno).");
            }
            
            Log::info("[AdminRosterController Assegna] Tutti i controlli preliminari SUPERATI. Procedo con l'assegnazione.");
            GiocatoreAcquistato::create([
                'user_id' => $squadra->id,
                'calciatore_id' => $calciatoreDaAssegnare->id,
                'prezzo_acquisto' => $prezzoAcquisto,
                'ruolo_al_momento_acquisto' => $calciatoreDaAssegnare->ruolo,
            ]);
            
            $squadra->crediti_rimanenti = $creditiCheResterebbero;
            $squadra->save();
            
            DB::commit();
            
            Log::info("ASSEGNAZIONE MANUALE COMPLETATA (AdminRosterController): Admin (ID: ".Auth::id().") ha assegnato {$calciatoreDaAssegnare->nome_completo} a {$squadra->name} per {$prezzoAcquisto} crediti. Tag lista: {$tagAttivo}. Crediti rimanenti squadra: {$squadra->crediti_rimanenti}");
            return redirect()->route('admin.giocatori.assegna.show')->with('success', "Calciatore {$calciatoreDaAssegnare->nome_completo} assegnato a {$squadra->name} per {$prezzoAcquisto} crediti.");
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("ERRORE ASSEGNAZIONE MANUALE ADMIN (AdminRosterController) per calciatore ID {$request->input('calciatore_id')} a squadra ID {$request->input('user_id')}. Errore: " . $e->getMessage() . " Riga: " . $e->getLine() . " File: " . $e->getFile());
            return redirect()->route('admin.giocatori.assegna.show')->withInput()->with('error', "Errore: " . $e->getMessage());
        }
    }
    
    public function visualizzaRoseSquadre()
    {
        $squadre = User::with(['giocatoriAcquistati.calciatore']) // Eager load per performance
        ->orderBy('name')
        ->get();
        
        $impostazioniLega = ImpostazioneLega::first(); // Può essere null se non configurata
        $limitiRuoli = null;
        $tagListaAttiva = $impostazioniLega ? $impostazioniLega->tag_lista_attiva : null;
        
        if ($impostazioniLega) {
            $limitiRuoli = [
                'P' => $impostazioniLega->num_portieri,
                'D' => $impostazioniLega->num_difensori,
                'C' => $impostazioniLega->num_centrocampisti,
                'A' => $impostazioniLega->num_attaccanti,
            ];
        }
        
        $squadreConDettagli = $squadre->map(function ($squadra) use ($limitiRuoli, $tagListaAttiva) {
            // Filtra gli acquisti per il tag_lista_attiva corrente
            $rosaFiltrata = $squadra->giocatoriAcquistati->filter(function ($acquisto) use ($tagListaAttiva) {
                // Assicurati che $acquisto->calciatore esista prima di accedervi
                return $tagListaAttiva && $acquisto->calciatore ? ($acquisto->calciatore->tag_lista_inserimento === $tagListaAttiva) : true;
            });
                
                $costoTotaleRosa = $rosaFiltrata->sum('prezzo_acquisto');
                
                // CALCOLO CONTEGGIO E COSTI PER RUOLO PER QUESTA SQUADRA
                $conteggioRuoliPerSquadra = collect(['P' => 0, 'D' => 0, 'C' => 0, 'A' => 0]);
                $costiPerRuoloPerSquadra = collect(['P' => 0, 'D' => 0, 'C' => 0, 'A' => 0]); // Inizializza correttamente
                
                if ($rosaFiltrata->isNotEmpty()) {
                    foreach ($rosaFiltrata as $acquisto) {
                        if ($acquisto->calciatore) { // Controlla sempre che la relazione sia caricata
                            $ruolo = $acquisto->calciatore->ruolo;
                            if ($conteggioRuoliPerSquadra->has($ruolo)) { // Usa has() per le collection
                                $conteggioRuoliPerSquadra->put($ruolo, $conteggioRuoliPerSquadra->get($ruolo) + 1);
                                $costiPerRuoloPerSquadra->put($ruolo, $costiPerRuoloPerSquadra->get($ruolo) + $acquisto->prezzo_acquisto);
                            }
                        }
                    }
                }
                // FINE CALCOLO
                
                return [
                    'id' => $squadra->id,
                    'name' => $squadra->name,
                    'is_admin' => $squadra->is_admin,
                    'crediti_iniziali_squadra' => $squadra->crediti_iniziali_squadra,
                    'crediti_rimanenti' => $squadra->crediti_rimanenti,
                    'costo_totale_rosa' => $costoTotaleRosa,
                    'rosa_giocatori' => $rosaFiltrata,
                    'conteggio_ruoli' => $conteggioRuoliPerSquadra,
                    'costi_per_ruolo' => $costiPerRuoloPerSquadra, // CHIAVE CORRETTA E VARIABILE CORRETTA
                    'limiti_ruoli' => $limitiRuoli,
                    'numero_giocatori_in_rosa' => $rosaFiltrata->count()
                ];
        });
            // dd($squadreConDettagli->first()); // Decommenta per debuggare i dati della prima squadra
            return view('admin.rose.squadre', compact('squadreConDettagli'));
    }

    public function showAssegnaForm(Request $request)
    {
        $squadre = User::orderBy('name')->get();
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $faseAstaCorrente = $impostazioniLega->fase_asta_corrente;
        $tagAttivo = $impostazioniLega->tag_lista_attiva;

        $ruoloDaFiltrare = null;
        if (in_array($faseAstaCorrente, ['P', 'D', 'C', 'A'])) {
            $ruoloDaFiltrare = $faseAstaCorrente;
        }

        // Per il dropdown dei ruoli (se l'admin vuole cambiare filtro manualmente quando non in fase P,D,C,A)
        $ruoliDisponibiliQuery = Calciatore::where('attivo', true);
        if ($tagAttivo) {
            $ruoliDisponibiliQuery->where('tag_lista_inserimento', $tagAttivo);
        }
        $ruoliDisponibili = $ruoliDisponibiliQuery->select('ruolo')->distinct()->orderByRaw("FIELD(ruolo, 'P', 'D', 'C', 'A')")->pluck('ruolo');

        // L'autocomplete ora usa una rotta dedicata, quindi non passiamo tutti i calciatori
        $idsCalciatoriGiaAcquistati = GiocatoreAcquistato::query();
        if($tagAttivo){
            // Filtra gli acquistati in base al tag dei calciatori, se GiocatoreAcquistato non ha un suo tag
            $idsCalciatoriGiaAcquistati = $idsCalciatoriGiaAcquistati->whereHas('calciatore', function($q) use ($tagAttivo){
                $q->where('tag_lista_inserimento', $tagAttivo);
            });
        }
        $idsCalciatoriGiaAcquistati = $idsCalciatoriGiaAcquistati->pluck('calciatore_id')->all();


        return view('admin.giocatori.assegna', compact(
            'squadre',
            'ruoliDisponibili',
            'idsCalciatoriGiaAcquistati', // Utile per il JS se vuoi disabilitare lato client
            'ruoloDaFiltrare',
            'tagAttivo',
            'faseAstaCorrente'
        ));
    }

    public function autocompleteGiocatori(Request $request)
    {
        $searchTerm = $request->input('q', '');
        $ruoloFiltroSpecifico = $request->input('ruolo', '');
        
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $faseAstaCorrente = $impostazioniLega->fase_asta_corrente;
        $tagAttivo = $impostazioniLega->tag_lista_attiva;
        
        $query = Calciatore::where('attivo', true);
        
        if ($tagAttivo) {
            $query->where('tag_lista_inserimento', $tagAttivo);
        }
        
        $ruoloQuery = null;
        if (in_array($faseAstaCorrente, ['P', 'D', 'C', 'A'])) {
            $ruoloQuery = $faseAstaCorrente;
        } elseif ($ruoloFiltroSpecifico) {
            $ruoloQuery = $ruoloFiltroSpecifico;
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
        
        $idsCalciatoriAcquistatiQuery = GiocatoreAcquistato::query();
        if($tagAttivo){
             $idsCalciatoriAcquistatiQuery->whereHas('calciatore', function($q) use ($tagAttivo){
                 $q->where('tag_lista_inserimento', $tagAttivo);
             });
        }
        $idsCalciatoriAcquistati = $idsCalciatoriAcquistatiQuery->pluck('calciatore_id')->all();

        if (!empty($idsCalciatoriAcquistati)) {
            $query->whereNotIn('calciatori.id', $idsCalciatoriAcquistati);
        }
        
        
        $calciatori = $query->select('calciatori.id', 'nome_completo', 'ruolo', 'squadra_serie_a', 'quotazione_iniziale')
                            ->orderBy('nome_completo')
                            ->limit(15)
                            ->get();
        
        $formattedCalciatori = $calciatori->map(function ($calciatore) use ($impostazioniLega) {
            $baseAsta = 1;
            if ($impostazioniLega->tipo_base_asta === 'quotazione_iniziale') {
                 $baseAsta = $calciatore->quotazione_iniziale > 0 ? $calciatore->quotazione_iniziale : 1;
            }
            return [
                'value' => $calciatore->id,
                'text' => "{$calciatore->nome_completo} ({$calciatore->ruolo} - {$calciatore->squadra_serie_a} - Qt.I: {$baseAsta})" // Mostra base asta corretta
            ];
        });
        return response()->json($formattedCalciatori);
    }

    public function gestioneChiamateAsta()
    {
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        
        $chiamateInAttesa = ChiamataAsta::where('stato_chiamata', 'in_attesa_admin')
                                        ->with(['calciatore', 'utenteChiamante'])
                                        ->orderBy('created_at', 'asc')
                                        ->get();
        
        $astaTapLiveEsistente = ChiamataAsta::where('stato_chiamata', 'in_asta_tap_live')->exists();
        
        return view('admin.asta.chiamate', compact(
            'chiamateInAttesa',
            'impostazioniLega',
            'astaTapLiveEsistente'
        ));
    }
    
    public function avviaAstaTap(Request $request, ChiamataAsta $chiamataAsta)
    {
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $calciatorePerAsta = $chiamataAsta->calciatore()->first(); // Carica il calciatore associato alla chiamata
        
        // Validazione preliminare: il calciatore deve esistere
        if (!$calciatorePerAsta) {
            Log::error("[Admin Avvio Asta TAP] Calciatore non trovato per Chiamata ID: {$chiamataAsta->id}");
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Errore: Calciatore non trovato per questa chiamata.');
        }
        
        Log::info("--- Admin Avvio Asta TAP (AdminRosterController) per Chiamata ID: {$chiamataAsta->id} ---");
        Log::info("[Admin Avvio] Calciatore: " . $calciatorePerAsta->nome_completo . " (ID: {$calciatorePerAsta->id}), Quotazione Iniziale: " . $calciatorePerAsta->quotazione_iniziale);
        Log::info("[Admin Avvio] Impostazione Lega 'tipo_base_asta': " . $impostazioniLega->tipo_base_asta);
        Log::info("[Admin Avvio] Impostazione Lega 'asta_tap_approvazione_admin': " . ($impostazioniLega->asta_tap_approvazione_admin ? 'true' : 'false'));
        
        
        // 1. VALIDAZIONI GENERALI SULL'ASTA E SULLE IMPOSTAZIONI
        if ($impostazioniLega->modalita_asta !== 'tap') {
            Log::warning("[Admin Avvio Asta TAP] Tentativo di avvio asta TAP (ID: {$chiamataAsta->id}) ma la modalità non è TAP. Modalità attuale: {$impostazioniLega->modalita_asta}");
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'La modalità asta TAP non è attiva.');
        }
        
        // Anche se l'admin avvia, ha senso che l'approvazione sia comunque richiesta se il flag è true.
        // Se il flag è false, questa rotta non dovrebbe essere usata per avvii automatici, ma solo per interventi admin.
        if (!$impostazioniLega->asta_tap_approvazione_admin) {
            Log::info("[Admin Avvio Asta TAP] Avvio forzato da admin per chiamata ID {$chiamataAsta->id} anche se approvazione non sarebbe richiesta.");
        }
        
        if ($chiamataAsta->stato_chiamata !== 'in_attesa_admin') {
            Log::warning("[Admin Avvio Asta TAP] Tentativo di avvio per chiamata ID {$chiamataAsta->id} non più in stato 'in_attesa_admin'. Stato attuale: {$chiamataAsta->stato_chiamata}");
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "Questa chiamata per {$calciatorePerAsta->nome_completo} non è più in attesa di approvazione (Stato: {$chiamataAsta->stato_chiamata}).");
        }
        
        if (ChiamataAsta::where('stato_chiamata', 'in_asta_tap_live')->where('id', '!=', $chiamataAsta->id)->exists()) {
            Log->warning("[Admin Avvio Asta TAP] Tentativo di avvio per chiamata ID {$chiamataAsta->id} ma esiste già un'altra asta TAP live.");
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Esiste già un\'altra asta TAP in corso. Annullala o attendi la sua conclusione.');
        }
        
        if ($calciatorePerAsta->ruolo !== $impostazioniLega->fase_asta_corrente) {
            Log::warning("[Admin Avvio Asta TAP] Tentativo di avvio per chiamata ID {$chiamataAsta->id} ma il ruolo ({$calciatorePerAsta->ruolo}) non corrisponde alla fase ({$impostazioniLega->fase_asta_corrente}).");
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "Il ruolo del giocatore ({$calciatorePerAsta->ruolo}) non corrisponde alla fase d'asta attuale ({$impostazioniLega->fase_asta_corrente}).");
        }
        
        
        // 2. CALCOLO BASE D'ASTA CORRETTA
        $quotazioneEffettivaCalciatore = ($calciatorePerAsta->quotazione_iniziale > 0 ? $calciatorePerAsta->quotazione_iniziale : 1);
        $baseAstaDaUsare = ($impostazioniLega->tipo_base_asta === 'credito_singolo') ? 1 : $quotazioneEffettivaCalciatore;
        
        Log::info("[Admin Avvio] Base Asta CALCOLATA da usare per {$calciatorePerAsta->nome_completo}: " . $baseAstaDaUsare);
        Log::info("[Admin Avvio] ChiamataAsta esistente 'prezzo_partenza_tap' (prima dell'update pianificato): " . $chiamataAsta->prezzo_partenza_tap);
        
        
        // 3. VALIDAZIONI SULL'UTENTE CHIAMANTE ORIGINALE
        $utenteChiamante = $chiamataAsta->utenteChiamante()->first();
        
        if (!$utenteChiamante) {
            Log::error("[Admin Avvio Asta TAP] Utente chiamante non trovato per Chiamata ID {$chiamataAsta->id}. Annullamento chiamata.");
            $chiamataAsta->update(['stato_chiamata' => 'annullata_admin', 'timestamp_fine_tap_prevista' => null]);
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "Utente chiamante originale non trovato. La chiamata per {$calciatorePerAsta->nome_completo} è stata annullata.");
        }
        
        if ($utenteChiamante->crediti_rimanenti < $baseAstaDaUsare) {
            Log::warning("[Admin Avvio Asta TAP] Utente chiamante {$utenteChiamante->name} (ID: {$utenteChiamante->id}) non ha crediti sufficienti (Rimanenti: {$utenteChiamante->crediti_rimanenti}) per base asta {$baseAstaDaUsare} per {$calciatorePerAsta->nome_completo}. Chiamata ID {$chiamataAsta->id} annullata.");
            $chiamataAsta->update(['stato_chiamata' => 'annullata_admin', 'timestamp_fine_tap_prevista' => null]);
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "L'utente {$utenteChiamante->name} non ha crediti sufficienti ({$utenteChiamante->crediti_rimanenti}) per la base d'asta di {$baseAstaDaUsare} per {$calciatorePerAsta->nome_completo}. La chiamata è stata annullata.");
        }
        
        // Validazione limiti rosa per l'utente chiamante
        $ruoloCalciatoreDaAvviare = $calciatorePerAsta->ruolo;
        $limitePerRuolo = 0;
        switch ($ruoloCalciatoreDaAvviare) {
            case 'P': $limitePerRuolo = $impostazioniLega->num_portieri; break;
            case 'D': $limitePerRuolo = $impostazioniLega->num_difensori; break;
            case 'C': $limitePerRuolo = $impostazioniLega->num_centrocampisti; break;
            case 'A': $limitePerRuolo = $impostazioniLega->num_attaccanti; break;
        }
        
        if ($limitePerRuolo > 0) {
            $giocatoriAttualiNelRuoloChiamante = GiocatoreAcquistato::where('user_id', $utenteChiamante->id)
            ->whereHas('calciatore', function($query) use ($ruoloCalciatoreDaAvviare, $impostazioniLega, $chiamataAsta) {
                $query->where('ruolo', $ruoloCalciatoreDaAvviare);
                $tagAstaCorrente = $chiamataAsta->tag_lista_calciatori ?: $impostazioniLega->tag_lista_attiva;
                if ($tagAstaCorrente) {
                    $query->where('tag_lista_inserimento', $tagAstaCorrente);
                }
            })->count();
            
            Log::info("[Admin Avvio Asta - Validazione Limiti Chiamante] Utente: {$utenteChiamante->name}, Calciatore: {$calciatorePerAsta->nome_completo} ({$ruoloCalciatoreDaAvviare}), Attuali nel ruolo: {$giocatoriAttualiNelRuoloChiamante}, Limite per ruolo: {$limitePerRuolo}");
            
            if ($giocatoriAttualiNelRuoloChiamante >= $limitePerRuolo) {
                $chiamataAsta->update(['stato_chiamata' => 'annullata_admin', 'timestamp_fine_tap_prevista' => null]);
                Log::warning("[Admin Avvio Asta TAP] Avvio asta per {$calciatorePerAsta->nome_completo} bloccato. L'utente chiamante {$utenteChiamante->name} ha raggiunto il limite ({$giocatoriAttualiNelRuoloChiamante}/{$limitePerRuolo}) per il ruolo {$ruoloCalciatoreDaAvviare}. Chiamata ID {$chiamataAsta->id} annullata.");
                return redirect()->route('admin.asta.chiamate.gestione')
                ->with('error', "L'utente {$utenteChiamante->name} ha già raggiunto il limite di {$limitePerRuolo} giocatori per il ruolo {$ruoloCalciatoreDaAvviare}. Impossibile avviare l'asta per {$calciatorePerAsta->nome_completo}. La chiamata è stata annullata.");
            }
        }
        
        
        // 4. AVVIO EFFETTIVO DELL'ASTA
        try {
            DB::beginTransaction();
            $chiamataAsta->update([
                'stato_chiamata' => 'in_asta_tap_live',
                'prezzo_partenza_tap' => $baseAstaDaUsare,      // Usa la base calcolata correttamente
                'prezzo_attuale_tap' => $baseAstaDaUsare,        // Usa la base calcolata correttamente
                'miglior_offerente_tap_id' => $chiamataAsta->user_id_chiamante, // Il chiamante originale fa l'offerta base
                'timestamp_fine_tap_prevista' => Carbon::now()->addSeconds($impostazioniLega->durata_countdown_secondi),
            ]);
            DB::commit();
            
            Log::info("Admin (ID: ".Auth::id().") ha avviato Asta TAP per {$calciatorePerAsta->nome_completo} (Chiamata ID: {$chiamataAsta->id}). Prezzo Partenza IMPOSTATO: {$baseAstaDaUsare}. Fine: {$chiamataAsta->timestamp_fine_tap_prevista}");
            
            // TODO: ORDINE CHIAMATA - Se l'ordine di chiamata è attivo, avanza il turno
            // if ($impostazioniLega->usa_ordine_chiamata) {
            //     // Assumendo che tu abbia un metodo per avanzare il turno sull'oggetto $impostazioniLega
            //     // o un service dedicato. Bisogna passare l'ID dell'utente che ha "usato" il turno.
            //     // $impostazioniLega->avanzaTurnoChiamata($chiamataAsta->user_id_chiamante);
            //     Log::info("[Admin Avvio Asta TAP] Ordine chiamata attivo. Tentativo avanzamento turno per utente ID: {$chiamataAsta->user_id_chiamante}");
            // }
            
            return redirect()->route('admin.asta.chiamate.gestione')->with('success', "Asta TAP per {$calciatorePerAsta->nome_completo} avviata! Base: {$baseAstaDaUsare}.");
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Errore durante l'avvio dell'asta TAP da parte dell'admin per Chiamata ID {$chiamataAsta->id}: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Errore tecnico durante l\'avvio dell\'asta TAP: ' . $e->getMessage());
        }
    }

    public function annullaChiamataTap(Request $request, ChiamataAsta $chiamataAsta)
    {
        Log::info("Admin (ID: ".Auth::id().") sta tentando di annullare la chiamata/asta TAP ID: {$chiamataAsta->id} per il calciatore: {$chiamataAsta->calciatore->nome_completo}");

        if (!in_array($chiamataAsta->stato_chiamata, ['in_attesa_admin', 'in_asta_tap_live'])) {
            Log::warning("Tentativo di annullare chiamata/asta TAP ID {$chiamataAsta->id} che non è in stato 'in_attesa_admin' o 'in_asta_tap_live'. Stato attuale: {$chiamataAsta->stato_chiamata}");
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Questa chiamata/asta non può essere annullata perché il suo stato attuale è: ' . $chiamataAsta->stato_chiamata);
        }

        $utenteChiamanteOriginaleId = $chiamataAsta->user_id_chiamante;
        $eraAstaLive = $chiamataAsta->stato_chiamata === 'in_asta_tap_live';

        try {
            DB::beginTransaction();

            $chiamataAsta->stato_chiamata = 'annullata_admin';
            $chiamataAsta->timestamp_fine_tap_prevista = null; // Resetta il timestamp di fine
            $chiamataAsta->miglior_offerente_tap_id = null; // Resetta miglior offerente
            // Non resettare prezzo_partenza_tap o prezzo_attuale_tap per mantenere uno storico, se si vuole.
            $chiamataAsta->save();

            // TODO: ORDINE CHIAMATA:
            // Se l'ordine di chiamata è attivo, il turno DEVE passare all'utente successivo
            // rispetto a $utenteChiamanteOriginaleId, perché la sua "chiamata" è stata consumata/annullata.
            // $impostazioniLega = ImpostazioneLega::firstOrFail();
            // if ($impostazioniLega->usa_ordine_chiamata) {
            //     $impostazioniLega->avanzaTurnoChiamata($utenteChiamanteOriginaleId); // Passa l'ID di chi aveva chiamato
            // }

            DB::commit();

            Log::info("Chiamata/Asta TAP ID {$chiamataAsta->id} per {$chiamataAsta->calciatore->nome_completo} annullata con successo dall'admin (ID: ".Auth::id()."). Era live: " . ($eraAstaLive ? 'Sì' : 'No'));
            return redirect()->route('admin.asta.chiamate.gestione')->with('success', "Chiamata/Asta per {$chiamataAsta->calciatore->nome_completo} annullata con successo.");

        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Errore durante l'annullamento della chiamata/asta TAP ID {$chiamataAsta->id}: " . $e->getMessage());
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Errore tecnico durante l\'annullamento della chiamata/asta.');
        }
    }
}