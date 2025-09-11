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
            <?php echo e(__('Calciatori Disponibili')); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h1 class="text-2xl font-bold mb-4">
                        Lista Calciatori Svincolati
                        <?php if($impostazioniLega->tag_lista_attiva): ?>
                            <span class="text-lg font-normal text-gray-500 dark:text-gray-400">(Tag Lista: <?php echo e($impostazioniLega->tag_lista_attiva); ?>)</span>
                        <?php endif; ?>
                    </h1>

                    <div class="mb-4 bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow-inner">
                        <form id="filter-form" action="<?php echo e(route('asta.calciatori.disponibili')); ?>" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="q" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cerca per Nome o Squadra</label>
                                <input type="text" name="q" id="q" value="<?php echo e(request('q')); ?>" placeholder="Es: Martinez o Inter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-600 dark:border-gray-500 dark:text-gray-300">
                            </div>
                            <div>
                                <label for="ruolo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Filtra per Ruolo</label>
                                <select name="ruolo" id="ruolo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-600 dark:border-gray-500 dark:text-gray-300">
                                    <option value="">Tutti i Ruoli</option>
                                    <?php $__currentLoopData = $ruoliDisponibili; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $ruolo): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                        <option value="<?php echo e($ruolo); ?>" <?php if(request('ruolo') == $ruolo): ?> selected <?php endif; ?>><?php echo e($ruolo); ?></option>
                                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                                </select>
                            </div>
                        </form>
                    </div>

                    
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Calciatore
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Squadra
                                    </th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                                        Quota
                                    </th>
                                    <th scope="col" class="relative px-6 py-3">
                                        <span class="sr-only">Azione</span>
                                    </th>
                                </tr>
                            </thead>
                            <tbody id="calciatori-list" class="bg-white divide-y divide-gray-200 dark:bg-gray-800 dark:divide-gray-700">
                                
                                <?php echo $__env->make('asta.partials.lista-calciatori', \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                            </tbody>
                        </table>
                    </div>
                    

                    <div class="mt-4">
                        
                        <?php echo e($calciatori->appends(request()->query())->links()); ?>

                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php $__env->startPush('scripts'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('filter-form');
            const searchInput = document.getElementById('q');
            const roleSelect = document.getElementById('ruolo');
            let debounceTimer;

            function fetchCalciatori(page = 1) {
                const query = new URLSearchParams(new FormData(form)).toString();
                const url = `<?php echo e(route('asta.calciatori.disponibili')); ?>?${query}&page=${page}`;

                fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    // Questa riga ora aggiorna correttamente il <tbody>
                    document.getElementById('calciatori-list').innerHTML = html;
                    // Aggiorna l'URL del browser senza ricaricare la pagina
                    window.history.pushState({}, '', url);

                    // Aggiungi qui la logica per riaggiornare la paginazione
                    // Se il tuo partial 'lista-calciatori' restituisce solo le righe,
                    // allora avrai bisogno di una risposta JSON dal controller
                    // che contenga sia l'HTML delle righe che l'HTML della paginazione.
                    // Per ora, la paginazione sotto la tabella (non AJAX) non si aggiornerà.
                    // Possiamo risolverlo dopo aver sistemato la tabella.
                })
                .catch(error => console.error('Errore durante il fetch:', error));
            }

            function debounceFetch() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => {
                    fetchCalciatori(1);
                }, 300);
            }

            searchInput.addEventListener('keyup', debounceFetch);
            roleSelect.addEventListener('change', debounceFetch);

            document.addEventListener('click', function(e) {
                // Modifica il selettore per la paginazione, ora che il div 'calciatori-list' è un tbody
                // La paginazione esterna non è all'interno di #calciatori-list, quindi il selettore deve puntare al div esterno della paginazione
                if (e.target.closest('.pagination') && e.target.matches('a')) { // Assumendo che 'pagination' sia una classe sul div della paginazione
                    e.preventDefault();
                    const pageUrl = new URL(e.target.href);
                    const page = pageUrl.searchParams.get('page');
                    fetchCalciatori(page);
                }
            });

            form.addEventListener('submit', function (e) {
                e.preventDefault();
                fetchCalciatori(1);
            });
        });
    </script>
    <?php $__env->stopPush(); ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da)): ?>
<?php $component = $__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da; ?>
<?php unset($__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da); ?>
<?php endif; ?><?php /**PATH C:\laragon\www\fantastazione\resources\views/asta/calciatori-disponibili.blade.php ENDPATH**/ ?>