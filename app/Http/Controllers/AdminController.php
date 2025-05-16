<?php

namespace App\Http\Controllers;

use App\Models\ImpostazioneLega;
use App\Models\User;
use App\Models\Calciatore;
use App\Models\GiocatoreAcquistato;
use League\Csv\Reader;
use League\Csv\Statement;

use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB; 
use Illuminate\Support\Facades\Log;
use Exception;

class AdminController extends Controller // O il nome del tuo controller
{   
    public function mostraMiaSquadraDashboard()
    {
        $user = Auth::user(); // Prende l'utente admin attualmente loggato
        
        // Controllo di sicurezza aggiuntivo (anche se la rotta è protetta da middleware admin)
        if (!$user || !$user->is_admin) {
            abort(403, 'Accesso non autorizzato a questa sezione.');
        }
        
        $squadra = $user; // Per usare la stessa variabile della vista 'squadra.dashboard'
        $impostazioniLega = ImpostazioneLega::firstOrFail(); // Recupera le impostazioni globali
        
        // Recupera la rosa dell'admin con i dettagli del calciatore
        $rosa = $squadra->giocatoriAcquistati()->with('calciatore')->get();
        $costoTotaleRosa = $rosa->sum('prezzo_acquisto');
        
        $conteggioRuoli = collect();
        if($rosa->isNotEmpty() && $rosa->every(fn($acquisto) => $acquisto->relationLoaded('calciatore') && $acquisto->calciatore)){
            $conteggioRuoli = $rosa->map(function($acquisto){
                return $acquisto->calciatore->ruolo;
            })->countBy();
        } else if ($rosa->isNotEmpty()) {
            $idsCalciatoriInRosa = $rosa->pluck('calciatore_id');
            if ($idsCalciatoriInRosa->isNotEmpty()){
                $conteggioRuoli = Calciatore::whereIn('id', $idsCalciatoriInRosa)->pluck('ruolo')->countBy();
            }
        }
        
        $limitiRuoli = [
            'P' => $impostazioniLega->num_portieri,
            'D' => $impostazioniLega->num_difensori,
            'C' => $impostazioniLega->num_centrocampisti,
            'A' => $impostazioniLega->num_attaccanti,
        ];
        $numeroGiocatoriInRosa = $rosa->count();
        $limiteGiocatoriTotaliInRosa = $impostazioniLega->num_portieri + $impostazioniLega->num_difensori + $impostazioniLega->num_centrocampisti + $impostazioniLega->num_attaccanti;
        
        // Riutilizziamo la stessa vista 'squadra.dashboard' passando i dati necessari
        return view('squadra.dashboard', compact(
            'squadra',
            'rosa',
            'impostazioniLega',
            'costoTotaleRosa',
            'conteggioRuoli',
            'limitiRuoli',
            'numeroGiocatoriInRosa',
            'limiteGiocatoriTotaliInRosa'
            ));
    }
    
    public function dashboard()
    {
        // Recupera le impostazioni della lega. Usa firstOrCreate per sicurezza se la tabella potesse essere vuota.
        $impostazioniLega = ImpostazioneLega::firstOrCreate([], [
            'fase_asta_corrente' => 'PRE_ASTA',
            'crediti_iniziali_lega' => 500,
            'num_portieri' => 6,
            'num_difensori' => 8,
            'num_centrocampisti' => 8,
            'num_attaccanti' => 6,
            // Aggiungi qui i default anche per i nuovi campi se necessario, anche se settings() dovrebbe gestirli
            'tag_lista_attiva' => null,
            'modalita_asta' => 'voce',
            'durata_countdown_secondi' => 60,
            'asta_tap_approvazione_admin' => true,
        ]);
        
        $numeroSquadre = User::where('is_admin', false)->count();
        $numeroCalciatoriImportati = Calciatore::count(); // Potresti voler filtrare per il tag_lista_attiva qui
        
        return view('admin.dashboard', compact(
            'impostazioniLega', // <-- PASSA LA VARIABILE ALLA VISTA
            'numeroSquadre',
            'numeroCalciatoriImportati'
            ));
    }
    public function editUser(User $user) // Laravel inietterà l'istanza User grazie al Route Model Binding
    {
        return view('admin.users.edit', compact('user'));
    }
    
    // In app/Http/Controllers/AdminController.php
    
