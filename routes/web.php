<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\AdminController; // Controller Admin generico
use App\Http\Controllers\Admin\AdminRosterController; // Controller specifico per Rose Admin
use App\Http\Controllers\GiocatoreImportController; // Controller per Import CSV
use App\Http\Controllers\AstaController; // Controller per logica Asta e Dashboard Squadra

/*
 |--------------------------------------------------------------------------
 | Web Routes
 |--------------------------------------------------------------------------
 |
 | Here is where you can register web routes for your application. These
 | routes are loaded by the RouteServiceProvider within a group which
 | contains the "web" middleware group. Now create something great!
 |
 */

// Pagina di benvenuto o home pubblica
Route::get('/', function () {
    return view('welcome'); // La vista di default di Laravel
});
    
    // Dashboard Route (gestita da AstaController per smistare Admin/Squadra)
    Route::get('/dashboard', [AstaController::class, 'dashboard'])
    ->middleware(['auth', 'verified']) // 'verified' è opzionale se non usi la verifica email
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
            
            // Dashboard Admin
            Route::get('/dashboard', [AdminController::class, 'dashboard'])->name('dashboard');
            Route::get('/mia-squadra', [AdminController::class, 'mostraMiaSquadraDashboard'])->name('mia_squadra.dashboard');
            
            // Gestione Utenti/Squadre
            Route::get('/users', [AdminController::class, 'users'])->name('users.index');
            Route::get('/users/{user}/edit', [AdminController::class, 'editUser'])->name('users.edit');
            Route::patch('/users/{user}', [AdminController::class, 'updateUser'])->name('users.update');
            
            // Impostazioni Lega e Asta
            Route::get('/settings', [AdminController::class, 'settings'])->name('settings.index');
            Route::post('/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
            
            // Importazione Calciatori CSV
            Route::get('/giocatori/import', [GiocatoreImportController::class, 'showImportForm'])->name('giocatori.import.show');
            Route::post('/giocatori/import', [GiocatoreImportController::class, 'handleImport'])->name('giocatori.import.handle');
            
            // Visualizzazione Lista Calciatori (Admin)
            Route::get('/giocatori', [GiocatoreImportController::class, 'indexGiocatori'])->name('giocatori.index');
            
            // Assegnazione Manuale Giocatori (Admin)
            Route::get('/giocatori/assegna', [AdminRosterController::class, 'showAssegnaForm'])->name('giocatori.assegna.show');
            Route::post('/giocatori/assegna', [AdminRosterController::class, 'handleAssegna'])->name('giocatori.assegna.handle');
            Route::get('/giocatori/autocomplete', [AdminRosterController::class, 'autocompleteGiocatori'])->name('giocatori.autocomplete'); // Per TomSelect
            
            // Visualizzazione Rose Squadre (Admin)
            Route::get('/rose-squadre', [AdminRosterController::class, 'visualizzaRoseSquadre'])->name('rose.squadre.index');
            
            // Asta Tap
            Route::get('/asta/chiamate', [AdminRosterController::class, 'gestioneChiamateAsta'])->name('asta.chiamate.gestione');
            Route::post('/asta/avvia-tap/{chiamataAsta}', [AdminRosterController::class, 'avviaAstaTap'])->name('asta.avvia.tap');
        });
            
            
            // === GRUPPO ROTTE SQUADRA (ASTA) ===
            Route::middleware(['auth'])->prefix('asta')->name('asta.')->group(function () {
                // Nota: Il middleware 'auth' qui assicura che l'utente sia loggato.
                // Il controller AstaController nel metodo dashboard farà il redirect se è un admin.
                // Per altre rotte qui, se vuoi che siano *solo* per non-admin, dovrai aggiungere un altro middleware.
                
                Route::get('/calciatori-disponibili', [AstaController::class, 'mostraCalciatoriDisponibili'])->name('calciatori.disponibili');
                Route::post('/registra-chiamata/{calciatore}', [AstaController::class, 'registraChiamata'])->name('registra.chiamata');
                Route::get('/live', [AstaController::class, 'mostraAstaLive'])->name('live');
                Route::post('/tap/rilancia/{chiamataAsta}', [AstaController::class, 'gestisciRilancioTap'])->name('tap.rilancia');
                Route::get('/tap/stato/{idChiamataAsta}', [AstaController::class, 'statoAstaTap'])->name('asta.tap.stato');
                Route::post('/tap/finalizza/{chiamataAsta}', [AstaController::class, 'finalizzaAstaTapScaduta'])->name('tap.finalizza');
            });