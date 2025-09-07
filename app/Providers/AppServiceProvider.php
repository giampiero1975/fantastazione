<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;         // Facade corretta
use Illuminate\Support\Facades\DB; // Aggiungi questo use statement
use Illuminate\Support\Facades\Log; // Aggiungi questo use statement
use App\Http\View\Composers\NavigationComposer; // Namespace e nome classe corretti

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }
    
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        View::composer(
            'layouts.navigation', // La vista a cui legare il composer
            NavigationComposer::class // Il nome completo della classe del composer
            );
        /*
        DB::listen(function ($query) {
            Log::debug($query->sql, $query->bindings);
        });
        */
    }
}