    public function updateUser(Request $request, User $user)
    {
        $validatedData = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', \Illuminate\Validation\Rule::unique('users')->ignore($user->id)],
            'crediti_iniziali_squadra' => ['required', 'integer', 'min:0'], // <-- MODIFICATO
            'crediti_rimanenti' => ['required', 'integer', 'min:0'],
            'is_admin' => ['nullable', 'boolean'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);
        
        $userData = [
            'name' => $validatedData['name'],
            'email' => $validatedData['email'],
            'crediti_iniziali_squadra' => $validatedData['crediti_iniziali_squadra'], // <-- MODIFICATO
            'crediti_rimanenti' => $validatedData['crediti_rimanenti'],
            'is_admin' => $request->has('is_admin') ? true : false,
        ];
        
        if ($request->filled('password')) {
            $userData['password'] = bcrypt($request->password);
        }
        
        $user->update($userData);
        
        return redirect()->route('admin.users.index')->with('success', 'Utente aggiornato con successo.');
    }
    
    public function users(Request $request) // Potresti rinominarlo in indexUsers o manageUsers
    {
        // TODO: Aggiungere filtri se necessario (es. per nome squadra, solo non-admin)
        $utenti = User::orderBy('name')->paginate(15); // Prendi tutti gli utenti, paginati
        
        return view('admin.users.index', compact('utenti')); // Cambiamo il nome della vista per chiarezza
    }
    
    /**
     * Mostra il form per importare i giocatori da CSV.
     */
    public function showImportForm()
    {
        // Passa eventuali dati aggiuntivi se necessario
        return view('admin.giocatori.import');
    }
    
