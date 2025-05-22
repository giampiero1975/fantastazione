<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Asta Live') }}
            @if(isset($impostazioniLega) && $impostazioniLega instanceof \App\Models\ImpostazioneLega)
                - Fase: <span class="font-bold" id="fase-asta-display">{{ $impostazioniLega->fase_asta_corrente }}</span>
                @if($impostazioniLega->tag_lista_attiva)
                    (Lista: <span class="font-bold" id="tag-lista-display">{{ $impostazioniLega->tag_lista_attiva }}</span>)
                @endif
            @else
                <span class="font-bold text-red-500"> {{ __('(Impostazioni Lega non caricate!)') }}</span>
            @endif
        </h2>
    </x-slot>

    <div class="py-6 md:py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            @if(isset($impostazioniLega) && $impostazioniLega->usa_ordine_chiamata)
            <div class="mb-6 p-4 bg-blue-100 dark:bg-blue-800 border-l-4 border-blue-500 dark:border-blue-400 text-blue-700 dark:text-blue-200 shadow-sm sm:rounded-lg" id="box-prossimo-chiamante">
                <p class="font-semibold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline-block mr-2 -mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    <span id="testo-prossimo-chiamante">
                        @if(isset($isAstaAttualmenteLive) && $isAstaAttualmenteLive)
                            Asta in corso...
                        @elseif(isset($prossimoChiamanteNome) && $prossimoChiamanteNome !== __('Da definire'))
                            È il turno di: <strong class="text-lg">{{ $prossimoChiamanteNome }}</strong> per effettuare la prossima chiamata.
                        @else
                            Prossimo a chiamare: <strong class="text-lg italic">{{ $prossimoChiamanteNome ?? __('Da definire') }}</strong>
                        @endif
                    </span>
                </p>
                @if(Auth::check() && isset($impostazioniLega) && $impostazioniLega->prossimo_turno_chiamata_user_id == Auth::id() && (!$isAstaAttualmenteLive && $statoChiamataDaMostrare !== 'in_attesa_admin') )
                    <p class="mt-2 text-sm" id="link-vai-a-chiamata">
                        <a href="{{ route('asta.calciatori.disponibili') }}" class="text-white bg-green-500 hover:bg-green-600 font-medium rounded-lg text-sm px-4 py-2 inline-flex items-center">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
                            </svg>
                            Tocca a te! Vai alla lista giocatori
                        </a>
                    </p>
                @endif
            </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">

                    <div id="messaggi-globali-asta" class="mb-6 text-center min-h-[2.5rem] p-1 text-sm">
                        @if (isset($messaggioEsitoIniziale) && $messaggioEsitoIniziale)
                            <div class="text-lg font-semibold p-3 rounded-lg
                                @if($statoChiamataDaMostrare === 'conclusa_tap_assegnato') bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-200
                                @elseif(in_array($statoChiamataDaMostrare, ['conclusa_tap_non_assegnato', 'annullata_admin'])) bg-orange-100 dark:bg-orange-800 text-orange-700 dark:text-orange-200
                                @else bg-gray-100 dark:bg-gray-700/50 text-gray-700 dark:text-gray-300
                                @endif">
                                {{ $messaggioEsitoIniziale }}
                            </div>
                        @endif
                        @if (session('success')) <div class="text-green-700 bg-green-100 dark:bg-green-800 dark:text-green-200 p-3 rounded-lg">{{ session('success') }}</div> @endif
                        @if (session('error')) <div class="text-red-700 bg-red-100 dark:bg-red-800 dark:text-red-200 p-3 rounded-lg">{{ session('error') }}</div> @endif
                    </div>

                    @if(isset($impostazioniLega) && $impostazioniLega instanceof \App\Models\ImpostazioneLega)
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
                            <div id="asta-container"
                                 data-chiamata-id="{{ $idChiamataInizialePerPolling ?? '' }}"
                                 class="{{ $isAstaAttualmenteLive ? '' : 'hidden' }}">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8 items-start">
                                    <div class="md:col-span-2 space-y-5 p-4 border border-gray-300 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                        <div>
                                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider">{{ __('Calciatore all\'Asta') }}</p>
                                            <h3 class="text-2xl md:text-3xl font-bold text-indigo-700 dark:text-indigo-400" id="calciatore-nome-display">
                                                {{ $isAstaAttualmenteLive && isset($calciatoreInAsta_nome) ? $calciatoreInAsta_nome : 'N/D' }}
                                            </h3>
                                            <p class="text-sm text-gray-600 dark:text-gray-300" id="calciatore-dettagli-display">
                                                @if($isAstaAttualmenteLive && isset($calciatoreInAsta_ruolo) && isset($calciatoreInAsta_squadra))
                                                    {{ $calciatoreInAsta_ruolo }} - {{ $calciatoreInAsta_squadra }}
                                                @else - @endif
                                            </p>
                                        </div>
                                        <hr class="dark:border-gray-600">
                                        <div>
                                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider">{{ __('Prezzo Partenza') }}</p>
                                            <p class="text-xl font-semibold" id="prezzo-partenza-display">
                                                {{ $isAstaAttualmenteLive && isset($prezzoPartenzaTapIniziale) ? $prezzoPartenzaTapIniziale : '--' }}
                                            </p>
                                        </div>
                                        <hr class="dark:border-gray-600">
                                        <div>
                                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider">{{ __('Migliore Offerta') }}</p>
                                            <p class="text-xl font-semibold" id="miglior-offerente-wrapper">
                                                <span class="text-green-600 dark:text-green-400" id="prezzo-attuale-valore">
                                                    {{ $isAstaAttualmenteLive && isset($prezzoAttualeTapIniziale) ? $prezzoAttualeTapIniziale : '--' }}
                                                </span>
                                                <span id="miglior-offerente-nome">
                                                    @if($isAstaAttualmenteLive && isset($migliorOfferente_nome))
                                                        da {{ $migliorOfferente_nome }}
                                                    @elseif($isAstaAttualmenteLive && (!isset($migliorOfferente_nome) && isset($prezzoAttualeTapIniziale) && $prezzoAttualeTapIniziale > 0 ) )
                                                         {{ __(' (Offerta base)') }}
                                                    @elseif($isAstaAttualmenteLive)
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
                                       data-timestamp-fine="{{ $timestampFineInizialePerCountdown ?? '' }}">
                                       {{-- Il JS popola questo --}}
                                    </p>
                                </div>
                                <div id="messaggi-rilancio-specifici" class="text-center text-sm mt-4 min-h-[1.5rem]"></div>
                            </div>

                            <div id="nessuna-asta-tap-attiva" class="{{ $isAstaAttualmenteLive ? 'hidden' : '' }}">
                                <div id="testo-placeholder-nessuna-asta" class="text-center text-gray-600 dark:text-gray-400 py-8">
                                    @if(isset($statoChiamataDaMostrare) && $statoChiamataDaMostrare === 'in_attesa_admin' && $impostazioniLega->asta_tap_approvazione_admin && isset($initialDatiAstaInAttesa_calciatoreNome))
                                        <div class="p-4 bg-blue-100 dark:bg-blue-800 border-l-4 border-blue-500 dark:border-blue-400 text-blue-700 dark:text-blue-200">
                                            Calciatore <strong>{{ $initialDatiAstaInAttesa_calciatoreNome ?? 'Sconosciuto' }}</strong>
                                            chiamato da <strong>{{ $initialDatiAstaInAttesa_chiamanteNome ?? 'Squadra' }}</strong>.
                                            In attesa di avvio da parte dell'admin.
                                        </div>
                                    @elseif(isset($statoChiamataDaMostrare) && in_array($statoChiamataDaMostrare, ['conclusa_tap_assegnato', 'conclusa_tap_non_assegnato', 'annullata_admin']))
                                        {{-- Messaggio gestito da #messaggi-globali-asta --}}
                                    @elseif(!isset($messaggioEsitoIniziale))
                                        Nessuna asta TAP attualmente attiva.
                                    @endif
                                </div>
                                @if (Auth::check() && !$isAstaAttualmenteLive && (!isset($statoChiamataDaMostrare) || $statoChiamataDaMostrare !== 'in_attesa_admin') )
                                    <p class="text-center mt-4" id="link-vai-a-chiamata-placeholder">
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
                                <p class="mt-4 text-gray-600 dark:text-gray-400">{{ __('L\'admin registrerà manually gli acquisti.') }}</p>
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
    console.log('[ASTA LIVE SCRIPT V5.2 - Gestione Countdown e ID Finalizzazione] Caricato');

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

    const boxProssimoChiamanteEl = document.getElementById('box-prossimo-chiamante');
    const testoProssimoChiamanteEl = document.getElementById('testo-prossimo-chiamante');
    const linkVaiAChiamataEl = document.getElementById('link-vai-a-chiamata');
    const linkVaiAChiamataPlaceholderEl = document.getElementById('link-vai-a-chiamata-placeholder');

    let idChiamataPerPolling = '{{ $idChiamataInizialePerPolling ?? "0" }}';
    let timestampFinePerCountdown = countdownElement && countdownElement.dataset.timestampFine && countdownElement.dataset.timestampFine !== 'ErroreParseTS' && countdownElement.dataset.timestampFine !== '' ? parseInt(countdownElement.dataset.timestampFine) * 1000 : 0;

    const currentUserId = {{ Auth::id() ?? 'null' }};
    const impostazioniLegaAstaTapApprovazioneAdmin = {{ Js::from(optional($impostazioniLega)->asta_tap_approvazione_admin ?? false) }};
    const impostazioniLegaUsaOrdineChiamata = {{ Js::from(optional($impostazioniLega)->usa_ordine_chiamata ?? false) }};

    let astaCountdownInterval = null;
    let astaPollingInterval = null;
    let astaFinalizzataClient = false;

    const URL_BASE_STATO = "{{ url('/asta/tap/stato') }}";
    const URL_BASE_RILANCIA = "{{ url('/asta/tap/rilancia') }}";
    const URL_BASE_FINALIZZA = "{{ url('/asta/tap/finalizza') }}";

    console.log('[JS Init V5.2] ID Chiamata Iniziale (da Blade):', idChiamataPerPolling);
    console.log('[JS Init V5.2] Timestamp Fine Iniziale (ms) (da Blade):', timestampFinePerCountdown);

    function resetInterfacciaPerNuovaAsta(messaggioPlaceholder = "Caricamento stato asta...") {
        console.log("[UI Reset V5.2] Resetto interfaccia.", messaggioPlaceholder);
        if(astaContainerEl) astaContainerEl.classList.add('hidden');
        if(nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.remove('hidden');
        if(testoPlaceholderNessunaAstaEl) {
            testoPlaceholderNessunaAstaEl.innerHTML = `<p class="text-gray-500 dark:text-gray-400">${messaggioPlaceholder}</p>`;
            testoPlaceholderNessunaAstaEl.style.display = 'block';
        }

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
        bottoniRilancio.forEach(btn => { btn.disabled = true; btn.classList.add('opacity-50', 'cursor-not-allowed'); });

        timestampFinePerCountdown = 0;
        if(astaCountdownInterval) clearInterval(astaCountdownInterval);
        astaCountdownInterval = null;
        astaFinalizzataClient = false;
        if(astaContainerEl) astaContainerEl.dataset.chiamataId = ''; // Resetta anche il dataset
    }

    function aggiornaInterfacciaConDatiServer(data) {
        if (!data) { console.warn("aggiornaInterfacciaConDatiServer: no data received."); return; }
        console.log("[UI Update V5.2] Dati ricevuti:", JSON.parse(JSON.stringify(data)));

        if (creditiPersonaliDisplayEl && data.crediti_utente_corrente !== undefined && data.crediti_utente_corrente !== null) {
            creditiPersonaliDisplayEl.textContent = data.crediti_utente_corrente;
        }
        
        if (impostazioniLegaUsaOrdineChiamata && testoProssimoChiamanteEl && boxProssimoChiamanteEl) {
            boxProssimoChiamanteEl.style.display = 'block';
            let testoTurno = '';
            let prossimoChiamanteNomeJS = data.prossimo_chiamante_nome || "{{ __('Da definire') }}";

            if (data.status === 'in_asta_tap_live') {
                testoTurno = 'Asta in corso...';
            } else if (prossimoChiamanteNomeJS && prossimoChiamanteNomeJS !== "{{ __('Da definire') }}") {
                testoTurno = `È il turno di: <strong class="text-lg">${prossimoChiamanteNomeJS}</strong> per effettuare la prossima chiamata.`;
            } else {
                testoTurno = `Prossimo a chiamare: <strong class="text-lg italic">${prossimoChiamanteNomeJS}</strong>`;
            }
            testoProssimoChiamanteEl.innerHTML = testoTurno;

            const isMyTurn = currentUserId && data.prossimo_chiamante_id && parseInt(data.prossimo_chiamante_id) === parseInt(currentUserId);
            const noAstaAttivaOInAttesaSpecificamente = data.status !== 'in_asta_tap_live' && data.status !== 'in_attesa_admin';

            if (linkVaiAChiamataEl) linkVaiAChiamataEl.style.display = (isMyTurn && noAstaAttivaOInAttesaSpecificamente) ? 'block' : 'none';
            
            if (linkVaiAChiamataPlaceholderEl) {
                 const deveMostrareLinkGenerico = !isMyTurn && 
                                                  noAstaAttivaOInAttesaSpecificamente && 
                                                  (!impostazioniLegaUsaOrdineChiamata || 
                                                  (impostazioniLegaUsaOrdineChiamata && (data.prossimo_chiamante_id !== null || data.status === 'non_trovata' || data.status === null || data.status === undefined) )) ;

                 linkVaiAChiamataPlaceholderEl.style.display = deveMostrareLinkGenerico ? 'block' : 'none';
            }
        } else if (boxProssimoChiamanteEl) {
            boxProssimoChiamanteEl.style.display = 'none';
        }

        if (data.status === 'in_asta_tap_live') {
            console.log("[UI Update V5.2] Stato Rilevato: IN_ASTA_TAP_LIVE per chiamata ID:", data.chiamata_id);
            astaFinalizzataClient = false;
            if (astaContainerEl) {
                astaContainerEl.classList.remove('hidden');
                astaContainerEl.dataset.chiamataId = data.chiamata_id; // Fondamentale aggiornare qui
            }
            if (nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.add('hidden');
            
            if (messaggiGlobaliDiv && (idChiamataPerPolling === "0" || (idChiamataPerPolling && parseInt(idChiamataPerPolling) === parseInt(data.chiamata_id)) )) {
                 if (!messaggiGlobaliDiv.innerHTML.includes('session(') && !messaggiGlobaliDiv.querySelector('.rounded-lg')) {
                     messaggiGlobaliDiv.innerHTML = '';
                 }
            }

            if(calciatoreNomeDisplayEl) calciatoreNomeDisplayEl.textContent = data.calciatore_nome || 'N/D';
            if(calciatoreDettagliDisplayEl) calciatoreDettagliDisplayEl.textContent = (data.calciatore_ruolo && data.calciatore_squadra_serie_a) ? `${data.calciatore_ruolo} - ${data.calciatore_squadra_serie_a}` : '-';
            if(prezzoPartenzaDisplayEl) prezzoPartenzaDisplayEl.textContent = data.prezzo_partenza_tap !== undefined ? data.prezzo_partenza_tap : '--';
            if(prezzoAttualeValEl) prezzoAttualeValEl.textContent = data.prezzo_attuale !== null ? data.prezzo_attuale : '--';
            
            let nomeMigliorOfferenteVis = '';
            if (data.miglior_offerente) { // Questo viene dal join nel controller se miglior_offerente_tap_id è settato
                nomeMigliorOfferenteVis = `da ${data.miglior_offerente}`;
                if (data.miglior_offerente_id === data.user_id_chiamante && data.prezzo_attuale == data.prezzo_partenza_tap) {
                     nomeMigliorOfferenteVis = `da ${data.chiamante_nome || data.miglior_offerente} (chiamata)`;
                }
            } else if (data.prezzo_attuale > 0 && data.user_id_chiamante && data.chiamante_nome) { // Offerta base del chiamante
                 nomeMigliorOfferenteVis = `da ${data.chiamante_nome} (chiamata)`;
            } else if (data.prezzo_attuale > 0) {
                 nomeMigliorOfferenteVis = ' (Offerta base)';
            } else {
                nomeMigliorOfferenteVis = ' - ';
            }
            if(migliorOfferenteNomeEl) migliorOfferenteNomeEl.textContent = nomeMigliorOfferenteVis;

            bottoniRilancio.forEach(btn => {
                const isDisabled = (data.miglior_offerente_id === currentUserId || !currentUserId);
                btn.disabled = isDisabled;
                btn.classList.toggle('opacity-50', isDisabled);
                btn.classList.toggle('cursor-not-allowed', isDisabled);
            });

            avviaOAggiornaCountdown(data.timestamp_fine_prevista_unix);

        } else if (data.asta_in_attesa_admin_id && data.dati_asta_in_attesa_admin && impostazioniLegaAstaTapApprovazioneAdmin) {
            console.log("[UI Update V5.2] Stato Rilevato: ASTA_IN_ATTESA_ADMIN ID:", data.asta_in_attesa_admin_id);
            if (astaContainerEl) astaContainerEl.classList.add('hidden');
            if (nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.remove('hidden');
            if (countdownElement) countdownElement.textContent = "ASTA NON ATTIVA";
            bottoniRilancio.forEach(btn => { btn.disabled = true; btn.classList.add('opacity-50', 'cursor-not-allowed'); });
            if (messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = '';

            const datiAttesa = data.dati_asta_in_attesa_admin;
            let msgAttesa = `Calciatore <strong>${datiAttesa.calciatore_nome || 'Sconosciuto'}</strong> chiamato da <strong>${datiAttesa.chiamante_nome || 'Squadra'}</strong>. In attesa di avvio da parte dell'admin.`;
            if (testoPlaceholderNessunaAstaEl) {
                testoPlaceholderNessunaAstaEl.innerHTML = `<div class="p-4 bg-blue-100 dark:bg-blue-800 border-l-4 border-blue-500 dark:border-blue-400 text-blue-700 dark:text-blue-200 rounded-md">${msgAttesa}</div>`;
                testoPlaceholderNessunaAstaEl.style.display = 'block';
            }
            if (messaggiGlobaliDiv && !messaggiGlobaliDiv.innerHTML.includes('session(') ) messaggiGlobaliDiv.innerHTML = '';
            if (linkVaiAChiamataPlaceholderEl) linkVaiAChiamataPlaceholderEl.style.display = 'none';

        } else { // Nessuna asta live, nessuna in attesa (o approvazione admin non attiva)
            console.log("[UI Update V5.2] Stato: NON IN_ASTA_TAP_LIVE e NESSUNA IN ATTESA. Stato ricevuto: " + data.status + ", Chiamata ID: " + data.chiamata_id);
            
            // Nascondi il container dell'asta live solo se l'asta che stavamo pollando è quella che ora non è live
            // o se siamo in discovery e non c'è nulla.
            if (astaContainerEl && (data.chiamata_id === parseInt(idChiamataPerPolling) || idChiamataPerPolling === "0" || data.status === 'non_trovata')) {
                 astaContainerEl.classList.add('hidden');
            }
            if(nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.remove('hidden');
            if(countdownElement) countdownElement.textContent = "ASTA NON ATTIVA";
            bottoniRilancio.forEach(btn => { btn.disabled = true; btn.classList.add('opacity-50', 'cursor-not-allowed'); });

            let bgColor = 'bg-gray-100 dark:bg-gray-700/50'; let textColor = 'text-gray-700 dark:text-gray-300';
            if (data.status === 'conclusa_tap_assegnato') { bgColor = 'bg-green-100 dark:bg-green-800'; textColor = 'text-green-700 dark:text-green-200'; }
            else if (['conclusa_tap_non_assegnato', 'annullata_admin', 'errore_assegnazione_finale'].includes(data.status)) { bgColor = 'bg-orange-100 dark:bg-orange-800'; textColor = 'text-orange-700 dark:text-orange-200'; }
            else if (data.status === 'non_trovata'){ bgColor = 'bg-yellow-100 dark:bg-yellow-700'; textColor = 'text-yellow-700 dark:text-yellow-200';}

            // Mostra il messaggio di esito solo se l'asta-container è nascosto (cioè, non stiamo già mostrando un'asta live)
            // O se il messaggio è per l'ID che stavamo pollando
            if (messaggiGlobaliDiv && data.messaggio_esito && (astaContainerEl.classList.contains('hidden') || parseInt(data.chiamata_id) === parseInt(idChiamataPerPolling) || idChiamataPerPolling === "0" )) {
                messaggiGlobaliDiv.innerHTML = `<div class="text-lg font-semibold p-3 rounded-lg ${bgColor} ${textColor}">${data.messaggio_esito}</div>`;
            } else if (messaggiGlobaliDiv && !data.messaggio_esito && !messaggiGlobaliDiv.innerHTML.includes('session(') && astaContainerEl.classList.contains('hidden') ) {
                 messaggiGlobaliDiv.innerHTML = ''; // Pulisci solo se non c'è un'asta e nessun messaggio di sessione
            }


            if(testoPlaceholderNessunaAstaEl) {
                // Mostra il placeholder "Nessuna asta TAP attualmente attiva" solo se:
                // 1. Non c'è un messaggio di esito specifico da mostrare
                // 2. E il contenitore dell'asta live è effettivamente nascosto
                const deveMostrarePlaceholderDefault = !data.messaggio_esito && astaContainerEl.classList.contains('hidden');
                testoPlaceholderNessunaAstaEl.style.display = deveMostrarePlaceholderDefault ? 'block' : 'none';
                if (deveMostrarePlaceholderDefault) {
                     testoPlaceholderNessunaAstaEl.innerHTML = "<p class='text-gray-500 dark:text-gray-400'>Nessuna asta TAP attualmente attiva.</p>";
                }
            }

            // Logica per fermare il countdown se l'asta polata è conclusa
            if (['conclusa_tap_assegnato', 'conclusa_tap_non_assegnato', 'annullata_admin'].includes(data.status) && // Rimosso 'non_trovata'
                idChiamataPerPolling && parseInt(idChiamataPerPolling) === parseInt(data.chiamata_id)) {
                if (astaCountdownInterval) clearInterval(astaCountdownInterval); astaCountdownInterval = null;
                console.log("[UI Update V5.2] Countdown fermato per stato finale:", data.status, "per ID:", data.chiamata_id);
            }
        }
    }

    function updateCountdown() {
        if (!timestampFinePerCountdown || timestampFinePerCountdown <= 0 || astaFinalizzataClient) {
            if(countdownElement) countdownElement.textContent = astaFinalizzataClient ? "TEMPO SCADUTO" : (idChiamataPerPolling && idChiamataPerPolling !== "0" && astaContainerEl && !astaContainerEl.classList.contains('hidden') ? "--:--" : "ASTA NON ATTIVA");
            if(astaCountdownInterval) clearInterval(astaCountdownInterval); astaCountdownInterval = null;
            return;
        }
        const now = new Date().getTime();
        const distance = timestampFinePerCountdown - now;

        if (distance < 0) {
            if(countdownElement) countdownElement.textContent = "TEMPO SCADUTO";
            if(astaCountdownInterval) clearInterval(astaCountdownInterval); astaCountdownInterval = null;

            const idAstaDaFinalizzareCorrente = idChiamataPerPolling ? parseInt(idChiamataPerPolling) : null;

            if (!astaFinalizzataClient && idAstaDaFinalizzareCorrente && idAstaDaFinalizzareCorrente !== 0) {
                astaFinalizzataClient = true;
                console.log("[Countdown JS V5.2] Tempo scaduto, chiamo finalizzaAstaSulServer per ID (da idChiamataPerPolling):", idAstaDaFinalizzareCorrente);
                finalizzaAstaSulServer(idAstaDaFinalizzareCorrente);
            } else {
                 console.warn("[Countdown JS V5.2] Tentativo di finalizzazione evitato (updateCountdown): astaFinalizzataClient=", astaFinalizzataClient, "idAstaDaFinalizzareCorrente=", idAstaDaFinalizzareCorrente);
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
        const tolleranzaPassato = 5; // Ridotta tolleranza per essere più reattivi

        console.log(`[Countdown JS V5.2] avviaOAggiorna. Ricevuto Unix: ${nuovoTimestampFineUnixServer}`);

        if (tsRicevutoUnix && tsRicevutoUnix > (oraAttualeUnix - tolleranzaPassato)) {
            timestampFinePerCountdown = tsRicevutoUnix * 1000;
            if (countdownElement) countdownElement.dataset.timestampFine = nuovoTimestampFineUnixServer.toString();
            console.log('[Countdown JS V5.2] Timestamp fine aggiornato a (ms):', timestampFinePerCountdown);
        } else {
            console.warn('[Countdown JS V5.2] Timestamp non valido, nullo, o troppo nel passato:', nuovoTimestampFineUnixServer, ". Imposto timestampFinePerCountdown a 0.");
            timestampFinePerCountdown = 0;
            if (countdownElement) countdownElement.dataset.timestampFine = '';
        }

        if(astaCountdownInterval) clearInterval(astaCountdownInterval);
        astaCountdownInterval = null;

        if (countdownElement && timestampFinePerCountdown > 0) {
            updateCountdown(); 
            astaCountdownInterval = setInterval(updateCountdown, 1000);
            console.log('[Countdown JS V5.2] Countdown avviato/resettato.');
        } else if (countdownElement) {
             countdownElement.textContent = (idChiamataPerPolling && idChiamataPerPolling !== "0" && astaContainerEl && !astaContainerEl.classList.contains('hidden')) ? "--:--" : "ASTA NON ATTIVA";
             console.log('[Countdown JS V5.2] Countdown NON avviato (timestamp non valido o nullo). Mostro ASTA NON ATTIVA o --:--.');
        }
    }

    bottoniRilancio.forEach(button => {
        button.addEventListener('click', function() {
            // ... (codice rilancio invariato, assicurati che idAstaPerRilancio sia corretto)
             if (this.disabled) return;
            const idAstaPerRilancio = astaContainerEl && astaContainerEl.dataset.chiamataId ? parseInt(astaContainerEl.dataset.chiamataId) : null;

            if (!idAstaPerRilancio || (idChiamataPerPolling && idChiamataPerPolling !== "0" && idAstaPerRilancio !== parseInt(idChiamataPerPolling)) ) {
                if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-red-500 dark:text-red-300">${'Errore: ID Asta per rilancio non corrisponde.'}</span>`;
                return;
            }
            const incremento = this.dataset.rilancio;
            if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-blue-500 dark:text-blue-300">${'Invio offerta...'}</span>`;
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');

            fetch(`${URL_BASE_RILANCIA}/${idAstaPerRilancio}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest'},
                body: JSON.stringify({ importo_rilancio: parseInt(incremento) })
            })
            .then(response => response.json().then(data => ({ ok: response.ok, status: response.status, data })))
            .then(({ ok, status, data }) => {
                if (ok && data.success) {
                    if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-green-600 dark:text-green-300">${data.message || 'Offerta registrata!'}</span>`;
                    if(data.nuovo_prezzo && prezzoAttualeValEl) prezzoAttualeValEl.textContent = data.nuovo_prezzo;
                    if(data.miglior_offerente && migliorOfferenteNomeEl) migliorOfferenteNomeEl.textContent = `da ${data.miglior_offerente}`;
                    if(data.timestamp_fine_tap_prevista_unix) avviaOAggiornaCountdown(data.timestamp_fine_tap_prevista_unix);
                    if (data.miglior_offerente_id === currentUserId) {
                        bottoniRilancio.forEach(btn => {
                            btn.disabled = true;
                            btn.classList.add('opacity-50', 'cursor-not-allowed');
                        });
                    }
                } else {
                    if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-red-500 dark:text-red-300">${'Errore'}: ${data.error || data.message || 'Offerta non valida ('+status+').'}</span>`;
                }
            })
            .catch(error => {
                console.error('[Rilancio JS] Catch Errore Fetch:', error);
                if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-red-500 dark:text-red-300">${error.message || 'Errore di connessione o server.'}</span>`;
            });
        });
    });

    function finalizzaAstaSulServer(chiamataIdDaFinalizzare) {
        if (!chiamataIdDaFinalizzare || chiamataIdDaFinalizzare === 0 || chiamataIdDaFinalizzare === "0") { // Controllo esplicito per "0"
            console.error('[Finalizza JS V5.2] ID chiamata non valido o "0" (discovery). Non chiamo il server per finalizzazione. ID:', chiamataIdDaFinalizzare);
            // Potrebbe essere necessario resettare astaFinalizzataClient se l'ID è 0
            if (chiamataIdDaFinalizzare === 0 || chiamataIdDaFinalizzare === "0") astaFinalizzataClient = false;
            return;
        }
        console.log('[Finalizza JS V5.2] Tentativo finalizzazione per ID (valore passato):', chiamataIdDaFinalizzare);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if(!csrfToken) { console.error('[Finalizza JS V5.2] CSRF Token mancante.'); return; }
        if(messaggiGlobaliDiv && !messaggiGlobaliDiv.innerHTML.includes('session(') && !messaggiGlobaliDiv.querySelector('.rounded-lg') ) messaggiGlobaliDiv.innerHTML = `<div class="p-2 text-blue-600 dark:text-blue-300">${'Finalizzazione asta in corso...'}</div>`;

        fetch(`${URL_BASE_FINALIZZA}/${chiamataIdDaFinalizzare}`, { // L'ID viene aggiunto qui
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            console.log('[Finalizza JS V5.2] Risposta da finalizzazione server:', data);
            // Il prossimo polling dovrebbe aggiornare, ma forziamo per immediatezza
            if (idChiamataPerPolling) fetchStatoAsta(idChiamataPerPolling);
        })
        .catch(error => {
            console.error('Errore durante la finalizzaAstaSulServer:', error);
            if(messaggiGlobaliDiv) messaggiGlobaliDiv.innerHTML = `<div class="p-2 text-red-500 dark:text-red-300">Errore tecnico durante la finalizzazione.</div>`;
            astaFinalizzataClient = false; // Permetti un nuovo tentativo se c'è un errore di rete
        });
    }

    function fetchStatoAsta(idAstaDaControllareInput) {
        const idNumericToControl = idAstaDaControllareInput ? parseInt(idAstaDaControllareInput) : 0;

        console.log(`[Polling Fetch V5.2] Chiamata fetchStatoAsta per ID: ${idNumericToControl}`);

        fetch(`${URL_BASE_STATO}/${idNumericToControl}`)
            .then(response => {
                if (!response.ok) {
                    console.error(`[Polling Fetch V5.2] Errore HTTP ${response.status} per ID ${idNumericToControl}`);
                    return response.json().then(errData => { throw { responseStatus: response.status, data: errData, requestedId: idNumericToControl }; });
                }
                return response.json();
            })
            .then(data => {
                if (!data) { console.warn("[Polling Fetch V5.2] Dati nulli ricevuti per ID:", idNumericToControl); return; }

                const serverReportedLiveId = data.asta_live_corrente_id ? parseInt(data.asta_live_corrente_id) : null;
                const serverReportedAttesaId = data.asta_in_attesa_admin_id ? parseInt(data.asta_in_attesa_admin_id) : null;
                let currentlyPollingIdNumeric = idChiamataPerPolling ? parseInt(idChiamataPerPolling) : 0;
                if (isNaN(currentlyPollingIdNumeric)) currentlyPollingIdNumeric = 0;

                console.log(`[Polling JS V5.2 Debug] Polling per ID: ${currentlyPollingIdNumeric}. Server dice LIVE: ${serverReportedLiveId}. Server dice ATTESA: ${serverReportedAttesaId}. Dati ricevuti per chiamata ID: ${data.chiamata_id}. Stato dati: ${data.status}`);

                if (serverReportedLiveId && serverReportedLiveId !== currentlyPollingIdNumeric) {
                    console.warn(`[Polling Transizione V5.2] NUOVA ASTA LIVE (ID: ${serverReportedLiveId}) rilevata! Cambio target polling.`);
                    if (window.astaPollingInterval) clearInterval(window.astaPollingInterval); window.astaPollingInterval = null;
                    resetInterfacciaPerNuovaAsta("Caricamento nuova asta live...");
                    idChiamataPerPolling = serverReportedLiveId.toString();
                    if (astaContainerEl) astaContainerEl.dataset.chiamataId = idChiamataPerPolling;
                    startPollingAsta(idChiamataPerPolling); 
                    return; 
                }

                if (!serverReportedLiveId && serverReportedAttesaId && serverReportedAttesaId !== currentlyPollingIdNumeric) {
                    console.warn(`[Polling Transizione V5.2] NUOVA ASTA IN ATTESA (ID: ${serverReportedAttesaId}) rilevata! Cambio target polling.`);
                    if (window.astaPollingInterval) clearInterval(window.astaPollingInterval); window.astaPollingInterval = null;
                    resetInterfacciaPerNuovaAsta("Caricamento stato asta in attesa...");
                    idChiamataPerPolling = serverReportedAttesaId.toString();
                     if (astaContainerEl) astaContainerEl.dataset.chiamataId = idChiamataPerPolling; // Aggiorna anche qui
                    startPollingAsta(idChiamataPerPolling);
                    return;
                }
                
                aggiornaInterfacciaConDatiServer(data); // Aggiorna con i dati dell'asta corrente o richiesta

                // Logica di finalizzazione migliorata: solo se stiamo pollando l'asta live CORRENTE
                if (data.status === 'in_asta_tap_live' && data.chiamata_id === currentlyPollingIdNumeric &&
                    data.secondi_rimanenti !== undefined && data.secondi_rimanenti <= 0 && !astaFinalizzataClient) {
                    console.log("[Polling Fetch V5.2] Asta corrente (ID:", currentlyPollingIdNumeric, ") live con tempo scaduto (secondi_rimanenti <=0). Avvio finalizzazione client.");
                    astaFinalizzataClient = true;
                    finalizzaAstaSulServer(currentlyPollingIdNumeric); // Usa l'ID che stiamo pollando
                }
                
                if (['conclusa_tap_assegnato', 'conclusa_tap_non_assegnato', 'annullata_admin', 'non_trovata'].includes(data.status) &&
                    data.chiamata_id === currentlyPollingIdNumeric && // Solo se lo stato finale è per l'asta che stavamo pollando
                    !serverReportedLiveId && !serverReportedAttesaId) {
                    if (window.astaPollingInterval) clearInterval(window.astaPollingInterval); window.astaPollingInterval = null;
                    console.log(`[Polling JS V5.2] Asta ${currentlyPollingIdNumeric} terminata. Avvio polling discovery (ID 0).`);
                    idChiamataPerPolling = '0'; 
                    if (astaContainerEl) astaContainerEl.dataset.chiamataId = ''; // Resetta anche il dataset
                    startPollingAsta('0'); 
                }

            })
            .catch(error => {
                console.error('[Polling Fetch V5.2] Errore Catch:', error);
                if (error.responseStatus === 404 && error.requestedId && parseInt(error.requestedId) !== 0) {
                     aggiornaInterfacciaConDatiServer({ status: 'non_trovata', chiamata_id: error.requestedId, messaggio_esito: 'Asta (ID: '+error.requestedId+') non più disponibile o terminata.', asta_live_corrente_id: null, asta_in_attesa_admin_id: null, dati_asta_in_attesa_admin: null });
                } else if (error.requestedId === 0) { 
                    console.error('[Polling Fetch V5.2 - Discovery] Errore durante la discovery. Riprovo tra poco.');
                }
            });
    }

    function startPollingAsta(idCurrentAsta) {
        const idNumeric = idCurrentAsta ? parseInt(idCurrentAsta) : 0;
         // Validazione dell'ID
        if (isNaN(idNumeric)) {
            console.error(`[Polling Start V5.2] Tentativo di avviare polling con ID non numerico: ${idCurrentAsta}. Interrotto.`);
            if (window.astaPollingInterval) clearInterval(window.astaPollingInterval);
            return;
        }


        if (window.astaPollingInterval) { clearInterval(window.astaPollingInterval); window.astaPollingInterval = null; }

        idChiamataPerPolling = idNumeric.toString();
        console.log('[Polling Start V5.2] Avvio/Riavvio per asta ID:', idChiamataPerPolling);
        
        // Aggiorna il dataset del container se l'asta non è per discovery
        if (astaContainerEl && idChiamataPerPolling !== "0") {
            astaContainerEl.dataset.chiamataId = idChiamataPerPolling;
        } else if (astaContainerEl) {
            astaContainerEl.dataset.chiamataId = ''; // Pulisci se discovery
        }


        fetchStatoAsta(idChiamataPerPolling); 
        window.astaPollingInterval = setInterval(function() {
            if (idChiamataPerPolling === null || idChiamataPerPolling === undefined) { 
                 if(window.astaPollingInterval) clearInterval(window.astaPollingInterval);
                 window.astaPollingInterval = null;
                 console.log('[Polling Stop V5.2] idChiamataPerPolling è nullo, polling fermato.');
                 return;
            }
            fetchStatoAsta(idChiamataPerPolling);
        }, 3000); 
    }

    // --- Avvio Iniziale Logica ---
    const initialAstaIdDaBlade = '{{ $idChiamataInizialePerPolling ?? "0" }}';
    const initialIsAstaLiveDaBlade = {{ $isAstaAttualmenteLive ? 'true' : 'false' }};
    const initialTimestampUnixDaBlade = '{{ $timestampFineInizialePerCountdown ?? "" }}';
    const initialIsAstaInAttesaAdminDaBlade = {{ isset($statoChiamataDaMostrare) && $statoChiamataDaMostrare === 'in_attesa_admin' && optional($impostazioniLega)->asta_tap_approvazione_admin ? 'true' : 'false' }};

    console.log(`[JS Init Blade V5.2] ID Iniziale: ${initialAstaIdDaBlade}, Live?: ${initialIsAstaLiveDaBlade}, Attesa?: ${initialIsAstaInAttesaAdminDaBlade}, TimestampUnixBlade: ${initialTimestampUnixDaBlade}`);
    
    // Imposta idChiamataPerPolling all'ID effettivo passato da Blade
    idChiamataPerPolling = initialAstaIdDaBlade && initialAstaIdDaBlade !== "0" ? initialAstaIdDaBlade : "0";
    if (astaContainerEl && idChiamataPerPolling !== "0") { // Aggiorna il dataset se abbiamo un ID specifico
        astaContainerEl.dataset.chiamataId = idChiamataPerPolling;
    }


    startPollingAsta(idChiamataPerPolling); // Avvia il polling con l'ID corretto

    if (initialIsAstaLiveDaBlade && initialTimestampUnixDaBlade && initialTimestampUnixDaBlade !== 'ErroreParseTS' && initialTimestampUnixDaBlade !== '') {
        avviaOAggiornaCountdown(parseInt(initialTimestampUnixDaBlade));
    } else if (countdownElement && !initialIsAstaLiveDaBlade && (!document.querySelector('#messaggi-globali-asta .rounded-lg'))) { // Solo se non ci sono messaggi di esito
        countdownElement.textContent = "ASTA NON ATTIVA";
    } else if (idChiamataPerPolling === '0' && !initialIsAstaInAttesaAdminDaBlade && !document.querySelector('#messaggi-globali-asta .rounded-lg')) {
        resetInterfacciaPerNuovaAsta("Nessuna asta TAP attiva o in attesa. In attesa di aggiornamenti...");
    }
});
</script>
@endpush
</x-app-layout>