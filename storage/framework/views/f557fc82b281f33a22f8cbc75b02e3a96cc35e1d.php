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
            <?php echo e(__('La Mia Dashboard Squadra')); ?> - <?php echo e($squadra->name); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-2">Riepilogo Squadra</h3>
                    <p><strong>Crediti Rimanenti:</strong> <?php echo e($squadra->crediti_rimanenti); ?> / <?php echo e($squadra->crediti_iniziali_squadra); ?></p>
                    <p><strong>Crediti Spesi:</strong> <?php echo e($costoTotaleRosa); ?></p>
                    <p class="flex items-center">
                        <strong class="mr-2">Giocatori in Rosa:</strong>
                        <?php
                            $rosaCompleta = ($numeroGiocatoriInRosa == $limiteGiocatoriTotaliInRosa);
                        ?>
                        <span class="px-3 py-1 text-xs font-semibold rounded-full
                            <?php echo e($rosaCompleta ? 'bg-green-100 dark:bg-green-700 text-green-800 dark:text-green-100' : 'bg-red-100 dark:bg-red-700 text-red-800 dark:text-red-100'); ?>">
                            <?php echo e($numeroGiocatoriInRosa); ?> / <?php echo e($limiteGiocatoriTotaliInRosa); ?>

                        </span>
                    </p>

                    
                    <div class="text-sm mt-2 mb-2">
                        <h4 class="font-semibold mb-1 text-gray-700 dark:text-gray-200">Composizione Rosa Dettagliata:</h4>
                        <div class="space-y-1"> 
                            <?php
                                $ruoliDisplay = ['P', 'D', 'C', 'A'];
                            ?>
                            <?php $__currentLoopData = $ruoliDisplay; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ruolo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <?php
                                    $conteggio = isset($conteggioRuoli) ? $conteggioRuoli->get($ruolo, 0) : 0;
                                    $costoRuolo = isset($costiPerRuolo) ? $costiPerRuolo->get($ruolo, 0) : 0;
                                    $limite = isset($limitiRuoli) && isset($limitiRuoli[$ruolo]) ? $limitiRuoli[$ruolo] : 0;

                                    $coloreTesto = 'text-gray-700 dark:text-gray-300'; // Default
                                    if ($limite > 0) {
                                        if ($conteggio == $limite) {
                                            $coloreTesto = 'text-green-600 dark:text-green-400';
                                        } elseif ($conteggio < $limite) {
                                            $coloreTesto = 'text-red-600 dark:text-red-400';
                                        } else { // $conteggio > $limite
                                            $coloreTesto = 'text-yellow-600 dark:text-yellow-400';
                                        }
                                    } elseif ($conteggio > 0 && $limite == 0) {
                                        $coloreTesto = 'text-blue-600 dark:text-blue-400';
                                    }
                                    $testoLimite = ($limite > 0) ? "/{$limite}" : ' ill.';
                                ?>
                                
                                <div class="py-1 px-2 rounded <?php echo e($loop->odd ? 'bg-gray-50 dark:bg-gray-700/50' : 'bg-white dark:bg-gray-800'); ?>">
                                    <strong class="font-bold <?php echo e($coloreTesto); ?>"><?php echo e($ruolo); ?>:</strong>
                                    <span class="<?php echo e($coloreTesto); ?>"><?php echo e($conteggio); ?><?php echo e($testoLimite); ?></span>
                                    
                                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                        (Spesi: <strong class="font-medium text-gray-700 dark:text-gray-200"><?php echo e($costoRuolo); ?> crd.</strong>)
                                    </span>
                                </div>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </div>
                    </div>
                    

                    <p class="mt-4"><strong>Fase Asta Corrente:</strong> <?php echo e($impostazioniLega->fase_asta_corrente); ?></p>
                    <p><strong>Lista Calciatori Attiva:</strong> <?php echo e($impostazioniLega->tag_lista_attiva ?? 'Non definita'); ?></p>
                </div>
            </div>

            
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">La Mia Rosa</h3>
                    <?php if($rosa->isNotEmpty()): ?>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-sm">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">R.</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nome Calciatore</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Squadra Serie A</th>
                                        <th class="px-4 py-2 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Prezzo Pagato</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <?php $__currentLoopData = $rosa->sortBy(function($acquisto){ return match(optional($acquisto->calciatore)->ruolo ?? '') {'P'=>1, 'D'=>2, 'C'=>3, 'A'=>4, default=>5}; }); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $acquisto): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <tr>
                                            <td class="px-4 py-2 whitespace-nowrap"><?php echo e(optional($acquisto->calciatore)->ruolo ?? 'N/D'); ?></td>
                                            <td class="px-4 py-2 whitespace-nowrap font-medium"><?php echo e(optional($acquisto->calciatore)->nome_completo ?? 'N/D'); ?></td>
                                            <td class="px-4 py-2 whitespace-nowrap"><?php echo e(optional($acquisto->calciatore)->squadra_serie_a ?? 'N/D'); ?></td>
                                            <td class="px-4 py-2 whitespace-nowrap"><?php echo e($acquisto->prezzo_acquisto); ?></td>
                                        </tr>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </tbody>
                            </table>
                        </div>
                    <?php else: ?>
                        <p>Non hai ancora acquistato nessun giocatore.</p>
                    <?php endif; ?>
                    <div class="mt-6">
                        <a href="<?php echo e(route('asta.calciatori.disponibili')); ?>" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Visualizza Calciatori Disponibili per l'Asta
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da)): ?>
<?php $component = $__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da; ?>
<?php unset($__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da); ?>
<?php endif; ?><?php /**PATH C:\laragon\www\fantastazione\resources\views/squadra/dashboard.blade.php ENDPATH**/ ?>