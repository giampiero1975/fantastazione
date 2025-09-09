<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Gestione Calciatori') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-medium mb-4">Elenco Calciatori Importati</h3>

                    <form id="filters-form" action="{{ route('admin.giocatori.index') }}" method="GET" class="mb-4 grid grid-cols-1 md:grid-cols-3 gap-4">
                        <input type="text" name="q" value="{{ request('q') }}" placeholder="Cerca per nome..." class="block w-full text-sm rounded-md border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                        
                        <select name="squadra_serie_a" class="block w-full text-sm rounded-md border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                            <option value="">Tutte le squadre</option>
                            @foreach ($squadre as $squadra)
                                <option value="{{ $squadra }}" {{ request('squadra_serie_a') == $squadra ? 'selected' : '' }}>{{ $squadra }}</option>
                            @endforeach
                        </select>
                        
                        <select name="tag_lista_inserimento" class="block w-full text-sm rounded-md border-gray-300 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-300">
                            <option value="">Tutti i Tag</option>
                            @foreach ($tagsDisponibili as $tag)
                                <option value="{{ $tag }}" {{ request('tag_lista_inserimento') == $tag ? 'selected' : '' }}>{{ $tag }}</option>
                            @endforeach
                        </select>
                    </form>

                    <div id="calciatori-table-container">
                        @include('admin.giocatori.partials.lista-calciatori', ['calciatori' => $calciatori])
                    </div>

                </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('filters-form');
            let debounceTimer;

            function fetchCalciatori(page = 1) {
                const formData = new FormData(form);
                const params = new URLSearchParams(formData);
                params.set('page', page);
                
                const url = `{{ route('admin.giocatori.index') }}?${params.toString()}`;

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
    @endpush
</x-app-layout>