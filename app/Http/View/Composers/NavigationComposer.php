<?php

namespace App\Http\View\Composers;

use Illuminate\View\View;
use App\Models\ImpostazioneLega; // Assicurati di importare il modello ImpostazioneLega
use Illuminate\Support\Facades\Log; // Opzionale, per debug

class NavigationComposer
{
    protected $impostazioniLega;
    protected $mostraLinkAstaLive;
    
    /**
     * Crea una nuova istanza del composer del profilo.
     *
     * @return void
     */
    public function __construct()
    {
        $this->impostazioniLega = ImpostazioneLega::first();
        $this->mostraLinkAstaLive = false; // Inizializza a false
        
        if ($this->impostazioniLega) {
            // CONDIZIONE AGGIORNATA:
            // Il link si mostra solo se la modalit� � 'tap' E si � in una fase d'asta P, D, C, o A.
            if ($this->impostazioniLega->modalita_asta === 'tap' &&
                in_array($this->impostazioniLega->fase_asta_corrente, ['P', 'D', 'C', 'A'])) {
                    $this->mostraLinkAstaLive = true;
                }
        } else {
            // Se le impostazioni non esistono, il link non viene mostrato.
            // Potresti voler loggare un avviso qui se le impostazioni sono cruciali e mancano.
            Log::warning('[NavigationComposer] ImpostazioniLega non trovate. Il link Asta Live non sar� visibile.');
        }
        
        // Log::info('[NavigationComposer] Costruttore eseguito. mostraLinkAstaLive: ' . ($this->mostraLinkAstaLive ? 'S�' : 'No') . '; Modalita Asta: ' . optional($this->impostazioniLega)->modalita_asta . '; Fase Asta: ' . optional($this->impostazioniLega)->fase_asta_corrente);
    }
    
    /**
     * Lega i dati alla vista.
     *
     * @param  \Illuminate\View\View  $view
     * @return void
     */
    public function compose(View $view)
    {
        $view->with('mostraLinkAstaLiveGlobal', $this->mostraLinkAstaLive);
        // Se vuoi passare l'intero oggetto delle impostazioni per altre logiche nel menu:
        // $view->with('impostazioniLegaGlobali', $this->impostazioniLega);
    }
}