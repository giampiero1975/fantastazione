<?php if (isset($component)) { $__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da = $component; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? (array) $attributes->getIterator() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag && $constructor = (new ReflectionClass(App\View\Components\AppLayout::class))->getConstructor()): ?>
<?php $attributes = $attributes->except(collect($constructor->getParameters())->map->getName()->all()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
     <?php $__env->slot('header', null, []); ?> 
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            <?php echo e(__('Asta Live')); ?>

            <?php if(isset($impostazioniLega) && $impostazioniLega instanceof \App\Models\ImpostazioneLega): ?>
                - Fase: <span class="font-bold" id="fase-asta-display"><?php echo e($impostazioniLega->fase_asta_corrente); ?></span>
                <?php if($impostazioniLega->tag_lista_attiva): ?>
                    (Lista: <span class="font-bold" id="tag-lista-display"><?php echo e($impostazioniLega->tag_lista_attiva); ?></span>)
                <?php endif; ?>
            <?php else: ?>
                <span class="font-bold text-red-500"> <?php echo e(__('(Impostazioni Lega non caricate!)')); ?></span>
            <?php endif; ?>
        </h2>
     <?php $__env->endSlot(); ?>

    <div class="py-6 md:py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">

            <?php if(isset($impostazioniLega) && $impostazioniLega->usa_ordine_chiamata): ?>
            <div class="mb-6 p-4 bg-blue-100 dark:bg-blue-800 border-l-4 border-blue-500 dark:border-blue-400 text-blue-700 dark:text-blue-200 shadow-sm sm:rounded-lg" id="box-prossimo-chiamante">
                <p class="font-semibold">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 inline-block mr-2 -mt-1" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 11-7.5 0 3.75 3.75 0 017.5 0zM4.501 20.118a7.5 7.5 0 0114.998 0A17.933 17.933 0 0112 21.75c-2.676 0-5.216-.584-7.499-1.632z" />
                    </svg>
                    <span id="testo-prossimo-chiamante">
                        <?php if(isset($isAstaAttualmenteLive) && $isAstaAttualmenteLive): ?>
                            Asta in corso...
                        <?php elseif(isset($prossimoChiamanteNome) && $prossimoChiamanteNome !== __('Da definire')): ?>
                            È il turno di: <strong class="text-lg"><?php echo e($prossimoChiamanteNome); ?></strong> per effettuare la prossima chiamata.
                        <?php else: ?>
                            Prossimo a chiamare: <strong class="text-lg italic"><?php echo e($prossimoChiamanteNome ?? __('Da definire')); ?></strong>
                        <?php endif; ?>
                    </span>
                </p>
                <p class="mt-2 text-sm" id="link-vai-a-chiamata" style="display: none;"> 
    <a href="<?php echo e(route('asta.calciatori.disponibili')); ?>" class="text-white bg-green-500 hover:bg-green-600 font-medium rounded-lg text-sm px-4 py-2 inline-flex items-center">
        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 mr-2" viewBox="0 0 20 20" fill="currentColor">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-11a1 1 0 10-2 0v2H7a1 1 0 100 2h2v2a1 1 0 102 0v-2h2a1 1 0 100-2h-2V7z" clip-rule="evenodd" />
        </svg>
        Tocca a te! Vai alla lista giocatori
    </a>
</p>
            </div>
            <?php endif; ?>

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-xl sm:rounded-lg">
                <div class="p-6 md:p-8 text-gray-900 dark:text-gray-100">

                    <div id="messaggi-globali-asta" class="mb-6 text-center min-h-[2.5rem] p-1 text-sm">
                        <?php if(isset($messaggioEsitoIniziale) && $messaggioEsitoIniziale): ?>
                            <div class="text-lg font-semibold p-3 rounded-lg
                                <?php if($statoChiamataDaMostrare === 'conclusa_tap_assegnato'): ?> bg-green-100 dark:bg-green-800 text-green-700 dark:text-green-200
                                <?php elseif(in_array($statoChiamataDaMostrare, ['conclusa_tap_non_assegnato', 'annullata_admin'])): ?> bg-orange-100 dark:bg-orange-800 text-orange-700 dark:text-orange-200
                                <?php else: ?> bg-gray-100 dark:bg-gray-700/50 text-gray-700 dark:text-gray-300
                                <?php endif; ?>">
                                <?php echo e($messaggioEsitoIniziale); ?>

                            </div>
                        <?php endif; ?>
                        <?php if(session('success')): ?> <div class="text-green-700 bg-green-100 dark:bg-green-800 dark:text-green-200 p-3 rounded-lg"><?php echo e(session('success')); ?></div> <?php endif; ?>
                        <?php if(session('error')): ?> <div class="text-red-700 bg-red-100 dark:bg-red-800 dark:text-red-200 p-3 rounded-lg"><?php echo e(session('error')); ?></div> <?php endif; ?>
                    </div>

                    <?php if(isset($impostazioniLega) && $impostazioniLega instanceof \App\Models\ImpostazioneLega): ?>
                        <div class="mb-4 text-center md:text-left">
                            <p><strong><?php echo e(__('Modalità Asta:')); ?></strong>
                                <span class="font-semibold <?php echo e($impostazioniLega->modalita_asta === 'tap' ? 'text-blue-600 dark:text-blue-400' : 'text-orange-600 dark:text-orange-400'); ?>" id="modalita-asta-display">
                                    <?php echo e($impostazioniLega->modalita_asta === 'tap' ? __('A Rilancio TAP') : __('A Voce (Admin assegna)')); ?>

                                    <?php if($impostazioniLega->modalita_asta === 'tap' && $impostazioniLega->asta_tap_approvazione_admin): ?>
                                        <span class="text-xs italic"> (con approvazione admin)</span>
                                    <?php elseif($impostazioniLega->modalita_asta === 'tap'): ?>
                                        <span class="text-xs italic"> (avvio automatico)</span>
                                    <?php endif; ?>
                                </span>
                            </p>
                            <?php if(auth()->guard()->check()): ?>
                            <p><strong><?php echo e(__('Crediti Personali:')); ?></strong> <span class="font-semibold" id="crediti-personali-display"><?php echo e($creditiRimanenti ?? 'N/D'); ?></span></p>
                            <?php endif; ?>
                        </div>
                        <hr class="my-6 dark:border-gray-700">

                        <?php if($impostazioniLega->modalita_asta === 'tap'): ?>
                            <div id="asta-container"
                                 data-chiamata-id="<?php echo e($idChiamataInizialePerPolling ?? ''); ?>"
                                 class="<?php echo e($isAstaAttualmenteLive ? '' : 'hidden'); ?>">
                                <div class="grid grid-cols-1 md:grid-cols-3 gap-6 md:gap-8 items-start">
                                    <div class="md:col-span-2 space-y-5 p-4 border border-gray-300 dark:border-gray-700 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                        <div>
                                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider"><?php echo e(__('Calciatore all\'Asta')); ?></p>
                                            <h3 class="text-2xl md:text-3xl font-bold text-indigo-700 dark:text-indigo-400" id="calciatore-nome-display">
                                                <?php echo e($isAstaAttualmenteLive && isset($calciatoreInAsta_nome) ? $calciatoreInAsta_nome : 'N/D'); ?>

                                            </h3>
                                            <p class="text-sm text-gray-600 dark:text-gray-300" id="calciatore-dettagli-display">
                                                <?php if($isAstaAttualmenteLive && isset($calciatoreInAsta_ruolo) && isset($calciatoreInAsta_squadra)): ?>
                                                    <?php echo e($calciatoreInAsta_ruolo); ?> - <?php echo e($calciatoreInAsta_squadra); ?>

                                                <?php else: ?> - <?php endif; ?>
                                            </p>
                                        </div>
                                        <hr class="dark:border-gray-600">
                                        <div>
                                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider"><?php echo e(__('Prezzo Partenza')); ?></p>
                                            <p class="text-xl font-semibold" id="prezzo-partenza-display">
                                                <?php echo e($isAstaAttualmenteLive && isset($prezzoPartenzaTapIniziale) ? $prezzoPartenzaTapIniziale : '--'); ?>

                                            </p>
                                        </div>
                                        <hr class="dark:border-gray-600">
                                        <div>
                                            <p class="text-xs uppercase text-gray-500 dark:text-gray-400 tracking-wider"><?php echo e(__('Migliore Offerta')); ?></p>
                                            <p class="text-xl font-semibold" id="miglior-offerente-wrapper">
                                                <span class="text-green-600 dark:text-green-400" id="prezzo-attuale-valore">
                                                    <?php echo e($isAstaAttualmenteLive && isset($prezzoAttualeTapIniziale) ? $prezzoAttualeTapIniziale : '--'); ?>

                                                </span>
                                                <span id="miglior-offerente-nome">
                                                    <?php if($isAstaAttualmenteLive && isset($migliorOfferente_nome)): ?>
                                                        da <?php echo e($migliorOfferente_nome); ?>

                                                    <?php elseif($isAstaAttualmenteLive && (!isset($migliorOfferente_nome) && isset($prezzoAttualeTapIniziale) && $prezzoAttualeTapIniziale > 0 ) ): ?>
                                                         <?php echo e(__(' (Offerta base)')); ?>

                                                    <?php elseif($isAstaAttualmenteLive): ?>
                                                        <?php echo e(__(' (Nessuna offerta superiore)')); ?>

                                                    <?php else: ?> - <?php endif; ?>
                                                </span>
                                            </p>
                                        </div>
                                    </div>

                                    <div class="md:col-span-1 space-y-3 flex flex-col justify-center">
                                        <h4 class="text-lg font-semibold mb-2 text-center"><?php echo e(__('Fai la tua Offerta')); ?></h4>
                                        <?php $incrementi = [1, 5, 10, 30]; ?>
                                        <?php $__currentLoopData = $incrementi; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $inc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <button type="button" data-rilancio="<?php echo e($inc); ?>"
                                                    class="rilancio-btn w-full justify-center text-lg font-bold py-3 px-6 rounded-full text-white focus:outline-none focus:ring-2 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 <?php switch($inc): case (1): ?> bg-indigo-600 hover:bg-indigo-700 focus:ring-indigo-500 <?php break; ?> <?php case (5): ?> bg-blue-500 hover:bg-blue-600 focus:ring-blue-500 <?php break; ?> <?php case (10): ?> bg-yellow-500 hover:bg-yellow-600 text-black focus:ring-yellow-500 <?php break; ?> <?php case (30): ?> bg-orange-500 hover:bg-orange-600 focus:ring-orange-500 <?php break; ?> <?php endswitch; ?>">
                                                +<?php echo e($inc); ?>

                                            </button>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                    </div>
                                </div>

                                <div class="text-center mt-8 p-4 border border-gray-300 dark:border-gray-600 rounded-lg bg-gray-50 dark:bg-gray-700/50">
                                    <p class="text-md uppercase text-gray-600 dark:text-gray-300 tracking-wider"><?php echo e(__('Tempo Rimanente')); ?></p>
                                    <p class="text-5xl md:text-6xl font-bold text-red-600 dark:text-red-400 my-2" id="countdown-timer"
                                       data-timestamp-fine="<?php echo e($timestampFineInizialePerCountdown ?? ''); ?>">
                                       
                                    </p>
                                </div>
                                <div id="messaggi-rilancio-specifici" class="text-center text-sm mt-4 min-h-[1.5rem]"></div>
                            </div>

                            <div id="nessuna-asta-tap-attiva" class="<?php echo e($isAstaAttualmenteLive ? 'hidden' : ''); ?>">
                                <div id="testo-placeholder-nessuna-asta" class="text-center text-gray-600 dark:text-gray-400 py-8">
                                    <?php if(isset($statoChiamataDaMostrare) && $statoChiamataDaMostrare === 'in_attesa_admin' && $impostazioniLega->asta_tap_approvazione_admin && isset($initialDatiAstaInAttesa_calciatoreNome)): ?>
                                        <div class="p-4 bg-blue-100 dark:bg-blue-800 border-l-4 border-blue-500 dark:border-blue-400 text-blue-700 dark:text-blue-200">
                                            Calciatore <strong><?php echo e($initialDatiAstaInAttesa_calciatoreNome ?? 'Sconosciuto'); ?></strong>
                                            chiamato da <strong><?php echo e($initialDatiAstaInAttesa_chiamanteNome ?? 'Squadra'); ?></strong>.
                                            In attesa di avvio da parte dell'admin.
                                        </div>
                                    <?php elseif(isset($statoChiamataDaMostrare) && in_array($statoChiamataDaMostrare, ['conclusa_tap_assegnato', 'conclusa_tap_non_assegnato', 'annullata_admin'])): ?>
                                        
                                    <?php elseif(!isset($messaggioEsitoIniziale)): ?>
                                        Nessuna asta TAP attualmente attiva.
                                    <?php endif; ?>
                                </div>
                                <?php if(Auth::check() && !$isAstaAttualmenteLive && (!isset($statoChiamataDaMostrare) || $statoChiamataDaMostrare !== 'in_attesa_admin') ): ?>
                                    <p class="text-center mt-4" id="link-vai-a-chiamata-placeholder">
                                        <a href="<?php echo e(route('asta.calciatori.disponibili')); ?>" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                            <?php echo e(__('Vai alla lista giocatori per chiamarne uno')); ?>

                                        </a>
                                    </p>
                                <?php endif; ?>
                            </div>

                        <?php elseif($impostazioniLega->modalita_asta === 'voce'): ?>
                            <div class="text-center py-8">
                                <h3 class="text-xl font-semibold mb-2"><?php echo e(__('Modalità Asta: A Voce')); ?></h3>
                                <p class="text-gray-600 dark:text-gray-400"><?php echo e(__('L\'asta si svolge a voce. Seguire le indicazioni dell\'amministratore.')); ?></p>
                                <p class="mt-4 text-gray-600 dark:text-gray-400"><?php echo e(__('L\'admin registrerà manually gli acquisti.')); ?></p>
                                <p class="text-center mt-4">
                                    <a href="<?php echo e(route('asta.calciatori.disponibili')); ?>" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                        <?php echo e(__('Visualizza Lista Giocatori di Riferimento')); ?>

                                    </a>
                                </p>
                            </div>
                        <?php else: ?>
                             <p class="text-center text-gray-600 dark:text-gray-400 py-8"><?php echo e(__('Modalità asta non definita o impostazioni mancanti.')); ?></p>
                        <?php endif; ?>
                    <?php else: ?>
                        <p class="text-center text-gray-600 dark:text-gray-400 py-8"><?php echo e(__('Impostazioni della lega non caricate. Contattare l\'admin.')); ?></p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

<?php $__env->startPush('scripts'); ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('[ASTA LIVE SCRIPT V11.1 - Gestione Congelamento Esito] Caricato');

    // Elementi DOM
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

    // Stato Globale JS
    let idChiamataPerPolling = '<?php echo e($idChiamataInizialePerPolling ?? "0"); ?>';
    let timestampFinePerCountdown = countdownElement && countdownElement.dataset.timestampFine && countdownElement.dataset.timestampFine !== 'ErroreParseTS' && countdownElement.dataset.timestampFine !== '' ? parseInt(countdownElement.dataset.timestampFine) * 1000 : 0;
    const currentUserId = <?php echo e(Auth::id() ?? 'null'); ?>;
    const impostazioniLegaAstaTapApprovazioneAdmin = <?php echo e(Js::from(optional($impostazioniLega)->asta_tap_approvazione_admin ?? false)); ?>;
    const impostazioniLegaUsaOrdineChiamata = <?php echo e(Js::from(optional($impostazioniLega)->usa_ordine_chiamata ?? false)); ?>;

    let astaCountdownInterval = null;
    let astaPollingInterval = null;
    let astaFinalizzataClient = false;

    let ultimoIdAstaAttivamenteMostrata = null; // ID dell'asta (live o ultima conclusa) attualmente renderizzata
    let datiUltimaAstaCongelata = null;     // Dati dell'ultima asta il cui esito è "congelato" sull'UI

    // URL API
    const URL_BASE_STATO = "<?php echo e(url('/asta/tap/stato')); ?>";
    const URL_BASE_RILANCIA = "<?php echo e(url('/asta/tap/rilancia')); ?>";
    const URL_BASE_FINALIZZA = "<?php echo e(url('/asta/tap/finalizza')); ?>";

    console.log(`[JS Init V11.1] ID Chiamata Iniziale (da Blade): ${idChiamataPerPolling}, Timestamp Fine (ms): ${timestampFinePerCountdown}`);

    function resetInterfacciaAstaAttiva(messaggioPlaceholder = "Nessuna asta TAP attualmente attiva.") {
        console.log("[UI Reset V11.1] Reset Interfaccia Asta Attiva.", messaggioPlaceholder);

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

        // Non nascondere astaContainerEl qui se vogliamo mostrare un'asta congelata
        // if(astaContainerEl) astaContainerEl.classList.add('hidden');
        // if(nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.remove('hidden');
        // if(testoPlaceholderNessunaAstaEl) {
        //     testoPlaceholderNessunaAstaEl.innerHTML = `<p class="text-gray-500 dark:text-gray-400">${messaggioPlaceholder}</p>`;
        //     testoPlaceholderNessunaAstaEl.style.display = 'block';
        // }
    }

    function mostraDatiAsta(data, tipoDisplay = 'live') { // tipoDisplay può essere 'live' o 'congelata'
        console.log(`[UI MostraDati V11.1] Tipo: ${tipoDisplay}, Dati:`, data);
        ultimoIdAstaAttivamenteMostrata = data.chiamata_id;

        if (astaContainerEl) astaContainerEl.classList.remove('hidden');
        if (nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.add('hidden');
        if (testoPlaceholderNessunaAstaEl) testoPlaceholderNessunaAstaEl.style.display = 'none';
        if (astaContainerEl) astaContainerEl.dataset.chiamataId = data.chiamata_id;

        if(calciatoreNomeDisplayEl) calciatoreNomeDisplayEl.textContent = data.calciatore_nome || 'N/D';
        if(calciatoreDettagliDisplayEl) calciatoreDettagliDisplayEl.textContent = (data.calciatore_ruolo && data.calciatore_squadra_serie_a) ? `${data.calciatore_ruolo} - ${data.calciatore_squadra_serie_a}` : '-';
        if(prezzoPartenzaDisplayEl) prezzoPartenzaDisplayEl.textContent = data.prezzo_partenza_tap !== undefined ? data.prezzo_partenza_tap : '--';

        if (tipoDisplay === 'live') {
            datiUltimaAstaCongelata = null; // Una nuova asta live "scongela" la precedente
            if (messaggiGlobaliDiv) messaggiGlobaliDiv.innerHTML = ''; // Pulisci messaggi globali per asta live

            if(prezzoAttualeValEl) prezzoAttualeValEl.textContent = data.prezzo_attuale !== null ? data.prezzo_attuale : '--';
            if(migliorOfferenteNomeEl) migliorOfferenteNomeEl.textContent = data.miglior_offerente ? `da ${data.miglior_offerente}` : (data.prezzo_attuale > 0 ? ' (Offerta base)' : ' - ');

            bottoniRilancio.forEach(btn => {
                const isDisabled = (data.miglior_offerente_id === currentUserId || !currentUserId);
                btn.disabled = isDisabled;
                btn.classList.toggle('opacity-50', isDisabled);
                btn.classList.toggle('cursor-not-allowed', isDisabled);
            });
            avviaOAggiornaCountdown(data.timestamp_fine_prevista_unix);
            astaFinalizzataClient = false; // Assicurati che non sia già finalizzata dal client

        } else { // 'congelata' o 'in_attesa_admin' o altro stato non-live
            datiUltimaAstaCongelata = data; // Conserva questi dati

            if(prezzoAttualeValEl) prezzoAttualeValEl.textContent = data.prezzo_finale !== null ? data.prezzo_finale : (data.prezzo_attuale_tap || '--');
            if(migliorOfferenteNomeEl) {
                let testoOfferta = ' - ';
                if(data.status_originale === 'conclusa_tap_assegnato' && data.vincitore_nome) {
                    testoOfferta = `vinto da ${data.vincitore_nome}`;
                } else if (data.miglior_offerente) {
                    testoOfferta = `da ${data.miglior_offerente}`;
                } else if (data.status_originale === 'conclusa_tap_non_assegnato' || data.status_originale === 'annullata_admin'){
                    testoOfferta = ' (Nessun Vincitore)';
                }
                 migliorOfferenteNomeEl.textContent = testoOfferta;
            }

            if(countdownElement) countdownElement.textContent = data.status === 'in_attesa_admin' ? "IN ATTESA ADMIN" : "ASTA TERMINATA";
            bottoniRilancio.forEach(btn => { btn.disabled = true; btn.classList.add('opacity-50', 'cursor-not-allowed'); });
            if(astaCountdownInterval) clearInterval(astaCountdownInterval);
            astaCountdownInterval = null;

            if (messaggiGlobaliDiv && data.messaggio_esito) {
                let bgColor = 'bg-gray-100 dark:bg-gray-700/50'; let textColor = 'text-gray-700 dark:text-gray-300';
                const statoMsg = data.status_originale || data.status; // Usa status_originale se presente
                if (statoMsg === 'conclusa_tap_assegnato') { bgColor = 'bg-green-100 dark:bg-green-800'; textColor = 'text-green-700 dark:text-green-200'; }
                else if (['conclusa_tap_non_assegnato', 'annullata_admin'].includes(statoMsg)) { bgColor = 'bg-orange-100 dark:bg-orange-800'; textColor = 'text-orange-700 dark:text-orange-200'; }
                else if (statoMsg === 'in_attesa_admin') { bgColor = 'bg-blue-100 dark:bg-blue-800'; textColor = 'text-blue-700 dark:text-blue-200';}
                messaggiGlobaliDiv.innerHTML = `<div class="text-lg font-semibold p-3 rounded-lg ${bgColor} ${textColor}">${data.messaggio_esito}</div>`;
            } else if (messaggiGlobaliDiv) {
                 messaggiGlobaliDiv.innerHTML = '';
            }
        }
    }

    function aggiornaInterfacciaGenerale(data) { // Per parti UI non specifiche dell'asta
        if (creditiPersonaliDisplayEl && data.crediti_utente_corrente !== undefined && data.crediti_utente_corrente !== null) {
            creditiPersonaliDisplayEl.textContent = data.crediti_utente_corrente;
        }
        if (impostazioniLegaUsaOrdineChiamata && testoProssimoChiamanteEl && boxProssimoChiamanteEl) {
            boxProssimoChiamanteEl.style.display = 'block';
            let testoTurno = '';
            let prossimoChiamanteNomeJS = data.prossimo_chiamante_nome || "<?php echo e(__('Da definire')); ?>";

            if (data.status === 'in_asta_tap_live') {
                testoTurno = 'Asta in corso...';
            } else if (prossimoChiamanteNomeJS && prossimoChiamanteNomeJS !== "<?php echo e(__('Da definire')); ?>") {
                testoTurno = `È il turno di: <strong class="text-lg">${prossimoChiamanteNomeJS}</strong> per effettuare la prossima chiamata.`;
            } else if (data.status === 'ultima_conclusa_da_mostrare' && prossimoChiamanteNomeJS && prossimoChiamanteNomeJS !== "<?php echo e(__('Da definire')); ?>") {
                testoTurno = `Prossimo a chiamare: <strong class="text-lg">${prossimoChiamanteNomeJS}</strong>.`;
            }
             else {
                testoTurno = `Prossimo a chiamare: <strong class="text-lg italic">${prossimoChiamanteNomeJS}</strong>`;
            }
            testoProssimoChiamanteEl.innerHTML = testoTurno;

            const isMyTurn = currentUserId && data.prossimo_chiamante_id && parseInt(data.prossimo_chiamante_id) === parseInt(currentUserId);
            const noAstaAttivaOInAttesaSpecificamente = data.status !== 'in_asta_tap_live' && data.status !== 'in_attesa_admin';
            console.log('[LINK CHECK V11.2]', {
                currentUserId: currentUserId,
                prossimo_chiamante_id: data.prossimo_chiamante_id,
                isMyTurn: isMyTurn,
                status: data.status,
                noAstaAttivaOInAttesaSpecificamente: noAstaAttivaOInAttesaSpecificamente,
                linkElExists: !!linkVaiAChiamataEl // Verifica se l'elemento è stato trovato
            });
            if (linkVaiAChiamataEl) linkVaiAChiamataEl.style.display = (isMyTurn && noAstaAttivaOInAttesaSpecificamente) ? 'block' : 'none';
            if (linkVaiAChiamataPlaceholderEl && nessunaAstaTapDivEl && !nessunaAstaTapDivEl.classList.contains('hidden')) { // Mostra solo se il div "nessuna asta" è visibile
                const deveMostrareLinkGenerico = !isMyTurn &&
                    noAstaAttivaOInAttesaSpecificamente &&
                    (!impostazioniLegaUsaOrdineChiamata || (impostazioniLegaUsaOrdineChiamata && data.prossimo_chiamante_id === null));
                linkVaiAChiamataPlaceholderEl.style.display = deveMostrareLinkGenerico ? 'block' : 'none';
            } else if (linkVaiAChiamataPlaceholderEl) {
                 linkVaiAChiamataPlaceholderEl.style.display = 'none';
            }

        } else if (boxProssimoChiamanteEl) {
            boxProssimoChiamanteEl.style.display = 'none';
        }
    }


    function fetchStatoAsta(idAstaDaControllareInput) {
        const idNumericToControl = idAstaDaControllareInput ? parseInt(idAstaDaControllareInput) : 0;
        console.log(`[Polling Fetch V11.1] Chiamata fetchStatoAsta per ID: ${idNumericToControl}`);

        fetch(`${URL_BASE_STATO}/${idNumericToControl}`)
            .then(response => {
                if (!response.ok) throw { responseStatus: response.status, requestedId: idNumericToControl };
                return response.json();
            })
            .then(data => {
                if (!data) { console.warn("[Polling Fetch V11.1] Dati nulli ricevuti per ID:", idNumericToControl); return; }
                console.log(`[Polling JS V11.1 Debug] Polling per ID: ${idChiamataPerPolling}. Dati ricevuti per Chiamata ID: ${data.chiamata_id}. Stato: ${data.status}. Asta Live Corrente ID: ${data.asta_live_corrente_id}`);

                aggiornaInterfacciaGenerale(data); // Aggiorna sempre crediti e prossimo chiamante

                if (data.status === 'in_asta_tap_live') {
                    // Se c'è un'asta live, mostrala e polla quell'ID.
                    if (idChiamataPerPolling !== data.chiamata_id.toString()) {
                        console.log(`[Polling V11.1] Cambio polling da ${idChiamataPerPolling} a ASTA LIVE ID ${data.chiamata_id}`);
                        idChiamataPerPolling = data.chiamata_id.toString();
                        if (window.astaPollingInterval) clearInterval(window.astaPollingInterval);
                        startPollingAsta(idChiamataPerPolling); // Riavvia polling per il nuovo ID
                    }
                    mostraDatiAsta(data, 'live');
                } else if (data.status === 'ultima_conclusa_da_mostrare') {
                    // Mostra l'ultima asta conclusa, ma continua a polare con ID 0 per scoprire nuove aste.
                    mostraDatiAsta(data, 'congelata');
                    if (idChiamataPerPolling !== '0') {
                        console.log(`[Polling V11.1] Mostrando ultima conclusa (ID ${data.chiamata_id}), passo a polling discovery (ID 0).`);
                        if (window.astaPollingInterval) clearInterval(window.astaPollingInterval);
                        idChiamataPerPolling = '0';
                        startPollingAsta('0');
                    }
                } else if (data.status === 'in_attesa_admin') {
                    // C'è un'asta in attesa. Mostra info e polla quell'ID.
                     mostraDatiAsta(data, 'in_attesa_admin'); // Usa la stessa logica di 'congelata' per l'UI
                    if (idChiamataPerPolling !== data.chiamata_id.toString()) {
                        console.log(`[Polling V11.1] Cambio polling da ${idChiamataPerPolling} a ASTA IN ATTESA ID ${data.chiamata_id}`);
                        idChiamataPerPolling = data.chiamata_id.toString();
                        if (window.astaPollingInterval) clearInterval(window.astaPollingInterval);
                        startPollingAsta(idChiamataPerPolling);
                    }
                } else { // Nessuna asta live, nessuna ultima da mostrare, nessuna in attesa (es. 'nessuna_asta_attiva_o_recente', 'non_trovata')
                    console.log(`[Polling V11.1] Nessuna asta attiva/recente/attesa. Stato: ${data.status}. Messaggio: ${data.messaggio_esito}`);
                    resetInterfacciaAstaAttiva(data.messaggio_esito || "Nessuna asta TAP attualmente attiva.");
                    // Visualizza il messaggio globale se fornito
                    if (messaggiGlobaliDiv && data.messaggio_esito) {
                        messaggiGlobaliDiv.innerHTML = `<div class="text-lg font-semibold p-3 rounded-lg bg-yellow-100 dark:bg-yellow-700 text-yellow-700 dark:text-yellow-200">${data.messaggio_esito}</div>`;
                    } else if (messaggiGlobaliDiv) {
                        messaggiGlobaliDiv.innerHTML = '';
                    }

                    if (astaContainerEl) astaContainerEl.classList.add('hidden');
                    if (nessunaAstaTapDivEl) nessunaAstaTapDivEl.classList.remove('hidden');
                    if (testoPlaceholderNessunaAstaEl) testoPlaceholderNessunaAstaEl.style.display = 'block';


                    if (idChiamataPerPolling !== '0' ) { // Se non stavamo già facendo discovery
                        console.log(`[Polling V11.1] Stato ${data.status} per ID ${data.chiamata_id}. Passo a polling discovery (ID 0).`);
                        if (window.astaPollingInterval) clearInterval(window.astaPollingInterval);
                        idChiamataPerPolling = '0';
                        startPollingAsta('0');
                    }
                }

                // Finalizzazione client se il timer scade per un'asta LIVE
                if (data.status === 'in_asta_tap_live' && data.chiamata_id === parseInt(idChiamataPerPolling) &&
                    data.secondi_rimanenti !== undefined && data.secondi_rimanenti <= 0 && !astaFinalizzataClient) {
                    console.log("[Polling Fetch V11.1] Asta live (ID:", parseInt(idChiamataPerPolling), ") con tempo scaduto. Avvio finalizzazione client.");
                    astaFinalizzataClient = true;
                    finalizzaAstaSulServer(parseInt(idChiamataPerPolling));
                }
            })
            .catch(error => {
                console.error('[Polling Fetch V11.1] Errore Catch:', error);
                 let errorMsg = "Errore di connessione o risposta non valida dal server.";
                if (error.responseStatus === 404 && error.requestedId && parseInt(error.requestedId) !== 0) {
                     errorMsg = 'Asta (ID: '+error.requestedId+') non più disponibile o terminata.';
                }
                if (idChiamataPerPolling === '0' && !datiUltimaAstaCongelata) { // Solo se non c'è un'asta congelata da mostrare
                     resetInterfacciaAstaAttiva(errorMsg);
                     if (messaggiGlobaliDiv) messaggiGlobaliDiv.innerHTML = `<div class="text-lg font-semibold p-3 rounded-lg bg-red-100 dark:bg-red-800 text-red-700 dark:text-red-200">${errorMsg}</div>`;
                } else if (!datiUltimaAstaCongelata){
                    // Se stavamo pollando un ID specifico e fallisce, potremmo voler passare a discovery
                     console.log(`[Polling V11.1] Errore pollando ID ${idChiamataPerPolling}. Passo a discovery (ID 0).`);
                     if (window.astaPollingInterval) clearInterval(window.astaPollingInterval);
                     idChiamataPerPolling = '0';
                     startPollingAsta('0');
                }
            });
    }

    function startPollingAsta(idCurrentAsta) {
        const idNumeric = idCurrentAsta ? parseInt(idCurrentAsta) : 0;
        if (isNaN(idNumeric)) {
            console.error(`[Polling Start V11.1] ID non numerico: ${idCurrentAsta}. Interrotto.`);
            if (window.astaPollingInterval) clearInterval(window.astaPollingInterval);
            return;
        }

        if (window.astaPollingInterval) { clearInterval(window.astaPollingInterval); }
        idChiamataPerPolling = idNumeric.toString(); // Aggiorna la variabile globale
        console.log('[Polling Start V11.1] Avvio/Riavvio per asta ID:', idChiamataPerPolling);

        fetchStatoAsta(idChiamataPerPolling); // Chiamata immediata
        window.astaPollingInterval = setInterval(() => {
            if (idChiamataPerPolling === null || idChiamataPerPolling === undefined) {
                 if(window.astaPollingInterval) clearInterval(window.astaPollingInterval);
                 console.log('[Polling Stop V11.1] idChiamataPerPolling nullo, polling fermato.');
                 return;
            }
            fetchStatoAsta(idChiamataPerPolling);
        }, 3000);
    }

    function updateCountdown() {
        if (!timestampFinePerCountdown || timestampFinePerCountdown <= 0 || astaFinalizzataClient) {
            if(countdownElement) {
                 if(astaFinalizzataClient && astaContainerEl && !astaContainerEl.classList.contains('hidden')) {
                    countdownElement.textContent = "TEMPO SCADUTO";
                 } else if (ultimoIdAstaAttivamenteMostrata && astaContainerEl && !astaContainerEl.classList.contains('hidden') && (!datiUltimaAstaCongelata || datiUltimaAstaCongelata.chiamata_id !== ultimoIdAstaAttivamenteMostrata) ) {
                     // Se stiamo mostrando un'asta che dovrebbe essere live, ma non c'è countdown, mostra --:--
                     countdownElement.textContent = "--:--";
                 } else if (!datiUltimaAstaCongelata && (!astaContainerEl || astaContainerEl.classList.contains('hidden'))) {
                     countdownElement.textContent = "ASTA NON ATTIVA";
                 } else if (datiUltimaAstaCongelata) {
                     countdownElement.textContent = "ASTA TERMINATA";
                 }
            }
            if(astaCountdownInterval) clearInterval(astaCountdownInterval);
            astaCountdownInterval = null;
            return;
        }
        const now = new Date().getTime();
        const distance = timestampFinePerCountdown - now;

        if (distance < 0) {
            if(countdownElement) countdownElement.textContent = "TEMPO SCADUTO";
            if(astaCountdownInterval) clearInterval(astaCountdownInterval);
            astaCountdownInterval = null;

            const idAstaDaFinalizzareCorrente = idChiamataPerPolling ? parseInt(idChiamataPerPolling) : null;
            if (!astaFinalizzataClient && idAstaDaFinalizzareCorrente && idAstaDaFinalizzareCorrente !== 0 && idAstaDaFinalizzareCorrente === ultimoIdAstaAttivamenteMostrata) {
                astaFinalizzataClient = true;
                console.log("[Countdown JS V11.1] Tempo scaduto, chiamo finalizzaAstaSulServer per ID:", idAstaDaFinalizzareCorrente);
                finalizzaAstaSulServer(idAstaDaFinalizzareCorrente);
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

        if (tsRicevutoUnix && tsRicevutoUnix > (oraAttualeUnix - 5)) { // 5 secondi di tolleranza per il passato
            timestampFinePerCountdown = tsRicevutoUnix * 1000;
            if (countdownElement) countdownElement.dataset.timestampFine = nuovoTimestampFineUnixServer.toString();
        } else {
            timestampFinePerCountdown = 0;
            if (countdownElement) countdownElement.dataset.timestampFine = '';
        }

        if(astaCountdownInterval) clearInterval(astaCountdownInterval);
        astaCountdownInterval = null;

        if (countdownElement && timestampFinePerCountdown > 0) {
            updateCountdown();
            astaCountdownInterval = setInterval(updateCountdown, 1000);
        } else if (countdownElement) {
             updateCountdown(); // Per mostrare lo stato corretto (es. --:-- o ASTA NON ATTIVA)
        }
    }

    bottoniRilancio.forEach(button => {
        button.addEventListener('click', function() {
            if (this.disabled) return;
            const idAstaPerRilancio = astaContainerEl && astaContainerEl.dataset.chiamataId ? parseInt(astaContainerEl.dataset.chiamataId) : null;

            if (!idAstaPerRilancio || idAstaPerRilancio !== ultimoIdAstaAttivamenteMostrata ) {
                if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-red-500 dark:text-red-300">${'Errore: Rilancio su asta non più attiva o ID non corrispondente.'}</span>`;
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
                    // Aggiorna UI con i dati ricevuti dal rilancio (nuovo prezzo, timestamp, ecc.)
                    if(prezzoAttualeValEl && data.nuovo_prezzo !== undefined) prezzoAttualeValEl.textContent = data.nuovo_prezzo;
                    if(migliorOfferenteNomeEl && data.miglior_offerente) migliorOfferenteNomeEl.textContent = `da ${data.miglior_offerente}`;
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
                console.error('[Rilancio JS V11.1] Catch Errore Fetch:', error);
                if(messaggiRilancioDiv) messaggiRilancioDiv.innerHTML = `<span class="text-red-500 dark:text-red-300">${error.message || 'Errore di connessione o server.'}</span>`;
            });
        });
    });

    function finalizzaAstaSulServer(chiamataIdDaFinalizzare) {
        if (!chiamataIdDaFinalizzare || chiamataIdDaFinalizzare === 0) {
            console.error('[Finalizza JS V11.1] ID chiamata non valido. Non chiamo il server. ID:', chiamataIdDaFinalizzare);
            astaFinalizzataClient = false; // Permetti un nuovo tentativo se era un errore
            return;
        }
        console.log('[Finalizza JS V11.1] Tentativo finalizzazione per ID:', chiamataIdDaFinalizzare);
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        if(!csrfToken) { console.error('[Finalizza JS V11.1] CSRF Token mancante.'); return; }

        if(messaggiGlobaliDiv) messaggiGlobaliDiv.innerHTML = `<div class="p-2 text-blue-600 dark:text-blue-300">${'Finalizzazione asta in corso...'}</div>`;

        fetch(`${URL_BASE_FINALIZZA}/${chiamataIdDaFinalizzare}`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(response => response.json())
        .then(data => {
            console.log('[Finalizza JS V11.1] Risposta da finalizzazione server:', data);
            // La risposta da finalizzazione ora dovrebbe includere i dati per l'aggiornamento dell'UI
            // e anche il nuovo 'prossimo_chiamante'.
            // Il prossimo ciclo di polling (che potrebbe essere per ID 0 ora) prenderà lo stato aggiornato.
            // Aggiorniamo subito l'interfaccia con l'esito della finalizzazione
            aggiornaInterfacciaGenerale(data); // Per prossimo chiamante e crediti
            if(data.status === 'conclusa_tap_assegnato' || data.status === 'conclusa_tap_non_assegnato' || data.status === 'annullata_admin') {
                data.status_originale = data.status; // Per la logica di colore in mostraDatiAsta
                mostraDatiAsta(data, 'congelata');
                 if (idChiamataPerPolling !== '0') { // Se non stavamo già facendo discovery, passaci
                    console.log(`[Finalizza JS V11.1] Asta ${chiamataIdDaFinalizzare} finalizzata. Passo a polling discovery (ID 0).`);
                    if (window.astaPollingInterval) clearInterval(window.astaPollingInterval);
                    idChiamataPerPolling = '0';
                    startPollingAsta('0');
                }
            } else {
                 fetchStatoAsta(idChiamataPerPolling); // Richiedi di nuovo lo stato per sicurezza
            }
        })
        .catch(error => {
            console.error('Errore durante la finalizzaAstaSulServer V11.1:', error);
            if(messaggiGlobaliDiv) messaggiGlobaliDiv.innerHTML = `<div class="p-2 text-red-500 dark:text-red-300">Errore tecnico durante la finalizzazione.</div>`;
            astaFinalizzataClient = false;
        });
    }

    // --- Avvio Iniziale Logica ---
    const initialAstaIdDaBlade = '<?php echo e($idChiamataInizialePerPolling ?? "0"); ?>';
    const initialIsAstaLiveDaBlade = <?php echo e($isAstaAttualmenteLive ? 'true' : 'false'); ?>;
    const initialTimestampUnixDaBlade = '<?php echo e($timestampFineInizialePerCountdown ?? ""); ?>';
    // const initialStatoChiamataDaMostrareBlade = '<?php echo e($statoChiamataDaMostrare ?? "non_attiva"); ?>';

    console.log(`[JS Init V11.1] Blade -> ID: ${initialAstaIdDaBlade}, Live?: ${initialIsAstaLiveDaBlade}, TimestampUnix: ${initialTimestampUnixDaBlade}`);

    idChiamataPerPolling = initialAstaIdDaBlade && initialAstaIdDaBlade !== "0" ? initialAstaIdDaBlade : "0";
    if (astaContainerEl && idChiamataPerPolling !== "0") {
        astaContainerEl.dataset.chiamataId = idChiamataPerPolling;
    }

    startPollingAsta(idChiamataPerPolling);

    // Se i dati iniziali da Blade indicano un'asta live, avvia subito il countdown
    // Altrimenti, il primo fetchStatoAsta si occuperà di aggiornare l'UI.
    if (initialIsAstaLiveDaBlade && initialTimestampUnixDaBlade && initialTimestampUnixDaBlade !== 'ErroreParseTS' && initialTimestampUnixDaBlade !== '') {
        avviaOAggiornaCountdown(parseInt(initialTimestampUnixDaBlade));
    } else {
        // Se non c'è un'asta live iniziale da Blade, ma c'è un'ID specifico,
        // la prima chiamata di fetchStatoAsta si occuperà di mostrare lo stato corretto (es. congelato o in attesa)
        // Se l'ID è '0', il primo fetch gestirà anche il caso di "ultima_conclusa_da_mostrare" o "nessuna_asta".
        // L'importante è che resetInterfacciaAstaAttiva non nasconda il container se vogliamo mostrare un'asta congelata.
        if (idChiamataPerPolling === '0' && !datiUltimaAstaCongelata && (!astaContainerEl || astaContainerEl.classList.contains('hidden'))) {
            // Solo se siamo in discovery e non c'è nulla di congelato e il container è nascosto
            resetInterfacciaAstaAttiva("Recupero stato asta...");
            if (messaggiGlobaliDiv) messaggiGlobaliDiv.innerHTML = '';
        }
    }
});
</script>
<?php $__env->stopPush(); ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da)): ?>
<?php $component = $__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da; ?>
<?php unset($__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da); ?>
<?php endif; ?><?php /**PATH C:\laragon\www\fantastazione\resources\views/asta/live.blade.php ENDPATH**/ ?>