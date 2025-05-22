<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Asta Live') }}
            @if(isset($impostazioniLega))
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
                        @if(isset($chiamataDaMostrare) && $chiamataDaMostrare->stato_chiamata === 'in_asta_tap_live')
                            Asta in corso...
                        @elseif(isset($prossimoChiamanteNome) && $prossimoChiamanteNome !== __('Da definire'))
                            È il turno di: <strong class="text-lg">{{ $prossimoChiamanteNome }}</strong> per effettuare la prossima chiamata.
                        @else
                            Prossimo a chiamare: <strong class="text-lg italic">{{ $prossimoChiamanteNome ?? __('Da definire') }}</strong>
                        @endif
                    </span>
                </p>
                @if(Auth::check() && isset($impostazioniLega) && $impostazioniLega->prossimo_turno_chiamata_user_id == Auth::id() && (!isset($chiamataDaMostrare) || !in_array(optional($chiamataDaMostrare)->stato_chiamata, ['in_asta_tap_live', 'in_attesa_admin'])) )
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
                                $isAstaAttualmenteLive = (isset($chiamataDaMostrare) && $chiamataDaMostrare->stato_chiamata === 'in_asta_tap_live' && isset($calciatoreInAsta));
                                $idChiamataInizialePerPolling = optional($chiamataDaMostrare)->id ?? null; // Può essere live, in attesa, o l'ultima conclusa
                                $timestampFineInizialePerCountdown = '';
                                if ($isAstaAttualmenteLive && $chiamataDaMostrare->timestamp_fine_tap_prevista) {
                                    try {
                                        $timestampFineInizialePerCountdown = Carbon::parse($chiamataDaMostrare->timestamp_fine_tap_prevista)->timestamp;
                                    } catch (\Exception $e) { $timestampFineInizialePerCountdown = 'ErroreParseTS'; }
                                }
                            @endphp

                            <div id="asta-container"
                                 data-chiamata-id="{{ $idChiamataInizialePerPolling }}"
                                 class="{{ $isAstaAttualmenteLive ? '' : 'hidden' }}">
                                {{-- Contenuto dell'asta TAP live (calciatore, prezzo, offerente, bottoni rilancio, timer) --}}
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8 items-start">
                                    <div class="md:col-span-2 space-y-5 p-4 border border-gray-300 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                        <div>
                                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider">{{ __('Calciatore all\'Asta') }}</p>
                                            <h3 class="text-2xl md:text-3xl font-bold text-indigo-700 dark:text-indigo-400" id="calciatore-nome-display">
                                                {{ $isAstaAttualmenteLive ? (optional($calciatoreInAsta)->nome_completo ?? 'N/D') : 'N/D' }}
                                            </h3>
                                            <p class="text-sm text-gray-600 dark:text-gray-300" id="calciatore-dettagli-display">
                                                @if($isAstaAttualmenteLive && isset($calciatoreInAsta))
                                                    {{ $calciatoreInAsta->ruolo }} - {{ $calciatoreInAsta->squadra_serie_a }}
                                                @else - @endif
                                            </p>
                                        </div>
                                        <hr class="dark:border-gray-600">
                                        <div>
                                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider">{{ __('Prezzo Partenza') }}</p>
                                            <p class="text-xl font-semibold" id="prezzo-partenza-display">
                                                {{ $isAstaAttualmenteLive ? (optional($chiamataDaMostrare)->prezzo_partenza_tap ?? '--') : '--' }}
                                            </p>
                                        </div>
                                        <hr class="dark:border-gray-600">
                                        <div>
                                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider">{{ __('Migliore Offerta') }}</p>
                                            <p class="text-xl font-semibold" id="miglior-offerente-wrapper">
                                                <span class="text-green-600 dark:text-green-400" id="prezzo-attuale-valore">
                                                    {{ $isAstaAttualmenteLive ? (optional($chiamataDaMostrare)->prezzo_attuale_tap ?? '--') : '--' }}
                                                </span>
                                                <span id="miglior-offerente-nome">
                                                    @if($isAstaAttualmenteLive && isset($migliorOfferente))
                                                        da {{ $migliorOfferente->name }}
                                                    @elseif($isAstaAttualmenteLive && isset($chiamataDaMostrare) && $chiamataDaMostrare->miglior_offerente_tap_id == optional($chiamataDaMostrare->utenteChiamante)->id)
                                                        da {{ optional($chiamataDaMostrare->utenteChiamante)->name }} (chiamata)
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
                                       data-timestamp-fine="{{ $timestampFineInizialePerCountdown }}">
                                       {{-- Il JS popola questo --}}
                                    </p>
                                </div>
                                <div id="messaggi-rilancio-specifici" class="text-center text-sm mt-4 min-h-[1.5rem]"></div>
                            </div>

                            <div id="nessuna-asta-tap-attiva" class="{{ $isAstaAttualmenteLive ? 'hidden' : '' }}">
                                <div id="testo-placeholder-nessuna-asta" class="text-center text-gray-600 dark:text-gray-400 py-8">
                                    @if(isset($chiamataDaMostrare) && $chiamataDaMostrare->stato_chiamata === 'in_attesa_admin' && $impostazioniLega->asta_tap_approvazione_admin)
                                        <div class="p-4 bg-blue-100 dark:bg-blue-800 border-l-4 border-blue-500 dark:border-blue-400 text-blue-700 dark:text-blue-200">
                                            Calciatore <strong>{{ optional($chiamataDaMostrare->calciatore)->nome_completo ?? 'Sconosciuto' }}</strong>
                                            chiamato da <strong>{{ optional($chiamataDaMostrare->utenteChiamante)->name ?? 'Squadra' }}</strong>.
                                            In attesa di avvio da parte dell'admin.
                                        </div>
                                    @elseif(isset($chiamataDaMostrare) && in_array($chiamataDaMostrare->stato_chiamata, ['conclusa_tap_assegnato', 'conclusa_tap_non_assegnato', 'annullata_admin']))
                                        {{-- Questo messaggio verrà gestito da messaggi-globali-asta, ma possiamo lasciare un placeholder se necessario --}}
                                    @else
                                        {{-- Questo sarà aggiornato dal JS se non ci sono aste attive o in attesa --}}
                                        Nessuna asta TAP attualmente attiva.
                                    @endif
                                </div>
                                @if (Auth::check() && (!isset($chiamataDaMostrare) || !in_array(optional($chiamataDaMostrare)->stato_chiamata, ['in_asta_tap_live', 'in_attesa_admin'])) )
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
    console.log('[ASTA LIVE SCRIPT V5 - Gestione Attesa Admin e Ordine Chiamata] Caricato');

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

    // Elementi per info ordine chiamata
    const boxProssimoChiamanteEl = document.getElementById('box-prossimo-chiamante');
    const testoProssimoChiamanteEl = document.getElementById('testo-prossimo-chiamante');
    const linkVaiAChiamataEl = document.getElementById('link-vai-a-chiamata');
    const linkVaiAChiamataPlaceholderEl = document.getElementById('link-vai-a-chiamata-placeholder');

    // Variabili inizializzate da Blade
    let idChiamataPerPolling = astaContainerEl ? astaContainerEl.dataset.chiamataId : null;
    let timestampFinePerCountdown = countdownElement && countdownElement.dataset.timestampFine && countdownElement.dataset.timestampFine !== 'ErroreParseTS' ? parseInt(countdownElement.dataset.timestampFine) * 1000 : 0;

    const currentUserId = {{ Auth::id() ?? 'null' }};
    const impostazioniLegaAstaTapApprovazioneAdmin = {{ Js::from(optional($impostazioniLega)->asta_tap_approvazione_admin ?? false) }};
    const impostazioniLegaUsaOrdineChiamata = {{ Js::from(optional($impostazioniLega)->usa_ordine_chiamata ?? false) }};

    let astaCountdownInterval = null;
    let astaPollingInterval = null;
    let astaFinalizzataClient = false;

    const URL_BASE_STATO = "{{ url('/asta/tap/stato') }}";
    const URL_BASE_RILANCIA = "{{ url('/asta/tap/rilancia') }}";
    const URL_BASE_FINALIZZA = "{{ url('/asta/tap/finalizza') }}";

    console.log('[JS Init V5] ID Chiamata Iniziale (da data-att):', idChiamataPerPolling);
    console.log('[JS Init V5] Timestamp Fine Iniziale (ms):', timestampFinePerCountdown);
    console.log('[JS Init V5] Approvazione Admin Richiesta:', impostazioniLegaAstaTapApprovazioneAdmin);
    console.log('[JS Init V5] Usa Ordine Chiamata:', impostazioniLegaUsaOrdineChiamata);


    function resetInterfacciaPerNuovaAsta(messaggioPlaceholder = "Caricamento stato asta...") {
        console.log("[UI Reset V5] Resetto interfaccia.", messaggioPlaceholder);
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
    }

    function aggiornaInterfacciaConDatiServer(data) {
        if (!data) { console.warn("aggiornaInterfacciaConDatiServer: no data received."); return; }
        console.log("[UI Update V5] Dati ricevuti:", JSON.parse(JSON.stringify(data)));

        if (creditiPersonaliDisplayEl && data.crediti_utente_corrente !== undefined && data.crediti_utente_corrente !== null) {
            creditiPersonaliDisplayEl.textContent = data.crediti_utente_corrente;
        }

        // Aggiorna info "Prossimo a chiamare" se l'ordine è attivo
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
            if (linkVaiAChiamataPlaceholderEl) { // Questo è il link generico sotto
                 linkVaiAChiamataPlaceholderEl.style.display = (!isMyTurn && noAstaAttivaOInAttesaSpecificamente) ? 'block' : 'none';
            }


        } else if (boxProssimoChiamanteEl) {
            boxProssimoChiamanteEl.style.display = 'none'; // Nascondi se l'ordine non è attivo
        }

        // Logica di visualizzazione principale
        if (data.status === 'in_asta_tap_live') {
            console.log("[UI Update V5] Stato Rilevato: IN_ASTA_TAP_LIVE per chiamata ID:", data.chiamata_id);
            astaFinalizzataClient = false;
            if (astaContainerEl) {
                astaContainerEl.classList.remove('hidden');
                astaContainerEl.dataset.chiamataId = data.chiamata_id;
            }
            if (nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.add('hidden');
            if (messaggiGlobaliDiv && parseInt(idChiamataPerPolling) === parseInt(data.chiamata_id) ) {
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

            avviaOAggiornaCountdown(data.timestamp_fine_prevista_unix);

        } else if (data.asta_in_attesa_admin_id && data.dati_asta_in_attesa_admin && impostazioniLegaAstaTapApprovazioneAdmin) {
            console.log("[UI Update V5] Stato Rilevato: ASTA_IN_ATTESA_ADMIN ID:", data.asta_in_attesa_admin_id);
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
            if (messaggiGlobaliDiv) messaggiGlobaliDiv.innerHTML = '';
            if (linkVaiAChiamataPlaceholderEl) linkVaiAChiamataPlaceholderEl.style.display = 'none';

        } else { // Nessuna asta live, nessuna in attesa (o approvazione admin non attiva) -> mostra esito dell'asta richiesta o messaggio di default
            console.log("[UI Update V5] Stato: NON IN_ASTA_TAP_LIVE e NESSUNA IN ATTESA. Stato ricevuto: " + data.status + ", Chiamata ID: " + data.chiamata_id);
            if (astaContainerEl && (!idChiamataPerPolling || parseInt(idChiamataPerPolling) === parseInt(data.chiamata_id) || data.status === 'non_trovata' )) {
                 astaContainerEl.classList.add('hidden');
            }
            if(nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.remove('hidden');
            if(countdownElement) countdownElement.textContent = "ASTA NON ATTIVA";
            bottoniRilancio.forEach(btn => { btn.disabled = true; btn.classList.add('opacity-50', 'cursor-not-allowed'); });

            let bgColor = 'bg-gray-100 dark:bg-gray-700/50'; let textColor = 'text-gray-700 dark:text-gray-300';
            if (data.status === 'conclusa_tap_assegnato') { bgColor = 'bg-green-100 dark:bg-green-800'; textColor = 'text-green-700 dark:text-green-200'; }
            else if (['conclusa_tap_non_assegnato', 'annullata_admin', 'errore_assegnazione_finale'].includes(data.status)) { bgColor = 'bg-orange-100 dark:bg-orange-800'; textColor = 'text-orange-700 dark:text-orange-200'; }
            else if (data.status === 'non_trovata'){ bgColor = 'bg-yellow-100 dark:bg-yellow-700'; textColor = 'text-yellow-700 dark:text-yellow-200';}


            if (messaggiGlobaliDiv && data.messaggio_esito) {
                messaggiGlobaliDiv.innerHTML = `<div class="text-lg font-semibold p-3 rounded-lg ${bgColor} ${textColor}">${data.messaggio_esito}</div>`;
            } else if (messaggiGlobaliDiv) {
                 messaggiGlobaliDiv.innerHTML = '';
            }

            if(testoPlaceholderNessunaAstaEl) {
                testoPlaceholderNessunaAstaEl.style.display = data.messaggio_esito ? 'none' : 'block';
                if (!data.messaggio_esito) {
                     testoPlaceholderNessunaAstaEl.innerHTML = "<p class='text-gray-500 dark:text-gray-400'>Nessuna asta TAP attualmente attiva.</p>";
                }
            }
            // Mostra link generico per chiamare se non c'è un'asta attiva/attesa e non è il turno dell'utente (se ordine attivo)
             if (linkVaiAChiamataPlaceholderEl &&
                data.status !== 'in_attesa_admin' &&
                !(impostazioniLegaUsaOrdineChiamata && data.prossimo_chiamante_id && parseInt(data.prossimo_chiamante_id) === parseInt(currentUserId))
                ) {
                 linkVaiAChiamataPlaceholderEl.style.display = 'block';
            } else if (linkVaiAChiamataPlaceholderEl){
                linkVaiAChiamataPlaceholderEl.style.display = 'none';
            }


            if (['conclusa_tap_assegnato', 'conclusa_tap_non_assegnato', 'annullata_admin', 'non_trovata'].includes(data.status) &&
                idChiamataPerPolling && parseInt(idChiamataPerPolling) === parseInt(data.chiamata_id)) {
                // Non fermare il polling interval qui, lascialo continuare per la discovery
                if (astaCountdownInterval) clearInterval(astaCountdownInterval); astaCountdownInterval = null;
                console.log("[UI Update V5] Countdown fermato per stato finale:", data.status, "per ID:", data.chiamata_id);
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
                console.log("[Countdown JS V5] Tempo scaduto, chiamo finalizzaAstaSulServer per ID:", idAstaDaFinalizzare);
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
        const tolleranzaPassato = 60; // secondi

        console.log(`[Countdown JS V5] avviaOAggiorna. Ricevuto Unix: ${nuovoTimestampFineUnixServer}`);

        if (tsRicevutoUnix && tsRicevutoUnix > (oraAttualeUnix - tolleranzaPassato)) {
            timestampFinePerCountdown = tsRicevutoUnix * 1000;
            if (countdownElement) countdownElement.dataset.timestampFine = nuovoTimestampFineUnixServer.toString();
        } else {
            console.warn('[Countdown JS V5] Timestamp non valido, nullo, o troppo nel passato:', nuovoTimestampFineUnixServer, ". Imposto timestampFinePerCountdown a 0.");
            timestampFinePerCountdown = 0;
            if (countdownElement) countdownElement.dataset.timestampFine = '';
        }

        if(astaCountdownInterval) clearInterval(astaCountdownInterval);
        astaCountdownInterval = null;

        if (countdownElement && timestampFinePerCountdown > 0) {
            updateCountdown();
            astaCountdownInterval = setInterval(updateCountdown, 1000);
            console.log('[Countdown JS V5] Countdown avviato/resettato. Fine (ms):', timestampFinePerCountdown);
        } else if (countdownElement) {
             countdownElement.textContent = (idChiamataPerPolling && astaContainerEl && !astaContainerEl.classList.contains('hidden')) ? "--:--" : "ASTA NON ATTIVA";
        }
    }

    bottoniRilancio.forEach(button => {
        button.addEventListener('click', function() {
            if (this.disabled) return;
            const idAstaPerRilancio = astaContainerEl && astaContainerEl.dataset.chiamataId ? parseInt(astaContainerEl.dataset.chiamataId) : null;

            if (!idAstaPerRilancio || (idChiamataPerPolling && idAstaPerRilancio !== parseInt(idChiamataPerPolling)) ) {
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
                    // Il polling successivo aggiornerà l'interfaccia.
                } else {
                    if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-red-500 dark:text-red-300">${'Errore'}: ${data.error || data.message || 'Offerta non valida ('+status+').'}</span>`;
                }
                 // Forza un fetch immediato dello stato dopo un rilancio per aggiornare subito l'UI
                if (idChiamataPerPolling) fetchStatoAsta(idChiamataPerPolling);
            })
            .catch(error => {
                console.error('[Rilancio JS] Catch Errore Fetch:', error);
                if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-red-500 dark:text-red-300">${error.message || 'Errore di connessione o server.'}</span>`;
            });
        });
    });

    function finalizzaAstaSulServer(chiamataIdDaFinalizzare) {
        if (!chiamataIdDaFinalizzare) { console.error('[Finalizza JS V5] ID chiamata mancante.'); return; }
        console.log('[Finalizza JS V5] Tentativo finalizzazione per ID:', chiamataIdDaFinalizzare);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if(!csrfToken) { console.error('[Finalizza JS V5] CSRF Token mancante.'); return; }
        if(messaggiGlobaliDiv && messaggiGlobaliDiv.innerHTML.trim() === '') messaggiGlobaliDiv.innerHTML = `<div class="p-2 text-blue-600 dark:text-blue-300">${'Finalizzazione asta in corso...'}</div>`;

        fetch(`${URL_BASE_FINALIZZA}/${chiamataIdDaFinalizzare}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            console.log('[Finalizza JS V5] Risposta da finalizzazione server:', data);
            // Il prossimo polling aggiornerà l'interfaccia, o forziamo un aggiornamento
            if (idChiamataPerPolling) fetchStatoAsta(idChiamataPerPolling);
        })
        .catch(error => {
            console.error('Errore durante la finalizzaAstaSulServer:', error);
            if(messaggiGlobaliDiv) messaggiGlobaliDiv.innerHTML = `<div class="p-2 text-red-500 dark:text-red-300">Errore tecnico durante la finalizzazione.</div>`;
        });
    }

    function fetchStatoAsta(idAstaDaControllareInput) {
        const idNumericToControl = idAstaDaControllareInput ? parseInt(idAstaDaControllareInput) : 0; // Se null/undefined, usa 0 per discovery

        if (idNumericToControl === 0) {
            console.warn('[Polling Fetch V5] ID 0 per discovery. Chiamo il server per stato generale.');
        } else if (isNaN(idNumericToControl)) {
            console.error('[Polling Fetch V5] ID asta da controllare NON VALIDO (NaN):', idAstaDaControllareInput);
            // Potremmo fermare il polling o tentare una discovery
            if(window.astaPollingInterval) clearInterval(window.astaPollingInterval); window.astaPollingInterval = null;
            resetInterfacciaPerNuovaAsta("Errore: ID asta non valido.");
            return;
        }

        fetch(`${URL_BASE_STATO}/${idNumericToControl}`) // Chiamerà /asta/tap/stato/0 per discovery
            .then(response => {
                if (!response.ok) {
                    console.error(`[Polling Fetch V5] Errore HTTP ${response.status} per ID ${idNumericToControl}`);
                    return response.json().then(errData => { throw { responseStatus: response.status, data: errData, requestedId: idNumericToControl }; });
                }
                return response.json();
            })
            .then(data => {
                if (!data) { console.warn("[Polling Fetch V5] Dati nulli ricevuti per ID:", idNumericToControl); return; }

                const serverReportedLiveId = data.asta_live_corrente_id ? parseInt(data.asta_live_corrente_id) : null;
                const serverReportedAttesaId = data.asta_in_attesa_admin_id ? parseInt(data.asta_in_attesa_admin_id) : null;
                let currentlyPollingIdNumeric = idChiamataPerPolling ? parseInt(idChiamataPerPolling) : 0;
                if (isNaN(currentlyPollingIdNumeric)) currentlyPollingIdNumeric = 0;


                console.log(`[Polling JS V5 Debug] Polling per ID: ${currentlyPollingIdNumeric}. Server dice LIVE: ${serverReportedLiveId}. Server dice ATTESA: ${serverReportedAttesaId}. Dati ricevuti per chiamata ID: ${data.chiamata_id}. Stato dati: ${data.status}`);

                // Logica di transizione prioritaria
                if (serverReportedLiveId && serverReportedLiveId !== currentlyPollingIdNumeric) {
                    console.warn(`[Polling Transizione V5] NUOVA ASTA LIVE (ID: ${serverReportedLiveId}) rilevata! Cambio target polling.`);
                    if (window.astaPollingInterval) clearInterval(window.astaPollingInterval);
                    resetInterfacciaPerNuovaAsta("Caricamento nuova asta live...");
                    idChiamataPerPolling = serverReportedLiveId.toString();
                    if (astaContainerEl) astaContainerEl.dataset.chiamataId = idChiamataPerPolling;
                    startPollingAsta(idChiamataPerPolling); // Riavvia polling per la nuova asta live
                    return; // Esce per evitare di processare dati vecchi/conflittuali
                }

                if (!serverReportedLiveId && serverReportedAttesaId && serverReportedAttesaId !== currentlyPollingIdNumeric) {
                    console.warn(`[Polling Transizione V5] NUOVA ASTA IN ATTESA (ID: ${serverReportedAttesaId}) rilevata! Cambio target polling.`);
                    if (window.astaPollingInterval) clearInterval(window.astaPollingInterval);
                    resetInterfacciaPerNuovaAsta("Caricamento stato asta in attesa...");
                    idChiamataPerPolling = serverReportedAttesaId.toString();
                    startPollingAsta(idChiamataPerPolling);
                    return;
                }

                // Se non ci sono transizioni di target, aggiorna l'interfaccia con i dati ricevuti
                aggiornaInterfacciaConDatiServer(data);

                // Logica di finalizzazione se l'asta corrente (quella polata) scade
                if (data.status === 'in_asta_tap_live' && data.chiamata_id === currentlyPollingIdNumeric &&
                    data.secondi_rimanenti !== undefined && data.secondi_rimanenti <= 0 && !astaFinalizzataClient) {
                    console.log("[Polling Fetch V5] Asta corrente (ID:", currentlyPollingIdNumeric, ") live con tempo scaduto. Avvio finalizzazione client.");
                    astaFinalizzataClient = true;
                    finalizzaAstaSulServer(currentlyPollingIdNumeric);
                }

                // Se l'asta polata è conclusa e non c'è una nuova asta live o in attesa,
                // passa a un polling di discovery.
                if (['conclusa_tap_assegnato', 'conclusa_tap_non_assegnato', 'annullata_admin', 'non_trovata'].includes(data.status) &&
                    data.chiamata_id === currentlyPollingIdNumeric &&
                    !serverReportedLiveId && !serverReportedAttesaId) {
                    if (window.astaPollingInterval) clearInterval(window.astaPollingInterval);
                    console.log(`[Polling JS V5] Asta ${currentlyPollingIdNumeric} terminata. Avvio polling discovery (ID 0).`);
                    idChiamataPerPolling = '0';
                    startPollingAsta('0'); // Inizia a fare polling per scoprire nuove aste
                }

            })
            .catch(error => {
                console.error('[Polling Fetch V5] Errore Catch:', error);
                if (error.responseStatus === 404 && error.requestedId && parseInt(error.requestedId) !== 0) {
                     aggiornaInterfacciaConDatiServer({ status: 'non_trovata', chiamata_id: error.requestedId, messaggio_esito: 'Asta (ID: '+error.requestedId+') non più disponibile.' });
                } else if (error.requestedId === 0) { // Errore durante la discovery
                    console.error('[Polling Fetch V5 - Discovery] Errore durante la discovery. Riprovo tra poco.');
                    // Non cambiare UI drasticamente, il polling si riavvierà
                }
            });
    }

    function startPollingAsta(idCurrentAsta) {
        const idNumeric = idCurrentAsta ? parseInt(idCurrentAsta) : 0;

        if (window.astaPollingInterval) { clearInterval(window.astaPollingInterval); window.astaPollingInterval = null; }

        idChiamataPerPolling = idNumeric.toString();
        console.log('[Polling Start V5] Avvio/Riavvio per asta ID:', idChiamataPerPolling);

        fetchStatoAsta(idChiamataPerPolling);
        window.astaPollingInterval = setInterval(function() {
            if (idChiamataPerPolling === null || idChiamataPerPolling === undefined) { // Controllo più robusto
                 if(window.astaPollingInterval) clearInterval(window.astaPollingInterval);
                 window.astaPollingInterval = null;
                 console.log('[Polling Stop V5] idChiamataPerPolling è nullo, polling fermato.');
                 return;
            }
            fetchStatoAsta(idChiamataPerPolling);
        }, 3000);
    }

    // --- Avvio Iniziale Logica ---
    const initialAstaIdDaBlade = "{{ optional($chiamataDaMostrare)->id ?? '0' }}"; // Se non c'è nulla, usa '0' per discovery
    const initialIsAstaLiveDaBlade = {{ (isset($chiamataDaMostrare) && $chiamataDaMostrare->stato_chiamata === 'in_asta_tap_live') ? 'true' : 'false' }};
    const initialTimestampUnixDaBlade = "{{ $timestampFineInizialePerCountdown ?? '' }}";
    const initialIsAstaInAttesaAdminDaBlade = {{ (isset($chiamataDaMostrare) && $chiamataDaMostrare->stato_chiamata === 'in_attesa_admin' && optional($impostazioniLega)->asta_tap_approvazione_admin) ? 'true' : 'false' }};
    const initialCalciatoreNomeAttesaDaBlade = "{{ $initialDatiAstaInAttesa_calciatoreNome ?? '' }}";
    const initialChiamanteNomeAttesaDaBlade = "{{ $initialDatiAstaInAttesa_chiamanteNome ?? '' }}";

    console.log(`[JS Init Blade V5] ID Iniziale: ${initialAstaIdDaBlade}, Live?: ${initialIsAstaLiveDaBlade}, Attesa?: ${initialIsAstaInAttesaAdminDaBlade}, Timestamp: ${initialTimestampUnixDaBlade}`);

    // Indipendentemente dallo stato iniziale, avviamo il polling.
    // Se c'è un ID specifico, polliamo per quello. Se è '0', il primo poll farà discovery.
    startPollingAsta(initialAstaIdDaBlade);

    // Se la pagina carica e sappiamo già che un'asta è live, avviamo il countdown.
    // Se è in attesa, il primo poll con aggiornaInterfacciaConDatiServer mostrerà il messaggio corretto.
    if (initialIsAstaLiveDaBlade && initialTimestampUnixDaBlade && initialTimestampUnixDaBlade !== 'ErroreParseTS') {
        avviaOAggiornaCountdown(parseInt(initialTimestampUnixDaBlade));
    } else if (initialIsAstaInAttesaAdminDaBlade) {
        // Mostra il messaggio di attesa subito se i dati sono disponibili da PHP
        if(testoPlaceholderNessunaAstaEl && nessunaAstaTapDivEl && !initialIsAstaLiveDaBlade) {
            nessunaAstaTapDivEl.classList.remove('hidden');
            if(astaContainerEl) astaContainerEl.classList.add('hidden');
            let msgAttesa = `Calciatore <strong>${initialCalciatoreNomeAttesaDaBlade || 'Sconosciuto'}</strong> chiamato da <strong>${initialChiamanteNomeAttesaDaBlade || 'Squadra'}</strong>. In attesa di avvio da parte dell'admin.`;
            testoPlaceholderNessunaAstaEl.innerHTML = `<div class="p-4 bg-blue-100 dark:bg-blue-800 border-l-4 border-blue-500 dark:border-blue-400 text-blue-700 dark:text-blue-200 rounded-md">${msgAttesa}</div>`;
            if (linkVaiAChiamataPlaceholderEl) linkVaiAChiamataPlaceholderEl.style.display = 'none';
         }
    } else if (countdownElement && !initialIsAstaLiveDaBlade) { // Se non è live e non in attesa, ma c'è un ID valido (es. asta conclusa)
        countdownElement.textContent = "ASTA NON ATTIVA";
    } else if (!initialAstaIdDaBlade || initialAstaIdDaBlade === '0') { // Se non c'è proprio nessun ID di riferimento
        resetInterfacciaPerNuovaAsta("Nessuna asta TAP attiva o in attesa. In attesa di aggiornamenti...");
    }
});
</script>
@endpush
</x-app-layout>