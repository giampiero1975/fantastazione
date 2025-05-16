<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Calciatore;
use League\Csv\Reader;
use League\Csv\Statement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class GiocatoreImportController extends Controller
{
    /**
     * Mostra il form per importare i giocatori da CSV.
     */
    public function showImportForm()
    {
        // Passa eventuali dati aggiuntivi se necessario
        return view('admin.giocatori.import'); // Assicurati che la vista esista qui
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
                throw new Exception("Impossibile determinare la stagione/tag dal nome del file: $filename. Assicurati che contenga 'Stagione_YYYY_YY' o 'Stagione_YYYY'.");
            }
            
            if (str_contains(strtolower($filename), 'ceduti')) {
                $isAttivo = false;
            }
            
            // 3. Processa il CSV usando league/csv
            $csv = Reader::createFromPath($file->getPathname(), 'r');
            // !! VERIFICA IL DELIMITATORE DEL TUO FILE CSV !! (;)
            $csv->setDelimiter(';');
            $csv->setHeaderOffset(0);
            
            $stmt = Statement::create();
            $records = $stmt->process($csv);
            
            // Opzionale: Inizia una transazione DB
            // DB::beginTransaction();
            
            foreach ($records as $index => $record) {
                $rowNumber = $index + 1;
                try {
                    // 4. Estrai e pulisci i dati CORREGGENDO LE CHIAVI
                    $idEsterno = trim($record['Id'] ?? null); // Chiave corretta: 'Id' (con 'd' minuscola)
                    $ruolo = strtoupper(trim($record['R'] ?? null)); // Chiave corretta: 'R' (senza punto)
                    $nome = trim($record['Nome'] ?? null);
                    $squadra = trim($record['Squadra'] ?? null);
                    $qtIniziale = (int) preg_replace('/[^0-9]/', '', $record['Qt.I'] ?? 0); // Chiave corretta: 'Qt.I' (senza spazio)
                    $qtAttuale = (int) preg_replace('/[^0-9]/', '', $record['Qt.A'] ?? 0);  // Chiave corretta: 'Qt.A' (senza spazio)
                    
                    // 5. Validazione dati riga (Esempio base)
                    if (empty($idEsterno) || empty($nome) || empty($ruolo) || empty($squadra) || !in_array($ruolo, ['P', 'D', 'C', 'A'])) {
                        throw new Exception("Dati mancanti o ruolo non valido ('$ruolo') per IdEsterno '$idEsterno' e Nome '$nome' alla riga $rowNumber");
                    }
                    
                    // 6. Crea o aggiorna il record nel database
                    Calciatore::updateOrCreate(
                        [
                            'id_esterno_giocatore' => $idEsterno,
                            'tag_lista_inserimento' => $tag_lista,
                        ],
                        [
                            'nome_completo' => $nome,
                            'ruolo' => $ruolo,
                            'squadra_serie_a' => $squadra,
                            'quotazione_iniziale' => $qtIniziale,
                            'quotazione_attuale' => $qtAttuale,
                            'attivo' => $isAttivo,
                        ]
                        );
                    $importedCount++;
                    
                } catch (Exception $e) {
                    $errorMessage = "Errore alla riga $rowNumber: " . $e->getMessage();
                    $errors[] = $errorMessage;
                    Log::error("Errore import CSV ($filename): " . $errorMessage);
                    // Considera se interrompere o continuare qui
                }
            } // Fine foreach
            
            // Opzionale: Conferma la transazione DB
            // DB::commit();
            
            // 7. Prepara messaggio e aggregato
            $message = "Importazione per '$tag_lista' completata. $importedCount giocatori importati/aggiornati.";
            if (!empty($errors)) {
                $message .= " Si sono verificati " . count($errors) . " errori (controlla i log per dettagli).";
            }
            
            $activePlayerCount = Calciatore::where('tag_lista_inserimento', $tag_lista)->where('attivo', true)->count();
            $aggregateMessage = "Tag '$tag_lista': $activePlayerCount calciatori attivi.";
            
            return redirect()->route('admin.giocatori.import.show')
            ->with('success', $message)
            ->with('aggregate', $aggregateMessage); // Potresti passare anche ->with('import_errors', $errors) se vuoi mostrarli
            
        } catch (Exception $e) {
            // Opzionale: Annulla transazione
            // DB::rollBack();
            Log::error("Errore generale import CSV ($filename): " . $e->getMessage());
            return redirect()->route('admin.giocatori.import.show')
            ->with('error', 'Errore durante l\'importazione: ' . $e->getMessage());
        }
    }
    
    public function indexGiocatori(Request $request)
    {
        $query = Calciatore::query();
        Log::debug('Valori request per filtri: ', $request->only(['tag_lista_inserimento', 'ruolo', 'squadra_serie_a', 'attivo']));
        
        if ($request->filled('tag_lista_inserimento')) {
            $query->where('tag_lista_inserimento', $request->input('tag_lista_inserimento'));
        }
        
        if ($request->filled('ruolo')) {
            $query->where('ruolo', $request->input('ruolo'));
        }
        
        if ($request->filled('squadra_serie_a')) {
            $searchTermSquadra = trim($request->input('squadra_serie_a'));
            Log::debug("Termine di ricerca per squadra (pulito): '$searchTermSquadra'");
            $query->where('squadra_serie_a', 'like', '%' . $searchTermSquadra . '%');
        }
        
        // FILTRO 4: STATO ATTIVO - MODIFICATO PER MOSTRARE TUTTI SE NON SPECIFICATO
        $attivoValue = $request->input('attivo');
        if ($attivoValue !== null && $attivoValue !== '') { // Applica filtro solo se '0' o '1' è selezionato
            $query->where('attivo', (bool)$attivoValue);
            Log::debug('Filtro attivo applicato con valore: ' . ((bool)$attivoValue ? 'true' : 'false'));
        } else {
            // Nessun filtro su 'attivo' applicato se l'utente seleziona "Tutti" per lo stato.
            // La query mostrerà sia attivi che non attivi.
            Log::debug('Filtro attivo NON applicato, mostra tutti gli stati.');
        }
        
        
        $query->leftJoin('giocatori_acquistati', 'calciatori.id', '=', 'giocatori_acquistati.calciatore_id')
        ->leftJoin('users', 'giocatori_acquistati.user_id', '=', 'users.id')
        ->select(
            'calciatori.*', // Seleziona tutte le colonne da calciatori
            'users.name as nome_squadra_acquirente',
            'giocatori_acquistati.prezzo_acquisto'
            );
        
        Log::debug('SQL Query: ' . $query->toSql());
        Log::debug('Bindings: ', $query->getBindings());
        
        $calciatori = $query->orderBy('tag_lista_inserimento', 'desc')
        ->orderByRaw("FIELD(ruolo, 'P', 'D', 'C', 'A')") // Ordina per ruolo standard
        ->orderBy('nome_completo')
        ->paginate(25); // Paginazione
        
        
        $tagsDisponibili = Calciatore::select('tag_lista_inserimento')->distinct()->orderBy('tag_lista_inserimento', 'desc')->pluck('tag_lista_inserimento');
        $ruoliDisponibili = Calciatore::select('ruolo')->distinct()->orderByRaw("FIELD(ruolo, 'P', 'D', 'C', 'A')")->pluck('ruolo');
        $squadreDisponibili = Calciatore::select('squadra_serie_a')->distinct()->orderBy('squadra_serie_a')->pluck('squadra_serie_a');
        
        return view('admin.giocatori.index', compact(
            'calciatori',
            'tagsDisponibili',
            'ruoliDisponibili',
            'squadreDisponibili'
            // Se passi $request->all() come 'filtriAttuali', puoi usare old() o accedere direttamente a $filtriAttuali['nome_filtro']
            // 'filtriAttuali' => $request->all() // O solo $request->query()
            ));
    }
}