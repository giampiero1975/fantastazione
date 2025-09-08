<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Calciatori Disponibili') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h1 class="text-2xl font-bold mb-4">
                        Lista Calciatori Svincolati
                        @if ($impostazioniLega->tag_lista_attiva)
                            <span class="text-lg font-normal text-gray-500 dark:text-gray-400">(Tag Lista: {{ $impostazioniLega->tag_lista_attiva }})</span>
                        @endif
                    </h1>

                    <div class="mb-4 bg-gray-50 dark:bg-gray-700 p-4 rounded-lg shadow-inner">
                        <form id="filter-form" action="{{ route('asta.calciatori.disponibili') }}" method="GET" class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <div>
                                <label for="q" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Cerca per Nome o Squadra</label>
                                <input type="text" name="q" id="q" value="{{ request('q') }}" placeholder="Es: Martinez o Inter" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-600 dark:border-gray-500 dark:text-gray-300">
                            </div>
                            <div>
                                <label for="ruolo" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Filtra per Ruolo</label>
                                <select name="ruolo" id="ruolo" class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-300 focus:ring focus:ring-indigo-200 focus:ring-opacity-50 dark:bg-gray-600 dark:border-gray-500 dark:text-gray-300">
                                    <option value="">Tutti i Ruoli</option>
                                    @foreach ($ruoliDisponibili as $ruolo)
                                        <option value="{{ $ruolo }}" @if(request('ruolo') == $ruolo) selected @endif>{{ $ruolo }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </form>
                    </div>
                    <div id="calciatori-list">
                        <div class="overflow-x-auto bg-white dark:bg-gray-800 rounded-lg shadow">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Calciatore</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Club</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Qt. I.</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Azione</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @forelse ($calciatori as $calciatore)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="font-bold text-xl text-gray-900 dark:text-white">{{ $calciatore->ruolo }}</div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900 dark:text-white">{{ $calciatore->nome_completo }}</div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $calciatore->squadra_serie_a }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $calciatore->quotazione_iniziale }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                @if ($impostazioniLega->modalita_asta === 'tap')
                                                    <form action="{{ route('asta.registra.chiamata', $calciatore->id) }}" method="POST">
                                                        @csrf
                                                        <button type="submit" class="px-4 py-2 bg-green-600 text-white text-xs font-semibold rounded hover:bg-green-700">
                                                            Chiama
                                                        </button>
                                                    </form>
                                                @else
                                                    <span class="text-xs text-gray-400">Voce</span>
                                                @endif
                                            </td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="4" class="px-6 py-4 whitespace-nowrap text-center text-sm text-gray-500 dark:text-gray-400">
                                                Nessun calciatore trovato con i criteri di ricerca.
                                            </td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4">
                            {{ $calciatori->appends(request()->query())->links() }}
                        </div>
                    </div>
                    </div>
            </div>
        </div>
    </div>

    @push('scripts')
    <script>
        // Questo script è opzionale ma rende l'esperienza più fluida.
        // Se lo rimuovi, il form funzionerà comunque con un ricaricamento completo della pagina.
        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('filter-form');
            const searchInput = document.getElementById('q');
            const roleSelect = document.getElementById('ruolo');
            let debounceTimer;

            function fetchCalciatori(page = 1) {
                const query = new URLSearchParams(new FormData(form)).toString();
                const url = `{{ route('asta.calciatori.disponibili') }}?${query}&page=${page}`;

                fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.text())
                .then(html => {
                    document.getElementById('calciatori-list').innerHTML = html;
                    // Aggiorna l'URL del browser senza ricaricare la pagina
                    window.history.pushState({}, '', url);
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
                if (e.target.matches('#calciatori-list .pagination a')) {
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
    @endpush
</x-app-layout>