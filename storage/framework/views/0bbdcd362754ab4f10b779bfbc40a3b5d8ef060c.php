<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nome Calciatore</th>
                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ruolo</th>
                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Squadra</th>
                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Qt. I.</th>
                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Acquistato Da</th>
                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Prezzo</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            <?php $__empty_1 = true; $__currentLoopData = $calciatori; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $calciatore): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                <tr>
                    <td class="px-4 py-3 text-sm font-medium"><?php echo e($calciatore->id); ?></td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100"><?php echo e($calciatore->nome_completo); ?></td>
                    <td class="px-4 py-3 text-sm font-medium"><?php echo e($calciatore->ruolo); ?></td>
                    <td class="px-4 py-3 text-sm font-medium"><?php echo e($calciatore->squadra_serie_a); ?></td>
                    <td class="px-4 py-3 text-sm font-medium"><?php echo e($calciatore->quotazione_iniziale); ?></td>
                    
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
            <?php if($calciatore->acquistoCorrente): ?> 
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100">
                    <?php echo e($calciatore->acquistoCorrente->user->name ?? 'N/A'); ?>

                </span>
            <?php else: ?>
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-100">
                    Svincolato
                </span>
            <?php endif; ?>
        </td>
        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
            <?php if($calciatore->acquistoCorrente): ?> 
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100">
                    <?php echo e($calciatore->acquistoCorrente->prezzo_acquisto ?? 'N/A'); ?>

                </span>
            <?php else: ?>
                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-100">
                    -
                </span>
            <?php endif; ?>
        </td>
                </tr>
            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                <tr>
                    <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                        Nessun calciatore trovato con i criteri di ricerca.
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>
<div class="mt-4">
    <?php echo e($calciatori->appends(request()->query())->links()); ?>

</div><?php /**PATH C:\laragon\www\fantastazione\resources\views/admin/giocatori/partials/lista-calciatori.blade.php ENDPATH**/ ?>