    /**
     * Gestisce il caricamento e l'importazione del file CSV dei giocatori.
     */
    public function handleImport(Request $request)
    {
        // 1. Validazione (solo file CSV)
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt'],
        ]);
        
        $file = $request->file('csv_file');
        $filename = $file->getClientOriginalName();
        $tag_lista = null;
        $isAttivo = true; // Default a true
        $importedCount = 0;
        $errors = [];
        
        try {
            // 2. Estrai il tag/stagione e lo stato attivo dal nome del file
            if (preg_match('/Stagione_(\d{4}(?:_\d{2})?)/i', $filename, $matches)) {
                $tag_lista = 'Stagione_' . $matches[1]; // Es. "Stagione_2024_25"
            } else {
                // Se il nome file non contiene un tag valido, genera errore
                throw new Exception("Impossibile determinare la stagione/tag dal nome del file: $filename. Assicurati che contenga 'Stagione_YYYY_YY' o 'Stagione_YYYY'.");
            }
            
            // Determina se i giocatori sono attivi basandosi sul nome file
            if (str_contains(strtolower($filename), 'ceduti')) {
                $isAttivo = false;
            }
            
            // 3. Processa il CSV usando league/csv
            $csv = Reader::createFromPath($file->getPathname(), 'r');
            // !! VERIFICA IL DELIMITATORE DEL TUO FILE CSV !!
            // Spesso in Italia è il punto e virgola (;) se esportato da Excel IT
            $csv->setDelimiter(';');
            $csv->setHeaderOffset(0); // La prima riga contiene le intestazioni
            
            $stmt = Statement::create(); // Opzionale: per elaborare i record
            $records = $stmt->process($csv);
            
            // Opzionale: Inizia una transazione DB
            // DB::beginTransaction();
            
            foreach ($records as $index => $record) {
                $rowNumber = $index + 1; // Numero riga per messaggi di errore
                try {
                    // 4. Estrai e pulisci i dati
                    $idEsterno = trim($record['ID'] ?? null); // Usa l'header esatto dal tuo CSV
                    $ruolo = strtoupper(trim($record['R.'] ?? null));
                    $nome = trim($record['Nome'] ?? null);
                    $squadra = trim($record['Squadra'] ?? null);
                    // Pulisci le quotazioni
                    $qtIniziale = (int) preg_replace('/[^0-9]/', '', $record['Qt. I.'] ?? 0);
                    $qtAttuale = (int) preg_replace('/[^0-9]/', '', $record['Qt. A.'] ?? 0); // Mappato su quotazione_attuale
                    
                    // 5. Validazione dati riga (Esempio base)
                    if (empty($idEsterno) || empty($nome) || empty($ruolo) || empty($squadra) || !in_array($ruolo, ['P', 'D', 'C', 'A'])) {
                        throw new Exception("Dati mancanti o ruolo non valido ($ruolo) alla riga $rowNumber");
                    }
                    
                    // 6. Crea o aggiorna il record nel database
                    Giocatore::updateOrCreate(
                        [
                            'id_esterno_giocatore' => $idEsterno,       // Chiave 1
                            'tag_lista_inserimento' => $tag_lista,   // Chiave 2 (Stagione/Tag)
                        ],
                        [
                            'nome_completo' => $nome,
                            'ruolo' => $ruolo,
                            'squadra_serie_a' => $squadra,
                            'quotazione_iniziale' => $qtIniziale,
                            'quotazione_attuale' => $qtAttuale,     // Dato aggiunto
                            'attivo' => $isAttivo,                 // Stato basato sul filename
                        ]
                        );
                    $importedCount++;
                    
                } catch (Exception $e) {
                    // Registra l'errore per questa riga e continua (o interrompi)
                    $errorMessage = "Errore alla riga $rowNumber: " . $e->getMessage();
                    $errors[] = $errorMessage;
                    Log::error("Errore import CSV ($filename): " . $errorMessage); // Logga l'errore
                    // Se vuoi interrompere al primo errore:
                    // DB::rollBack(); // Annulla transazione se usata
                    // return redirect()->route('admin.giocatori.import.show')->with('error', $errorMessage);
                }
            } // Fine foreach
            
            // Opzionale: Conferma la transazione DB se tutto ok (o se gestisci errori per riga)
            // DB::commit();
            
            // 7. Prepara messaggio di successo/errore e aggregato
            $message = "Importazione per '$tag_lista' completata. $importedCount giocatori importati/aggiornati.";
            if (!empty($errors)) {
                // Riporta solo il numero di errori per non appesantire il messaggio flash
                $message .= " Si sono verificati " . count($errors) . " errori (controlla i log per dettagli).";
                // Potresti passare $errors alla vista se vuoi mostrarli tutti
                // ->with('import_errors', $errors);
            }
            
            // Calcola l'aggregato
            $activePlayerCount = Giocatore::where('tag_lista_inserimento', $tag_lista)
            ->where('attivo', true)
            ->count();
            $aggregateMessage = "Tag '$tag_lista': $activePlayerCount calciatori attivi.";
            
            return redirect()->route('admin.giocatori.import.show')
            ->with('success', $message)
            ->with('aggregate', $aggregateMessage);
            
            
        } catch (Exception $e) {
            // Errore generale (es. parsing filename, lettura file, transazione fallita)
            // DB::rollBack(); // Annulla transazione se usata
            Log::error("Errore generale import CSV ($filename): " . $e->getMessage());
            return redirect()->route('admin.giocatori.import.show')
            ->with('error', 'Errore durante l\'importazione: ' . $e->getMessage());
        }
    }
    
    public function settings()
    {
        // Carica la prima (e unica) riga di impostazioni. Creala con valori di default se non esiste.
        $impostazioni = ImpostazioneLega::firstOrCreate(
            [], // Nessuna condizione di ricerca, prende la prima o crea se vuota
            [   // Valori di default SOLO se viene creata una nuova riga
                'fase_asta_corrente' => 'PRE_ASTA',
                'crediti_iniziali_lega' => 500,
                'num_portieri' => 6,
                'num_difensori' => 8,
                'num_centrocampisti' => 8,
                'num_attaccanti' => 6,
                'tag_lista_attiva' => null,
                'modalita_asta' => 'voce',
                'durata_countdown_secondi' => 60,
                'asta_tap_approvazione_admin' => true,
            ]
            );
        
        $fasiPossibili = [
            'PRE_ASTA' => 'Pre-Asta (Chiusa)',
            'P' => 'Portieri',
            'D' => 'Difensori',
            'C' => 'Centrocampisti',
            'A' => 'Attaccanti',
            'CONCLUSA' => 'Asta Conclusa'
        ];
        
        $tagsCalciatoriDisponibili = Calciatore::select('tag_lista_inserimento')
        ->whereNotNull('tag_lista_inserimento')
        ->where('tag_lista_inserimento', '!=', '')
        ->distinct()
        ->orderBy('tag_lista_inserimento', 'desc')
        ->pluck('tag_lista_inserimento');
        
        // LA CHIAVE È QUI: assicurati che la variabile passata con compact sia 'impostazioni'
        // se la vista usa $impostazioni->nome_campo
        return view('admin.settings.index', compact(
            'impostazioni', // <--- NOME CORRETTO DELLA VARIABILE
            'fasiPossibili',
            'tagsCalciatoriDisponibili'
            ));
    }
    
    public function updateSettings(Request $request)
    {
        $fasiAstaCheRichiedonoLista = ['P', 'D', 'C', 'A']; // Fasi in cui è necessaria una lista giocatori attiva
        
        $validatedData = $request->validate([
            'fase_asta_corrente' => ['required', 'string', Rule::in(['PRE_ASTA', 'P', 'D', 'C', 'A', 'CONCLUSA'])],
            'tag_lista_attiva' => [
                // Obbligatorio se la fase_asta_corrente è P, D, C, o A
                Rule::requiredIf(function () use ($request, $fasiAstaCheRichiedonoLista) {
                    return in_array($request->input('fase_asta_corrente'), $fasiAstaCheRichiedonoLista);
                }),
                'nullable', // Altrimenti può essere nullo
                'string',
                'max:255'
                    ],
                    'modalita_asta' => ['required', 'string', Rule::in(['voce', 'tap'])],
                    'durata_countdown_secondi' => ['required', 'integer', 'min:10', 'max:300'], // Min 10s, Max 5 minuti
                    'asta_tap_approvazione_admin' => ['nullable', 'boolean'], // Gestito con $request->boolean() dopo
                    'crediti_iniziali_lega' => ['required', 'integer', 'min:1', 'max:9999'], // Max crediti ragionevole
                    'num_portieri' => ['required', 'integer', 'min:0', 'max:25'],
                    'num_difensori' => ['required', 'integer', 'min:0', 'max:25'],
                    'num_centrocampisti' => ['required', 'integer', 'min:0', 'max:25'],
                    'num_attaccanti' => ['required', 'integer', 'min:0', 'max:25'],
                    'reset_asta_completo' => ['nullable', 'boolean'], // Checkbox per il reset
                    ]);
        
        // Recupera l'unica riga di impostazioni o creala se non esiste
        $impostazioni = ImpostazioneLega::firstOrFail(); // Dovrebbe esistere grazie a firstOrCreate nel metodo settings()
        
        // Prepara l'array dei dati da aggiornare, gestendo i booleani dai checkbox
        $datiDaAggiornare = $validatedData;
        $datiDaAggiornare['asta_tap_approvazione_admin'] = $request->boolean('asta_tap_approvazione_admin');
        // Il campo 'reset_asta_completo' non va salvato nel DB, lo usiamo solo per la logica qui sotto
        
        $messaggioSuccesso = 'Impostazioni dell\'asta aggiornate con successo.';
        
        // Logica di RESET ASTA COMPLETA se il checkbox è stato selezionato
        if ($request->boolean('reset_asta_completo')) {
            // Controllo di sicurezza aggiuntivo: se si resetta, tag_lista_attiva deve essere presente
            // perché la fase verrà forzata a 'P'.
            if (empty($validatedData['tag_lista_attiva'])) {
                return redirect()->route('admin.settings.index')
                ->withInput() // Mantiene i dati del form per correzione
                ->withErrors(['tag_lista_attiva' => 'Quando si prepara una nuova asta completa (reset), è obbligatorio selezionare un "Tag Lista Calciatori Attiva".']);
            }
            
            $creditiDaAssegnare = $validatedData['crediti_iniziali_lega'];
            
            // 1. Resetta i crediti di TUTTE le squadre (inclusi admin che partecipano)
            User::query()->update([
                'crediti_iniziali_squadra' => $creditiDaAssegnare,
                'crediti_rimanenti' => $creditiDaAssegnare,
            ]);
            Log::info("Crediti resettati per tutte le squadre a: $creditiDaAssegnare, Admin ID: " . auth()->id());
            
            // 2. Svuota la tabella giocatori_acquistati (reset globale delle rose)
            GiocatoreAcquistato::query()->delete();
            Log::info("Tabella giocatori_acquistati svuotata, Admin ID: " . auth()->id());
            
            // 3. Imposta la fase a Portieri (P)
            $datiDaAggiornare['fase_asta_corrente'] = 'P';
            
            Log::info("Admin (ID: " . auth()->id() . ") ha eseguito un RESET ASTA COMPLETO. Tag lista attiva: " . $validatedData['tag_lista_attiva'] . ".");
            $messaggioSuccesso = 'IMPOSTAZIONI AGGIORNATE. NUOVA ASTA PREPARATA: Crediti squadre resettati, rose svuotate e fase impostata a Portieri!';
        }
        
        // Rimuovi 'reset_asta_completo' dai dati da aggiornare perché non è una colonna del DB
        unset($datiDaAggiornare['reset_asta_completo']);
        
        // Aggiorna le impostazioni della lega
        $impostazioni->update($datiDaAggiornare);
        
        return redirect()->route('admin.settings.index')->with('success', $messaggioSuccesso);
    }
}