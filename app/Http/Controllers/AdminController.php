<?php

namespace App\Http\Controllers;

use App\Models\ImpostazioneLega;
use App\Models\User;
use App\Models\Calciatore;
use App\Models\GiocatoreAcquistato;
use App\Models\ChiamataAsta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Carbon\Carbon;

class AdminController extends Controller
{
    public function mostraMiaSquadraDashboard()
    {
        $user = Auth::user();
        if (!$user || !$user->is_admin) {
            abort(403, 'Accesso non autorizzato a questa sezione.');
        }
        
        $squadra = $user;
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $tagListaAttiva = $impostazioniLega->tag_lista_attiva;
        
        $rosaQuery = $squadra->giocatoriAcquistati()->with('calciatore');
        if ($tagListaAttiva) {
            $rosaQuery->whereHas('calciatore', function ($q) use ($tagListaAttiva) {
                $q->where('tag_lista_inserimento', $tagListaAttiva);
            });
        }
        $rosa = $rosaQuery->get();
        $costoTotaleRosa = $rosa->sum('prezzo_acquisto');
        
        $conteggioRuoli = collect(['P' => 0, 'D' => 0, 'C' => 0, 'A' => 0]);
        $costiPerRuolo = collect(['P' => 0, 'D' => 0, 'C' => 0, 'A' => 0]);
        
        if ($rosa->isNotEmpty()) {
            foreach ($rosa as $acquisto) {
                if ($acquisto->calciatore) {
                    $ruolo = $acquisto->calciatore->ruolo;
                    if ($conteggioRuoli->has($ruolo)) {
                        $conteggioRuoli->put($ruolo, $conteggioRuoli->get($ruolo) + 1);
                        $costiPerRuolo->put($ruolo, $costiPerRuolo->get($ruolo) + $acquisto->prezzo_acquisto);
                    }
                }
            }
        }
        
        $limitiRuoli = [
            'P' => $impostazioniLega->num_portieri,
            'D' => $impostazioniLega->num_difensori,
            'C' => $impostazioniLega->num_centrocampisti,
            'A' => $impostazioniLega->num_attaccanti,
        ];
        $numeroGiocatoriInRosa = $rosa->count();
        $limiteGiocatoriTotaliInRosa = array_sum($limitiRuoli);
        
        return view('squadra.dashboard', compact(
            'squadra', 'rosa', 'impostazioniLega', 'costoTotaleRosa',
            'conteggioRuoli', 'costiPerRuolo', 'limitiRuoli',
            'numeroGiocatoriInRosa', 'limiteGiocatoriTotaliInRosa'
            ));
    }
    
    public function dashboard()
    {
        $impostazioniLega = ImpostazioneLega::firstOrCreate([], [
            'fase_asta_corrente' => 'PRE_ASTA', 'crediti_iniziali_lega' => 500,
            'num_portieri' => 3, 'num_difensori' => 8, 'num_centrocampisti' => 8, 'num_attaccanti' => 6,
            'tag_lista_attiva' => null, 'modalita_asta' => 'voce',
            'durata_countdown_secondi' => 60, 'asta_tap_approvazione_admin' => true,
            'tipo_base_asta' => 'quotazione_iniziale',
            'usa_ordine_chiamata' => false,
            'prossimo_turno_chiamata_user_id' => null,
        ]);
        
        $numeroSquadre = User::where('is_admin', false)->count();
        $numeroCalciatoriImportati = $impostazioniLega->tag_lista_attiva ?
        Calciatore::where('tag_lista_inserimento', $impostazioniLega->tag_lista_attiva)->count() :
        Calciatore::count();
        
        return view('admin.dashboard', compact(
            'impostazioniLega', 'numeroSquadre', 'numeroCalciatoriImportati'
            ));
    }
    
    public function users(Request $request)
    {
        $utenti = User::orderBy('ordine_chiamata', 'asc')->orderBy('name', 'asc')->paginate(15);
        return view('admin.users.index', compact('utenti'));
    }
    
    public function editUser(User $user)
    {
        return view('admin.users.edit', compact('user'));
    }
    
