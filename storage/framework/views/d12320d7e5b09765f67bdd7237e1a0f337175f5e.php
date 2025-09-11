<?php $__empty_1 = true; $__currentLoopData = $calciatori; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $calciatore): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
    <tr>
        <td class="px-6 py-4 whitespace-nowrap">
            <div class="flex items-center">
                <div class="font-bold text-xl text-gray-900 dark:text-white"><?php echo e($calciatore->ruolo); ?></div>
                <div class="ml-4">
                    <div class="text-sm font-medium text-gray-900 dark:text-white"><?php echo e($calciatore->nome_completo); ?></div>
                </div>
            </div>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo e($calciatore->squadra_serie_a); ?></td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400"><?php echo e($calciatore->quotazione_iniziale); ?></td>
        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
            <?php
                $fasiAstaChiamabili = ['P', 'D', 'C', 'A'];
                $isTapModeAndValidPhase = ($impostazioniLega->modalita_asta === 'tap' && 
                                           in_array($impostazioniLega->fase_asta_corrente, $fasiAstaChiamabili));
                $isTurnoCorretto = true; 
                if (($impostazioniLega->usa_ordine_chiamata ?? false) === true) { 
                    $isTurnoCorretto = ($user && $user->id === ($impostazioniLega->prossimo_turno_chiamata_user_id ?? null));
                }
                $canCallPlayer = $isTapModeAndValidPhase && $isTurnoCorretto;
            ?>
            <?php if($canCallPlayer): ?>
                <form action="<?php echo e(route('asta.registra.chiamata', $calciatore->id)); ?>" method="POST">
                    <?php echo csrf_field(); ?>
                    <button type="submit" class="px-4 py-2 bg-green-600 text-white text-xs font-semibold rounded hover:bg-green-700">
                        Chiama
                    </button>
                </form>
            <?php elseif($isTapModeAndValidPhase && !$isTurnoCorretto): ?>
                <span class="text-xs text-yellow-500 dark:text-yellow-400"></span>
            <?php elseif($impostazioniLega->modalita_asta === 'tap' && !in_array($impostazioniLega->fase_asta_corrente, $fasiAstaChiamabili)): ?>
                <span class="text-xs text-red-500 dark:text-red-400">Asta non in fase di chiamata.</span>
            <?php else: ?>
                <span class="text-xs text-gray-400">Voce</span>
            <?php endif; ?>
        </td>
    </tr>
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
    <tr>
        <td colspan="4" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500 dark:text-gray-400">
            Nessun calciatore trovato con i criteri di ricerca.
        </td>
    </tr>
<?php endif; ?><?php /**PATH C:\laragon\www\fantastazione\resources\views/asta/partials/lista-calciatori.blade.php ENDPATH**/ ?>