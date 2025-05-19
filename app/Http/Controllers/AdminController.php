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
use Carbon\Carbon; // Aggiunto per AdminRosterController@avviaAstaTap e annullaChiamataTap

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
        
        $conteggioRuoli = collect();
        if ($rosa->isNotEmpty()) {
            $idsCalciatoriInRosa = $rosa->pluck('calciatore_id')->unique()->toArray();
            if(!empty($idsCalciatoriInRosa)){
                $queryRuoli = Calciatore::whereIn('id', $idsCalciatoriInRosa);
                if ($tagListaAttiva) {
                    $queryRuoli->where('tag_lista_inserimento', $tagListaAttiva);
                }
                $conteggioRuoli = $queryRuoli->pluck('ruolo')->countBy();
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
            'conteggioRuoli', 'limitiRuoli', 'numeroGiocatoriInRosa', 'limiteGiocatoriTotaliInRosa'
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
    
    public function settings()
    {
        $impostazioni = ImpostazioneLega::firstOrFail();
        $fasiPossibili = [
            'PRE_ASTA' => 'Pre-Asta (Chiusa)', 'P' => 'Portieri', 'D' => 'Difensori',
            'C' => 'Centrocampisti', 'A' => 'Attaccanti', 'CONCLUSA' => 'Asta Conclusa'
        ];
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
        $fasiAstaCheRichiedonoLista = ['P', 'D', 'C', 'A'];
        $impostazioni = ImpostazioneLega::firstOrFail();
        $faseAstaPrecedenteDB = $impostazioni->fase_asta_corrente;
        $tagListaAttivaDB = $impostazioni->tag_lista_attiva;
        
        $validatedData = $request->validate([
            'fase_asta_corrente' => ['required', 'string', Rule::in(['PRE_ASTA', 'P', 'D', 'C', 'A', 'CONCLUSA'])],
            'tag_lista_attiva' => [
                Rule::requiredIf(fn() => in_array($request->input('fase_asta_corrente'), $fasiAstaCheRichiedonoLista)),
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
        
        // Controllo avanzamento fase
        $mappaRuoliPrecedenti = ['D' => 'P', 'C' => 'D', 'A' => 'C'];
        $ruoloDaCompletare = $mappaRuoliPrecedenti[$nuovaFaseAstaRichiesta] ?? null;
        
        if ($ruoloDaCompletare && $faseAstaPrecedenteDB === $ruoloDaCompletare) {
            Log::info("Controllo avanzamento fase da {$faseAstaPrecedenteDB} a {$nuovaFaseAstaRichiesta}. Controllo reparto {$ruoloDaCompletare} con tag '{$tagListaAttivaDB}'.");
            $limiteRuolo = match ($ruoloDaCompletare) {
                'P' => $impostazioni->num_portieri, 'D' => $impostazioni->num_difensori,
                'C' => $impostazioni->num_centrocampisti, default => 0,
            };
            
            if ($limiteRuolo > 0) {
                $squadreDaControllare = User::all();
                $squadreIncomplete = [];
                foreach ($squadreDaControllare as $squadra) {
                    $conteggioGiocatoriRuolo = GiocatoreAcquistato::where('user_id', $squadra->id)
                    ->whereHas('calciatore', function ($query) use ($ruoloDaCompletare, $tagListaAttivaDB) {
                        $query->where('ruolo', $ruoloDaCompletare);
                        if ($tagListaAttivaDB) {
                            $query->where('tag_lista_inserimento', $tagListaAttivaDB);
                        }
                    })->count();
                    if ($conteggioGiocatoriRuolo < $limiteRuolo) {
                        $squadreIncomplete[] = "{$squadra->name} ({$conteggioGiocatoriRuolo}/{$limiteRuolo} {$ruoloDaCompletare})";
                    }
                }
                if (!empty($squadreIncomplete)) {
                    $elenco = implode(', ', $squadreIncomplete);
                    return redirect()->route('admin.settings.index')->withInput()
                    ->with('error', "Impossibile cambiare fase. Squadre incomplete per reparto {$ruoloDaCompletare} (lista '{$tagListaAttivaDB}'): {$elenco}.");
                }
            }
        }
        
        $datiDaAggiornare = $validatedData;
        $datiDaAggiornare['asta_tap_approvazione_admin'] = $request->boolean('asta_tap_approvazione_admin');
        $datiDaAggiornare['usa_ordine_chiamata'] = $request->boolean('usa_ordine_chiamata');
        $messaggioSuccesso = 'Impostazioni aggiornate.';
        
        if ($request->boolean('reset_asta_completo')) {
            if (empty($validatedData['tag_lista_attiva'])) {
                return redirect()->route('admin.settings.index')->withInput()
                ->withErrors(['tag_lista_attiva' => 'Per il reset, selezionare un "Tag Lista Calciatori Attiva".']);
            }
            if ($datiDaAggiornare['fase_asta_corrente'] !== 'P') {
                $datiDaAggiornare['fase_asta_corrente'] = 'P';
            }
            $crediti = $validatedData['crediti_iniziali_lega'];
            User::query()->update(['crediti_iniziali_squadra' => $crediti, 'crediti_rimanenti' => $crediti]);
            GiocatoreAcquistato::query()->delete();
            ChiamataAsta::whereIn('stato_chiamata', ['in_attesa_admin', 'in_asta_tap_live'])
            ->update(['stato_chiamata' => 'annullata_admin', 'timestamp_fine_tap_prevista' => null]);
            
            if ($datiDaAggiornare['usa_ordine_chiamata']) {
                $primoChiamante = User::orderBy('ordine_chiamata', 'asc')
                ->orderBy('id', 'asc')
                ->first(); // Potrebbe essere admin se partecipa e ha ordine basso
                $datiDaAggiornare['prossimo_turno_chiamata_user_id'] = $primoChiamante ? $primoChiamante->id : null;
            } else {
                $datiDaAggiornare['prossimo_turno_chiamata_user_id'] = null;
            }
            
            Log::info("Admin (ID: ".Auth::id().") RESET ASTA COMPLETO. Tag: {$validatedData['tag_lista_attiva']}. Fase: P.");
            $messaggioSuccesso = 'ASTA RESETTATA: Crediti e rose azzerati, chiamate TAP annullate, fase impostata a Portieri!';
        }
        // Se non c'è reset, e si abilita l'ordine di chiamata per la prima volta o si cambia,
        // potrebbe essere necessario inizializzare prossimo_turno_chiamata_user_id se è null.
        elseif ($datiDaAggiornare['usa_ordine_chiamata'] && is_null($impostazioni->prossimo_turno_chiamata_user_id)) {
            $primoChiamante = User::orderBy('ordine_chiamata', 'asc')
            ->orderBy('id', 'asc')
            ->first();
            $datiDaAggiornare['prossimo_turno_chiamata_user_id'] = $primoChiamante ? $primoChiamante->id : null;
        } elseif (!$datiDaAggiornare['usa_ordine_chiamata']) {
            $datiDaAggiornare['prossimo_turno_chiamata_user_id'] = null; // Disabilita il turno
        }
        
        
        unset($datiDaAggiornare['reset_asta_completo']);
        $impostazioni->update($datiDaAggiornare);
        
        return redirect()->route('admin.settings.index')->with('success', $messaggioSuccesso);
    }
    
    // Metodi che erano in AdminRosterController
    public function visualizzaRoseSquadre()
    {
        $squadre = User::with(['giocatoriAcquistati.calciatore'])
        ->orderBy('name')
        ->get();
        
        $impostazioniLega = ImpostazioneLega::first(); // Può essere null se non ancora creata
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
        $ruoloDaFiltrare = in_array($faseAstaCorrente, ['P', 'D', 'C', 'A']) ? $faseAstaCorrente : null;
        
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
        $impostazioniLega = ImpostazioneLega::firstOrFail(); // Per tipo_base_asta e tag_lista_attiva
        $calciatore = Calciatore::find($request->input('calciatore_id'));
        $baseAstaMinima = 1;
        if ($calciatore && $impostazioniLega->tipo_base_asta === 'quotazione_iniziale') {
            $baseAstaMinima = $calciatore->quotazione_iniziale > 0 ? $calciatore->quotazione_iniziale : 1;
        }
        
        $validatedData = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'calciatore_id' => ['required', 'exists:calciatori,id'],
            'prezzo_acquisto' => ['required', 'integer', 'min:' . $baseAstaMinima],
        ]);
        
        $squadra = User::findOrFail($validatedData['user_id']);
        // $calciatore è già stato caricato per $baseAstaMinima, se esiste
        if(!$calciatore) $calciatore = Calciatore::findOrFail($validatedData['calciatore_id']);
        $prezzoAcquisto = (int)$validatedData['prezzo_acquisto'];
        
        $faseAstaCorrente = $impostazioniLega->fase_asta_corrente;
        $tagAttivo = $impostazioniLega->tag_lista_attiva;
        
        DB::beginTransaction();
        try {
            if (in_array($faseAstaCorrente, ['P', 'D', 'C', 'A'])) {
                if ($calciatore->ruolo !== $faseAstaCorrente) {
                    throw new Exception("Non puoi assegnare un {$calciatore->ruolo} durante la fase {$faseAstaCorrente}.");
                }
                // TODO: Aggiungere controllo completamento reparti precedenti per la squadra
            } elseif (in_array($faseAstaCorrente, ['PRE_ASTA', 'CONCLUSA'])) {
                Log::info("Admin assegna manualmente fuori fase: Fase {$faseAstaCorrente}. Admin ID: " . Auth::id());
            }
            
            $giaAcquistatoQuery = GiocatoreAcquistato::where('calciatore_id', $calciatore->id);
            if ($tagAttivo) {
                $giaAcquistatoQuery->whereHas('calciatore', function($q) use ($tagAttivo){
                    $q->where('tag_lista_inserimento', $tagAttivo);
                });
            }
            if ($giaAcquistatoQuery->exists()) {
                throw new Exception("{$calciatore->nome_completo} è già stato acquistato (tag: {$tagAttivo}).");
            }
            if ($squadra->crediti_rimanenti < $prezzoAcquisto) {
                throw new Exception("{$squadra->name} non ha crediti (Disponibili: {$squadra->crediti_rimanenti}, Richiesti: {$prezzoAcquisto}).");
            }
            
            // Conteggio e limiti rosa (come in gestisciRilancioTap)
            $rosaAttualeQuery = GiocatoreAcquistato::where('user_id', $squadra->id);
            if ($tagAttivo) { $rosaAttualeQuery->whereHas('calciatore', fn($q) => $q->where('tag_lista_inserimento', $tagAttivo)); }
            $rosaAttuale = $rosaAttualeQuery->get();
            $conteggioRuoli = collect();
            if ($rosaAttuale->isNotEmpty()) {
                $idsInRosa = $rosaAttuale->pluck('calciatore_id')->unique()->toArray();
                if(!empty($idsInRosa)){
                    $qRuoli = Calciatore::whereIn('id', $idsInRosa);
                    if($tagAttivo) $qRuoli->where('tag_lista_inserimento', $tagAttivo);
                    $conteggioRuoli = $qRuoli->pluck('ruolo')->countBy();
                }
            }
            $numGiocatoriAttuali = $rosaAttuale->count();
            $limiteSistema = array_sum([$impostazioniLega->num_portieri, $impostazioniLega->num_difensori, $impostazioniLega->num_centrocampisti, $impostazioniLega->num_attaccanti]);
            $limiteRuoloSpec = match ($calciatore->ruolo) { 'P' => $impostazioniLega->num_portieri, 'D' => $impostazioniLega->num_difensori, 'C' => $impostazioniLega->num_centrocampisti, 'A' => $impostazioniLega->num_attaccanti, default => 0 };
            
            if (($numGiocatoriAttuali + 1) > $limiteSistema && $limiteSistema > 0) throw new Exception("Superato limite rosa ({$limiteSistema}).");
            if ((($conteggioRuoli[$calciatore->ruolo] ?? 0) + 1) > $limiteRuoloSpec && $limiteRuoloSpec > 0) throw new Exception("Superato limite per ruolo {$calciatore->ruolo} ({$limiteRuoloSpec}).");
            
            $creditiDopo = $squadra->crediti_rimanenti - $prezzoAcquisto;
            $slotDopo = $limiteSistema - ($numGiocatoriAttuali + 1);
            if ($slotDopo > 0 && $creditiDopo < $slotDopo) throw new Exception("Crediti insufficienti per completare la rosa dopo questo acquisto.");
            
            GiocatoreAcquistato::create([
                'user_id' => $squadra->id, 'calciatore_id' => $calciatore->id,
                'prezzo_acquisto' => $prezzoAcquisto, 'ruolo_al_momento_acquisto' => $calciatore->ruolo,
            ]);
            $squadra->crediti_rimanenti -= $prezzoAcquisto;
            $squadra->save();
            DB::commit();
            
            // TODO: ORDINE CHIAMATA: if ($impostazioniLega->usa_ordine_chiamata && $impostazioniLega->modalita_asta === 'tap') { $impostazioniLega->avanzaTurnoChiamata($squadra->id); }
            // L'avanzamento turno per assegnazione manuale è da decidere se influisce sul turno TAP
            
            return redirect()->route('admin.giocatori.assegna.show')->with('success', "{$calciatore->nome_completo} assegnato a {$squadra->name} per {$prezzoAcquisto} cr.");
        } catch (Exception $e) {
            DB::rollBack();
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
        if ($tagAttivo) { $query->where('tag_lista_inserimento', $tagAttivo); }
        $ruoloQuery = in_array($faseAstaCorrente, ['P', 'D', 'C', 'A']) ? $faseAstaCorrente : ($ruoloFiltroSpecifico ?: null);
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
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        if ($impostazioniLega->modalita_asta !== 'tap' || !$impostazioniLega->asta_tap_approvazione_admin) {
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Modalità asta o approvazione non corrette.');
        }
        if ($chiamataAsta->stato_chiamata !== 'in_attesa_admin') {
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "Chiamata per {$chiamataAsta->calciatore->nome_completo} non più in attesa.");
        }
        if (ChiamataAsta::where('stato_chiamata', 'in_asta_tap_live')->where('id', '!=', $chiamataAsta->id)->exists()) {
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Altra asta TAP in corso.');
        }
        if ($chiamataAsta->calciatore->ruolo !== $impostazioniLega->fase_asta_corrente) {
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "Ruolo giocatore ({$chiamataAsta->calciatore->ruolo}) non corrisponde alla fase ({$impostazioniLega->fase_asta_corrente}).");
        }
        
        $utenteChiamante = User::find($chiamataAsta->user_id_chiamante);
        $baseAstaEffettiva = ($impostazioniLega->tipo_base_asta === 'credito_singolo') ? 1 : (optional($chiamataAsta->calciatore)->quotazione_iniziale > 0 ? $chiamataAsta->calciatore->quotazione_iniziale : 1);
        
        if (!$utenteChiamante) {
            $chiamataAsta->update(['stato_chiamata' => 'annullata_admin', 'timestamp_fine_tap_prevista' => null]);
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "Utente chiamante non trovato. Chiamata per {$chiamataAsta->calciatore->nome_completo} annullata.");
        }
        if ($utenteChiamante->crediti_rimanenti < $baseAstaEffettiva) {
            $chiamataAsta->update(['stato_chiamata' => 'annullata_admin', 'timestamp_fine_tap_prevista' => null]);
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "{$utenteChiamante->name} non ha crediti ({$utenteChiamante->crediti_rimanenti}) per base asta {$baseAstaEffettiva} per {$chiamataAsta->calciatore->nome_completo}. Chiamata annullata.");
        }
        
        try {
            DB::beginTransaction();
            $chiamataAsta->update([
                'stato_chiamata' => 'in_asta_tap_live', 'prezzo_partenza_tap' => $baseAstaEffettiva,
                'prezzo_attuale_tap' => $baseAstaEffettiva, 'miglior_offerente_tap_id' => $chiamataAsta->user_id_chiamante,
                'timestamp_fine_tap_prevista' => Carbon::now()->addSeconds($impostazioniLega->durata_countdown_secondi),
            ]);
            DB::commit();
            Log::info("Admin ".Auth::id()." avvia Asta TAP per {$chiamataAsta->calciatore->nome_completo} (ID: {$chiamataAsta->id}). Base: {$baseAstaEffettiva}. Fine: {$chiamataAsta->timestamp_fine_tap_prevista}");
            // TODO: ORDINE CHIAMATA: if ($impostazioniLega->usa_ordine_chiamata) { $impostazioniLega->avanzaTurnoChiamata($chiamataAsta->user_id_chiamante); }
            return redirect()->route('admin.asta.chiamate.gestione')->with('success', "Asta TAP per {$chiamataAsta->calciatore->nome_completo} avviata! Base: {$baseAstaEffettiva}.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Errore avvio asta TAP admin per Chiamata ID {$chiamataAsta->id}: " . $e->getMessage());
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Errore tecnico avvio asta: ' . $e->getMessage());
        }
    }
    
    public function annullaChiamataTap(Request $request, ChiamataAsta $chiamataAsta)
    {
        if (!in_array($chiamataAsta->stato_chiamata, ['in_attesa_admin', 'in_asta_tap_live'])) {
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Chiamata/Asta non annullabile (stato: ' . $chiamataAsta->stato_chiamata . ').');
        }
        $utenteChiamanteOriginaleId = $chiamataAsta->user_id_chiamante;
        try {
            DB::beginTransaction();
            $chiamataAsta->update([
                'stato_chiamata' => 'annullata_admin',
                'timestamp_fine_tap_prevista' => null,
                'miglior_offerente_tap_id' => null
            ]);
            DB::commit();
            Log::info("Admin ".Auth::id()." annulla Chiamata/Asta TAP ID {$chiamataAsta->id} per {$chiamataAsta->calciatore->nome_completo}.");
            // TODO: ORDINE CHIAMATA:
            // $impostazioniLega = ImpostazioneLega::firstOrFail();
            // if ($impostazioniLega->usa_ordine_chiamata) {
            //     $impostazioniLega->avanzaTurnoChiamata($utenteChiamanteOriginaleId);
            // }
            return redirect()->route('admin.asta.chiamate.gestione')->with('success', "Chiamata/Asta per {$chiamataAsta->calciatore->nome_completo} annullata.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Errore annullamento chiamata/asta TAP ID {$chiamataAsta->id}: " . $e->getMessage());
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Errore tecnico annullamento.');
        }
    }
}