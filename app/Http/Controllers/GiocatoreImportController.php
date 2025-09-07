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
        $request->validate(['csv_file' => ['required', 'file', 'mimes:csv,txt']]);
        
        $file = $request->file('csv_file');
        $filename = $file->getClientOriginalName();
        $tag_lista = null;
        $isAttivo = true;
        $importedCount = 0;
        $errors = [];
        
        try {
            if (preg_match('/Stagione_(\d{4}(?:_\d{2})?)/i', $filename, $matches)) {
                $tag_lista = 'Stagione_' . $matches[1];
            } else {
                throw new Exception("Impossibile determinare la stagione/tag dal nome del file: $filename.");
            }
            
            if (str_contains(strtolower($filename), 'ceduti')) {
                $isAttivo = false;
            }
            
            $fileContent = file_get_contents($file->getRealPath());
            
            // Manteniamo la funzione di sostituzione che sappiamo funzionare in PHP
            $caratteriErrati = ["\x95", "\x8D", "\x8A", "\x92", "\xe0", "\xe8", "\xe9", "\xec", "\xf2", "\xf9", "\xc0", "\xc8", "\xc9", "\xcc", "\xd2", "\xd9", "\xe4", "\xf6", "\xfc", "\xc4", "\xd6", "\xdc", "\xdf", "\xe7", "\xc7", "\xf1", "\xd1", "\xe2", "\xea", "\xee", "\xf4", "\xc2", "\xca", "\xce", "\xd4", "\xeb", "\xef", "\xe5", "\xc5", "\xe6", "\xc6", "\xf8", "\xd8", "\xff", "\x9F"];
            $caratteriCorretti = ["ò", "í", "é", "'", "à", "è", "é", "ì", "ò", "ù", "À", "È", "É", "Ì", "Ò", "Ù", "ä", "ö", "ü", "Ä", "Ö", "Ü", "ß", "ç", "Ç", "ñ", "Ñ", "â", "ê", "î", "ô", "Â", "Ê", "Î", "Ô", "ë", "ï", "å", "Å", "æ", "Æ", "ø", "Ø", "ÿ", "Ÿ"];
            $fileContent = str_replace($caratteriErrati, $caratteriCorretti, $fileContent);
            
            $csv = Reader::createFromString($fileContent);
            $csv->setDelimiter(';');
            $csv->setHeaderOffset(0);
            
            $records = Statement::create()->process($csv);
            
            foreach ($records as $index => $record) {
                $rowNumber = $index + 2;
                try {
                    $idEsterno = trim($record['Id'] ?? null);
                    $ruolo = strtoupper(trim($record['R'] ?? null));
                    $nome = trim($record['Nome'] ?? null);
                    $squadra = trim($record['Squadra'] ?? null);
                    $qtIniziale = (int) preg_replace('/[^0-9]/', '', $record['Qt.I'] ?? 0);
                    $qtAttuale = (int) preg_replace('/[^0-9]/', '', $record['Qt.A'] ?? 0);
                    
                    if (empty($idEsterno) || empty($nome)) {
                        throw new Exception("Dati 'Id' o 'Nome' mancanti.");
                    }
                    
                    // ==================== QUERY GREZZA (ULTIMA PROVA) ====================
                    // Invece di usare Eloquent, scriviamo la query a mano.
                    // Questo bypassa ogni possibile "magia" o bug di Laravel/Eloquent.
                    $sql = "
                    INSERT INTO calciatori (
                        id_esterno_giocatore, tag_lista_inserimento, nome_completo, ruolo,
                        squadra_serie_a, quotazione_iniziale, quotazione_attuale, attivo,
                        created_at, updated_at
                    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE
                        nome_completo = VALUES(nome_completo),
                        ruolo = VALUES(ruolo),
                        squadra_serie_a = VALUES(squadra_serie_a),
                        quotazione_iniziale = VALUES(quotazione_iniziale),
                        quotazione_attuale = VALUES(quotazione_attuale),
                        attivo = VALUES(attivo),
                        updated_at = NOW()
                ";
                    
                    $bindings = [
                        $idEsterno, $tag_lista, $nome, $ruolo, $squadra,
                        $qtIniziale, $qtAttuale, $isAttivo
                    ];
                    
                    // Eseguiamo la query usando il metodo di base di Laravel
                    DB::statement($sql, $bindings);
                    // ====================================================================
                    
                    $importedCount++;
                    
                } catch (Exception $e) {
                    $errorMessage = "Errore alla riga $rowNumber: " . $e->getMessage();
                    $errors[] = $errorMessage;
                    Log::error("Errore import CSV ($filename): " . $errorMessage);
                }
            }
            
            $message = "Importazione completata. $importedCount giocatori importati/aggiornati.";
            if (!empty($errors)) {
                $message .= " Si sono verificati " . count($errors) . " errori.";
            }
            
            return redirect()->route('admin.giocatori.import.show')->with('success', $message);
            
        } catch (Exception $e) {
            Log::error("Errore generale import CSV ($filename): " . $e->getMessage());
            return redirect()->route('admin.giocatori.import.show')->with('error', 'Errore: ' . $e->getMessage());
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