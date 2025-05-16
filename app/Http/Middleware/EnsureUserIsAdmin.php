<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Support\Facades\Auth; // Importa la facade Auth

class EnsureUserIsAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Controlla se l'utente è autenticato E se ha il flag is_admin a true
        if (!Auth::check() || !Auth::user()->is_admin) {
            // Se non è admin, puoi decidere cosa fare:
            // Opzione 1: Reindirizzare (es. alla dashboard normale o home)
            // return redirect('/dashboard')->with('error', 'Accesso non autorizzato.');
            
            // Opzione 2: Mostrare un errore 403 Forbidden
            abort(403, 'ACCESSO NON AUTORIZZATO.');
        }
        
        // Se l'utente è admin, prosegui con la richiesta originale
        return $next($request);
    }
}
