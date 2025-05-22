<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController; // Controller Admin unificato
use App\Http\Controllers\GiocatoreImportController; // Mantenuto separato per ora
use App\Http\Controllers\AstaController;

/*
 |--------------------------------------------------------------------------
 | Web Routes
 |--------------------------------------------------------------------------
 */

// Pagina di benvenuto o home pubblica
Route::get('/', function () {
    return view('welcome');
});
    
    // Dashboard Route (gestita da AstaController per smistare Admin/Squadra)
    Route::get('/dashboard', [AstaController::class, 'dashboard'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');
    
    // Rotte per la gestione del profilo utente (da Breeze)
    Route::middleware('auth')->group(function () {
        Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
        Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
        Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    });
        
        // Rotte di Autenticazione (quelle definite da Breeze in routes/auth.php)
        require __DIR__.'/auth.php';
        
        
        // === GRUPPO ROTTE ADMIN ===
        Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
            
            // === API INTERNE PER ADMIN (per popolare dinamicamente i dropdown) ===
            Route::prefix('api')->name('api.')->group(function () {
                Route::get('/squadra/{user}/rosa-per-sostituzione', [AdminController::class, 'getSquadraRosaPerSostituzione'])
                ->name('squadra.rosa'); // Nome completo generato: admin.api.squadra.rosa
            });
            
            // --- Dashboard Admin ---
            Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
            Route::get('/mia-squadra', [AdminController::class, 'mostraMiaSquadraDashboard'])->name('mia_squadra.dashboard');
            
            // --- Gestione Utenti/Squadre ---
            Route::prefix('utenti')->name('utenti.')->group(function () {
            Route::get('/', [AdminController::class, 'users'])->name('index');
            Route::get('/crea', [AdminController::class, 'createUserForm'])->name('create');
            Route::post('/', [AdminController::class, 'storeUser'])->name('store'); // Definisce admin.utenti.store
            Route::get('/{user}/edit', [AdminController::class, 'editUser'])->name('edit');
            Route::patch('/{user}', [AdminController::class, 'updateUser'])->name('update');
            });
                
                // --- Impostazioni Lega e Asta ---
                Route::prefix('impostazioni')->name('impostazioni.')->group(function () {
                    Route::get('/', [AdminController::class, 'settings'])->name('index');
                    Route::post('/', [AdminController::class, 'updateSettings'])->name('update');
                });
                    
                    // --- Gestione Giocatori ---
                    Route::prefix('giocatori')->name('giocatori.')->group(function () {
                        // Importazione CSV (manteniamo GiocatoreImportController per ora)
                        Route::get('/import', [GiocatoreImportController::class, 'showImportForm'])->name('import.show');
                        Route::post('/import', [GiocatoreImportController::class, 'handleImport'])->name('import.handle');
                        
                        // Elenco Giocatori (manteniamo GiocatoreImportController per ora)
                        Route::get('/', [GiocatoreImportController::class, 'indexGiocatori'])->name('index');
                        
                        // Assegnazione Manuale Giocatori (ora in AdminController)
                        Route::get('/assegna', [AdminController::class, 'showAssegnaForm'])->name('assegna.show'); // Metodo showAssegnaForm
                        Route::post('/assegna', [AdminController::class, 'handlePlayerAssignment'])->name('assegna.handle'); // Metodo handlePlayerAssignment (o handleAssegna)
                        Route::get('/autocomplete', [AdminController::class, 'autocompletePlayers'])->name('autocomplete'); // Metodo autocompletePlayers
                    });
                        
                        // --- Gestione Rose Squadre ---
                        Route::prefix('rose')->name('rose.')->group(function () {
                            Route::delete('/svincola/{giocatoreAcquistato}', [AdminController::class, 'svincolaGiocatore'])->name('svincola');
                            Route::get('/', [AdminController::class, 'visualizzaRoseSquadre'])->name('squadre.index'); // Metodo visualizzaRoseSquadre
                            Route::get('/sostituisci', [AdminController::class, 'showSostituzioneForm'])->name('sostituisci.show');
                            Route::post('/sostituisci', [AdminController::class, 'handleSostituzione'])->name('sostituisci.handle');
                        });
                            
                            // --- Gestione Asta (specifico Admin) ---
                            Route::prefix('asta')->name('asta.')->group(function () {
                                // Gestione chiamate Asta TAP (per approvazione admin)
                                Route::get('/chiamate', [AdminController::class, 'gestioneChiamateAsta'])->name('chiamate.gestione'); // Metodo manageAuctionCalls
                                Route::post('/avvia-tap/{chiamataAsta}', [AdminController::class, 'avviaAstaTap'])->name('avvia.tap'); // Metodo startTapAuction
                                Route::post('/annulla-chiamata/{chiamataAsta}', [AdminController::class, 'annullaChiamataTap'])->name('chiamata.annulla'); // NUOVA ROTTA, assicurati di avere il metodo cancelTapAuctionCall
                            });
        });
            
            
            // === GRUPPO ROTTE SQUADRA (ASTA) ===
            Route::middleware(['auth'])->prefix('asta')->name('asta.')->group(function () {
                Route::get('/calciatori-disponibili', [AstaController::class, 'mostraCalciatoriDisponibili'])->name('calciatori.disponibili');
                Route::post('/registra-chiamata/{calciatore}', [AstaController::class, 'registraChiamata'])->name('registra.chiamata');
                Route::get('/live', [AstaController::class, 'mostraAstaLive'])->name('live');
                Route::post('/tap/rilancia/{chiamataAsta}', [AstaController::class, 'gestisciRilancioTap'])->name('tap.rilancia');
                Route::get('/tap/stato/{idChiamataAsta}', [AstaController::class, 'statoAstaTap'])->name('asta.tap.stato');
                //Route::post('/tap/finalizza/{chiamataAsta}', [AstaController::class, 'finalizzaAstaTapScaduta'])->name('tap.finalizza');
                Route::post('/tap/finalizza/{idChiamataAstaUrlParam}', [AstaController::class, 'finalizzaAstaTapScaduta'])->name('tap.finalizza');
            });