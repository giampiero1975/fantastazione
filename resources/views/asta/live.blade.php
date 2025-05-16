<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Asta Live') }}
            @if(isset($impostazioniLega))
                - Fase: <span class="font-bold" id="fase-asta-display">{{ $impostazioniLega->fase_asta_corrente }}</span>
                @if($impostazioniLega->tag_lista_attiva) (Lista: <span class="font-bold" id="tag-lista-display">{{ $impostazioniLega->tag_lista_attiva }}</span>) @endif
            @else
                <span class="font-bold text-red-500"> {{ __('(Impostazioni Lega non caricate!)') }}</span>
            @endif
        </h2>
    </x-slot>

    <div class="py-6 md:py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">

                    {{-- Messaggio Globale per Esito Asta o Errori Generali --}}
                    <div id="messaggi-globali-asta" class="mb-6 text-center h-auto min-h-[2.5rem] p-3 rounded-lg text-sm">
                        @if (session('success')) <div class="text-green-700 bg-green-100 dark:bg-green-800 dark:text-green-200 p-3 rounded-lg">{{ session('success') }}</div> @endif
                        @if (session('error')) <div class="text-red-700 bg-red-100 dark:bg-red-800 dark:text-red-200 p-3 rounded-lg">{{ session('error') }}</div> @endif
                    </div>

                    @if(isset($impostazioniLega))
                        <div class="mb-4 text-center md:text-left">
                            <p><strong>{{ __('Modalità Asta:') }}</strong>
                                <span class="font-semibold {{ $impostazioniLega->modalita_asta === 'tap' ? 'text-blue-600 dark:text-blue-400' : 'text-orange-600 dark:text-orange-400' }}" id="modalita-asta-display">
                                    {{ $impostazioniLega->modalita_asta === 'tap' ? __('A Rilancio TAP (con countdown)') : __('A Voce (Admin assegna)') }}
                                </span>
                            </p>
                            <p><strong>{{ __('Crediti Rimanenti Personali:') }}</strong> <span class="font-semibold" id="crediti-personali-display">{{ $creditiRimanenti ?? 'N/D' }}</span></p>
                        </div>
                        <hr class="my-6 dark:border-gray-700">

                        {{-- Sezione Asta TAP --}}
                        @if ($impostazioniLega->modalita_asta === 'tap')
                            {{-- Contenitore per l'asta TAP, l'ID è usato da JS --}}
                            <div id="asta-container" 
                                 data-chiamata-id="{{ (isset($astaTapAttiva) && $astaTapAttiva) ? $astaTapAttiva->id : '' }}"
                                 class="{{ (isset($astaTapAttiva) && $astaTapAttiva && isset($calciatoreInAsta)) ? '' : 'hidden' }}">
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8 items-start">
                                    {{-- Colonna Sinistra: DATI ASTA --}}
                                    <div class="md:col-span-2 space-y-5 p-4 border border-gray-300 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                        <div>
                                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider">{{ __('Calciatore all\'Asta') }}</p>
                                            <h3 class="text-2xl md:text-3xl font-bold text-indigo-700 dark:text-indigo-400" id="calciatore-nome-display">{{ $calciatoreInAsta->nome_completo ?? 'N/D' }}</h3>
                                            <p class="text-sm text-gray-600 dark:text-gray-300" id="calciatore-dettagli-display">
                                                @if(isset($calciatoreInAsta))
                                                    {{ $calciatoreInAsta->ruolo }} - {{ $calciatoreInAsta->squadra_serie_a }}
                                                @endif
                                            </p>
                                        </div>
                                        <hr class="dark:border-gray-600">
                                        <div>
                                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider">{{ __('Prezzo Partenza') }}</p>
                                            <p class="text-xl font-semibold" id="prezzo-partenza-display">{{ $astaTapAttiva->prezzo_partenza_tap ?? '--' }}</p>
                                        </div>
                                        <hr class="dark:border-gray-600">
                                        <div>
                                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider">{{ __('Migliore Offerta') }}</p>
                                            <p class="text-xl font-semibold" id="miglior-offerente-wrapper">
                                                <span class="text-green-600 dark:text-green-400" id="prezzo-attuale-valore">{{ $astaTapAttiva->prezzo_attuale_tap ?? '--' }}</span>
                                                <span id="miglior-offerente-nome">
                                                    @if($migliorOfferente)
                                                        da {{ $migliorOfferente->name }}
                                                    @elseif(isset($astaTapAttiva) && isset($astaTapAttiva->utenteChiamante) && $astaTapAttiva->miglior_offerente_tap_id == $astaTapAttiva->user_id_chiamante)
                                                        da {{ $astaTapAttiva->utenteChiamante->name }} (chiamata)
                                                    @else
                                                        {{ __(' (Nessuna offerta superiore)') }}
                                                    @endif
                                                </span>
                                            </p>
                                        </div>
                                    </div>

                                    {{-- Colonna Destra: TAP (Pulsanti di Rilancio) --}}
                                    <div class="md:col-span-1 space-y-3 flex flex-col justify-center">
                                        <h4 class="text-lg font-semibold mb-2 text-center">{{ __('Fai la tua Offerta') }}</h4>
                                        @php $incrementi = [1, 5, 10, 30]; @endphp
                                        @foreach($incrementi as $inc)
                                            <button type="button" data-rilancio="{{ $inc }}"
                                                    class="rilancio-btn w-full justify-center text-lg font-bold py-3 px-6 rounded-full text-white focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150
                                                    @switch($inc)
                                                        @case(1) bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500 @break
                                                        @case(5) bg-blue-500 hover:bg-blue-600 focus:ring-blue-500 @break
                                                        @case(10) bg-yellow-500 hover:bg-yellow-600 text-black focus:ring-yellow-500 @break
                                                        @case(30) bg-orange-500 hover:bg-orange-600 focus:ring-orange-500 @break
                                                    @endswitch
                                                    {{-- La disabilitazione per admin è stata rimossa, come da tua richiesta --}}
                                                    ">
                                                +{{ $inc }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                {{-- Tempo Rimanente (sotto le due colonne) --}}
                                <div class="text-center mt-8 p-4 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                    <p class="text-md uppercase text-gray-600 dark:text-gray-300 tracking-wider">{{ __('Tempo Rimanente') }}</p>
                                    <p class="text-5xl md:text-6xl font-bold text-red-600 dark:text-red-400 my-2" id="countdown-timer"
                                       data-timestamp-fine="{{ (isset($astaTapAttiva) && $astaTapAttiva->timestamp_fine_tap_prevista) ? ($astaTapAttiva->timestamp_fine_tap_prevista instanceof \Carbon\Carbon ? $astaTapAttiva->timestamp_fine_tap_prevista->timestamp : strtotime($astaTapAttiva->timestamp_fine_tap_prevista)) : '' }}">
                                        --:--
                                    </p>
                                </div>
                                <div id="messaggi-rilancio-specifici" class="text-center text-sm mt-4 h-6"></div>
                            </div>
                            
                            {{-- Messaggio da mostrare se non c'è un'asta TAP attiva --}}
                            <div id="nessuna-asta-tap-attiva" class="{{ (isset($astaTapAttiva) && $astaTapAttiva && isset($calciatoreInAsta)) ? 'hidden' : '' }}">
                                <p class="text-center text-gray-600 dark:text-gray-400 py-8">{{ __('Nessuna asta TAP attualmente attiva o nessun giocatore chiamato per l\'asta TAP.') }}</p>
                                <p class="text-center mt-4">
                                    <a href="{{ route('asta.calciatori.disponibili') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ __('Vai alla lista giocatori per chiamarne uno') }}
                                    </a>
                                </p>
                            </div>

                        @elseif ($impostazioniLega->modalita_asta === 'voce')
                             <div class="text-center py-8">
                                <h3 class="text-xl font-semibold mb-2">{{ __('Modalità Asta: A Voce') }}</h3>
                                <p class="text-gray-600 dark:text-gray-400">{{ __('L\'asta si svolge a voce. Seguire le indicazioni dell\'amministratore.') }}</p>
                                <p class="mt-4 text-gray-600 dark:text-gray-400">{{ __('L\'admin registrerà manualmente gli acquisti.') }}</p>
                                <p class="text-center mt-4">
                                    <a href="{{ route('asta.calciatori.disponibili') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ __('Visualizza Lista Giocatori di Riferimento') }}
                                    </a>
                                </p>
                            </div>
                        @else
                             <p class="text-center text-gray-600 dark:text-gray-400 py-8">{{ __('Modalità asta non definita correttamente.') }}</p>
                        @endif
                    @else
                        <p class="text-center text-gray-600 dark:text-gray-400 py-8">{{ __('Impostazioni della lega non disponibili.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('[ASTA LIVE SCRIPT V-POLLING-FINALE] Caricato');

    // Elementi DOM principali
    const countdownElement = document.getElementById('countdown-timer');
    const astaContainerEl = document.getElementById('asta-container'); // Contiene data-chiamata-id
    const nessunaAstaTapDivEl = document.getElementById('nessuna-asta-tap-attiva');
    const prezzoAttualeValEl = document.getElementById('prezzo-attuale-valore');
    const migliorOfferenteNomeEl = document.getElementById('miglior-offerente-nome');
    const messaggiGlobaliDiv = document.getElementById('messaggi-globali-asta');
    const messaggiRilancioDiv = document.getElementById('messaggi-rilancio-specifici');
    const bottoniRilancio = document.querySelectorAll('.rilancio-btn');
    const calciatoreNomeDisplayEl = document.getElementById('calciatore-nome-display');
    const calciatoreDettagliDisplayEl = document.getElementById('calciatore-dettagli-display');
    const prezzoPartenzaDisplayEl = document.getElementById('prezzo-partenza-display');

    // Stato e ID
    let chiamataAstaIdCorrente = null;
    if (astaContainerEl && astaContainerEl.dataset.chiamataId && astaContainerEl.dataset.chiamataId !== '') {
        chiamataAstaIdCorrente = parseInt(astaContainerEl.dataset.chiamataId);
    }
    let timestampFinePerCountdown = 0;
    if (countdownElement && countdownElement.dataset.timestampFine && countdownElement.dataset.timestampFine !== '') {
        const parsedTimestamp = parseInt(countdownElement.dataset.timestampFine);
        if (!isNaN(parsedTimestamp) && parsedTimestamp > 0) {
            timestampFinePerCountdown = parsedTimestamp * 1000;
        }
    }
    const currentUserId = {{ Auth::id() ?? 'null' }};
    let astaCountdownInterval = null;
    let astaPollingInterval = null;
    let astaFinalizzataClient = false;

    console.log('[JS Init] ID Asta:', chiamataAstaIdCorrente, 'TS Fine (ms):', timestampFinePerCountdown, 'User ID:', currentUserId);

    // --- FUNZIONE CENTRALE PER AGGIORNARE L'INTERFACCIA UTENTE ---
    function aggiornaInterfacciaConDatiServer(data) {
        if (!data) { console.warn("aggiornaInterfacciaConDatiServer: no data received."); return; }
        console.log("[UI Update] Dati da server:", data);

        if (data.status === 'in_asta_tap_live' && (data.chiamata_id || chiamataAstaIdCorrente)) {
            const idAstaUi = data.chiamata_id || chiamataAstaIdCorrente;
            if(astaContainerEl) {
                astaContainerEl.classList.remove('hidden');
                astaContainerEl.dataset.chiamataId = idAstaUi; 
                chiamataAstaIdCorrente = idAstaUi;
            }
            if(nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.add('hidden');
            
            if(calciatoreNomeDisplayEl && data.calciatore_nome) calciatoreNomeDisplayEl.textContent = data.calciatore_nome;
            if(calciatoreDettagliDisplayEl && data.calciatore_ruolo && data.calciatore_squadra_serie_a) {
                calciatoreDettagliDisplayEl.textContent = `${data.calciatore_ruolo} - ${data.calciatore_squadra_serie_a}`;
            }
            if(prezzoPartenzaDisplayEl && data.prezzo_partenza_tap !== undefined) prezzoPartenzaDisplayEl.textContent = data.prezzo_partenza_tap;
            if(prezzoAttualeValEl) prezzoAttualeValEl.textContent = data.prezzo_attuale !== null ? data.prezzo_attuale : '--';
            if(migliorOfferenteNomeEl) migliorOfferenteNomeEl.textContent = data.miglior_offerente ? `da ${data.miglior_offerente}` : ' (Nessuna offerta)';
            
            bottoniRilancio.forEach(btn => {
                btn.disabled = (data.miglior_offerente_id === currentUserId); 
            });
            
            if (messaggiGlobaliDiv && !data.messaggio_esito) messaggiGlobaliDiv.innerHTML = '';

            if(countdownElement && data.timestamp_fine_prevista_unix !== undefined) { // Controlla se la chiave esiste
                const serverTimestampFineUnix = data.timestamp_fine_prevista_unix;
                console.log('[Polling UI] Ricevuto timestamp_fine_prevista_unix dal server:', serverTimestampFineUnix);
                if (serverTimestampFineUnix !== null && !isNaN(parseInt(serverTimestampFineUnix))) {
                    const serverTimestampFineMs = parseInt(serverTimestampFineUnix) * 1000;
                    if (timestampFinePerCountdown !== serverTimestampFineMs || astaFinalizzataClient) {
                        console.log('[Polling UI] Timestamp fine cambiato o asta resettata. Vecchio(ms):', timestampFinePerCountdown, 'Nuovo(ms):', serverTimestampFineMs);
                        avviaOAggiornaCountdown(serverTimestampFineUnix); // Passa il timestamp Unix (secondi)
                    }
                } else {
                    console.warn('[Polling UI] Timestamp fine dal server è null o non valido. Non aggiorno countdown.');
                    // Potresti voler fermare il countdown se il server non dà più un tempo valido
                    // if(astaCountdownInterval) clearInterval(astaCountdownInterval);
                    // if(countdownElement) countdownElement.textContent = "--:--";
                }
            }
        } else { 
            if(astaPollingInterval) clearInterval(astaPollingInterval); window.astaPollingInterval = null;
            if(astaCountdownInterval) clearInterval(astaCountdownInterval); astaCountdownInterval = null;
            if(countdownElement) countdownElement.textContent = "ASTA CONCLUSA";
            bottoniRilancio.forEach(btn => btn.disabled = true);

            if(messaggiGlobaliDiv) {
                let displayMessage = data.messaggio_esito || (data.status === 'non_attiva' ? 'Nessuna asta TAP attiva.' : 'Asta terminata.');
                let msgClass = 'text-gray-500'; 
                if (data.status === 'conclusa_tap_assegnato') msgClass = 'text-green-700 bg-green-100 dark:bg-green-800 dark:text-green-200 p-3 rounded-lg font-bold';
                else if (data.status === 'conclusa_tap_non_assegnato' || (data.status && data.status.includes('errore'))) msgClass = 'text-orange-700 bg-orange-100 dark:bg-orange-800 dark:text-orange-200 p-3 rounded-lg';
                messaggiGlobaliDiv.innerHTML = `<span class="${msgClass}">${displayMessage}</span>`;
            }
            astaFinalizzataClient = true; 
            if(astaContainerEl) astaContainerEl.classList.add('hidden'); 
            if(nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.remove('hidden');
        }
    }

    // --- FUNZIONE COUNTDOWN ---
    function updateCountdown() {
        if (astaFinalizzataClient || !timestampFinePerCountdown || timestampFinePerCountdown <= 0) {
            if(countdownElement) countdownElement.textContent = astaFinalizzataClient ? "ASTA CONCLUSA" : "--:--";
            if(astaCountdownInterval) clearInterval(astaCountdownInterval);
            return;
        }
        const now = new Date().getTime();
        const distance = timestampFinePerCountdown - now;

        if (distance < 0) {
            if(countdownElement) countdownElement.textContent = "TEMPO SCADUTO - Finalizzazione...";
            if(astaCountdownInterval) clearInterval(astaCountdownInterval);
            const idAstaDaFinalizzare = astaContainerEl && astaContainerEl.dataset.chiamataId ? parseInt(astaContainerEl.dataset.chiamataId) : null;
            if (!astaFinalizzataClient && idAstaDaFinalizzare) {
                astaFinalizzataClient = true; 
                finalizzaAstaSulServer(idAstaDaFinalizzare);
            }
            return;
        }
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        if(countdownElement) countdownElement.textContent = (minutes < 10 ? "0" : "") + minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
    }

    function avviaOAggiornaCountdown(nuovoTimestampFineUnixServer) {
    	if (nuovoTimestampFineUnixServer !== undefined && nuovoTimestampFineUnixServer !== null && !isNaN(parseInt(nuovoTimestampFineUnixServer))) { 
            timestampFinePerCountdown = parseInt(nuovoTimestampFineUnixServer) * 1000;
            if (countdownElement) countdownElement.dataset.timestampFine = nuovoTimestampFineUnixServer; // Salva il timestamp in secondi
            console.log('[Countdown JS] Timestamp aggiornato a (Unix):', nuovoTimestampFineUnixServer, 'equivale a (ms):', timestampFinePerCountdown);
        } else {
            // Se il timestamp non è valido, potremmo voler resettare il countdown a --:--
            // timestampFinePerCountdown = 0; // O lasciare il valore precedente
            // console.warn('[Countdown JS] Tentativo di aggiornare con timestamp non valido:', nuovoTimestampFineUnixServer);
        }
        
        if(astaCountdownInterval) clearInterval(astaCountdownInterval); 
        
        if (countdownElement && timestampFinePerCountdown > 0) {
            astaFinalizzataClient = false; 
            updateCountdown(); 
            astaCountdownInterval = setInterval(updateCountdown, 1000);
            console.log('[Countdown JS] Avviato/Resettato. Nuova Fine (ms):', timestampFinePerCountdown);
        } else if (countdownElement) {
             countdownElement.textContent = "--:--";
        }
    }
    
    // --- LOGICA PER I RILANCI TAP ---
    bottoniRilancio.forEach(button => {
        button.addEventListener('click', function() {
            if (this.hasAttribute('disabled')) return; 
            const idAstaPerRilancio = astaContainerEl && astaContainerEl.dataset.chiamataId ? parseInt(astaContainerEl.dataset.chiamataId) : null;
            if (!idAstaPerRilancio) {
                if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-red-500 dark:text-red-300">${'Errore: ID Asta non disponibile.'}</span>`;
                return;
            }
            const incremento = this.dataset.rilancio;
            if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-blue-500 dark:text-blue-300">${'Invio offerta...'}</span>`;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (!csrfToken) { /* ... gestione errore CSRF ... */ return; }

            fetch(`{{ url('/asta/tap/rilancia') }}/${idAstaPerRilancio}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ importo_rilancio: parseInt(incremento) })
            })
            .then(response => {
                if (response.status === 419) throw new Error('Sessione scaduta. Ricarica.');
                if (!response.ok) return response.json().then(errData => { throw errData; });
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-green-500 dark:text-green-300">${data.message || 'Offerta OK!'}</span>`;
                    // Non chiamare aggiornaInterfacciaConDatiServer qui direttamente con i dati del rilancio.
                    // Il polling prenderà i dati aggiornati dal server e sincronizzerà l'UI per tutti.
                    // Per un feedback più immediato SOLO all'utente che ha rilanciato, potresti fare un aggiornamento parziale,
                    // ma è più semplice affidarsi al prossimo ciclo di polling.
                    // Se vuoi un feedback immediato sul timer resettato:
                    if(data.timestamp_fine_tap_prevista_unix !== undefined) {
                        avviaOAggiornaCountdown(data.timestamp_fine_tap_prevista_unix); 
                    }
                } else { 
                    if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-red-500 dark:text-red-300">${'Errore'}: ${data.error || 'Offerta non valida.'}</span>`;
                }
            })
            .catch(error => { 
                console.error('[Rilancio JS] Catch Errore Fetch:', error);
                let errorMessage = 'Errore di connessione o del server.'; // Messaggio di default

                // Controlla se 'error' è l'oggetto JSON che ci aspettiamo dal server
                // in caso di errore di validazione (es. status 400 o 422)
                if (typeof error === 'object' && error !== null && error.error) {
                    errorMessage = error.error; // Usa il messaggio di errore specifico dal server
                } else if (error && error.message) { 
                    // Altri tipi di errore (es. errore di rete, errore CSRF personalizzato)
                    errorMessage = error.message;
                }

                if(messaggiRilancioDiv) {
                    messaggiRilancioDiv.innerHTML = `<span class="text-red-500 dark:text-red-300">${errorMessage}</span>`;
                } else {
                    // Fallback se messaggiRilancioDiv non esiste, anche se dovrebbe
                    alert(`Errore: ${errorMessage}`);
                }
            });
        });
    });

    // --- LOGICA PER FINALIZZARE L'ASTA ---
    function finalizzaAstaSulServer(chiamataId) { /* ... (come la versione precedente) ... */
        if (!chiamataId) { /* ... */ return; }
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if(!csrfToken) { /* ... */ return; }
        if(messaggiGlobaliDiv) messaggiGlobaliDiv.innerHTML = `<span class="text-blue-500 dark:text-blue-300">${'Finalizzazione...'}</span>`;
        fetch(`{{ url('/asta/tap/finalizza') }}/${chiamataId}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            aggiornaInterfacciaConDatiServer(data); // Usa la funzione centrale per mostrare l'esito
        })
        .catch(error => console.error('Errore durante la finalizzaAstaSulServer:', error));
    }

    // --- IMPLEMENTAZIONE POLLING AJAX ---
    function startPollingAsta(idAstaDaPollare) {
        if (!idAstaDaPollare) { console.warn('[Polling] Non avviato: ID chiamata mancante.'); return; }
        if (window.astaPollingInterval) { console.log('[Polling] Già attivo.'); return; }
        
        console.log('[Polling] Avvio per asta ID:', idAstaDaPollare);
        window.astaPollingInterval = setInterval(function() {
            const currentAstaIdOnPage = astaContainerEl && astaContainerEl.dataset.chiamataId ? parseInt(astaContainerEl.dataset.chiamataId) : null;
            if (astaFinalizzataClient || !currentAstaIdOnPage || currentAstaIdOnPage !== idAstaDaPollare) { 
                if(window.astaPollingInterval) clearInterval(window.astaPollingInterval);
                window.astaPollingInterval = null; 
                return;
            }

            fetch(`{{ route('asta.tap.stato', '') }}/${idAstaDaPollare}`)
                .then(response => {
                    if (!response.ok) {
                        if (response.status === 404) {
                            aggiornaInterfacciaConDatiServer({ status: 'non_attiva', messaggio_esito: 'Asta non più disponibile (404).' });
                        }
                        throw new Error(`Errore HTTP Polling! ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data) return; 
                    aggiornaInterfacciaConDatiServer(data);

                    if (data.status === 'in_asta_tap_live' && data.secondi_rimanenti <= 0 && !astaFinalizzataClient) {
                        astaFinalizzataClient = true;
                        finalizzaAstaSulServer(idAstaDaPollare);
                    }
                })
                .catch(error => console.error('Errore durante il polling:', error));
        }, 1000); // Poll ogni 1 secondo
    }
    
    // Avvio Iniziale Countdown e Polling
    const isModalitaTapJS = "{{ (isset($impostazioniLega) && $impostazioniLega->modalita_asta === 'tap') ? 'true' : 'false' }}" === 'true';
    const isAstaLiveInCorsoJS = "{{ (isset($astaTapAttiva) && $astaTapAttiva && $astaTapAttiva->stato_chiamata === 'in_asta_tap_live') ? 'true' : 'false' }}" === 'true';

    if (chiamataAstaIdCorrente && isModalitaTapJS && isAstaLiveInCorsoJS) {
        console.log('[Init JS] Asta TAP live rilevata. Avvio countdown e polling.');
        avviaOAggiornaCountdown(); // Usa il timestampFinePerCountdown letto dal data-attribute
        startPollingAsta(chiamataAstaIdCorrente); 
    } else {
        console.warn('[Init JS] Nessun countdown o polling avviato. Dettagli:', {id: chiamataAstaIdCorrente, tap: isModalitaTapJS, live: isAstaLiveInCorsoJS, tsMs: timestampFinePerCountdown});
        if(astaContainerEl) astaContainerEl.classList.add('hidden');
        if(nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.remove('hidden');
    }
});
</script>
@endpush
</x-app-layout>