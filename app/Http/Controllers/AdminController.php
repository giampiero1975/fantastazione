<?php

namespace App\Http\Controllers;

use App\Models\ImpostazioneLega;
use App\Models\User;
use App\Models\Calciatore; // Se usato direttamente qui
use App\Models\GiocatoreAcquistato; // Se usato direttamente qui
use App\Models\ChiamataAsta; // Se usato direttamente qui
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash; // Già presente o da aggiungere
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules; // <-- AGGIUNGI QUESTA RIGA
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
        $impostazioni = ImpostazioneLega::first();
        $ordinePersonalizzato = $impostazioni ? $impostazioni->ordine_squadre_personalizzato : [];
        
        $query = User::query();
        
        // Se esiste un ordine personalizzato, lo usiamo per ordinare
        if (!empty($ordinePersonalizzato)) {
            // Converte l'array di ID in una stringa per la clausola FIELD di MySQL
            $ordineIdsString = implode(',', array_map('intval', $ordinePersonalizzato));
            $query->orderByRaw("FIELD(id, $ordineIdsString)");
        }
        
        // Aggiungiamo un ordinamento per nome come fallback
        $query->orderBy('id', 'asc');
        
        $utenti = $query->paginate(15);
        
        return view('admin.utenti.index', compact('utenti'));
    }
    public function editUser(User $user)
    {
        return view('admin.utenti.edit', compact('user'));
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
            'phone_number' => $validatedData['phone_number']
        ];
        
        if ($request->filled('password')) {
            $userData['password'] = bcrypt($request->password);
        }
        
        $user->update($userData);
        return redirect()->route('admin.utenti.index')->with('success', 'Utente aggiornato con successo.');
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
            'CONCLUSA' => 'Asta Conclusa (Mercato 1)',
            'SVINCOLI_STAGIONALI' => 'Sostituzioni Stagionali (Max ' . (ImpostazioneLega::first()->max_sostituzioni_stagionali ?? 5) . ')',
            'ASTA_RIPARAZIONE' => 'Asta di Riparazione',
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
        $mappaNomi = $this->getFasiDropdownOpzioni(); // Usa il metodo helper per coerenza
        return $mappaNomi[$codiceFase] ?? $codiceFase;
    }
    
    public function settings()
    {
        // Carica le impostazioni, creandole con valori di default se non esistono.
        // È importante avere valori di default sensati qui se ImpostazioneLega::firstOrFail() non è adatto.
        $impostazioni = ImpostazioneLega::firstOrCreate([], [
            'fase_asta_corrente' => 'PRE_ASTA',
            'crediti_iniziali_lega' => 500,
            'num_portieri' => 3,
            'num_difensori' => 8,
            'num_centrocampisti' => 8,
            'num_attaccanti' => 6,
            'tag_lista_attiva' => null,
            'modalita_asta' => 'voce', // o 'tap' a seconda del default preferito
            'durata_countdown_secondi' => 60,
            'asta_tap_approvazione_admin' => true,
            'usa_ordine_chiamata' => false,
            'prossimo_turno_chiamata_user_id' => null,
            'tipo_base_asta' => 'quotazione_iniziale',
            'ordine_squadre_personalizzato' => null, // Default a null per l'array JSON
            'max_sostituzioni_stagionali' => 5,
            'percentuale_crediti_svincolo_riparazione' => 50,
        ]);
        
        
        $fasiPossibili = $this->getFasiDropdownOpzioni(); // Metodo helper da definire o assicurarsi esista
        $tagsCalciatoriDisponibili = Calciatore::select('tag_lista_inserimento')
        ->whereNotNull('tag_lista_inserimento')->where('tag_lista_inserimento', '!=', '')
        ->distinct()->orderBy('tag_lista_inserimento', 'desc')->pluck('tag_lista_inserimento');
        
        // Utenti per il dropdown "Prossima Squadra a Chiamare"
        $utentiPerSelezioneProssimo = User::orderBy('name')->get(['id', 'name']);
        
        // Squadre da mostrare nella sezione per definire l'ordine di chiamata.
        // Ora l'ordine è determinato da `ordine_squadre_personalizzato`, quindi recuperiamo tutte le squadre
        // e poi la vista si occuperà di mostrare l'ordine attuale basato su quell'array.
        $tutteLeSquadre = User::orderBy('id', 'asc')->get(['id', 'name', 'is_admin']);
        $squadrePerOrdinamento = collect(); // Inizializza come collection vuota
        
        $ordineSalvato = $impostazioni->ordine_squadre_personalizzato; // Questo è un array di ID utente
        
        if (is_array($ordineSalvato) && !empty($ordineSalvato)) {
            // Crea una mappa ID => posizione per un ordinamento efficiente
            $mappaPosizioni = array_flip($ordineSalvato);
            $squadrePerOrdinamento = $tutteLeSquadre->sortBy(function ($squadra) use ($mappaPosizioni) {
                return $mappaPosizioni[$squadra->id] ?? PHP_INT_MAX;
            })->values(); // values() per re-indicizzare la collection
        } else {
            // Se non c'è un ordine personalizzato salvato, o non è attivo `usa_ordine_chiamata`,
            // passa semplicemente tutte le squadre ordinate per nome.
            // La vista gestirà se mostrare o meno gli input per l'ordine.
            $squadrePerOrdinamento = $tutteLeSquadre;
        }
        // Log::debug('[Settings View] Squadre per ordinamento:', $squadrePerOrdinamento->pluck('name', 'id')->toArray());
        // Log::debug('[Settings View] Ordine personalizzato salvato:', $ordineSalvato);
        
        
        return view('admin.impostazioni.index', compact(
            'impostazioni',
            'fasiPossibili',
            'tagsCalciatoriDisponibili',
            'utentiPerSelezioneProssimo',
            'squadrePerOrdinamento' // Passa la collection ordinata o quella di default
            ));
    }
    
    public function updateSettings(Request $request)
    {
        Log::debug('Dati Richiesta in updateSettings:', $request->all());
        
        $impostazioni = ImpostazioneLega::firstOrFail();
        $faseAttualeNelDB = $impostazioni->fase_asta_corrente;
        $tagListaAttivaDB = $impostazioni->tag_lista_attiva;
        
        $fasiPossibiliOpzioni = $this->getFasiDropdownOpzioni();
        $isResetCompletoRichiesto = $request->boolean('reset_asta_completo');
        $faseAstaRichiestaDalForm = $request->input('fase_asta_corrente');
        
        $condizioneRequiredCreditiLimiti = $isResetCompletoRichiesto ||
        ($faseAttualeNelDB === 'PRE_ASTA' && $faseAstaRichiestaDalForm === 'PRE_ASTA');
        
        Log::debug("[UpdateSettings Validazione] Reset richiesto: " . ($isResetCompletoRichiesto ? 'Sì' : 'No') .
            ". Fase DB: " . $faseAttualeNelDB .
            ". Fase Form: " . $faseAstaRichiestaDalForm .
            ". Condizione Required Crediti/Limiti: " . ($condizioneRequiredCreditiLimiti ? 'Sì' : 'No'));
        
        $regoleValidazione = [
            'fase_asta_corrente' => ['required', 'string', Rule::in(array_keys($fasiPossibiliOpzioni))],
            'tag_lista_attiva' => [
                Rule::requiredIf(fn () => in_array($faseAstaRichiestaDalForm, $this->getFasiRuoloOrdinate()) || $isResetCompletoRichiesto),
                'nullable', 'string', 'max:255'
            ],
            'modalita_asta' => ['required', 'string', Rule::in(['voce', 'tap'])],
            'durata_countdown_secondi' => ['required', 'integer', 'min:10', 'max:300'],
            'asta_tap_approvazione_admin' => ['required', 'boolean'],
            'usa_ordine_chiamata' => ['required', 'boolean'],
            'crediti_iniziali_lega' => [
                Rule::requiredIf($condizioneRequiredCreditiLimiti),
                'nullable', 'integer', 'min:1', 'max:9999'
            ],
            'num_portieri' => [
                Rule::requiredIf($condizioneRequiredCreditiLimiti),
                'nullable', 'integer', 'min:0', 'max:25'
            ],
            'num_difensori' => [
                Rule::requiredIf($condizioneRequiredCreditiLimiti),
                'nullable', 'integer', 'min:0', 'max:25'
            ],
            'num_centrocampisti' => [
                Rule::requiredIf($condizioneRequiredCreditiLimiti),
                'nullable', 'integer', 'min:0', 'max:25'
            ],
            'num_attaccanti' => [
                Rule::requiredIf($condizioneRequiredCreditiLimiti),
                'nullable', 'integer', 'min:0', 'max:25'
            ],
            'tipo_base_asta' => ['required', Rule::in(['quotazione_iniziale', 'credito_singolo'])],
            'ordine_squadre_personalizzato_input' => ['nullable', 'array'],
            'ordine_squadre_personalizzato_input.*' => ['nullable', 'integer', 'min:1'],
            'prossimo_turno_chiamata_user_id' => ['nullable', 'exists:users,id'],
            'max_sostituzioni_stagionali' => ['required', 'integer', 'min:0', 'max:99'],
            'percentuale_crediti_svincolo_riparazione' => ['required', 'integer', 'min:0', 'max:100'],
        ];
        
        $validatedData = $request->validate($regoleValidazione);
        Log::debug('[UpdateSettings] Dati validati: ', $validatedData);
        
        $datiDaAggiornare = collect($validatedData)->except(['ordine_squadre_personalizzato_input', 'reset_asta_completo'])->all();
        $tagListaPerOperazioni = $datiDaAggiornare['tag_lista_attiva'] ?? $tagListaAttivaDB;
        
        if ($request->boolean('usa_ordine_chiamata') && $request->has('ordine_squadre_personalizzato_input')) {
            $inputOrdine = $request->input('ordine_squadre_personalizzato_input');
            $squadreOrdinateId = [];
            if (is_array($inputOrdine)) {
                $squadreConOrdine = [];
                foreach ($inputOrdine as $userId => $ordineNumerico) {
                    if ($ordineNumerico !== null && $ordineNumerico !== '' && filter_var($ordineNumerico, FILTER_VALIDATE_INT) !== false) {
                        $squadreConOrdine[(int)$userId] = (int)$ordineNumerico;
                    }
                }
                asort($squadreConOrdine);
                $squadreOrdinateId = array_keys($squadreConOrdine);
                // $squadreOrdinateId = array_map('intval', $squadreOrdinateId); // Già int da (int)$userId
            }
            $datiDaAggiornare['ordine_squadre_personalizzato'] = $squadreOrdinateId;
            Log::info('[UpdateSettings Ordine] Salvataggio ordine squadre personalizzato: ' . json_encode($squadreOrdinateId));
        } elseif (!$request->boolean('usa_ordine_chiamata')) {
            $datiDaAggiornare['ordine_squadre_personalizzato'] = null;
            $datiDaAggiornare['prossimo_turno_chiamata_user_id'] = null;
            Log::info('[UpdateSettings Ordine] Ordine chiamata disattivato. ordine_squadre_personalizzato e prossimo_turno_chiamata_user_id resettati.');
        } else {
            // Se 'usa_ordine_chiamata' è true ma non ci sono input per l'ordine (es. nessuna squadra da ordinare),
            // mantieni l'ordine esistente o imposta a null se non c'era.
            $datiDaAggiornare['ordine_squadre_personalizzato'] = $impostazioni->ordine_squadre_personalizzato ?? null;
            Log::info('[UpdateSettings Ordine] usa_ordine_chiamata attivo, ma nessun input `ordine_squadre_personalizzato_input` ricevuto. Ordine mantenuto/resettato a: ' . json_encode($datiDaAggiornare['ordine_squadre_personalizzato']));
        }
        
        if (!$condizioneRequiredCreditiLimiti && !$isResetCompletoRichiesto) {
            $campiDaPreservare = ['crediti_iniziali_lega', 'num_portieri', 'num_difensori', 'num_centrocampisti', 'num_attaccanti'];
            foreach ($campiDaPreservare as $campo) {
                if (!isset($validatedData[$campo]) || is_null($validatedData[$campo])) {
                    $datiDaAggiornare[$campo] = $impostazioni->$campo;
                }
            }
            Log::info("[UpdateSettings] Valori per crediti/limiti rosa preservati dal DB.");
        }
        
        if (!$isResetCompletoRichiesto && $faseAstaRichiestaDalForm !== $faseAttualeNelDB) {
            $fasiRuoloOrdinate = $this->getFasiRuoloOrdinate();
            $indiceAttualeDB = array_search($faseAttualeNelDB, $fasiRuoloOrdinate);
            $indiceNuovoForm = array_search($faseAstaRichiestaDalForm, $fasiRuoloOrdinate);
            
            if ($faseAttualeNelDB === 'PRE_ASTA') {
                $fasiPostPreAstaPermesse = ['P', 'CONCLUSA', 'SVINCOLI_STAGIONALI', 'ASTA_RIPARAZIONE'];
                if (!in_array($faseAstaRichiestaDalForm, $fasiPostPreAstaPermesse)) {
                    return redirect()->route('admin.impostazioni.index')->withInput()
                    ->with('error', "Dalla fase PRE_ASTA puoi passare solo alla fase Portieri (P) o a fasi di mercato successive.");
                }
            } elseif ($indiceAttualeDB !== false) {
                $ruoloDaAverCompletato = $fasiRuoloOrdinate[$indiceAttualeDB];
                $avanzamentoLogico = ($indiceNuovoForm !== false && $indiceNuovoForm === $indiceAttualeDB + 1) ||
                ($ruoloDaAverCompletato === 'A' && $faseAstaRichiestaDalForm === 'CONCLUSA');
                
                if (!$avanzamentoLogico && !in_array($faseAstaRichiestaDalForm, ['CONCLUSA', 'SVINCOLI_STAGIONALI', 'ASTA_RIPARAZIONE'])) {
                    return redirect()->route('admin.impostazioni.index')->withInput()
                    ->with('error', "Cambio fase non consentito. Da una fase d'asta per ruolo ({$this->getNomeFaseCompleto($faseAttualeNelDB)}) puoi avanzare al ruolo successivo, a CONCLUSA (da A), o a SVINCOLI STAGIONALI/ASTA_RIPARAZIONE.");
                }
                
                if ($avanzamentoLogico) {
                    $limiteRuolo = match ($ruoloDaAverCompletato) {
                        'P' => $datiDaAggiornare['num_portieri'] ?? $impostazioni->num_portieri,
                        'D' => $datiDaAggiornare['num_difensori'] ?? $impostazioni->num_difensori,
                        'C' => $datiDaAggiornare['num_centrocampisti'] ?? $impostazioni->num_centrocampisti,
                        'A' => $datiDaAggiornare['num_attaccanti'] ?? $impostazioni->num_attaccanti,
                        default => 0,
                    };
                    
                    if ($limiteRuolo > 0) {
                        $squadrePartecipanti = User::where('is_admin', false)->get(); // Considera solo i non admin per i check di completamento
                        $squadreIncomplete = [];
                        foreach ($squadrePartecipanti as $squadra) {
                            $countQuery = GiocatoreAcquistato::where('user_id', $squadra->id)
                            ->whereHas('calciatore', function ($query) use ($ruoloDaAverCompletato, $tagListaPerOperazioni) {
                                $query->where('ruolo', $ruoloDaAverCompletato);
                                if ($tagListaPerOperazioni) {
                                    $query->where('tag_lista_inserimento', $tagListaPerOperazioni);
                                }
                            });
                                $giocatoriNelRuolo = $countQuery->count();
                                if ($giocatoriNelRuolo < $limiteRuolo) {
                                    $squadreIncomplete[] = $squadra->name . " (ha {$giocatoriNelRuolo}/{$limiteRuolo} " . $this->getNomeFaseCompleto($ruoloDaAverCompletato) . ")";
                                }
                        }
                        if (!empty($squadreIncomplete)) {
                            $messaggioErrore = "Impossibile avanzare alla fase {$this->getNomeFaseCompleto($faseAstaRichiestaDalForm)}. Le seguenti squadre non hanno completato il reparto {$this->getNomeFaseCompleto($ruoloDaAverCompletato)} (per il tag '{$tagListaPerOperazioni}'): " . implode('; ', $squadreIncomplete);
                            return redirect()->route('admin.impostazioni.index')->withInput()->with('error', $messaggioErrore);
                        }
                    }
                }
            }
        }
        
        
        $messaggioSuccesso = 'Impostazioni aggiornate con successo.';
        $impostazioni->fill($datiDaAggiornare); // Applica tutti i dati validati e processati
        
        DB::beginTransaction();
        try {
            if ($isResetCompletoRichiesto) {
                if (empty($tagListaPerOperazioni)) {
                    DB::rollBack();
                    return redirect()->route('admin.impostazioni.index')->withInput()
                    ->withErrors(['tag_lista_attiva' => 'Per il reset completo, è obbligatorio selezionare un "Tag Lista Calciatori Attiva".']);
                }
                if (!in_array($impostazioni->fase_asta_corrente, ['PRE_ASTA', 'P'])) {
                    $impostazioni->fase_asta_corrente = 'P';
                }
                
                $crediti = $impostazioni->crediti_iniziali_lega;
                User::query()->update([
                    'crediti_iniziali_squadra' => $crediti,
                    'crediti_rimanenti' => $crediti,
                    'sostituzioni_stagionali_usate' => 0
                ]);
                GiocatoreAcquistato::whereHas('calciatore', function ($query) use ($tagListaPerOperazioni) {
                    $query->where('tag_lista_inserimento', $tagListaPerOperazioni);
                })->delete();
                ChiamataAsta::where('tag_lista_calciatori', $tagListaPerOperazioni)->delete();
                
                $impostazioni->prossimo_turno_chiamata_user_id = null;
                $messaggioSuccesso = 'ASTA RESETTATA (per il tag '.$tagListaPerOperazioni.'): Crediti, rose, sostituzioni usate azzerate. Chiamate TAP cancellate. Fase: ' . $this->getNomeFaseCompleto($impostazioni->fase_asta_corrente) . '.';
                Log::info("RESET ASTA ESEGUITO: " . $messaggioSuccesso);
            }
            
            // Logica per il prossimo turno di chiamata
            if ($impostazioni->usa_ordine_chiamata) {
                $prossimoTurnoDalForm = $request->input('prossimo_turno_chiamata_user_id'); // Valore originale dal form
                $prossimoTurnoOriginaleDB = $impostazioni->getOriginal('prossimo_turno_chiamata_user_id');
                $usaOrdineChiamataOriginaleDB = $impostazioni->getOriginal('usa_ordine_chiamata');
                $ordinePersonalizzatoOriginaleDB = $impostazioni->getOriginal('ordine_squadre_personalizzato');
                $ordinePersonalizzatoSalvato = $impostazioni->ordine_squadre_personalizzato; // Valore già aggiornato da fill()
                
                $ordineCambiatoEffettivamente = ($ordinePersonalizzatoSalvato !== $ordinePersonalizzatoOriginaleDB);
                
                $condizionePerRicalcolo =
                ($usaOrdineChiamataOriginaleDB == false && $impostazioni->usa_ordine_chiamata == true) ||
                ($faseAttualeNelDB !== $impostazioni->fase_asta_corrente && $impostazioni->usa_ordine_chiamata) ||
                ($ordineCambiatoEffettivamente && $impostazioni->usa_ordine_chiamata) ||
                (is_null($prossimoTurnoOriginaleDB) && $impostazioni->usa_ordine_chiamata && !$prossimoTurnoDalForm);
                
                if ($prossimoTurnoDalForm && $prossimoTurnoDalForm != $prossimoTurnoOriginaleDB) {
                    $impostazioni->prossimo_turno_chiamata_user_id = $prossimoTurnoDalForm; // Accetta la forzatura dall'admin
                    Log::info("[UpdateSettings Ordine] Prossimo turno FORZATO dall'admin a ID: " . $impostazioni->prossimo_turno_chiamata_user_id);
                    // Verifica se l'utente forzato è idoneo
                    $utenteForzato = User::find($impostazioni->prossimo_turno_chiamata_user_id);
                    if (!$utenteForzato ||
                        !$impostazioni->utentePuoChiamare($utenteForzato) ||
                        (is_array($ordinePersonalizzatoSalvato) && !in_array($utenteForzato->id, $ordinePersonalizzatoSalvato)) ) {
                            Log::info("[UpdateSettings Ordine] Utente forzato ID {$impostazioni->prossimo_turno_chiamata_user_id} non valido/idoneo o non nell'ordine. Ricalcolo partendo da lui.");
                            $impostazioni->avanzaTurnoChiamata($impostazioni->prossimo_turno_chiamata_user_id, true);
                        }
                } elseif ($condizionePerRicalcolo) {
                    Log::info("[UpdateSettings Ordine] Ricalcolo/Inizializzazione del prossimo turno (forzaReinizializzazione=true).");
                    $impostazioni->avanzaTurnoChiamata(null, true);
                }
                // Se nessuna condizione di ricalcolo o forzatura, $impostazioni->prossimo_turno_chiamata_user_id è già quello dal form
                // o quello originale se il campo form era vuoto.
            } else {
                $impostazioni->prossimo_turno_chiamata_user_id = null;
                Log::info("[UpdateSettings Ordine] Ordine chiamata disattivato. Prossimo resettato a null.");
            }
            
            // Fine logica prossimo turno
            Log::debug('[UpdateSettings PRE-SAVE] Dati in $impostazioni->getAttributes(): ', $impostazioni->getAttributes());
            Log::debug('[UpdateSettings PRE-SAVE] Dati in $impostazioni->getDirty(): ', $impostazioni->getDirty());
            $impostazioni->save();
            
            // Se è stato fatto un reset e l'ordine è attivo, assicurati che il primo turno sia calcolato.
            if ($isResetCompletoRichiesto && $impostazioni->usa_ordine_chiamata) {
                $valorePrecedente = $impostazioni->prossimo_turno_chiamata_user_id;
                $impostazioni->avanzaTurnoChiamata(null, true);
                if ($impostazioni->prossimo_turno_chiamata_user_id !== $valorePrecedente) {
                    $impostazioni->saveQuietly();
                }
                Log::info("[UpdateSettings Ordine] Reset completo: Prossimo turno post-salvataggio (ID: " . ($impostazioni->prossimo_turno_chiamata_user_id ?? 'Nessuno') . ")");
            }
            
            
            DB::commit();
            return redirect()->route('admin.impostazioni.index')->with('success', $messaggioSuccesso);
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Errore aggiornamento impostazioni: " . $e->getMessage() . " Trace: " . $e->getTraceAsString());
            return redirect()->route('admin.impostazioni.index')->withInput()->with('error', 'Errore durante il salvataggio: ' . $e->getMessage());
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
            return view('admin.rose.index', compact('squadreConDettagli'));
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
    
    public function handlePlayerAssignment(Request $request) // Nome corretto come da tue indicazioni
    {
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $calciatoreDaAssegnare = Calciatore::find($request->input('calciatore_id'));
        
        if (!$calciatoreDaAssegnare) {
            Log::error("[AdminController@handleAssegna ERRORE] Calciatore con ID {$request->input('calciatore_id')} non trovato.");
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
            Log::info("=== INIZIO ASSEGNAZIONE MANUALE ADMIN (AdminController@handleAssegna) ===");
            Log::info("Admin ID: " . Auth::id());
            Log::info("Squadra Target: {$squadra->name} (ID: {$squadra->id})");
            Log::info("Calciatore da Assegnare: {$calciatoreDaAssegnare->nome_completo} (ID: {$calciatoreDaAssegnare->id}), Ruolo: {$calciatoreDaAssegnare->ruolo}");
            Log::info("Prezzo Acquisto Proposto: {$prezzoAcquisto}");
            Log::info("Tag Lista Attiva: {$tagAttivo}");
            Log::info("Fase Asta Corrente nel DB: {$faseAstaCorrenteDB}");
            
            // 1. Controllo Fase Asta e Ruolo Calciatore (se l'asta è in una fase di ruolo P,D,C,A)
            if (in_array($faseAstaCorrenteDB, $this->getFasiRuoloOrdinate())) {
                if ($calciatoreDaAssegnare->ruolo !== $faseAstaCorrenteDB) {
                    throw new Exception("Non puoi assegnare un giocatore di ruolo {$calciatoreDaAssegnare->ruolo} durante la fase dedicata al ruolo {$this->getNomeFaseCompleto($faseAstaCorrenteDB)} ({$faseAstaCorrenteDB}).");
                }
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
            $creditiRimanentiPrimaDellAcquisto = $squadra->crediti_rimanenti;
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
                foreach (['P', 'D', 'C', 'A'] as $rVal) { // Variabile $r rinominata per evitare conflitti
                    if (!$conteggioRuoliAttuali->has($rVal)) { $conteggioRuoliAttuali->put($rVal, 0); }
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
                $ruoloDelCalciatoreDaAssegnare = $calciatoreDaAssegnare->ruolo;
                $limitePerRuoloSpecifico = match ($ruoloDelCalciatoreDaAssegnare) {
                    'P' => $limitePortieri,
                    'D' => $limiteDifensori,
                    'C' => $limiteCentrocampisti,
                    'A' => $limiteAttaccanti,
                    default => 0,
                };
                if ($limitePerRuoloSpecifico > 0 && (($conteggioRuoliAttuali->get($ruoloDelCalciatoreDaAssegnare, 0) + 1) > $limitePerRuoloSpecifico)) {
                    Log::debug("Conteggio Ruoli per Squadra ID {$squadra->id} prima di assegnare {$calciatoreDaAssegnare->nome_completo}:", $conteggioRuoliAttuali->all());
                    Log::debug("Tentativo di aggiungere ruolo: {$ruoloDelCalciatoreDaAssegnare}. Giocatori attuali nel ruolo: " . $conteggioRuoliAttuali->get($ruoloDelCalciatoreDaAssegnare, 0));
                    Log::debug("Limite per ruolo {$ruoloDelCalciatoreDaAssegnare}: {$limitePerRuoloSpecifico}");
                    throw new Exception("La squadra {$squadra->name} supererebbe il limite di {$limitePerRuoloSpecifico} giocatori per il ruolo {$ruoloDelCalciatoreDaAssegnare} (attualmente ne ha {$conteggioRuoliAttuali->get($ruoloDelCalciatoreDaAssegnare, 0)} per la lista '{$tagAttivo}').");
                }
                
                // 7. REGOLA DEL BANCO SALTATO
                $creditiCheResterebbero = $creditiRimanentiPrimaDellAcquisto - $prezzoAcquisto;
                $numGiocatoriInRosaDopoQuestoAcquisto = $numGiocatoriAttualiInRosa + 1;
                $slotAncoraDaRiempireEffettivamente = $limiteGiocatoriTotaliSistema - $numGiocatoriInRosaDopoQuestoAcquisto;
                
                Log::info("[Banco Saltato Check - Assegnazione Manuale] Squadra: {$squadra->name}, Crediti Post: {$creditiCheResterebbero}, Slot da Riempire Post: {$slotAncoraDaRiempireEffettivamente}");
                if ($slotAncoraDaRiempireEffettivamente > 0 && $creditiCheResterebbero < $slotAncoraDaRiempireEffettivamente) {
                    throw new Exception("BANCO SALTATO! Per {$squadra->name}: dopo aver speso {$prezzoAcquisto} per {$calciatoreDaAssegnare->nome_completo}, resterebbero {$creditiCheResterebbero} crediti con {$slotAncoraDaRiempireEffettivamente} slot ancora da riempire (min. 1 crd l'uno).");
                }
                
                // Se tutti i controlli sono superati, procedi con l'assegnazione
                GiocatoreAcquistato::create([
                    'user_id' => $squadra->id,
                    'calciatore_id' => $calciatoreDaAssegnare->id,
                    'prezzo_acquisto' => $prezzoAcquisto,
                    'ruolo_al_momento_acquisto' => $calciatoreDaAssegnare->ruolo,
                ]);
                
                $squadra->crediti_rimanenti = $creditiCheResterebbero;
                $squadra->save();
                
                // Avanzamento turno se l'ordine di chiamata è attivo
                $idUtenteDelTurnoCorrente = null;
                if ($impostazioniLega->usa_ordine_chiamata && $impostazioniLega->prossimo_turno_chiamata_user_id) {
                    $idUtenteDelTurnoCorrente = $impostazioniLega->prossimo_turno_chiamata_user_id;
                    Log::info("[HandleAssegnaManuale] Ordine chiamata attivo. ID utente di turno (prima di avanzare): {$idUtenteDelTurnoCorrente}");
                }
                
                if ($impostazioniLega->usa_ordine_chiamata && $idUtenteDelTurnoCorrente) {
                    Log::info("[HandleAssegnaManuale] Avanzamento turno perché 'usa_ordine_chiamata' è attivo. Turno consumato da User ID: {$idUtenteDelTurnoCorrente}");
                    $impostazioniLega->avanzaTurnoChiamata($idUtenteDelTurnoCorrente);
                } elseif ($impostazioniLega->usa_ordine_chiamata && !$idUtenteDelTurnoCorrente) {
                    Log::info("[HandleAssegnaManuale] Ordine chiamata attivo, ma nessun utente era specificato come prossimo. Tento inizializzazione turno.");
                    $impostazioniLega->avanzaTurnoChiamata(null);
                }
                
                DB::commit();
                
                Log::info("ASSEGNAZIONE MANUALE COMPLETATA: Admin (ID: ".Auth::id().") ha assegnato {$calciatoreDaAssegnare->nome_completo} a {$squadra->name} per {$prezzoAcquisto} crediti. Tag lista: {$tagAttivo}. Crediti rimanenti squadra: {$squadra->crediti_rimanenti}");
                return redirect()->route('admin.giocatori.assegna.show')->with('success', "Calciatore {$calciatoreDaAssegnare->nome_completo} assegnato a {$squadra->name} per {$prezzoAcquisto} crediti.");
                
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("ERRORE ASSEGNAZIONE MANUALE ADMIN (AdminController@handleAssegna) per calciatore ID {$request->input('calciatore_id')} a squadra ID {$request->input('user_id')}. Errore: " . $e->getMessage(), ['exception' => $e]);
            return redirect()->route('admin.giocatori.assegna.show')->withInput()->with('error', "Errore: " . $e->getMessage());
        }
    }
    
    public function autocompletePlayers(Request $request)
    {
        Log::info('--- [ADMIN AUTOCOMPLETE GIOCATORI] --- Richiesta ricevuta.');
        $searchTerm = $request->input('q', '');
        $ruoloFiltroSpecifico = $request->input('ruolo', '');
        Log::info("[Admin Autocomplete] Input: q='{$searchTerm}', ruoloFiltroSpecifico='{$ruoloFiltroSpecifico}'");
        
        $impostazioniLega = ImpostazioneLega::first();
        if (!$impostazioniLega) {
            Log::error('[Admin Autocomplete] ERRORE CRITICO: ImpostazioniLega non trovate. Impossibile procedere.');
            return response()->json([]); // Restituisce array vuoto se non ci sono impostazioni
        }
        Log::info("[Admin Autocomplete] ImpostazioniLega caricate. ID: {$impostazioniLega->id}");
        
        $faseAstaCorrente = $impostazioniLega->fase_asta_corrente;
        $tagAttivo = $impostazioniLega->tag_lista_attiva;
        Log::info("[Admin Autocomplete] Fase Asta: {$faseAstaCorrente}, Tag Attivo: {$tagAttivo}, Tipo Base Asta: {$impostazioniLega->tipo_base_asta}");
        
        $query = Calciatore::where('attivo', true);
        Log::info('[Admin Autocomplete] Query base: Calciatore::where(attivo, true)');
        
        if ($tagAttivo) {
            $query->where('tag_lista_inserimento', $tagAttivo);
            Log::info("[Admin Autocomplete] Filtro applicato: tag_lista_inserimento = {$tagAttivo}");
        } else {
            Log::warning('[Admin Autocomplete] Nessun tag_lista_attiva definito nelle impostazioni lega. L\'autocomplete potrebbe mostrare giocatori da tutte le liste.');
        }
        
        $ruoloQueryDefinitivo = null;
        if (in_array($faseAstaCorrente, $this->getFasiRuoloOrdinate())) {
            $ruoloQueryDefinitivo = $faseAstaCorrente;
            Log::info("[Admin Autocomplete] Ruolo Query determinato dalla Fase Asta Corrente: {$ruoloQueryDefinitivo}");
        } elseif ($ruoloFiltroSpecifico) {
            $ruoloQueryDefinitivo = $ruoloFiltroSpecifico;
            Log::info("[Admin Autocomplete] Ruolo Query determinato dal parametro 'ruolo': {$ruoloQueryDefinitivo}");
        } else {
            Log::info("[Admin Autocomplete] Nessun filtro per ruolo specifico applicato (né da fase asta né da parametro).");
        }
        
        if ($ruoloQueryDefinitivo) {
            $query->where('ruolo', $ruoloQueryDefinitivo);
            Log::info("[Admin Autocomplete] Filtro applicato: ruolo = {$ruoloQueryDefinitivo}");
        }
        
        if (!empty($searchTerm)) {
            $query->where(function ($q) use ($searchTerm) {
                $searchTermLower = strtolower($searchTerm);
                // Considera di aggiungere un escape per caratteri speciali se $searchTermLower viene da input utente non filtrato
                $q->whereRaw('LOWER(nome_completo) LIKE ?', ["%{$searchTermLower}%"])
                ->orWhereRaw('LOWER(squadra_serie_a) LIKE ?', ["%{$searchTermLower}%"]);
            });
                Log::info("[Admin Autocomplete] Filtro applicato per searchTerm: {$searchTerm}");
        } else {
            Log::info("[Admin Autocomplete] Nessun searchTerm fornito.");
        }
        
        $idsCalciatoriAcquistatiQuery = GiocatoreAcquistato::query();
        if ($tagAttivo) {
            $idsCalciatoriAcquistatiQuery->whereHas('calciatore', function($q) use ($tagAttivo) {
                $q->where('tag_lista_inserimento', $tagAttivo);
            });
        }
        $idsCalciatoriAcquistati = $idsCalciatoriAcquistatiQuery->pluck('calciatore_id')->all();
        
        if (!empty($idsCalciatoriAcquistati)) {
            $query->whereNotIn('calciatori.id', $idsCalciatoriAcquistati);
            Log::info('[Admin Autocomplete] Esclusi ID Giocatori acquistati (per tag attivo): ' . implode(', ', $idsCalciatoriAcquistati));
        } else {
            Log::info('[Admin Autocomplete] Nessun giocatore acquistato da escludere (per il tag attivo, se presente).');
        }
        
        $calciatori = $query->select('calciatori.id', 'nome_completo', 'ruolo', 'squadra_serie_a', 'quotazione_iniziale')
        ->orderBy('nome_completo')
        ->limit(25) // Limite per i risultati dell'autocomplete
        ->get();
        
        Log::info("[Admin Autocomplete] SQL Eseguito: " . $query->toSql(), $query->getBindings());
        Log::info("[Admin Autocomplete] Numero calciatori trovati dalla query: " . $calciatori->count());
        
        $formattedCalciatori = $calciatori->map(function ($calciatore) use ($impostazioniLega) {
            $baseAsta = 1; // Default
            if ($impostazioniLega->tipo_base_asta === 'quotazione_iniziale') {
                $baseAsta = $calciatore->quotazione_iniziale > 0 ? $calciatore->quotazione_iniziale : 1;
            }
            return [
                'value' => $calciatore->id,
                'text' => "{$calciatore->nome_completo} ({$calciatore->ruolo} - {$calciatore->squadra_serie_a} - Qt.I: {$baseAsta})"
                ];
        });
            
            if ($calciatori->isEmpty() && !empty($searchTerm)) {
                Log::info("[Admin Autocomplete] Nessun calciatore trovato per '{$searchTerm}' con ruolo '{$ruoloQueryDefinitivo}'.");
            } elseif ($calciatori->isEmpty()) {
                Log::info("[Admin Autocomplete] Nessun calciatore trovato per ruolo '{$ruoloQueryDefinitivo}' (senza searchTerm).");
            }
            
            Log::info("[Admin Autocomplete] Invio risposta JSON. Numero risultati formattati: " . $formattedCalciatori->count());
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
        $calciatorePerAsta = $chiamataAsta->calciatore()->first();
        
        if (!$calciatorePerAsta) {
            Log::error("[Admin Avvio Asta TAP] Calciatore non trovato per Chiamata ID: {$chiamataAsta->id}");
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Errore: Calciatore non trovato per questa chiamata.');
        }
        
        Log::info("--- Admin Avvio Asta TAP (AdminController) per Chiamata ID: {$chiamataAsta->id} ---"); // Modificato da AdminRosterController
        Log::info("[Admin Avvio] Calciatore: " . $calciatorePerAsta->nome_completo . " (ID: {$calciatorePerAsta->id}), Quotazione Iniziale: " . $calciatorePerAsta->quotazione_iniziale);
        Log::info("[Admin Avvio] Impostazione Lega 'tipo_base_asta': " . $impostazioniLega->tipo_base_asta);
        Log::info("[Admin Avvio] Impostazione Lega 'asta_tap_approvazione_admin': " . ($impostazioniLega->asta_tap_approvazione_admin ? 'true' : 'false'));
        
        // 1. VALIDAZIONI GENERALI SULL'ASTA E SULLE IMPOSTAZIONI (come prima)
        if ($impostazioniLega->modalita_asta !== 'tap') {
            Log::warning("[Admin Avvio Asta TAP] Tentativo di avvio asta TAP (ID: {$chiamataAsta->id}) ma la modalità non è TAP. Modalità attuale: {$impostazioniLega->modalita_asta}");
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'La modalità asta TAP non è attiva.');
        }
        
        if ($chiamataAsta->stato_chiamata !== 'in_attesa_admin') {
            Log::warning("[Admin Avvio Asta TAP] Tentativo di avvio per chiamata ID {$chiamataAsta->id} non più in stato 'in_attesa_admin'. Stato attuale: {$chiamataAsta->stato_chiamata}");
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "Questa chiamata per {$calciatorePerAsta->nome_completo} non è più in attesa di approvazione (Stato: {$chiamataAsta->stato_chiamata}).");
        }
        
        $queryAstaLiveEsistente = ChiamataAsta::where('stato_chiamata', 'in_asta_tap_live')->where('id', '!=', $chiamataAsta->id);
        if($impostazioniLega->tag_lista_attiva){
            $queryAstaLiveEsistente->where('tag_lista_calciatori', $impostazioniLega->tag_lista_attiva);
        }
        if ($queryAstaLiveEsistente->exists()) {
            Log::warning("[Admin Avvio Asta TAP] Tentativo di avvio per chiamata ID {$chiamataAsta->id} ma esiste già un'altra asta TAP live per il tag '{$impostazioniLega->tag_lista_attiva}'.");
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Esiste già un\'altra asta TAP in corso per questa lista. Annullala o attendi la sua conclusione.');
        }
        
        if ($calciatorePerAsta->ruolo !== $impostazioniLega->fase_asta_corrente) {
            Log::warning("[Admin Avvio Asta TAP] Tentativo di avvio per chiamata ID {$chiamataAsta->id} ma il ruolo ({$calciatorePerAsta->ruolo}) non corrisponde alla fase ({$impostazioniLega->fase_asta_corrente}).");
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "Il ruolo del giocatore ({$calciatorePerAsta->ruolo}) non corrisponde alla fase d'asta attuale ({$impostazioniLega->fase_asta_corrente}).");
        }
        
        // 2. CALCOLO BASE D'ASTA CORRETTA (come prima)
        $quotazioneEffettivaCalciatore = ($calciatorePerAsta->quotazione_iniziale > 0 ? $calciatorePerAsta->quotazione_iniziale : 1);
        $baseAstaDaUsare = ($impostazioniLega->tipo_base_asta === 'credito_singolo') ? 1 : $quotazioneEffettivaCalciatore;
        Log::info("[Admin Avvio] Base Asta CALCOLATA da usare per {$calciatorePerAsta->nome_completo}: " . $baseAstaDaUsare);
        
        // 3. VALIDAZIONI SULL'UTENTE CHIAMANTE ORIGINALE (come prima)
        $utenteChiamante = $chiamataAsta->utenteChiamante()->first();
        if (!$utenteChiamante) {
            // ... (gestione errore come prima)
            Log::error("[Admin Avvio Asta TAP] Utente chiamante non trovato per Chiamata ID {$chiamataAsta->id}. Annullamento chiamata.");
            $chiamataAsta->update(['stato_chiamata' => 'annullata_admin', 'timestamp_fine_tap_prevista' => null]);
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "Utente chiamante originale non trovato. La chiamata per {$calciatorePerAsta->nome_completo} è stata annullata.");
        }
        if ($utenteChiamante->crediti_rimanenti < $baseAstaDaUsare) {
            // ... (gestione errore come prima)
            Log::warning("[Admin Avvio Asta TAP] Utente chiamante {$utenteChiamante->name} (ID: {$utenteChiamante->id}) non ha crediti sufficienti (Rimanenti: {$utenteChiamante->crediti_rimanenti}) per base asta {$baseAstaDaUsare} per {$calciatorePerAsta->nome_completo}. Chiamata ID {$chiamataAsta->id} annullata.");
            $chiamataAsta->update(['stato_chiamata' => 'annullata_admin', 'timestamp_fine_tap_prevista' => null]);
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', "L'utente {$utenteChiamante->name} non ha crediti sufficienti ({$utenteChiamante->crediti_rimanenti}) per la base d'asta di {$baseAstaDaUsare} per {$calciatorePerAsta->nome_completo}. La chiamata è stata annullata.");
        }
        // ... (validazione limiti rosa chiamante come prima) ...
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
                'prezzo_partenza_tap' => $baseAstaDaUsare,
                'prezzo_attuale_tap' => $baseAstaDaUsare,
                'miglior_offerente_tap_id' => $chiamataAsta->user_id_chiamante,
                'timestamp_fine_tap_prevista' => Carbon::now()->addSeconds($impostazioniLega->durata_countdown_secondi),
            ]);
            DB::commit();
            
            Log::info("Admin (ID: ".Auth::id().") ha avviato Asta TAP per {$calciatorePerAsta->nome_completo} (Chiamata ID: {$chiamataAsta->id}). Prezzo Partenza IMPOSTATO: {$baseAstaDaUsare}. Fine: {$chiamataAsta->timestamp_fine_tap_prevista}");
            
            // Avanzamento turno se l'ordine di chiamata è attivo (questa logica è già presente in ImpostazioneLega@avanzaTurnoChiamata,
            // che viene chiamata DOPO la finalizzazione dell'asta. Qui l'asta è appena INIZIATA, il turno del chiamante è "in uso").
            // Non è necessario avanzare il turno qui; verrà avanzato alla conclusione dell'asta.
            
            // === MODIFICA REDIRECT ===
            $messaggioSuccesso = "Asta TAP per {$this->sanitizeForJsonResponse($calciatorePerAsta->nome_completo)} avviata! Base: {$baseAstaDaUsare}.";
            // Aggiungi un link per tornare alla gestione chiamate nel messaggio flash, usando HTML
            // Nota: per usare HTML nel messaggio flash, la vista deve stamparlo con {!! !!} invece di {{ }}
            // Ma per sicurezza e semplicità, lo mettiamo come testo separato.
            // In alternativa, crea un messaggio flash con più dati:
            // session()->flash('status_con_link', [
            //     'message' => $messaggioSuccesso,
            //     'link_text' => 'Torna a Gestione Chiamate',
            //     'link_url' => route('admin.asta.chiamate.gestione')
            // ]);
            // e gestiscilo nella vista.
            
            // Per ora, reindirizziamo direttamente a asta.live
            return redirect()->route('asta.live')->with('success', $messaggioSuccesso);
            // Se preferisci dare la scelta all'admin:
            // return redirect()->route('admin.asta.chiamate.gestione')
            //                ->with('success', $messaggioSuccesso . ' Visualizza l\'asta live o continua a gestire le chiamate.');
            
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Errore durante l'avvio dell'asta TAP da parte dell'admin per Chiamata ID {$chiamataAsta->id}: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Errore tecnico durante l\'avvio dell\'asta TAP: ' . $this->sanitizeForJsonResponse($e->getMessage()));
        }
    }
    
    private function sanitizeForJsonResponse($string)
    {
        if ($string === null) return null;
        $string = (string)$string;
        return mb_convert_encoding($string, 'UTF-8', 'UTF-8');
    }
    
    public function annullaChiamataTap(Request $request, ChiamataAsta $chiamataAsta)
    {
        Log::info("Admin (ID: ".Auth::id().") sta tentando di annullare la chiamata/asta TAP ID: {$chiamataAsta->id} per il calciatore: {$chiamataAsta->calciatore->nome_completo}");
        
        $statoPrecedente = $chiamataAsta->stato_chiamata; // Salva lo stato prima di modificarlo
        $idUtenteChiamanteOriginario = $chiamataAsta->user_id_chiamante; // Salva l'ID del chiamante originario dell'asta
        
        if (!in_array($statoPrecedente, ['in_attesa_admin', 'in_asta_tap_live'])) {
            Log::warning("Tentativo di annullare chiamata/asta TAP ID {$chiamataAsta->id} che non è in stato 'in_attesa_admin' o 'in_asta_tap_live'. Stato attuale: {$statoPrecedente}");
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Questa chiamata/asta non può essere annullata perché il suo stato attuale è: ' . $statoPrecedente);
        }
        
        try {
            DB::beginTransaction();
            
            $chiamataAsta->stato_chiamata = 'annullata_admin';
            $chiamataAsta->timestamp_fine_tap_prevista = null; // Resetta il timestamp di fine
            $chiamataAsta->miglior_offerente_tap_id = null; // Resetta miglior offerente se l'asta viene annullata
            // Potresti voler resettare anche prezzo_attuale_tap a prezzo_partenza_tap o a null, a seconda delle tue regole
            // $chiamataAsta->prezzo_attuale_tap = $chiamataAsta->prezzo_partenza_tap;
            $chiamataAsta->save();
            
            // === PUNTO CHIAVE: AVANZA IL TURNO SOLO SE L'ASTA ERA LIVE ===
            if ($statoPrecedente === 'in_asta_tap_live') {
                $impostazioniLega = ImpostazioneLega::firstOrFail(); // Carica le impostazioni
                if ($impostazioniLega->usa_ordine_chiamata && $idUtenteChiamanteOriginario) {
                    Log::info("[AnnullaChiamataTAP ID:{$chiamataAsta->id}] Asta era LIVE. Avanzamento turno per utente chiamante originario ID: {$idUtenteChiamanteOriginario}");
                    $impostazioniLega->avanzaTurnoChiamata($idUtenteChiamanteOriginario);
                }
            } else {
                // Se era solo 'in_attesa_admin' e viene annullata, il turno dell'utente che ha chiamato NON viene consumato/avanzato.
                Log::info("[AnnullaChiamataTAP ID:{$chiamataAsta->id}] Asta era 'in_attesa_admin'. Il turno NON avanza.");
            }
            // =========================================================
            
            DB::commit();
            
            Log::info("Chiamata/Asta TAP ID {$chiamataAsta->id} per {$chiamataAsta->calciatore->nome_completo} annullata con successo dall'admin (ID: ".Auth::id()."). Stato precedente: " . $statoPrecedente);
            return redirect()->route('admin.asta.chiamate.gestione')->with('success', "Chiamata/Asta per {$chiamataAsta->calciatore->nome_completo} annullata con successo.");
            
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Errore durante l'annullamento della chiamata/asta TAP ID {$chiamataAsta->id}: " . $e->getMessage());
            return redirect()->route('admin.asta.chiamate.gestione')->with('error', 'Errore tecnico durante l\'annullamento della chiamata/asta.');
        }
    }
    
    public function createUserForm()
    {
        $impostazioniLega = ImpostazioneLega::first();
        // Prendi i crediti di default dalle impostazioni della lega, o un valore di fallback
        $defaultCrediti = $impostazioniLega ? $impostazioniLega->crediti_iniziali_lega : 500;
        
        return view('admin.utenti.create', compact('defaultCrediti'));
    }
    
    /**
     * Salva una nuova squadra/utente creata dall'admin.
     */
    public function storeUser(Request $request)
    {
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'nome_proprietario' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)],
            'phone_number' => ['nullable', 'string', 'max:20'], // Aggiungi validazioni più specifiche se necessario (es. regex)
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'crediti_iniziali_squadra' => ['required', 'integer', 'min:0'],
            // Per 'ordine_chiamata', l'unicità è importante solo se il valore è fornito.
            // Se è null, non deve essere unico.
            //'ordine_chiamata' => ['nullable', 'integer', 'min:1', Rule::unique(User::class, 'ordine_chiamata')->where(function ($query) use ($request) {
            //return $request->input('ordine_chiamata') !== null && $request->input('ordine_chiamata') !== '';
            //})],
            ]);
        
        try {
            $user = User::create([
                'name' => $validatedData['name'],
                'nome_proprietario' => $validatedData['nome_proprietario'],
                'email' => $validatedData['email'],
                'phone_number' => $validatedData['phone_number'],
                'password' => Hash::make($validatedData['password']),
                'crediti_iniziali_squadra' => $validatedData['crediti_iniziali_squadra'],
                'crediti_rimanenti' => $validatedData['crediti_iniziali_squadra'], // Inizialmente i crediti rimanenti sono uguali a quelli iniziali
                //'ordine_chiamata' => ($validatedData['ordine_chiamata'] === '' || $validatedData['ordine_chiamata'] === null) ? null : (int)$validatedData['ordine_chiamata'],
                'is_admin' => false, // Le squadre create da qui di default NON sono admin
                'email_verified_at' => now(), // Opzionale: puoi decidere se verificarle subito o meno
            ]);
            
            // Se l'ordine di chiamata è attivo e il nuovo utente ha un ordine,
            // potrebbe essere necessario ricalcolare/verificare il 'prossimo_turno_chiamata_user_id'
            // Ma questo potrebbe essere gestito meglio quando si modificano le impostazioni lega.
            // Per ora, ci concentriamo sulla creazione.
            
            Log::info("Admin (ID: ".Auth::id().") ha creato una nuova squadra: {$user->name} (ID: {$user->id})");
            return redirect()->route('admin.utenti.index')->with('success', 'Nuova squadra/utente creata con successo.');
            
        } catch (Exception $e) {
            Log::error("Errore durante la creazione della squadra da parte dell'admin: " . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Errore durante la creazione della squadra: ' . $e->getMessage());
        }
    }
    
    // app/Http/Controllers/AdminController.php
    // app/Http/Controllers/AdminController.php
    public function showSostituzioneForm(Request $request)
    {
        $squadre = User::orderBy('name')->get();
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $tagAttivo = $impostazioniLega->tag_lista_attiva;
        $faseAstaCorrentePHP = $impostazioniLega->fase_asta_corrente; // Variabile PHP
        
        $sostituzioneAbilitataPHP = false;
        $prezzoNuovoAcquistoBloccatoPHP = false; // Variabile PHP
        $messaggioBloccoPHP = null;
        $modalitaSostituzionePHP = null;
        
        $fasiSostituzionePermessa = ['CONCLUSA', 'SVINCOLI_STAGIONALI'];
        
        if (in_array($faseAstaCorrentePHP, $fasiSostituzionePermessa)) {
            $sostituzioneAbilitataPHP = true;
            if ($faseAstaCorrentePHP === 'CONCLUSA') {
                $prezzoNuovoAcquistoBloccatoPHP = true;
                $modalitaSostituzionePHP = 'CONCLUSA';
            } elseif ($faseAstaCorrentePHP === 'SVINCOLI_STAGIONALI') {
                $prezzoNuovoAcquistoBloccatoPHP = true;
                $modalitaSostituzionePHP = 'STAGIONALE';
            }
        } else {
            $messaggioBloccoPHP = "La funzionalità di sostituzione diretta non è attiva per la fase asta corrente ({$faseAstaCorrentePHP}).";
        }
        
        $calciatoriDisponibili = collect(); // Inizializza
        if ($sostituzioneAbilitataPHP) {
            // === INIZIO LOGICA AGGIUNTA PER CARICARE I CALCIATORI DISPONIBILI ===
            $queryCalciatori = Calciatore::where('attivo', true);
            if ($tagAttivo) {
                $queryCalciatori->where('tag_lista_inserimento', $tagAttivo);
            }
            
            // Ottieni gli ID dei calciatori già acquistati da QUALSIASI squadra per il tag attivo
            $idsCalciatoriGiaAcquistatiThisTagQuery = GiocatoreAcquistato::query();
            if ($tagAttivo) {
                $idsCalciatoriGiaAcquistatiThisTagQuery->whereHas('calciatore', function($q) use ($tagAttivo) {
                    $q->where('tag_lista_inserimento', $tagAttivo);
                });
            }
            $idsCalciatoriGiaAcquistatiThisTag = $idsCalciatoriGiaAcquistatiThisTagQuery->pluck('calciatore_id')->all();
            
            if (!empty($idsCalciatoriGiaAcquistatiThisTag)) {
                $queryCalciatori->whereNotIn('id', $idsCalciatoriGiaAcquistatiThisTag);
            }
            
            // Seleziona solo i campi necessari per ridurre il payload
            $calciatoriDisponibili = $queryCalciatori
            ->select('id', 'nome_completo', 'ruolo', 'squadra_serie_a', 'quotazione_iniziale')
            ->orderBy('ruolo') // Opzionale: pre-ordina per ruolo
            ->orderBy('nome_completo')
            ->get();
            // === FINE LOGICA AGGIUNTA ===
        }
        
        //dd(session()->all());
        return view('admin.rose.sostituisci', [
            'squadre' => $squadre,
            'impostazioniLega' => $impostazioniLega,
            'tagAttivo' => $tagAttivo,
            'calciatoriDisponibili' => $calciatoriDisponibili, // Ora $calciatoriDisponibili è popolato
            'faseAstaCorrente' => $faseAstaCorrentePHP,
            'sostituzioneAbilitata' => $sostituzioneAbilitataPHP,
            'prezzoInBloccato' => $prezzoNuovoAcquistoBloccatoPHP,
            'messaggioBlocco' => $messaggioBloccoPHP,
            'modalitaSostituzione' => $modalitaSostituzionePHP
        ]);
    }
    
    public function handleSostituzione(Request $request)
    {
        $impostazioniLega = ImpostazioneLega::firstOrFail();
        $faseAstaCorrente = $impostazioniLega->fase_asta_corrente;
        $tagAttivo = $impostazioniLega->tag_lista_attiva;
        $modalitaSostituzione = null;
        
        if ($faseAstaCorrente === 'CONCLUSA') $modalitaSostituzione = 'CONCLUSA';
        if ($faseAstaCorrente === 'SVINCOLI_STAGIONALI') $modalitaSostituzione = 'STAGIONALE';
        
        $fasiPermesse = ['CONCLUSA', 'SVINCOLI_STAGIONALI'];
        if (!in_array($faseAstaCorrente, $fasiPermesse)) {
            return redirect()->route('admin.rose.sostituisci.show')->with('error', "Le sostituzioni dirette sono permesse solo in fasi specifiche. Fase attuale: {$faseAstaCorrente}.");
        }
        
        $validated = $request->validate([
            'user_id' => ['required', 'exists:users,id'],
            'giocatore_out_id' => ['required', 'exists:giocatori_acquistati,id'],
            'calciatore_in_id_hidden_alpine' => ['required', 'exists:calciatori,id'],
            'prezzo_nuovo_acquisto' => ['required', 'integer', 'min:0'],
        ]);
        
        $squadra = User::findOrFail($validated['user_id']);
        $acquistoDaRimuovere = GiocatoreAcquistato::with('calciatore')->findOrFail($validated['giocatore_out_id']);
        $calciatoreDaSvincolare = $acquistoDaRimuovere->calciatore;
        $calciatoreDaAcquistare = Calciatore::findOrFail($validated['calciatore_in_id_hidden_alpine']);
        $prezzoNuovoAcquistoInput = (int)$validated['prezzo_nuovo_acquisto'];
        $prezzoGiocatoreSvincolatoOriginale = $acquistoDaRimuovere->prezzo_acquisto;
        
        if (!$calciatoreDaSvincolare) { // Aggiunto controllo per sicurezza
            return redirect()->back()->withInput()->with('error', 'Errore: Dati del giocatore da svincolare mancanti.');
        }
        if (!$calciatoreDaAcquistare) { // Aggiunto controllo per sicurezza
            return redirect()->back()->withInput()->with('error', 'Errore: Dati del giocatore da acquistare mancanti.');
        }
        
        
        if ($acquistoDaRimuovere->user_id != $squadra->id) {
            return redirect()->back()->withInput()->with('error', 'Errore: Il giocatore da svincolare non appartiene alla squadra selezionata.');
        }
        if ($calciatoreDaSvincolare->ruolo !== $calciatoreDaAcquistare->ruolo) {
            return redirect()->back()->withInput()->with('error', 'Errore: Il giocatore da acquistare deve avere lo stesso ruolo del giocatore svincolato.');
        }
        // Non si può acquistare lo stesso giocatore che si sta svincolando
        if ($calciatoreDaSvincolare->id === $calciatoreDaAcquistare->id) {
            return redirect()->back()->withInput()->with('error', 'Non puoi sostituire un giocatore con se stesso.');
        }
        
        
        $prezzoEffettivoNuovoAcquisto = $prezzoNuovoAcquistoInput;
        $creditiNettiOperazione = 0;
        
        if ($modalitaSostituzione === 'CONCLUSA') {
            if ($prezzoNuovoAcquistoInput !== $prezzoGiocatoreSvincolatoOriginale) {
                return redirect()->back()->withInput()->with('error', "Errore Fase CONCLUSA: Prezzo IN ({$prezzoNuovoAcquistoInput}cr) deve essere uguale a prezzo OUT ({$prezzoGiocatoreSvincolatoOriginale}cr).");
            }
            $prezzoEffettivoNuovoAcquisto = $prezzoGiocatoreSvincolatoOriginale;
            $creditiNettiOperazione = 0;
        } elseif ($modalitaSostituzione === 'STAGIONALE') {
            if ($prezzoNuovoAcquistoInput !== $prezzoGiocatoreSvincolatoOriginale) {
                return redirect()->back()->withInput()->with('error', "Errore Fase SVINCOLI STAGIONALI: Prezzo IN ({$prezzoNuovoAcquistoInput}cr) deve essere uguale al valore ereditato ({$prezzoGiocatoreSvincolatoOriginale}cr).");
            }
            // ==== CONTROLLO LIMITE SOSTITUZIONI STAGIONALI ====
            if ($impostazioniLega->max_sostituzioni_stagionali > 0 && $squadra->sostituzioni_stagionali_usate >= $impostazioniLega->max_sostituzioni_stagionali) {
                Log::warning("Tentativo di sostituzione stagionale per {$squadra->name} fallito: limite di {$impostazioniLega->max_sostituzioni_stagionali} sostituzioni raggiunto.");
                
                $maxSost = $impostazioniLega->max_sostituzioni_stagionali;
                // Assicuriamoci che il nome della squadra sia una stringa UTF-8 pulita
                $nomeSquadra = mb_convert_encoding($squadra->name, 'UTF-8', 'UTF-8');
                
                $messaggioPulito = "Limite massimo di {$maxSost} sostituzioni stagionali già raggiunto per la squadra {$nomeSquadra}.";
                $messaggioPulito = mb_convert_encoding($messaggioPulito, 'UTF-8', 'UTF-8');
                return redirect()->back()->withInput()->with('error', (string) $messaggioPulito);
            }
            // =================================================
            $prezzoEffettivoNuovoAcquisto = $prezzoGiocatoreSvincolatoOriginale;
            $creditiNettiOperazione = 0;
        } else {
            $creditiNettiOperazione = $prezzoGiocatoreSvincolatoOriginale - $prezzoEffettivoNuovoAcquisto;
        }
        
        DB::beginTransaction();
        try {
            $nuoviCreditiSquadra = $squadra->crediti_rimanenti + $creditiNettiOperazione;
            if ($nuoviCreditiSquadra < 0) {
                DB::rollBack(); // Importante fare rollback prima del redirect
                return redirect()->back()->withInput()->with('error', "Crediti insufficienti per completare la sostituzione. Saldo risulterebbe: {$nuoviCreditiSquadra}cr.");
            }
            
            // Verifica che il giocatore "IN" non sia già in rosa alla squadra corrente (oltre a quello che esce)
            $calciatoreInPresenteInRosa = GiocatoreAcquistato::where('user_id', $squadra->id)
            ->where('calciatore_id', $calciatoreDaAcquistare->id)
            ->where('id', '!=', $acquistoDaRimuovere->id) // Escludi il record che stiamo per rimuovere, se per caso l'ID calciatore fosse lo stesso (non dovrebbe essere)
            ->exists();
            
            if ($calciatoreInPresenteInRosa) {
                DB::rollBack();
                return redirect()->back()->withInput()->with('error', "Il calciatore {$calciatoreDaAcquistare->nome_completo} è già presente nella rosa di {$squadra->name}.");
            }
            
            
            $giaAcquistatoDaAltriQuery = GiocatoreAcquistato::where('calciatore_id', $calciatoreDaAcquistare->id)
            ->where('user_id', '!=', $squadra->id); // Escludi la squadra corrente dalla verifica
            if ($tagAttivo) {
                $giaAcquistatoDaAltriQuery->whereHas('calciatore', fn($q) => $q->where('tag_lista_inserimento', $tagAttivo));
            }
            if ($giaAcquistatoDaAltriQuery->exists()) {
                DB::rollBack();
                return redirect()->back()->withInput()->with('error', "Il calciatore {$calciatoreDaAcquistare->nome_completo} è già in rosa ad un'altra squadra per il tag '$tagAttivo'.");
            }
            
            $numGiocatoriAttualiRosa = $squadra->giocatoriAcquistati()
            ->whereHas('calciatore', fn($q) => $q->where('tag_lista_inserimento', $tagAttivo))
            ->where('id', '!=', $acquistoDaRimuovere->id) // Non contare quello che sta per essere svincolato
            ->count();
            $limiteGiocatoriSistema = $impostazioniLega->num_portieri + $impostazioniLega->num_difensori + $impostazioniLega->num_centrocampisti + $impostazioniLega->num_attaccanti;
            
            // La logica del banco saltato in sostituzione è leggermente diversa:
            // si considera che un giocatore esce e uno entra, quindi il numero totale di giocatori in rosa NON cambia.
            // Dobbiamo solo assicurarci che i crediti siano sufficienti per gli slot *ipoteticamente vuoti* SE ce ne fossero.
            // Ma siccome è una sostituzione 1-a-1, il numero di slot totali da riempire non cambia.
            // La verifica importante è che $nuoviCreditiSquadra non sia negativo.
            // E se la rosa NON fosse piena, che $nuoviCreditiSquadra sia >= slot ancora da riempire.
            $slotDaRiempire = $limiteGiocatoriSistema - ($numGiocatoriAttualiRosa); // Slot vuoti *dopo* lo svincolo del giocatore OUT e *prima* dell'acquisto del giocatore IN
            // dato che la sostituzione è 1 a 1, il numero di giocatori totali non cambia
            // quindi $numGiocatoriAttualiRosa è il numero di giocatori che la squadra avrà *senza* il giocatore OUT
            // e *senza* ancora il giocatore IN.
            
            if ($slotDaRiempire > 0 && $nuoviCreditiSquadra < $slotDaRiempire) {
                DB::rollBack();
                return redirect()->back()->withInput()->with('error', "BANCO SALTATO! Dopo la sostituzione, la squadra {$squadra->name} avrebbe {$nuoviCreditiSquadra}cr con {$slotDaRiempire} slot ancora da riempire.");
            }
            
            
            $acquistoDaRimuovere->delete();
            GiocatoreAcquistato::create([
                'user_id' => $squadra->id,
                'calciatore_id' => $calciatoreDaAcquistare->id,
                'prezzo_acquisto' => $prezzoEffettivoNuovoAcquisto,
                'ruolo_al_momento_acquisto' => $calciatoreDaAcquistare->ruolo,
            ]);
            
            $squadra->crediti_rimanenti = $nuoviCreditiSquadra;
            if ($modalitaSostituzione === 'STAGIONALE') {
                $squadra->increment('sostituzioni_stagionali_usate');
            }
            $squadra->save();
            
            DB::commit();
            return redirect()->route('admin.rose.squadre.index')->with('success', "Sostituzione completata per {$squadra->name}: {$calciatoreDaSvincolare->nome_completo} (Valore: {$prezzoGiocatoreSvincolatoOriginale}) con {$calciatoreDaAcquistare->nome_completo} (Nuovo Valore/Costo: {$prezzoEffettivoNuovoAcquisto}). Crediti rimanenti: {$squadra->crediti_rimanenti}.");
        } catch (Exception $e) {
            DB::rollBack();
            Log::error("Errore durante la sostituzione per squadra ID {$squadra->id}: " . $e->getMessage());
            return redirect()->back()->withInput()->with('error', 'Errore tecnico durante la sostituzione: ' . $e->getMessage());
        }
    }
    public function getSquadraRosaPerSostituzione(Request $request, User $user)
        {
            $tagAttivo = $request->query('tag_attivo');
            
            $rosaQuery = $user->giocatoriAcquistati()
            ->with('calciatore:id,nome_completo,ruolo,squadra_serie_a'); // Seleziona solo le colonne necessarie
            
            if ($tagAttivo) {
                $rosaQuery->whereHas('calciatore', function ($q) use ($tagAttivo) {
                    $q->where('tag_lista_inserimento', $tagAttivo);
                });
            }
            
            $rosaFormattata = $rosaQuery->get()->map(function($acquisto) {
                return [
                    'id' => $acquisto->id, // ID del record GiocatoreAcquistato
                    'prezzo_acquisto' => $acquisto->prezzo_acquisto,
                    'calciatore' => $acquisto->calciatore // Oggetto calciatore completo (o solo i campi che ti servono)
                ];
            });
                
                return response()->json([
                    'rosa' => $rosaFormattata,
                    'crediti_rimanenti' => $user->crediti_rimanenti,
                    'nome_squadra' => $user->name,
                ]);
        }
        
        // app/Http/Controllers/AdminController.php
        public function svincolaGiocatore(Request $request, GiocatoreAcquistato $giocatoreAcquistato)
        {
            $impostazioniLega = ImpostazioneLega::firstOrFail();
            $faseAstaCorrente = $impostazioniLega->fase_asta_corrente;
            
            $squadra = $giocatoreAcquistato->user;
            $calciatore = $giocatoreAcquistato->calciatore; // Assicurati che la relazione sia caricata se necessario
            if (!$calciatore) {
                // Ricarica con la relazione se non presente
                $giocatoreAcquistato->load('calciatore');
                $calciatore = $giocatoreAcquistato->calciatore;
            }
            
            if (!$squadra || !$calciatore) {
                Log::error("[Admin Svincolo] Errore: Squadra o Calciatore non trovati per GiocatoreAcquistato ID: {$giocatoreAcquistato->id}");
                return redirect()->route('admin.rose.squadre.index')->with('error', 'Errore: Dati del giocatore o della squadra mancanti.');
            }
            
            Log::info("Admin ID ".Auth::id()." sta tentando uno svincolo per GiocatoreAcquistato ID: {$giocatoreAcquistato->id} (Calciatore: {$calciatore->nome_completo}, Squadra: {$squadra->name}). Fase asta attuale: {$faseAstaCorrente}");
            
            $prezzoAcquistoOriginale = $giocatoreAcquistato->prezzo_acquisto;
            $creditiDaRestituire = 0;
            $messaggioLogOperazione = "";
            
            // Determina i crediti da restituire in base alla fase
            if ($faseAstaCorrente === 'ASTA_RIPARAZIONE' || $faseAstaCorrente === 'SVINCOLI_STAGIONALI' /* O una fase dedicata come 'PRE_RIPARAZIONE' */) {
                $percentualeRecupero = $impostazioniLega->percentuale_crediti_svincolo_riparazione;
                $creditiDaRestituire = floor(($prezzoAcquistoOriginale * $percentualeRecupero) / 100);
                $messaggioLogOperazione = "Svincolo in fase '{$faseAstaCorrente}'. Percentuale recupero: {$percentualeRecupero}%. Crediti restituiti: {$creditiDaRestituire} su {$prezzoAcquistoOriginale}.";
                Log::info($messaggioLogOperazione);
                
            } elseif (in_array($faseAstaCorrente, ['PRE_ASTA', 'CONCLUSA'])) {
                $creditiDaRestituire = $prezzoAcquistoOriginale; // 100%
                $messaggioLogOperazione = "Svincolo in fase '{$faseAstaCorrente}'. Restituzione 100%. Crediti restituiti: {$creditiDaRestituire}.";
                Log::info($messaggioLogOperazione);
            } else {
                Log::warning("Admin ID ".Auth::id()." ha tentato uno svincolo per GiocatoreAcquistato ID: {$giocatoreAcquistato->id} in fase asta non permessa per svincoli diretti: {$faseAstaCorrente}");
                return redirect()->route('admin.rose.squadre.index')->with('error', "Lo svincolo dei giocatori non è permesso nella fase asta attuale ({$faseAstaCorrente}). Utilizzare la funzione di Sostituzione se appropriata.");
            }
            
            DB::beginTransaction();
            try {
                $squadra->crediti_rimanenti += $creditiDaRestituire;
                // In caso di SVINCOLI_STAGIONALI, uno svincolo semplice NON dovrebbe consumare uno slot sostituzione.
                // Gli slot si consumano solo con l'operazione di "sostituzione" (out + in).
                // Se però volessi che anche lo svincolo semplice in questa fase conti, dovresti aggiungere logica qui.
                $squadra->save();
                
                $giocatoreAcquistato->delete();
                
                DB::commit();
                Log::info("SVINCOLO COMPLETATO: Admin ID ".Auth::id().". Giocatore {$calciatore->nome_completo} (ID: {$calciatore->id}) svincolato da {$squadra->name} (ID: {$squadra->id}). " . $messaggioLogOperazione);
                return redirect()->route('admin.rose.squadre.index')
                ->with('success', "Giocatore {$calciatore->nome_completo} svincolato con successo da {$squadra->name}. Crediti restituiti: {$creditiDaRestituire}.");
                
            } catch (Exception $e) {
                DB::rollBack();
                Log::error("Errore durante lo svincolo di GiocatoreAcquistato ID {$giocatoreAcquistato->id}: " . $e->getMessage());
                return redirect()->route('admin.rose.squadre.index')->with('error', 'Errore tecnico durante lo svincolo: ' . $e->getMessage());
            }
        }
}