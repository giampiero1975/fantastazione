<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Asta Live') }}
            @if(isset($impostazioniLega))
                - Fase: <span class="font-bold" id="fase-asta-display">{{ $impostazioniLega->fase_asta_corrente }}</span>
                @if($impostazioniLega->tag_lista_attiva)
                    (Lista: <span class="font-bold" id="tag-lista-display">{{ $impostazioniLega->tag_lista_attiva }}</span>)
                @endif
                @if($impostazioniLega->usa_ordine_chiamata && isset($prossimoChiamanteNome))
                    <span class="text-sm ml-4">Prossimo a chiamare: <span class="font-bold">{{ $prossimoChiamanteNome }}</span></span>
                @elseif($impostazioniLega->usa_ordine_chiamata && !isset($chiamataDaMostrare) && !isset($prossimoChiamanteNome))
                    <span class="text-sm ml-4">Prossimo a chiamare: <span class="font-bold italic">
                        @if(isset($prossimoChiamanteNome)) {{ $prossimoChiamanteNome }} @else {{ __('Da definire') }} @endif
                    </span></span>
                @endif
            @else
                <span class="font-bold text-red-500"> {{ __('(Impostazioni Lega non caricate!)') }}</span>
            @endif
        </h2>
    </x-slot>

    <div class="py-6 md:py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">

                    <div id="messaggi-globali-asta" class="mb-6 text-center min-h-[2.5rem] p-1 text-sm">
                        @if (session('success')) <div class="text-green-700 bg-green-100 dark:bg-green-800 dark:text-green-200 p-3 rounded-lg">{{ session('success') }}</div> @endif
                        @if (session('error')) <div class="text-red-700 bg-red-100 dark:bg-red-800 dark:text-red-200 p-3 rounded-lg">{{ session('error') }}</div> @endif
                    </div>

                    @if(isset($impostazioniLega))
                        <div class="mb-4 text-center md:text-left">
                            <p><strong>{{ __('Modalità Asta:') }}</strong>
                                <span class="font-semibold {{ $impostazioniLega->modalita_asta === 'tap' ? 'text-blue-600 dark:text-blue-400' : 'text-orange-600 dark:text-orange-400' }}" id="modalita-asta-display">
                                    {{ $impostazioniLega->modalita_asta === 'tap' ? __('A Rilancio TAP') : __('A Voce (Admin assegna)') }}
                                    @if($impostazioniLega->modalita_asta === 'tap' && $impostazioniLega->asta_tap_approvazione_admin)
                                        <span class="text-xs italic"> (con approvazione admin)</span>
                                    @elseif($impostazioniLega->modalita_asta === 'tap')
                                        <span class="text-xs italic"> (avvio automatico)</span>
                                    @endif
                                </span>
                            </p>
                            @auth
                            <p><strong>{{ __('Crediti Personali:') }}</strong> <span class="font-semibold" id="crediti-personali-display">{{ $creditiRimanenti ?? 'N/D' }}</span></p>
                            @endauth
                        </div>
                        <hr class="my-6 dark:border-gray-700">

                        @if ($impostazioniLega->modalita_asta === 'tap')
                            @php
                                $astaTapEffettivamenteLive = (isset($chiamataDaMostrare) && $chiamataDaMostrare->stato_chiamata === 'in_asta_tap_live' && isset($calciatoreInAsta));
                                $idChiamataPerPollingInit = optional($chiamataDaMostrare)->id ?? '';
                                $timestampFinePerCountdownInit = '';
                                if ($astaTapEffettivamenteLive && $chiamataDaMostrare->timestamp_fine_tap_prevista) {
                                    try {
                                        $timestampFinePerCountdownInit = \Carbon\Carbon::parse($chiamataDaMostrare->timestamp_fine_tap_prevista)->timestamp;
                                    } catch (\Exception $e) { $timestampFinePerCountdownInit = 'ErroreParseTS'; }
                                }
                            @endphp

                            <div id="asta-container"
                                 data-chiamata-id="{{ $idChiamataPerPollingInit }}"
                                 class="{{ $astaTapEffettivamenteLive ? '' : 'hidden' }}">
                                
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8 items-start">
                                    <div class="md:col-span-2 space-y-5 p-4 border border-gray-300 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                        <div>
                                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider">{{ __('Calciatore all\'Asta') }}</p>
                                            <h3 class="text-2xl md:text-3xl font-bold text-indigo-700 dark:text-indigo-400" id="calciatore-nome-display">
                                                {{ $astaTapEffettivamenteLive ? (optional($calciatoreInAsta)->nome_completo ?? 'N/D') : 'N/D' }}
                                            </h3>
                                            <p class="text-sm text-gray-600 dark:text-gray-300" id="calciatore-dettagli-display">
                                                @if($astaTapEffettivamenteLive && isset($calciatoreInAsta))
                                                    {{ $calciatoreInAsta->ruolo }} - {{ $calciatoreInAsta->squadra_serie_a }}
                                                @else - @endif
                                            </p>
                                        </div>
                                        <hr class="dark:border-gray-600">
                                        <div>
                                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider">{{ __('Prezzo Partenza') }}</p>
                                            <p class="text-xl font-semibold" id="prezzo-partenza-display">
                                                {{ $astaTapEffettivamenteLive ? (optional($chiamataDaMostrare)->prezzo_partenza_tap ?? '--') : '--' }}
                                            </p>
                                        </div>
                                        <hr class="dark:border-gray-600">
                                        <div>
                                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider">{{ __('Migliore Offerta') }}</p>
                                            <p class="text-xl font-semibold" id="miglior-offerente-wrapper">
                                                <span class="text-green-600 dark:text-green-400" id="prezzo-attuale-valore">
                                                    {{ $astaTapEffettivamenteLive ? (optional($chiamataDaMostrare)->prezzo_attuale_tap ?? '--') : '--' }}
                                                </span>
                                                <span id="miglior-offerente-nome">
                                                    @if($astaTapEffettivamenteLive && isset($migliorOfferente))
                                                        da {{ $migliorOfferente->name }}
                                                    @elseif($astaTapEffettivamenteLive && isset($chiamataDaMostrare) && $chiamataDaMostrare->miglior_offerente_tap_id == optional($chiamataDaMostrare->utenteChiamante)->id)
                                                        da {{ optional($chiamataDaMostrare->utenteChiamante)->name }} (chiamata)
                                                    @elseif($astaTapEffettivamenteLive)
                                                        {{ __(' (Nessuna offerta superiore)') }}
                                                    @else - @endif
                                                </span>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="md:col-span-1 space-y-3 flex flex-col justify-center">
                                        <h4 class="text-lg font-semibold mb-2 text-center">{{ __('Fai la tua Offerta') }}</h4>
                                        @php $incrementi = [1, 5, 10, 30]; @endphp
                                        @foreach($incrementi as $inc)
                                            <button type="button" data-rilancio="{{ $inc }}"
                                                    class="rilancio-btn w-full justify-center text-lg font-bold py-3 px-6 rounded-full text-white focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 @switch($inc) @case(1) bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500 @break @case(5) bg-blue-500 hover:bg-blue-600 focus:ring-blue-500 @break @case(10) bg-yellow-500 hover:bg-yellow-600 text-black focus:ring-yellow-500 @break @case(30) bg-orange-500 hover:bg-orange-600 focus:ring-orange-500 @break @endswitch">
                                                +{{ $inc }}
                                            </button>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="text-center mt-8 p-4 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                    <p class="text-md uppercase text-gray-600 dark:text-gray-300 tracking-wider">{{ __('Tempo Rimanente') }}</p>
                                    <p class="text-5xl md:text-6xl font-bold text-red-600 dark:text-red-400 my-2" id="countdown-timer"
                                       data-timestamp-fine="{{ $timestampFinePerCountdownInit }}">
                                       {{-- Il JS popola questo --}}
                                    </p>
                                </div>
                                <div id="messaggi-rilancio-specifici" class="text-center text-sm mt-4 min-h-[1.5rem]"></div>
                            </div>
                            
                            <div id="nessuna-asta-tap-attiva" class="{{ $astaTapEffettivamenteLive ? 'hidden' : '' }}">
                                <p id="testo-placeholder-nessuna-asta" class="text-center text-gray-600 dark:text-gray-400 py-8">
                                    {{-- Questo sarà aggiornato dal JS --}}
                                </p>
                                @if (!isset($chiamataDaMostrare) || !in_array(optional($chiamataDaMostrare)->stato_chiamata, ['in_asta_tap_live', 'in_attesa_admin']))
                                <p class="text-center mt-4">
                                    <a href="{{ route('asta.calciatori.disponibili') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        {{ __('Vai alla lista giocatori per chiamarne uno') }}
                                    </a>
                                </p>
                                @endif
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
                             <p class="text-center text-gray-600 dark:text-gray-400 py-8">{{ __('Modalità asta non definita o impostazioni mancanti.') }}</p>
                        @endif
                    @else
                        <p class="text-center text-gray-600 dark:text-gray-400 py-8">{{ __('Impostazioni della lega non caricate. Contattare l\'admin.') }}</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('[ASTA LIVE SCRIPT v.FixUrlGen.Final.Revised] Caricato');

    const astaContainerEl = document.getElementById('asta-container');
    const nessunaAstaTapDivEl = document.getElementById('nessuna-asta-tap-attiva');
    const testoPlaceholderNessunaAstaEl = document.getElementById('testo-placeholder-nessuna-asta');
    const countdownElement = document.getElementById('countdown-timer');
    const prezzoAttualeValEl = document.getElementById('prezzo-attuale-valore');
    const migliorOfferenteNomeEl = document.getElementById('miglior-offerente-nome');
    const messaggiGlobaliDiv = document.getElementById('messaggi-globali-asta');
    const messaggiRilancioDiv = document.getElementById('messaggi-rilancio-specifici');
    const bottoniRilancio = document.querySelectorAll('.rilancio-btn');
    const calciatoreNomeDisplayEl = document.getElementById('calciatore-nome-display');
    const calciatoreDettagliDisplayEl = document.getElementById('calciatore-dettagli-display');
    const prezzoPartenzaDisplayEl = document.getElementById('prezzo-partenza-display');
    const creditiPersonaliDisplayEl = document.getElementById('crediti-personali-display');

    let idChiamataPerPolling = astaContainerEl ? astaContainerEl.dataset.chiamataId : null;
    let timestampFinePerCountdown = countdownElement && countdownElement.dataset.timestampFine && countdownElement.dataset.timestampFine !== 'ErroreParseTS' ? parseInt(countdownElement.dataset.timestampFine) * 1000 : 0;
    
    const currentUserId = {{ Auth::id() ?? 'null' }};
    let astaCountdownInterval = null;
    let astaPollingInterval = null;
    let astaFinalizzataClient = false;

    // URL base per le chiamate AJAX (costruiti con url() di Blade, sicuro)
    const URL_BASE_STATO = "{{ url('/asta/tap/stato') }}";
    const URL_BASE_RILANCIA = "{{ url('/asta/tap/rilancia') }}";
    const URL_BASE_FINALIZZA = "{{ url('/asta/tap/finalizza') }}";

    console.log('[JS Init] ID Chiamata per Polling (da data-att):', idChiamataPerPolling);
    console.log('[JS Init] Timestamp Fine Iniziale (ms):', timestampFinePerCountdown);

    function resetInterfacciaPerNuovaAsta() {
        console.log("[UI Reset] Resetto interfaccia per cambio/nuova asta.");
        if(astaContainerEl) astaContainerEl.classList.add('hidden');
        if(nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.remove('hidden');
        if(testoPlaceholderNessunaAstaEl) testoPlaceholderNessunaAstaEl.textContent = "Caricamento nuova asta...";
        // NON pulire messaggiGlobaliDiv qui, aspettiamo i dati della nuova asta o esito

        if(calciatoreNomeDisplayEl) calciatoreNomeDisplayEl.textContent = 'In attesa...';
        if(calciatoreDettagliDisplayEl) calciatoreDettagliDisplayEl.textContent = '-';
        if(prezzoPartenzaDisplayEl) prezzoPartenzaDisplayEl.textContent = '--';
        if(prezzoAttualeValEl) prezzoAttualeValEl.textContent = '--';
        if(migliorOfferenteNomeEl) migliorOfferenteNomeEl.textContent = '-';
        if(countdownElement) {
            countdownElement.textContent = '--:--';
            countdownElement.dataset.timestampFine = '';
        }
        if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = '';
        
        timestampFinePerCountdown = 0;
        if(astaCountdownInterval) clearInterval(astaCountdownInterval);
        astaCountdownInterval = null;
        astaFinalizzataClient = false; // Fondamentale per la nuova asta
    }

    function aggiornaInterfacciaConDatiServer(data) {
        if (!data) { console.warn("aggiornaInterfacciaConDatiServer: no data received."); return; }
        console.log("[UI Update] Dati ricevuti:", JSON.parse(JSON.stringify(data)));

        if (creditiPersonaliDisplayEl && data.crediti_utente_corrente !== undefined) {
            creditiPersonaliDisplayEl.textContent = data.crediti_utente_corrente;
        }

        if (data.status === 'in_asta_tap_live') {
            console.log("[UI Update] Stato Rilevato: IN_ASTA_TAP_LIVE per chiamata ID:", data.chiamata_id);
            astaFinalizzataClient = false; 
            if(astaContainerEl) {
                astaContainerEl.classList.remove('hidden');
                astaContainerEl.dataset.chiamataId = data.chiamata_id; 
            }
            if(nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.add('hidden');
            // Pulisci messaggi globali SOLO se l'asta che stiamo mostrando è effettivamente quella live
            if(messaggiGlobaliDiv && parseInt(idChiamataPerPolling) === parseInt(data.chiamata_id)) {
                 messaggiGlobaliDiv.innerHTML = ''; 
            }

            if(calciatoreNomeDisplayEl) calciatoreNomeDisplayEl.textContent = data.calciatore_nome || 'N/D';
            if(calciatoreDettagliDisplayEl) calciatoreDettagliDisplayEl.textContent = (data.calciatore_ruolo && data.calciatore_squadra_serie_a) ? `${data.calciatore_ruolo} - ${data.calciatore_squadra_serie_a}` : '-';
            if(prezzoPartenzaDisplayEl) prezzoPartenzaDisplayEl.textContent = data.prezzo_partenza_tap !== undefined ? data.prezzo_partenza_tap : '--';
            if(prezzoAttualeValEl) prezzoAttualeValEl.textContent = data.prezzo_attuale !== null ? data.prezzo_attuale : '--';
            if(migliorOfferenteNomeEl) migliorOfferenteNomeEl.textContent = data.miglior_offerente ? `da ${data.miglior_offerente}` : (data.prezzo_attuale > 0 ? ' (Offerta base)' : ' - ');
            
            bottoniRilancio.forEach(btn => {
                const isDisabled = (data.miglior_offerente_id === currentUserId || !currentUserId);
                btn.disabled = isDisabled;
                btn.classList.toggle('opacity-50', isDisabled);
                btn.classList.toggle('cursor-not-allowed', isDisabled);
            });
            
            if(countdownElement && data.timestamp_fine_prevista_unix !== undefined) {
                const serverTimestampFineUnix = data.timestamp_fine_prevista_unix;
                if (serverTimestampFineUnix !== null && !isNaN(parseInt(serverTimestampFineUnix))) {
                    // Solo se il timestamp è effettivamente diverso o l'asta è stata appena "svegliata"
                    if ( (timestampFinePerCountdown / 1000) !== parseInt(serverTimestampFineUnix) || astaFinalizzataClient ) {
                         avviaOAggiornaCountdown(serverTimestampFineUnix);
                    }
                } else if (timestampFinePerCountdown !==0) {
                     avviaOAggiornaCountdown(null); // Ferma countdown se il server non manda più un TS valido
                }
            }
        } else { // L'asta NON è 'in_asta_tap_live'
            console.log("[UI Update] Stato: NON IN_ASTA_TAP_LIVE (" + data.status + ") per chiamata ID:", data.chiamata_id);
            
            // Nascondi il container dell'asta solo se i dati si riferiscono all'asta che era visualizzata (o che si pensava fosse live)
            if (astaContainerEl && (!idChiamataPerPolling || parseInt(idChiamataPerPolling) === parseInt(data.chiamata_id))) {
                 astaContainerEl.classList.add('hidden');
            }
            if(nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.remove('hidden');
            if(countdownElement) countdownElement.textContent = "ASTA NON ATTIVA";
             bottoniRilancio.forEach(btn => { btn.disabled = true; btn.classList.add('opacity-50', 'cursor-not-allowed'); });

            if (messaggiGlobaliDiv && data.messaggio_esito) {
                let bgColor = 'bg-gray-100 dark:bg-gray-600'; let textColor = 'text-gray-700 dark:text-gray-200';
                if(data.status === 'conclusa_tap_assegnato') { bgColor = 'bg-green-100 dark:bg-green-800'; textColor = 'text-green-700 dark:text-green-200'; }
                else if (['conclusa_tap_non_assegnato', 'annullata_admin', 'errore_assegnazione_finale', 'non_trovata'].includes(data.status)) { bgColor = 'bg-orange-100 dark:bg-orange-800'; textColor = 'text-orange-700 dark:text-orange-200'; }
                else if (data.status === 'in_attesa_admin') { bgColor = 'bg-blue-100 dark:bg-blue-800'; textColor = 'text-blue-700 dark:text-blue-200'; }
                messaggiGlobaliDiv.innerHTML = `<div class="text-lg font-semibold p-3 rounded-lg ${bgColor} ${textColor}">${data.messaggio_esito}</div>`;
            } else if (messaggiGlobaliDiv) {
                 messaggiGlobaliDiv.innerHTML = ''; // Pulisci se non c'è un messaggio esito specifico da mostrare qui
            }
            
            if(testoPlaceholderNessunaAstaEl) {
                testoPlaceholderNessunaAstaEl.style.display = data.messaggio_esito ? 'none' : 'block';
                if (!data.messaggio_esito && data.status !== 'in_attesa_admin') {
                     testoPlaceholderNessunaAstaEl.textContent = "Nessuna asta TAP attualmente attiva.";
                } else if (!data.messaggio_esito && data.status === 'in_attesa_admin') {
                    testoPlaceholderNessunaAstaEl.textContent = "In attesa di avvio da parte dell'admin..."; // Questo dovrebbe essere gestito da messaggio_esito
                }
            }

            if (['conclusa_tap_assegnato', 'conclusa_tap_non_assegnato', 'annullata_admin', 'non_trovata'].includes(data.status) && 
                idChiamataPerPolling && parseInt(idChiamataPerPolling) === parseInt(data.chiamata_id)) {
                if (astaPollingInterval) clearInterval(astaPollingInterval); window.astaPollingInterval = null;
                if (astaCountdownInterval) clearInterval(astaCountdownInterval); astaCountdownInterval = null;
                console.log("[UI Update] Polling e Countdown fermati per stato finale:", data.status, "per ID:", data.chiamata_id);
                // Non resettare idChiamataPerPolling qui, così se una nuova asta parte, il confronto la rileva
            }
        }
    }

    function updateCountdown() {
        if (!timestampFinePerCountdown || timestampFinePerCountdown <= 0 || astaFinalizzataClient) {
            if(countdownElement) countdownElement.textContent = astaFinalizzataClient ? "TEMPO SCADUTO" : (idChiamataPerPolling && astaContainerEl && !astaContainerEl.classList.contains('hidden') ? "--:--" : "ASTA NON ATTIVA");
            if(astaCountdownInterval) clearInterval(astaCountdownInterval); astaCountdownInterval = null;
            return;
        }
        const now = new Date().getTime();
        const distance = timestampFinePerCountdown - now;

        if (distance < 0) {
            if(countdownElement) countdownElement.textContent = "TEMPO SCADUTO";
            if(astaCountdownInterval) clearInterval(astaCountdownInterval); astaCountdownInterval = null;
            
            const idAstaDaFinalizzare = astaContainerEl && astaContainerEl.dataset.chiamataId ? parseInt(astaContainerEl.dataset.chiamataId) : null;
            if (!astaFinalizzataClient && idAstaDaFinalizzare && idChiamataPerPolling && parseInt(idChiamataPerPolling) === idAstaDaFinalizzare) {
                astaFinalizzataClient = true;
                console.log("[Countdown JS] Tempo scaduto, chiamo finalizzaAstaSulServer per ID:", idAstaDaFinalizzare);
                finalizzaAstaSulServer(idAstaDaFinalizzare);
            }
            return;
        }
        const minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
        const seconds = Math.floor((distance % (1000 * 60)) / 1000);
        if(countdownElement) countdownElement.textContent = (minutes < 10 ? "0" : "") + minutes + ":" + (seconds < 10 ? "0" : "") + seconds;
    }

    function avviaOAggiornaCountdown(nuovoTimestampFineUnixServer) {
        const tsRicevutoUnix = nuovoTimestampFineUnixServer ? parseInt(nuovoTimestampFineUnixServer) : 0;
        const oraAttualeUnix = Math.floor(Date.now() / 1000);
        const tolleranzaPassato = 60; 

        console.log(`[Countdown JS] avviaOAggiornaCountdown. Ricevuto Unix: ${nuovoTimestampFineUnixServer}`);
        console.log(`[Countdown JS] Confronto: TS Ricevuto (int): ${tsRicevutoUnix}, Ora Attuale (int): ${oraAttualeUnix}, Condizione per partire: ${tsRicevutoUnix > (oraAttualeUnix - tolleranzaPassato)}`);

        if (tsRicevutoUnix && tsRicevutoUnix > (oraAttualeUnix - tolleranzaPassato)) {
            timestampFinePerCountdown = tsRicevutoUnix * 1000; 
            if (countdownElement) countdownElement.dataset.timestampFine = nuovoTimestampFineUnixServer.toString();
            console.log('[Countdown JS] Timestamp valido. Aggiornato a (ms):', timestampFinePerCountdown);
        } else {
            console.warn('[Countdown JS] Timestamp non valido, nullo, o troppo nel passato:', nuovoTimestampFineUnixServer, ". Imposto timestampFinePerCountdown a 0.");
            timestampFinePerCountdown = 0;
            if (countdownElement) countdownElement.dataset.timestampFine = '';
        }
        
        if(astaCountdownInterval) clearInterval(astaCountdownInterval); 
        astaCountdownInterval = null;
        
        if (countdownElement && timestampFinePerCountdown > 0) {
            updateCountdown(); 
            astaCountdownInterval = setInterval(updateCountdown, 1000);
            console.log('[Countdown JS] Countdown avviato/resettato. Fine (ms):', timestampFinePerCountdown);
        } else if (countdownElement) {
             countdownElement.textContent = (idChiamataPerPolling && astaContainerEl && !astaContainerEl.classList.contains('hidden')) ? "--:--" : "ASTA NON ATTIVA";
        }
    }
    
    bottoniRilancio.forEach(button => {
        button.addEventListener('click', function() {
            if (this.disabled) return;
            const idAstaPerRilancio = astaContainerEl && astaContainerEl.dataset.chiamataId ? parseInt(astaContainerEl.dataset.chiamataId) : null;
            
            if (!idAstaPerRilancio || (idChiamataPerPolling && idAstaPerRilancio !== parseInt(idChiamataPerPolling)) ) {
                if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-red-500 dark:text-red-300">${'Errore: ID Asta per rilancio non corrisponde a quella monitorata.'}</span>`;
                return;
            }
            const incremento = this.dataset.rilancio;
            if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-blue-500 dark:text-blue-300">${'Invio offerta...'}</span>`;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            fetch(`${URL_BASE_RILANCIA}/${idAstaPerRilancio}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken,
                    'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({ importo_rilancio: parseInt(incremento) })
            })
            .then(response => {
                if (response.status === 419) throw new Error('Sessione scaduta. Ricarica la pagina.');
                return response.json().then(data => ({ ok: response.ok, status: response.status, data }));
            })
            .then(({ ok, status, data }) => {
                if (ok && data.success) {
                    if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-green-600 dark:text-green-300">${data.message || 'Offerta registrata!'}</span>`;
                    if(data.timestamp_fine_tap_prevista_unix !== undefined) {
                        avviaOAggiornaCountdown(data.timestamp_fine_tap_prevista_unix); 
                    }
                    if(idChiamataPerPolling) fetchStatoAsta(idChiamataPerPolling);

                } else { 
                    if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-red-500 dark:text-red-300">${'Errore'}: ${data.error || data.message || 'Offerta non valida ('+status+').'}</span>`;
                }
            })
            .catch(error => { 
                console.error('[Rilancio JS] Catch Errore Fetch:', error);
                let errorMessage = error.message || 'Errore di connessione o del server.';
                if (error.data && error.data.error) errorMessage = error.data.error;
                if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-red-500 dark:text-red-300">${errorMessage}</span>`;
            });
        });
    });

    function finalizzaAstaSulServer(chiamataIdDaFinalizzare) {
        if (!chiamataIdDaFinalizzare) { console.error('[Finalizza JS] ID chiamata mancante.'); return; }
        console.log('[Finalizza JS] Tentativo finalizzazione per ID:', chiamataIdDaFinalizzare);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if(!csrfToken) { console.error('[Finalizza JS] CSRF Token mancante.'); return; }
        if(messaggiGlobaliDiv) messaggiGlobaliDiv.innerHTML = `<span class="text-blue-500 dark:text-blue-300">${'Finalizzazione asta in corso...'}</span>`;
        
        fetch(`${URL_BASE_FINALIZZA}/${chiamataIdDaFinalizzare}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            console.log('[Finalizza JS] Risposta da finalizzazione server:', data);
            if(idChiamataPerPolling) fetchStatoAsta(idChiamataPerPolling); 
        })
        .catch(error => {
            console.error('Errore durante la finalizzaAstaSulServer:', error);
            if(messaggiGlobaliDiv) messaggiGlobaliDiv.innerHTML = `<span class="text-red-500 dark:text-red-300">Errore tecnico durante la finalizzazione.</span>`;
        });
    }

    function fetchStatoAsta(idAstaDaControllare) {
        const idNumericToControl = parseInt(idAstaDaControllare);
        if (!idNumericToControl || isNaN(idNumericToControl)) {
            console.warn('[Polling Fetch] ID asta da controllare non valido o mancante:', idAstaDaControllare);
            aggiornaInterfacciaConDatiServer({ status: 'non_attiva', chiamata_id: idAstaDaControllare, messaggio_esito: 'Nessun ID asta valido da monitorare.' });
            if(window.astaPollingInterval) clearInterval(window.astaPollingInterval); window.astaPollingInterval = null;
            return;
        }
        // console.log('[Polling Fetch] Richiedo stato per ID:', idNumericToControl);
        
        fetch(`${URL_BASE_STATO}/${idNumericToControl}`)
            .then(response => {
                if (!response.ok) { return response.json().then(errData => { throw { responseStatus: response.status, data: errData }; }); }
                return response.json();
            })
            .then(data => {
                if (!data) { console.warn("[Polling Fetch] Dati nulli ricevuti."); return; }

                const serverReportedLiveId = data.asta_live_corrente_id ? parseInt(data.asta_live_corrente_id) : null;
                const currentlyPollingIdNumeric = idChiamataPerPolling ? parseInt(idChiamataPerPolling) : null;

                console.log(`[Polling Debug] Polling per ID: ${currentlyPollingIdNumeric}. Server dice LIVE è: ${serverReportedLiveId}. Dati per asta ID: ${data.chiamata_id}. Stato asta richiesta: ${data.status}`);

                if (serverReportedLiveId && serverReportedLiveId !== currentlyPollingIdNumeric) {
                    console.warn(`[Polling Transizione] NUOVA ASTA LIVE RILEVATA! Server ID: ${serverReportedLiveId}, Polling Attuale ID: ${currentlyPollingIdNumeric}. CAMBIO TARGET.`);
                    
                    if (window.astaPollingInterval) { clearInterval(window.astaPollingInterval); window.astaPollingInterval = null; }
                    resetInterfacciaPerNuovaAsta(); 
                    
                    idChiamataPerPolling = serverReportedLiveId.toString(); 
                    if (astaContainerEl) astaContainerEl.dataset.chiamataId = idChiamataPerPolling;
                    
                    startPollingAsta(idChiamataPerPolling); 
                    return; 
                }
                
                aggiornaInterfacciaConDatiServer(data); 

                if (data.status === 'in_asta_tap_live' && data.secondi_rimanenti !== undefined && data.secondi_rimanenti <= 0 && !astaFinalizzataClient) {
                    console.log("[Polling Fetch] Stato live con tempo scaduto (secondi_rimanenti <= 0), avvio finalizzazione client per ID:", idNumericToControl);
                    astaFinalizzataClient = true;
                    finalizzaAstaSulServer(idNumericToControl);
                }
            })
            .catch(error => {
                console.error('[Polling Fetch] Errore durante il fetchStatoAsta:', error);
                if (error.responseStatus === 404 && idChiamataPerPolling) { // Controlla che stiamo ancora pollando per un ID
                     aggiornaInterfacciaConDatiServer({ status: 'non_trovata', chiamata_id: parseInt(idChiamataPerPolling), messaggio_esito: 'Asta (ID: '+idChiamataPerPolling+') terminata o non più disponibile.' });
                } else if (idChiamataPerPolling) { // Altro errore, ma loggalo
                     console.error("Errore non gestito nel fetch o l'asta non è 404:", error);
                }
            });
    }

    function startPollingAsta(idCurrentAsta) {
        const idNumeric = parseInt(idCurrentAsta);
        if (!idNumeric || isNaN(idNumeric)) {
            console.warn('[Polling Start] Non avviato: ID chiamata per polling non valido:', idCurrentAsta);
            aggiornaInterfacciaConDatiServer({ status: 'non_attiva', messaggio_esito: 'Pronto per una nuova asta (ID non valido per startPolling).' });
            idChiamataPerPolling = null; 
            if (astaContainerEl) astaContainerEl.dataset.chiamataId = '';
            return;
        }
        if (window.astaPollingInterval) { clearInterval(window.astaPollingInterval); window.astaPollingInterval = null; }
        
        idChiamataPerPolling = idNumeric.toString();
        console.log('[Polling Start] Avvio/Riavvio per asta ID:', idChiamataPerPolling);
        fetchStatoAsta(idChiamataPerPolling); 
        window.astaPollingInterval = setInterval(function() {
            if (!idChiamataPerPolling) {
                 if(window.astaPollingInterval) clearInterval(window.astaPollingInterval);
                 window.astaPollingInterval = null; return;
            }
            fetchStatoAsta(idChiamataPerPolling);
        }, 3000);
    }
    
    // --- Avvio Iniziale Logica ---
    const initialAstaIdFromBlade = "{{ $idChiamataPerPollingInit }}"; // Da PHP, può essere ID live o ultima processata
    const initialIsAstaLiveFromBlade = {{ $astaTapEffettivamenteLive ? 'true' : 'false' }};
    const initialTimestampUnixFromBlade = "{{ $timestampFinePerCountdownInit }}";

    console.log('[JS Init Blade] initialAstaIdToPoll:', initialAstaIdFromBlade, 'initialIsAstaLive:', initialIsAstaLiveFromBlade, 'initialTimestampUnix:', initialTimestampUnixFromBlade);

    if (initialAstaIdFromBlade && initialAstaIdFromBlade !== '') {
        idChiamataPerPolling = initialAstaIdFromBlade; 
        startPollingAsta(idChiamataPerPolling); 
        
        if (initialIsAstaLiveFromBlade && initialTimestampUnixFromBlade && initialTimestampUnixFromBlade !== '' && initialTimestampUnixFromBlade !== 'ErroreParseTS') {
            console.log('[JS Init Blade] Asta è live al caricamento, avvio countdown.');
            avviaOAggiornaCountdown(parseInt(initialTimestampUnixFromBlade));
        } else if (countdownElement) {
             const statoInizialeChiamataPHP = "{{ optional($chiamataDaMostrare)->stato_chiamata ?? '' }}";
             console.log("[JS Init Blade] Asta non live o timestamp non valido al caricamento. Stato PHP:", statoInizialeChiamataPHP);
             if (statoInizialeChiamataPHP !== 'in_asta_tap_live' && statoInizialeChiamataPHP !== '') {
                // Non impostare il testo del countdown qui, lascia che il primo polling lo faccia
                // tramite aggiornaInterfacciaConDatiServer, che mostrerà il messaggio_esito
             } else if (statoInizialeChiamataPHP === 'in_asta_tap_live' && (!initialTimestampUnixFromBlade || initialTimestampUnixFromBlade === '' || initialTimestampUnixFromBlade === 'ErroreParseTS')) {
                  countdownElement.textContent = "--:--"; // Live ma senza timestamp valido
             } else if (!initialAstaIdFromBlade) {
                 countdownElement.textContent = "ASTA NON ATTIVA";
             }
        }
    } else {
        console.warn('[JS Init Blade] Nessun ID chiamata iniziale valido passato da PHP. Pagina in attesa.');
        if(astaContainerEl) astaContainerEl.classList.add('hidden');
        if(nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.remove('hidden');
        if(testoPlaceholderNessunaAstaEl) testoPlaceholderNessunaAstaEl.textContent = "Nessuna asta TAP attualmente attiva. Pronta per una chiamata.";
        if(countdownElement) countdownElement.textContent = "ASTA NON ATTIVA";
    }
});
</script>
@endpush
</x-app-layout>