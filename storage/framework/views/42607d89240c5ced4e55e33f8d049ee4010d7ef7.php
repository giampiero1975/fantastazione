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
            <?php echo e(__('Gestione Calciatori')); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium mb-4">Elenco Calciatori Importati</h3>

                    <form id="filters-form" action="<?php echo e(route('admin.giocatori.index')); ?>" method="GET" class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <input type="text" name="q" value="<?php echo e(request('q')); ?>" placeholder="Cerca per nome..." class="block w-full text-sm rounded-md border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                        
                        <select name="squadra_serie_a" class="block w-full text-sm rounded-md border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                            <option value="">Tutte le squadre</option>
                            <?php $__currentLoopData = $squadre; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $squadra): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($squadra); ?>" <?php echo e(request('squadra_serie_a') == $squadra ? 'selected' : ''); ?>><?php echo e($squadra); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                        
                        <select name="tag_lista_inserimento" class="block w-full text-sm rounded-md border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                            <option value="">Tutti i Tag</option>
                            <?php $__currentLoopData = $tagsDisponibili; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $tag): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                <option value="<?php echo e($tag); ?>" <?php echo e(request('tag_lista_inserimento') == $tag ? 'selected' : ''); ?>><?php echo e($tag); ?></option>
                            <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                        </select>
                    </form>

                    <div id="calciatori-table-container">
                        <?php echo $__env->make('admin.giocatori.partials.lista-calciatori', ['calciatori' => $calciatori], \Illuminate\Support\Arr::except(get_defined_vars(), ['__data', '__path']))->render(); ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <?php $__env->startPush('scripts'); ?>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('filters-form');
            let debounceTimer;

            function fetchCalciatori(page = 1) {
                const formData = new FormData(form);
                const params = new URLSearchParams(formData);
                params.set('page', page);
                
                const url = `<?php echo e(route('admin.giocatori.index')); ?>?${params.toString()}`;

                fetch(url, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                })
                .then(response => response.text())
                .then(html => {
                    document.getElementById('calciatori-table-container').innerHTML = html;
                    window.history.pushState({}, '', url);
                })
                .catch(error => console.error('Errore durante il caricamento dei dati:', error));
            }

            function debounceFetch() {
                clearTimeout(debounceTimer);
                debounceTimer = setTimeout(() => fetchCalciatori(1), 300);
            }

            form.addEventListener('keyup', debounceFetch);
            form.addEventListener('change', debounceFetch);

            document.addEventListener('click', function(e) {
                if (e.target.matches('#calciatori-table-container .pagination a')) {
                    e.preventDefault();
                    const page = new URL(e.target.href).searchParams.get('page');
                    fetchCalciatori(page);
                }
            });
        });
    </script>
    <?php $__env->stopPush(); ?>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da)): ?>
<?php $component = $__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da; ?>
<?php unset($__componentOriginal8e2ce59650f81721f93fef32250174d77c3531da); ?>
<?php endif; ?><?php /**PATH C:\laragon\www\fantastazione\resources\views/admin/giocatori/index.blade.php ENDPATH**/ ?>