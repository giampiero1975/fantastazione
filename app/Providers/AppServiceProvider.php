<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\View;         // Facade corretta
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
    }
}