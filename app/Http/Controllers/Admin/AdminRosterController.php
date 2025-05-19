<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Calciatore;
use App\Models\GiocatoreAcquistato;
use App\Models\ImpostazioneLega;
use App\Models\ChiamataAsta; // Assicurati che sia importato
use Carbon\Carbon; // Per il timestamp
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth; // Aggiunto per Auth::id()
use Exception;

class AdminRosterController extends Controller
{
    public function visualizzaRoseSquadre()
    {
        $squadre = User::with(['giocatoriAcquistati.calciatore'])
                       ->orderBy('name')
                       ->get();

        $impostazioniLega = ImpostazioneLega::first();
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
            $rosa = $squadra->giocatoriAcquistati->filter(function ($acquisto) use ($tagListaAttiva) {
                if (!$tagListaAttiva || !$acquisto->calciatore) {
                    // Se non c'è un tag attivo o il calciatore non è caricato, non possiamo filtrare specificamente
                    // In un'asta reale, ogni acquisto dovrebbe essere implicitamente legato al tag dell'asta in corso
                    // Se GiocatoreAcquistato avesse un campo 'tag_lista_asta', filtreremmo su quello.
                    // Per ora, ci basiamo sul tag_lista_inserimento del calciatore.
                    return $tagListaAttiva ? (optional($acquisto->calciatore)->tag_lista_inserimento === $tagListaAttiva) : true;
                }
                return $acquisto->calciatore->tag_lista_inserimento === $tagListaAttiva;
            });

            $costoTotaleRosa = $rosa->sum('prezzo_acquisto');
            $conteggioRuoliEffettivo = collect();

            if ($rosa->isNotEmpty()) {
                $idsCalciatoriFiltrati = $rosa->pluck('calciatore_id')->unique()->toArray();
                if(!empty($idsCalciatoriFiltrati)) {
                    $queryRuoli = Calciatore::whereIn('id', $idsCalciatoriFiltrati);
                    if ($tagListaAttiva) {
                         $queryRuoli->where('tag_lista_inserimento', $tagListaAttiva);
                    }
                    $conteggioRuoliEffettivo = $queryRuoli->pluck('ruolo')->countBy();
                }
            }

            return [
                'id' => $squadra->id,
                'name' => $squadra->name,
                'is_admin' => $squadra->is_admin,
                'crediti_iniziali_squadra' => $squadra->crediti_iniziali_squadra,
                'crediti_rimanenti' => $squadra->crediti_rimanenti,
                'costo_totale_rosa' => $costoTotaleRosa,
                'rosa_giocatori' => $rosa, // Rosa filtrata
                'conteggio_ruoli' => $conteggioRuoliEffettivo,
                'limiti_ruoli' => $limitiRuoli,
                'numero_giocatori_in_rosa' => $rosa->count() // Conteggio sulla rosa filtrata
            ];
        });

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

    public function handleAssegna(Request $request)
    {
        $validatedData = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'calciatore_id' => ['required', 'exists:calciatori,id'],
            'prezzo_acquisto' => ['required', 'integer', 'min:0'], // Permetti 0 se vuoi acquisti gratuiti
        ]);
        
        $squadra = User::findOrFail($validatedData['user_id']);
        $calciatore = Calciatore::findOrFail($validatedData['calciatore_id']);
        $prezzoAcquisto = (int)$validatedData['prezzo_acquisto'];
        
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $faseAstaCorrente = $impostazioniLega->fase_asta_corrente;
        $tagAttivo = $impostazioniLega->tag_lista_attiva;
        
        // RIGA 150 circa - CORREZIONE: DB::beginTransaction();
        DB::beginTransaction();
        try {
            // 0. Controllo Giocatore Già Acquistato (per il tag attivo)
            $giaAcquistatoGlobale = GiocatoreAcquistato::where('calciatore_id', $calciatore->id)
            ->whereHas('calciatore', function ($q) use ($tagAttivo) {
                // Questo controllo è più complesso. Se un giocatore è acquistato
                // da QUALSIASI squadra per il tag_lista_attiva, non dovrebbe essere riassegnabile.
                // Dobbiamo verificare che il GiocatoreAcquistato sia relativo a un Calciatore
                // con lo stesso tag_lista_inserimento.
                // Oppure, se GiocatoreAcquistato ha un campo tag_lista, usarlo.
                // Per ora, presumiamo che il calciatore sia univoco per tag.
                if ($tagAttivo) {
                    // Questa verifica se IL CALCIATORE STESSO ha il tag corretto.
                    // Ma per vedere se è stato ACQUISTATO IN QUESTA ASTA/STAGIONE
                    // GiocatoreAcquistato dovrebbe avere un riferimento al tag dell'asta.
                    // Se Calciatore.tag_lista_inserimento è l'unico riferimento:
                    // $q->where('tag_lista_inserimento', $tagAttivo); // Questo è sul calciatore, non sull'acquisto.
                    // Se GiocatoreAcquistato ha un campo tipo 'tag_asta'
                    // ->where('tag_asta', $tagAttivo)
                }
            })->exists();
            
            // Controlliamo specificamente per QUESTA squadra, ma il controllo globale è più restrittivo.
            // Per ora, manteniamo il controllo se QUESTA squadra ha già il giocatore.
            $giaAcquistatoDallaSquadra = GiocatoreAcquistato::where('user_id', $squadra->id)
            ->where('calciatore_id', $calciatore->id)
            // Aggiungere filtro per tag/stagione se GiocatoreAcquistato ha un campo per esso
            ->exists();
            if ($giaAcquistatoDallaSquadra) { // O $giaAcquistatoGlobale se vuoi essere più restrittivo
                throw new Exception("{$calciatore->nome_completo} è già presente nella rosa di {$squadra->name}.");
            }
            
            
            // 1. CONTROLLO FASE ASTA E RUOLO CALCIATORE
            $ruoliFaseMappa = ['P' => 'Portieri', 'D' => 'Difensori', 'C' => 'Centrocampisti', 'A' => 'Attaccanti'];
            if (array_key_exists($faseAstaCorrente, $ruoliFaseMappa)) {
                if ($calciatore->ruolo !== $faseAstaCorrente) {
                    throw new Exception("Non puoi assegnare un giocatore di ruolo {$calciatore->ruolo} durante la fase dedicata al ruolo {$ruoliFaseMappa[$faseAstaCorrente]} ({$faseAstaCorrente}).");
                }
                // Controllo completamento reparti precedenti (se l'admin deve seguire le regole)
                // Questa logica può diventare complessa e dipende se vuoi che l'admin possa forzare.
                // Per ora, la commentiamo ma è un punto da considerare.
                /*
                 $repartiPrecedenti = [];
                 if ($faseAstaCorrente === 'D') $repartiPrecedenti = ['P' => $impostazioniLega->num_portieri];
                 if ($faseAstaCorrente === 'C') $repartiPrecedenti = ['P' => $impostazioniLega->num_portieri, 'D' => $impostazioniLega->num_difensori];
                 if ($faseAstaCorrente === 'A') $repartiPrecedenti = ['P' => $impostazioniLega->num_portieri, 'D' => $impostazioniLega->num_difensori, 'C' => $impostazioniLega->num_centrocampisti];
                 
                 foreach ($repartiPrecedenti as $ruoloPrec => $limitePrec) {
                 $numGiocatoriRuoloPrec = GiocatoreAcquistato::where('user_id', $squadra->id)
                 ->whereHas('calciatore', function($q) use ($ruoloPrec, $tagAttivo) {
                 $q->where('ruolo', $ruoloPrec);
                 if ($tagAttivo) $q->where('tag_lista_inserimento', $tagAttivo);
                 })->count();
                 if ($numGiocatoriRuoloPrec < $limitePrec) {
                 throw new Exception("La squadra {$squadra->name} deve prima completare il reparto {$ruoliFaseMappa[$ruoloPrec]} (attuali: $numGiocatoriRuoloPrec/{$limitePrec}).");
                 }
                 }
                 */
            } elseif (in_array($faseAstaCorrente, ['PRE_ASTA', 'CONCLUSA'])) {
                Log::info("Assegnazione manuale admin fuori fase attiva: Fase attuale {$faseAstaCorrente}. Admin ID: " . auth()->id());
            }
            
            // 2. Verifica Crediti Squadra
            if ($squadra->crediti_rimanenti < $prezzoAcquisto) {
                throw new Exception("La squadra {$squadra->name} non ha crediti sufficienti (Disponibili: {$squadra->crediti_rimanenti}, Richiesti: {$prezzoAcquisto}).");
            }
            
            // 3. Verifica Limiti Rosa e Ruoli
            // Conteggio giocatori per il tag attivo dell'asta
            $rosaAttualeFiltrata = GiocatoreAcquistato::where('user_id', $squadra->id)
            ->whereHas('calciatore', function($q) use ($tagAttivo) {
                if ($tagAttivo) {
                    // Assumiamo che il 'tag_lista_inserimento' sul modello Calciatore sia il riferimento per la stagione/lista
                    $q->where('tag_lista_inserimento', $tagAttivo);
                }
                // Se GiocatoreAcquistato avesse un campo 'tag_asta', lo useremmo qui:
                // else if ($tag_specifico_acquisto) $q->where('tag_asta', $tag_specifico_acquisto)
            })->with('calciatore:id,ruolo') // Eager load solo le colonne necessarie
            ->get();
            
            $numGiocatoriTotaliInRosaFiltrata = $rosaAttualeFiltrata->count();
            $conteggioRuoliFiltrato = $rosaAttualeFiltrata->map(fn($acq) => $acq->calciatore->ruolo)->filter()->countBy();
            
            $limiteGiocatoriTotaliSistema = $impostazioniLega->num_portieri + $impostazioniLega->num_difensori + $impostazioniLega->num_centrocampisti + $impostazioniLega->num_attaccanti;
            
            if ($numGiocatoriTotaliInRosaFiltrata >= $limiteGiocatoriTotaliSistema) {
                throw new Exception("La squadra {$squadra->name} ha già raggiunto il limite massimo di {$limiteGiocatoriTotaliSistema} giocatori per la lista '{$tagAttivo}'.");
            }
            
            $limitePerRuoloCorrente = match ($calciatore->ruolo) {
                'P' => $impostazioniLega->num_portieri,
                'D' => $impostazioniLega->num_difensori,
                'C' => $impostazioniLega->num_centrocampisti,
                'A' => $impostazioniLega->num_attaccanti,
                default => 0,
            };
            
            if ($limitePerRuoloCorrente > 0 && ($conteggioRuoliFiltrato->get($calciatore->ruolo, 0) >= $limitePerRuoloCorrente)) {
                throw new Exception("La squadra {$squadra->name} ha già raggiunto il limite di {$limitePerRuoloCorrente} giocatori per il ruolo {$calciatore->ruolo} per la lista '{$tagAttivo}'.");
            }
            
            // REGOLA ULTIMO CREDITO (se il prezzo è 1 e sono gli ultimi crediti)
            if ($prezzoAcquisto === 1 && $squadra->crediti_rimanenti === 1) {
                $postiLiberi = $limiteGiocatoriTotaliSistema - $numGiocatoriTotaliInRosaFiltrata;
                if ($postiLiberi <= 0) { // Dovrebbe essere già coperto dal check precedente, ma per sicurezza
                    throw new Exception("Impossibile acquistare a 1 credito. La rosa è piena.");
                }
                // Non sono necessarie altre verifiche se è l'ultimo credito, l'acquisto è permesso se c'è posto.
                Log::info("Acquisto a 1 con ultimo credito per {$squadra->name}, giocatore {$calciatore->nome_completo}.");
            }
            
            // REGOLA PORTIERI (non puoi prendere l'ultimo portiere se hai già 2 portieri e ti rimarrebbero crediti per altri ruoli)
            // Questa regola è complessa da applicare qui senza conoscere le intenzioni esatte.
            // Solitamente si applica quando *tutti* i giocatori di un ruolo sono stati chiamati.
            // Per l'assegnazione manuale, l'admin ha più controllo. Se vuoi implementarla strettamente:
            if ($calciatore->ruolo === 'P' && ($conteggioRuoliFiltrato->get('P', 0) == $impostazioniLega->num_portieri - 1) ) {
                // Sta per prendere l'ultimo portiere
                $creditiDopoAcquistoPortiere = $squadra->crediti_rimanenti - $prezzoAcquisto;
                $difensoriMancanti = $impostazioniLega->num_difensori - ($conteggioRuoliFiltrato->get('D', 0));
                $centrocampistiMancanti = $impostazioniLega->num_centrocampisti - ($conteggioRuoliFiltrato->get('C', 0));
                $attaccantiMancanti = $impostazioniLega->num_attaccanti - ($conteggioRuoliFiltrato->get('A', 0));
                $giocatoriMancantiAltriRuoli = $difensoriMancanti + $centrocampistiMancanti + $attaccantiMancanti;
                
                if ($giocatoriMancantiAltriRuoli > 0 && $creditiDopoAcquistoPortiere < $giocatoriMancantiAltriRuoli) {
                    throw new Exception("Non puoi acquistare l'ultimo portiere. Dopo l'acquisto ti rimarrebbero {$creditiDopoAcquistoPortiere} crediti, ma devi ancora acquistare {$giocatoriMancantiAltriRuoli} giocatori negli altri ruoli (minimo 1 credito l'uno).");
                }
            }
            
            
            // Se tutte le validazioni passano:
            GiocatoreAcquistato::create([
                'user_id' => $squadra->id,
                'calciatore_id' => $calciatore->id,
                'prezzo_acquisto' => $prezzoAcquisto,
                'ruolo_al_momento_acquisto' => $calciatore->ruolo,
                // Aggiungi qui 'tag_lista_calciatori' o 'tag_asta' se la tabella GiocatoreAcquistato ha un campo per esso.
                // Esempio: 'tag_asta' => $tagAttivo,
            ]);
            
            $squadra->crediti_rimanenti -= $prezzoAcquisto;
            $squadra->save();
            
            DB::commit(); // CORREZIONE: DB::commit();
            
            Log::info("Admin (ID: ".auth()->id().") ha assegnato manualmente {$calciatore->nome_completo} a {$squadra->name} per {$prezzoAcquisto} crediti. Tag lista: {$tagAttivo}.");
            return redirect()->route('admin.giocatori.assegna.show')->with('success', "Calciatore {$calciatore->nome_completo} assegnato a {$squadra->name} per {$prezzoAcquisto} crediti.");
            
        } catch (Exception $e) {
            DB::rollBack(); // CORREZIONE: DB::rollBack();
            Log::error("Errore assegnazione manuale admin per {$calciatore->nome_completo} a {$squadra->name}. Errore: " . $e->getMessage());
            return redirect()->route('admin.giocatori.assegna.show')->withInput()->with('error', $e->getMessage());
        }
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
        
        // ... (altre validazioni iniziali come prima) ...
        if ($impostazioniLega->modalita_asta !== 'tap' /* || !$impostazioniLega->asta_tap_approvazione_admin */) { // Anche se non serve approvazione, questa rotta viene chiamata se l'admin la forza. Meglio controllare solo la modalità.
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'La modalità asta TAP non è attiva o l\'approvazione admin non è richiesta per l\'avvio.');
        }
        if ($chiamataAsta->stato_chiamata !== 'in_attesa_admin') {
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "Questa chiamata per {$chiamataAsta->calciatore->nome_completo} non è più in attesa di approvazione (Stato: {$chiamataAsta->stato_chiamata}).");
        }
        // ... (controllo asta live esistente e fase/ruolo) ...
        
        // --- VALIDAZIONE LIMITI ROSA PER IL CHIAMANTE ORIGINALE ---
        $utenteChiamante = $chiamataAsta->utenteChiamante()->first(); // Assicurati di prendere l'oggetto User
        $calciatorePerAsta = $chiamataAsta->calciatore()->first();   // Assicurati di prendere l'oggetto Calciatore
        
        if ($utenteChiamante && $calciatorePerAsta) {
            $ruoloCalciatoreDaAvviare = $calciatorePerAsta->ruolo;
            $limitePerRuolo = 0;
            switch ($ruoloCalciatoreDaAvviare) {
                case 'P': $limitePerRuolo = $impostazioniLega->num_portieri; break;
                case 'D': $limitePerRuolo = $impostazioniLega->num_difensori; break;
                case 'C': $limitePerRuolo = $impostazioniLega->num_centrocampisti; break;
                case 'A': $limitePerRuolo = $impostazioniLega->num_attaccanti; break;
            }
            
            if ($limitePerRuolo > 0) {
                // Calcola i giocatori attuali nel ruolo per l'utente chiamante
                $giocatoriAttualiNelRuoloChiamante = GiocatoreAcquistato::where('user_id', $utenteChiamante->id)
                ->whereHas('calciatore', function($query) use ($ruoloCalciatoreDaAvviare, $impostazioniLega, $chiamataAsta) {
                    $query->where('ruolo', $ruoloCalciatoreDaAvviare);
                    // Filtra per il tag lista dell'asta che si sta per avviare
                    $tagAstaCorrente = $chiamataAsta->tag_lista_calciatori ?: $impostazioniLega->tag_lista_attiva;
                    if ($tagAstaCorrente) {
                        $query->where('tag_lista_inserimento', $tagAstaCorrente);
                    }
                })->count();
                
                // Log corretto
                Log::info("[Avvia Asta Validazione Admin] Utente Chiamante: {$utenteChiamante->name}, Calciatore: {$calciatorePerAsta->nome_completo} ({$ruoloCalciatoreDaAvviare}), Attuali nel ruolo: {$giocatoriAttualiNelRuoloChiamante}, Limite: {$limitePerRuolo}");
                
                // LA RIGA 398 POTREBBE ESSERE QUESTA CONDIZIONE IF o un log successivo
                // Assicurati che qui e nei log seguenti si usi $giocatoriAttualiNelRuoloChiamante
                if ($giocatoriAttualiNelRuoloChiamante >= $limitePerRuolo) {
                    // Annulla la chiamata e informa l'admin
                    $chiamataAsta->update(['stato_chiamata' => 'annullata_admin']); // O uno stato più specifico come 'annullata_limite_raggiunto'
                    Log::warning("AdminRosterController@avviaAstaTap: Avvio asta per {$calciatorePerAsta->nome_completo} bloccato. L'utente chiamante {$utenteChiamante->name} ha raggiunto il limite ({$giocatoriAttualiNelRuoloChiamante}/{$limitePerRuolo}) per il ruolo {$ruoloCalciatoreDaAvviare}. Chiamata ID {$chiamataAsta->id} annullata.");
                    return redirect()->route('admin.asta.chiamate.gestione')
                    ->with('error', "L'utente {$utenteChiamante->name} ha già raggiunto il limite di {$limitePerRuolo} giocatori per il ruolo {$ruoloCalciatoreDaAvviare}. Impossibile avviare l'asta per {$calciatorePerAsta->nome_completo}. La chiamata è stata annullata.");
                }
            }
        } else {
            Log::error("[Avvia Asta Validazione Admin] Dati utente chiamante o calciatore mancanti per Chiamata ID: {$chiamataAsta->id}");
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Dati della chiamata incompleti, impossibile validare i limiti.');
        }
        // --- FINE NUOVA VALIDAZIONE LIMITI ROSA ---
        
        // --- AVVIO ASTA TAP (codice esistente) ---
        // ... (il tuo try-catch con DB::beginTransaction(); $chiamataAsta->update([...]); DB::commit(); ...) ...
        try {
            DB::beginTransaction();
            $prezzoPartenza = $calciatorePerAsta->quotazione_iniziale > 0 ? $calciatorePerAsta->quotazione_iniziale : 1;
            $chiamataAsta->update([
                'stato_chiamata' => 'in_asta_tap_live',
                'prezzo_partenza_tap' => $prezzoPartenza,
                'prezzo_attuale_tap' => $prezzoPartenza,
                'miglior_offerente_tap_id' => $chiamataAsta->user_id_chiamante,
                'timestamp_fine_tap_prevista' => Carbon::now()->addSeconds($impostazioniLega->durata_countdown_secondi),
            ]);
            DB::commit();
            Log::info("Admin (ID: ".auth()->id().") ha avviato Asta TAP per {$chiamataAsta->calciatore->nome_completo} (Chiamata ID: {$chiamataAsta->id}). Prezzo base: {$prezzoPartenza}. Fine: {$chiamataAsta->timestamp_fine_tap_prevista}");
            return redirect()->route('admin.asta.chiamate.gestione')->with('success', "Asta TAP per {$chiamataAsta->calciatore->nome_completo} avviata! Prezzo base: {$prezzoPartenza}.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Errore durante l'avvio dell'asta TAP da parte dell'admin per Chiamata ID {$chiamataAsta->id}: " . $e->getMessage());
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