    public function updateUser(Request $request, User $user)
    {
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'crediti_iniziali_squadra' => ['required', 'integer', 'min:0'],
            'crediti_rimanenti' => ['required', 'integer', 'min:0'],
            'is_admin' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'nome_proprietario' => ['nullable', 'string', 'max:255'],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'ordine_chiamata' => ['nullable', 'integer', 'min:1', Rule::unique('users','ordine_chiamata')->ignore($user->id)],
        ]);
        
        $userData = [
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'crediti_iniziali_squadra' => $validatedData['crediti_iniziali_squadra'],
            'crediti_rimanenti' => $validatedData['crediti_rimanenti'],
            'is_admin' => $request->boolean('is_admin'),
            'nome_proprietario' => $validatedData['nome_proprietario'],
            'phone_number' => $validatedData['phone_number'],
            'ordine_chiamata' => $validatedData['ordine_chiamata'],
        ];
        
        if ($request->filled('password')) {
            $userData['password'] = bcrypt($request->password);
        }
        
        $user->update($userData);
        return redirect()->route('admin.users.index')->with('success', 'Utente aggiornato con successo.');
    }
    
    // Funzione helper per ottenere le fasi possibili per il dropdown nel form
    private function getFasiDropdownOpzioni()
    {
        return [
            'PRE_ASTA' => 'Pre-Asta (Chiusa)',
            'P' => 'Portieri (P)',
            'D' => 'Difensori (D)',
            'C' => 'Centrocampisti (C)',
            'A' => 'Attaccanti (A)',
            'CONCLUSA' => 'Asta Conclusa'
        ];
    }
    
    // Funzione helper per ottenere l'ordine sequenziale delle fasi di ruolo
    private function getFasiRuoloOrdinate()
    {
        return ['P', 'D', 'C', 'A'];
    }
    
    // Funzione helper per ottenere il nome completo di una fase
    private function getNomeFaseCompleto($codiceFase)
    {
        $mappaNomi = [
            'P' => 'Portieri', 'D' => 'Difensori', 'C' => 'Centrocampisti', 'A' => 'Attaccanti',
            'PRE_ASTA' => 'Pre-Asta', 'CONCLUSA' => 'Asta Conclusa'
        ];
        return $mappaNomi[$codiceFase] ?? $codiceFase;
    }
    
    public function settings()
    {
        $impostazioni = ImpostazioneLega::firstOrFail();
        $fasiPossibili = $this->getFasiDropdownOpzioni(); // Usa l'helper
        $tagsCalciatoriDisponibili = Calciatore::select('tag_lista_inserimento')
        ->whereNotNull('tag_lista_inserimento')->where('tag_lista_inserimento', '!=', '')
        ->distinct()->orderBy('tag_lista_inserimento', 'desc')->pluck('tag_lista_inserimento');
        
        $utentiPerOrdineChiamata = User::orderBy('name')->get(['id', 'name']);
        
        return view('admin.settings.index', compact(
            'impostazioni', 'fasiPossibili', 'tagsCalciatoriDisponibili', 'utentiPerOrdineChiamata'
            ));
    }
    
    public function updateSettings(Request $request)
    {
        $impostazioni = ImpostazioneLega::firstOrFail();
        $faseAstaAttualeDB = $impostazioni->fase_asta_corrente;
        $tagListaAttivaDB = $impostazioni->tag_lista_attiva ?: $request->input('tag_lista_attiva'); // Prende il tag attuale o quello in richiesta se il DB è null
        
        $fasiRuoloOrdinate = $this->getFasiRuoloOrdinate();
        $fasiDropdown = $this->getFasiDropdownOpzioni();
        
        $validatedData = $request->validate([
            'fase_asta_corrente' => ['required', 'string', Rule::in(array_keys($fasiDropdown))],
            'tag_lista_attiva' => [
                Rule::requiredIf(fn() => in_array($request->input('fase_asta_corrente'), $fasiRuoloOrdinate)),
                'nullable', 'string', 'max:255'
            ],
            'modalita_asta' => ['required', 'string', Rule::in(['voce', 'tap'])],
            'durata_countdown_secondi' => ['required', 'integer', 'min:10', 'max:300'],
            'asta_tap_approvazione_admin' => ['nullable', 'boolean'],
            'crediti_iniziali_lega' => ['required', 'integer', 'min:1', 'max:9999'],
            'num_portieri' => ['required', 'integer', 'min:0', 'max:25'],
            'num_difensori' => ['required', 'integer', 'min:0', 'max:25'],
            'num_centrocampisti' => ['required', 'integer', 'min:0', 'max:25'],
            'num_attaccanti' => ['required', 'integer', 'min:0', 'max:25'],
            'reset_asta_completo' => ['nullable', 'boolean'],
            'tipo_base_asta' => ['required', Rule::in(['quotazione_iniziale', 'credito_singolo'])],
            'usa_ordine_chiamata' => ['nullable', 'boolean'],
            'prossimo_turno_chiamata_user_id' => ['nullable', 'exists:users,id'],
        ]);
        
        $nuovaFaseAstaRichiesta = $validatedData['fase_asta_corrente'];
        
        // LOGICA DI CONTROLLO AVANZAMENTO FASE
        if (!$request->boolean('reset_asta_completo') && $nuovaFaseAstaRichiesta !== $faseAstaAttualeDB) {
            $indiceAttuale = array_search($faseAstaAttualeDB, $fasiRuoloOrdinate);
            $indiceNuovo = array_search($nuovaFaseAstaRichiesta, $fasiRuoloOrdinate);
            
            // Caso 1: Si tenta di passare a una fase di ruolo (P, D, C, A)
            if ($indiceNuovo !== false) {
                if ($faseAstaAttualeDB === 'PRE_ASTA') {
                    if ($nuovaFaseAstaRichiesta !== 'P') {
                        return redirect()->route('admin.settings.index')->withInput()
                        ->with('error', "Dalla fase PRE_ASTA puoi passare solo alla fase Portieri (P).");
                    }
                    // Da PRE_ASTA a P è consentito senza controlli sui reparti
                    Log::info("Inizio asta: da PRE_ASTA a P. Nessun controllo sui reparti.");
                } elseif ($faseAstaAttualeDB === 'CONCLUSA'){
                    // Da CONCLUSA si può solo tornare a PRE_ASTA o resettare.
                    if ($nuovaFaseAstaRichiesta !== 'PRE_ASTA'){
                        return redirect()->route('admin.settings.index')->withInput()
                        ->with('error', "Dall'asta CONCLUSA puoi solo tornare a PRE_ASTA o effettuare un reset completo.");
                    }
                } elseif ($indiceAttuale !== false) { // Se la fase attuale è una fase di ruolo P, D, C, A
                    if ($indiceNuovo === $indiceAttuale + 1) {
                        // Si sta avanzando alla fase immediatamente successiva (es. da P a D)
                        $ruoloDaAverCompletato = $fasiRuoloOrdinate[$indiceAttuale]; // = $faseAstaAttualeDB
                        Log::info("Controllo avanzamento fase da {$faseAstaAttualeDB} a {$nuovaFaseAstaRichiesta}. Controllo completamento reparto {$ruoloDaAverCompletato} con tag '{$tagListaAttivaDB}'.");
                        
                        $limiteRuolo = match ($ruoloDaAverCompletato) {
                            'P' => $impostazioni->num_portieri, 'D' => $impostazioni->num_difensori,
                            'C' => $impostazioni->num_centrocampisti, 'A' => $impostazioni->num_attaccanti,
                            default => 0,
                        };
                        
                        if ($limiteRuolo > 0) {
                            $squadreDaControllare = User::where('is_admin', false)->get();
                            if ($squadreDaControllare->isEmpty() && User::where('is_admin',true)->count() >= 1){
                                Log::info("Nessuna squadra partecipante (solo admin), consento avanzamento fase da {$faseAstaAttualeDB} a {$nuovaFaseAstaRichiesta}.");
                            } else {
                                $squadreIncomplete = [];
                                foreach ($squadreDaControllare as $squadra) {
                                    $conteggioGiocatoriRuolo = GiocatoreAcquistato::where('user_id', $squadra->id)
                                    ->whereHas('calciatore', function ($query) use ($ruoloDaAverCompletato, $tagListaAttivaDB) {
                                        $query->where('ruolo', $ruoloDaAverCompletato);
                                        if ($tagListaAttivaDB) {
                                            $query->where('tag_lista_inserimento', $tagListaAttivaDB);
                                        }
                                    })->count();
                                    if ($conteggioGiocatoriRuolo < $limiteRuolo) {
                                        $squadreIncomplete[] = "{$squadra->name} ({$conteggioGiocatoriRuolo}/{$limiteRuolo} {$ruoloDaAverCompletato})";
                                    }
                                }
                                if (!empty($squadreIncomplete)) {
                                    $elenco = implode(', ', $squadreIncomplete);
                                    Log::warning("Avanzamento fase BLOCCATO da {$faseAstaAttualeDB} a {$nuovaFaseAstaRichiesta}. Squadre incomplete per reparto {$ruoloDaAverCompletato}: {$elenco}");
                                    return redirect()->route('admin.settings.index')->withInput()
                                    ->with('error', "Impossibile passare a {$this->getNomeFaseCompleto($nuovaFaseAstaRichiesta)}. Il reparto {$this->getNomeFaseCompleto($ruoloDaAverCompletato)} (lista '{$tagListaAttivaDB}') non è completo per: {$elenco}.");
                                }
                                Log::info("Reparto {$ruoloDaAverCompletato} completo per tutte le squadre. Avanzamento a {$nuovaFaseAstaRichiesta} consentito.");
                            }
                        } else {
                            Log::info("Limite ruolo per {$ruoloDaAverCompletato} è 0, controllo completamento non necessario.");
                        }
                    } else { // Tentativo di saltare fasi o andare indietro in modo non permesso
                        Log::warning("Tentativo di cambio fase non sequenziale da {$faseAstaAttualeDB} a {$nuovaFaseAstaRichiesta}. Bloccato.");
                        return redirect()->route('admin.settings.index')->withInput()
                        ->with('error', "Cambio fase non consentito. Devi seguire l'ordine P > D > C > A e completare ogni reparto prima di avanzare.");
                    }
                }
            } // Altri cambi (es. da P a CONCLUSA, da C a PRE_ASTA) sono gestiti o bloccati dal reset
        }
        
        
        // GESTIONE AGGIORNAMENTO DATI
        $datiDaAggiornare = $validatedData;
        $datiDaAggiornare['asta_tap_approvazione_admin'] = $request->boolean('asta_tap_approvazione_admin');
        $datiDaAggiornare['usa_ordine_chiamata'] = $request->boolean('usa_ordine_chiamata');
        $messaggioSuccesso = 'Impostazioni aggiornate con successo.';
        
        if ($request->boolean('reset_asta_completo')) {
            if (empty($validatedData['tag_lista_attiva'])) {
                return redirect()->route('admin.settings.index')->withInput()
                ->withErrors(['tag_lista_attiva' => 'Per il reset completo, è obbligatorio selezionare un "Tag Lista Calciatori Attiva".']);
            }
            if (!in_array($datiDaAggiornare['fase_asta_corrente'], ['PRE_ASTA', 'P'])) {
                $datiDaAggiornare['fase_asta_corrente'] = 'P'; // Default a 'P' per un reset se non è PRE_ASTA
            }
            $crediti = $validatedData['crediti_iniziali_lega'];
            User::query()->update(['crediti_iniziali_squadra' => $crediti, 'crediti_rimanenti' => $crediti]);
            
            // Cancella solo gli acquisti relativi al tag_lista_attiva specificato per il reset.
            // O se vuoi cancellare TUTTO indipendentemente dal tag, rimuovi il whereHas.
            GiocatoreAcquistato::whereHas('calciatore', function ($query) use ($validatedData) {
                $query->where('tag_lista_inserimento', $validatedData['tag_lista_attiva']);
            })->delete();
            // O più semplicemente (se vuoi cancellare tutti gli acquisti di tutte le stagioni):
            // GiocatoreAcquistato::query()->delete();
            
            
            ChiamataAsta::whereIn('stato_chiamata', ['in_attesa_admin', 'in_asta_tap_live'])
            ->where('tag_lista_calciatori', $validatedData['tag_lista_attiva']) // Annulla solo quelle del tag attivo
            ->update(['stato_chiamata' => 'annullata_admin', 'timestamp_fine_tap_prevista' => null]);
            
            if ($datiDaAggiornare['usa_ordine_chiamata']) {
                $primoChiamante = User::where('is_admin', false)->orderBy('ordine_chiamata', 'asc')->orderBy('id', 'asc')->first();
                $datiDaAggiornare['prossimo_turno_chiamata_user_id'] = $primoChiamante ? $primoChiamante->id : null;
            } else {
                $datiDaAggiornare['prossimo_turno_chiamata_user_id'] = null;
            }
            
            Log::info("Admin (ID: ".Auth::id().") HA ESEGUITO UN RESET ASTA COMPLETO. Tag: {$validatedData['tag_lista_attiva']}. Fase: {$datiDaAggiornare['fase_asta_corrente']}.");
            $messaggioSuccesso = 'ASTA RESETTATA (per il tag '.$validatedData['tag_lista_attiva'].'): Crediti e rose azzerati, chiamate TAP annullate, fase impostata a ' . $this->getNomeFaseCompleto($datiDaAggiornare['fase_asta_corrente']) . '!';
            
        } elseif ($datiDaAggiornare['usa_ordine_chiamata'] &&
            ($impostazioni->usa_ordine_chiamata == false || is_null($impostazioni->prossimo_turno_chiamata_user_id))) {
                // Se l'ordine di chiamata viene attivato (o era attivo ma senza un prossimo turno definito)
                // e non c'è un reset, imposta il primo chiamante.
                $primoChiamante = User::where('is_admin', false)->orderBy('ordine_chiamata', 'asc')->orderBy('id', 'asc')->first();
                $datiDaAggiornare['prossimo_turno_chiamata_user_id'] = $primoChiamante ? $primoChiamante->id : null;
                Log::info("Ordine chiamata attivato o resettato. Prossimo chiamante ID: " . ($datiDaAggiornare['prossimo_turno_chiamata_user_id'] ?? 'Nessuno'));
        } elseif (!$datiDaAggiornare['usa_ordine_chiamata']) {
            $datiDaAggiornare['prossimo_turno_chiamata_user_id'] = null;
            Log::info("Ordine chiamata disattivato. Prossimo chiamante resettato.");
        }
        
        unset($datiDaAggiornare['reset_asta_completo']);
        $impostazioni->update($datiDaAggiornare);
        
        return redirect()->route('admin.settings.index')->with('success', $messaggioSuccesso);
    }
    
    
    // ---- Metodi precedentemente in AdminRosterController ----
    // Li includo qui assumendo che tu voglia consolidare.
    // Assicurati che le rotte in web.php puntino a AdminController::class per queste azioni.
    
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
                'P' => $impostazioniLega->num_portieri, 'D' => $impostazioniLega->num_difensori,
                'C' => $impostazioniLega->num_centrocampisti, 'A' => $impostazioniLega->num_attaccanti,
            ];
        }
        
        $squadreConDettagli = $squadre->map(function ($squadra) use ($limitiRuoli, $tagListaAttiva) {
            $rosa = $squadra->giocatoriAcquistati->filter(function ($acquisto) use ($tagListaAttiva) {
                return $tagListaAttiva ? (optional($acquisto->calciatore)->tag_lista_inserimento === $tagListaAttiva) : true;
            });
                $costoTotaleRosa = $rosa->sum('prezzo_acquisto');
                $conteggioRuoliEffettivo = collect();
                if ($rosa->isNotEmpty()) {
                    $idsCalciatoriFiltrati = $rosa->pluck('calciatore_id')->unique()->toArray();
                    if(!empty($idsCalciatoriFiltrati)){
                        $queryRuoli = Calciatore::whereIn('id', $idsCalciatoriFiltrati);
                        if ($tagListaAttiva) { $queryRuoli->where('tag_lista_inserimento', $tagListaAttiva); }
                        $conteggioRuoliEffettivo = $queryRuoli->pluck('ruolo')->countBy();
                    }
                }
                return [
                    'id' => $squadra->id, 'name' => $squadra->name, 'is_admin' => $squadra->is_admin,
                    'crediti_iniziali_squadra' => $squadra->crediti_iniziali_squadra,
                    'crediti_rimanenti' => $squadra->crediti_rimanenti,
                    'costo_totale_rosa' => $costoTotaleRosa, 'rosa_giocatori' => $rosa,
                    'conteggio_ruoli' => $conteggioRuoliEffettivo, 'limiti_ruoli' => $limitiRuoli,
                    'numero_giocatori_in_rosa' => $rosa->count()
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
        $ruoloDaFiltrare = in_array($faseAstaCorrente, $this->getFasiRuoloOrdinate()) ? $faseAstaCorrente : null;
        
        $ruoliDisponibiliQuery = Calciatore::where('attivo', true);
        if ($tagAttivo) { $ruoliDisponibiliQuery->where('tag_lista_inserimento', $tagAttivo); }
        $ruoliDisponibili = $ruoliDisponibiliQuery->select('ruolo')->distinct()->orderByRaw("FIELD(ruolo, 'P', 'D', 'C', 'A')")->pluck('ruolo');
        
        $idsCalciatoriGiaAcquistatiQuery = GiocatoreAcquistato::query();
        if($tagAttivo){
            $idsCalciatoriGiaAcquistatiQuery->whereHas('calciatore', function($q) use ($tagAttivo){
                $q->where('tag_lista_inserimento', $tagAttivo);
            });
        }
        $idsCalciatoriGiaAcquistati = $idsCalciatoriGiaAcquistatiQuery->pluck('calciatore_id')->all();
        
        return view('admin.giocatori.assegna', compact(
            'squadre', 'ruoliDisponibili', 'idsCalciatoriGiaAcquistati',
            'ruoloDaFiltrare', 'tagAttivo', 'faseAstaCorrente'
            ));
    }
    
    public function handleAssegna(Request $request)
    {
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $calciatoreDaAssegnare = Calciatore::find($request->input('calciatore_id'));
        
        if (!$calciatoreDaAssegnare) {
            Log::error("[Admin Assegna ERRORE] Calciatore con ID {$request->input('calciatore_id')} non trovato.");
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
            Log::info("=== INIZIO ASSEGNAZIONE MANUALE ADMIN ===");
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
            } else {
                Log::info("Assegnazione manuale in fase non di ruolo ({$faseAstaCorrenteDB}). Controllo ruolo vs fase saltato.");
            }
            
            // 2. Controllo se il calciatore è già stato acquistato (per il tag attivo da QUALSIASI squadra)
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
            
            // 3. Verifica Crediti Squadra per questo acquisto
            $creditiRimanentiPrimaDellAcquisto = $squadra->crediti_rimanenti; // Salva i crediti prima del potenziale acquisto
            if ($creditiRimanentiPrimaDellAcquisto < $prezzoAcquisto) {
                throw new Exception("La squadra {$squadra->name} non ha crediti sufficienti (Disponibili: {$creditiRimanentiPrimaDellAcquisto}, Richiesti per questo acquisto: {$prezzoAcquisto}).");
            }
            
            // 4. Calcolo Limiti e Conteggi Rosa ATTUALI (per il tag attivo)
            $rosaAttualeQuery = GiocatoreAcquistato::where('user_id', $squadra->id);
            if ($tagAttivo) {
                $rosaAttualeQuery->whereHas('calciatore', fn($q) => $q->where('tag_lista_inserimento', $tagAttivo));
            }
            $rosaAttuale = $rosaAttualeQuery->with('calciatore:id,ruolo')->get();
            $numGiocatoriAttualiInRosa = $rosaAttuale->count();
            
            $conteggioRuoliAttuali = $rosaAttuale->mapToGroups(function ($item) {
                if ($item->calciatore) { return [$item->calciatore->ruolo => 1]; }
                return [];
            })->map(function ($items) {
                return $items->count();
            });
                foreach (['P', 'D', 'C', 'A'] as $r) {
                    if (!$conteggioRuoliAttuali->has($r)) { $conteggioRuoliAttuali->put($r, 0); }
                }
                
                $limitePortieri = $impostazioniLega->num_portieri;
                $limiteDifensori = $impostazioniLega->num_difensori;
                $limiteCentrocampisti = $impostazioniLega->num_centrocampisti;
                $limiteAttaccanti = $impostazioniLega->num_attaccanti;
                $limiteGiocatoriTotaliSistema = $limitePortieri + $limiteDifensori + $limiteCentrocampisti + $limiteAttaccanti;
                
                // 5. Verifica Limite Giocatori Totali in Rosa (dopo questo acquisto)
                if (($numGiocatoriAttualiInRosa + 1) > $limiteGiocatoriTotaliSistema && $limiteGiocatoriTotaliSistema > 0) {
                    throw new Exception("La squadra {$squadra->name} supererebbe il limite massimo di {$limiteGiocatoriTotaliSistema} giocatori (attualmente ne ha {$numGiocatoriAttualiInRosa} per la lista '{$tagAttivo}').");
                }
                
                // 6. Verifica Limite Giocatori per Ruolo Specifico (dopo questo acquisto)
                $ruoloDelCalciatoreDaAssegnare = $calciatoreDaAssegnare->ruolo; // Usiamo variabile locale per chiarezza
                $limitePerRuoloSpecifico = match ($ruoloDelCalciatoreDaAssegnare) {
                    'P' => $limitePortieri, 'D' => $limiteDifensori,
                    'C' => $limiteCentrocampisti, 'A' => $limiteAttaccanti, default => 0,
                };
                if ($limitePerRuoloSpecifico > 0 && (($conteggioRuoliAttuali->get($ruoloDelCalciatoreDaAssegnare, 0) + 1) > $limitePerRuoloSpecifico)) {
                    throw new Exception("La squadra {$squadra->name} supererebbe il limite di {$limitePerRuoloSpecifico} giocatori per il ruolo {$ruoloDelCalciatoreDaAssegnare} (attualmente ne ha {$conteggioRuoliAttuali->get($ruoloDelCalciatoreDaAssegnare, 0)} per la lista '{$tagAttivo}').");
                }
                
                // 7. REGOLA DEL BANCO SALTATO
                $creditiCheResterebbero = $creditiRimanentiPrimaDellAcquisto - $prezzoAcquisto;
                $numGiocatoriInRosaDopoQuestoAcquisto = $numGiocatoriAttualiInRosa + 1;
                $slotAncoraDaRiempireEffettivamente = $limiteGiocatoriTotaliSistema - $numGiocatoriInRosaDopoQuestoAcquisto;
                
                Log::info("---------------- BANCO SALTATO CHECK (MANUALE) ----------------");
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
                Log::info("-----------------------------------------------------------------");
                
                if ($slotAncoraDaRiempireEffettivamente > 0 && $creditiCheResterebbero < $slotAncoraDaRiempireEffettivamente) {
                    Log::warning("BANCO SALTATO - ECCEZIONE LANCIATA! Per {$squadra->name}. Crediti post: {$creditiCheResterebbero}, Slot post: {$slotAncoraDaRiempireEffettivamente}");
                    throw new Exception("BANCO SALTATO! Per {$squadra->name}: dopo aver speso {$prezzoAcquisto} per {$calciatoreDaAssegnare->nome_completo}, resterebbero {$creditiCheResterebbero} crediti con {$slotAncoraDaRiempireEffettivamente} slot ancora da riempire (min. 1 crd l'uno).");
                }
                
                Log::info("[Admin Assegna] Tutti i controlli preliminari SUPERATI (inclusa regola banco saltato). Procedo con l'assegnazione.");
                GiocatoreAcquistato::create([
                    'user_id' => $squadra->id,
                    'calciatore_id' => $calciatoreDaAssegnare->id,
                    'prezzo_acquisto' => $prezzoAcquisto,
                    'ruolo_al_momento_acquisto' => $calciatoreDaAssegnare->ruolo,
                ]);
                
                $squadra->crediti_rimanenti = $creditiCheResterebbero; // Usa i crediti già calcolati per il check
                $squadra->save();
                
                DB::commit();
                
                Log::info("ASSEGNAZIONE MANUALE COMPLETATA: Admin (ID: ".Auth::id().") ha assegnato {$calciatoreDaAssegnare->nome_completo} a {$squadra->name} per {$prezzoAcquisto} crediti. Tag lista: {$tagAttivo}. Crediti rimanenti squadra: {$squadra->crediti_rimanenti}");
                return redirect()->route('admin.giocatori.assegna.show')->with('success', "Calciatore {$calciatoreDaAssegnare->nome_completo} assegnato a {$squadra->name} per {$prezzoAcquisto} crediti.");
                
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("ERRORE ASSEGNAZIONE MANUALE ADMIN per calciatore ID {$request->input('calciatore_id')} a squadra ID {$request->input('user_id')}. Errore: " . $e->getMessage() . " Riga: " . $e->getLine() . " File: " . $e->getFile());
            return redirect()->route('admin.giocatori.assegna.show')->withInput()->with('error', "Errore: " . $e->getMessage());
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
        if ($tagAttivo) { $query->where('tag_lista_inserimento', $tagAttivo); }
        
        $ruoloQuery = in_array($faseAstaCorrente, $this->getFasiRuoloOrdinate()) ? $faseAstaCorrente : ($ruoloFiltroSpecifico ?: null);
        if ($ruoloQuery) { $query->where('ruolo', $ruoloQuery); }
        
        if (!empty($searchTerm)) {
            $query->where(fn($q) => $q->whereRaw('LOWER(nome_completo) LIKE ?', ["%".strtolower($searchTerm)."%"])
                ->orWhereRaw('LOWER(squadra_serie_a) LIKE ?', ["%".strtolower($searchTerm)."%"]));
        }
        
        $idsAcquistatiQuery = GiocatoreAcquistato::query();
        if($tagAttivo){ $idsAcquistatiQuery->whereHas('calciatore', fn($q) => $q->where('tag_lista_inserimento', $tagAttivo)); }
        $idsCalciatoriAcquistati = $idsAcquistatiQuery->pluck('calciatore_id')->all();
        if (!empty($idsCalciatoriAcquistati)) { $query->whereNotIn('calciatori.id', $idsCalciatoriAcquistati); }
        
        $calciatori = $query->select('id', 'nome_completo', 'ruolo', 'squadra_serie_a', 'quotazione_iniziale')
        ->orderBy('nome_completo')->limit(20)->get();
        $formattedCalciatori = $calciatori->map(function ($c) use ($impostazioniLega) {
            $base = ($impostazioniLega->tipo_base_asta === 'credito_singolo') ? 1 : ($c->quotazione_iniziale > 0 ? $c->quotazione_iniziale : 1);
            return ['value' => $c->id, 'text' => "{$c->nome_completo} ({$c->ruolo} - {$c->squadra_serie_a} - Base: {$base})"];
        });
            return response()->json($formattedCalciatori);
    }
    
    public function gestioneChiamateAsta()
    {
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $chiamateInAttesa = ChiamataAsta::where('stato_chiamata', 'in_attesa_admin')
        ->with(['calciatore', 'utenteChiamante'])
        ->orderBy('created_at', 'asc')->get();
        $astaTapLiveEsistente = ChiamataAsta::where('stato_chiamata', 'in_asta_tap_live')->exists();
        return view('admin.asta.chiamate', compact('chiamateInAttesa', 'impostazioniLega', 'astaTapLiveEsistente'));
    }
    
    public function avviaAstaTap(Request $request, ChiamataAsta $chiamataAsta)
    {
        // Questa è la versione CORRETTA che abbiamo sviluppato insieme
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $calciatorePerAsta = $chiamataAsta->calciatore()->first();
        
        if (!$calciatorePerAsta) {
            Log::error("[Admin Avvio Asta TAP] Calciatore non trovato per Chiamata ID: {$chiamataAsta->id}");
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Errore: Calciatore non trovato per questa chiamata.');
        }
        
        Log::info("--- Admin Avvio Asta TAP (AdminController) per Chiamata ID: {$chiamataAsta->id} ---");
        Log::info("[Admin Avvio] Calciatore: " . $calciatorePerAsta->nome_completo . " (ID: {$calciatorePerAsta->id}), Quotazione Iniziale: " . $calciatorePerAsta->quotazione_iniziale);
        Log::info("[Admin Avvio] Impostazione Lega 'tipo_base_asta': " . $impostazioniLega->tipo_base_asta);
        Log::info("[Admin Avvio] Impostazione Lega 'asta_tap_approvazione_admin': " . ($impostazioniLega->asta_tap_approvazione_admin ? 'true' : 'false'));
        
        if ($impostazioniLega->modalita_asta !== 'tap') {
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'La modalità asta TAP non è attiva.');
        }
        if ($chiamataAsta->stato_chiamata !== 'in_attesa_admin') {
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "Questa chiamata per {$calciatorePerAsta->nome_completo} non è più in attesa (Stato: {$chiamataAsta->stato_chiamata}).");
        }
        if (ChiamataAsta::where('stato_chiamata', 'in_asta_tap_live')->where('id', '!=', $chiamataAsta->id)->exists()) {
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Altra asta TAP in corso.');
        }
        if ($calciatorePerAsta->ruolo !== $impostazioniLega->fase_asta_corrente) {
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "Ruolo giocatore ({$calciatorePerAsta->ruolo}) non corrisponde alla fase ({$impostazioniLega->fase_asta_corrente}).");
        }
        
        $quotazioneEffettivaCalciatore = ($calciatorePerAsta->quotazione_iniziale > 0 ? $calciatorePerAsta->quotazione_iniziale : 1);
        $baseAstaDaUsare = ($impostazioniLega->tipo_base_asta === 'credito_singolo') ? 1 : $quotazioneEffettivaCalciatore;
        Log::info("[Admin Avvio] Base Asta CALCOLATA da usare per {$calciatorePerAsta->nome_completo}: " . $baseAstaDaUsare);
        
        $utenteChiamante = $chiamataAsta->utenteChiamante()->first();
        if (!$utenteChiamante) {
            $chiamataAsta->update(['stato_chiamata' => 'annullata_admin', 'timestamp_fine_tap_prevista' => null]);
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "Utente chiamante non trovato. Chiamata per {$calciatorePerAsta->nome_completo} annullata.");
        }
        if ($utenteChiamante->crediti_rimanenti < $baseAstaDaUsare) {
            $chiamataAsta->update(['stato_chiamata' => 'annullata_admin', 'timestamp_fine_tap_prevista' => null]);
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "{$utenteChiamante->name} non ha crediti ({$utenteChiamante->crediti_rimanenti}) per base asta {$baseAstaDaUsare}. Chiamata annullata.");
        }
        
        $ruoloCalciatoreDaAvviare = $calciatorePerAsta->ruolo;
        $limitePerRuolo = match ($ruoloCalciatoreDaAvviare) {
            'P' => $impostazioniLega->num_portieri, 'D' => $impostazioniLega->num_difensori,
            'C' => $impostazioniLega->num_centrocampisti, 'A' => $impostazioniLega->num_attaccanti,
            default => 0,
        };
        if ($limitePerRuolo > 0) {
            $giocatoriAttualiNelRuoloChiamante = GiocatoreAcquistato::where('user_id', $utenteChiamante->id)
            ->whereHas('calciatore', function($query) use ($ruoloCalciatoreDaAvviare, $impostazioniLega, $chiamataAsta) {
                $query->where('ruolo', $ruoloCalciatoreDaAvviare);
                $tagAstaCorrente = $chiamataAsta->tag_lista_calciatori ?: $impostazioniLega->tag_lista_attiva;
                if ($tagAstaCorrente) { $query->where('tag_lista_inserimento', $tagAstaCorrente); }
            })->count();
            if ($giocatoriAttualiNelRuoloChiamante >= $limitePerRuolo) {
                $chiamataAsta->update(['stato_chiamata' => 'annullata_admin', 'timestamp_fine_tap_prevista' => null]);
                return redirect()->route('admin.asta.chiamate.gestione')->with('error', "L'utente {$utenteChiamante->name} ha già il limite per il ruolo {$ruoloCalciatoreDaAvviare}. Chiamata annullata.");
            }
        }
        
        try {
            DB::beginTransaction();
            $chiamataAsta->update([
                'stato_chiamata' => 'in_asta_tap_live',
                'prezzo_partenza_tap' => $baseAstaDaUsare,
                'prezzo_attuale_tap' => $baseAstaDaUsare,
                'miglior_offerente_tap_id' => $chiamataAsta->user_id_chiamante,
                'timestamp_fine_tap_prevista' => Carbon::now()->addSeconds($impostazioniLega->durata_countdown_secondi),
            ]);
            DB::commit();
            Log::info("Admin ".Auth::id()." avvia Asta TAP per {$calciatorePerAsta->nome_completo} (ID: {$chiamataAsta->id}). Base: {$baseAstaDaUsare}. Fine: {$chiamataAsta->timestamp_fine_tap_prevista}");
            return redirect()->route('admin.asta.chiamate.gestione')->with('success', "Asta TAP per {$calciatorePerAsta->nome_completo} avviata! Base: {$baseAstaDaUsare}.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Errore avvio asta TAP admin per Chiamata ID {$chiamataAsta->id}: " . $e->getMessage());
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Errore tecnico avvio asta: ' . $e->getMessage());
        }
    }
    
    public function annullaChiamataTap(Request $request, ChiamataAsta $chiamataAsta)
    {
        Log::info("Admin (ID: ".Auth::id().") sta tentando di annullare la chiamata/asta TAP ID: {$chiamataAsta->id} per il calciatore: {$chiamataAsta->calciatore->nome_completo}");
        if (!in_array($chiamataAsta->stato_chiamata, ['in_attesa_admin', 'in_asta_tap_live'])) {
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Chiamata/Asta non annullabile (stato: ' . $chiamataAsta->stato_chiamata . ').');
        }
        // $utenteChiamanteOriginaleId = $chiamataAsta->user_id_chiamante; // Per eventuale logica ordine chiamata
        try {
            DB::beginTransaction();
            $chiamataAsta->update([
                'stato_chiamata' => 'annullata_admin',
                'timestamp_fine_tap_prevista' => null,
                'miglior_offerente_tap_id' => null
            ]);
            DB::commit();
            Log::info("Chiamata/Asta TAP ID {$chiamataAsta->id} per {$chiamataAsta->calciatore->nome_completo} annullata.");
            return redirect()->route('admin.asta.chiamate.gestione')->with('success', "Chiamata/Asta per {$chiamataAsta->calciatore->nome_completo} annullata.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Errore annullamento chiamata/asta TAP ID {$chiamataAsta->id}: " . $e->getMessage());
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Errore tecnico annullamento.');
        }
    }
}