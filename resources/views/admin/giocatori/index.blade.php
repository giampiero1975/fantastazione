<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Elenco Calciatori Importati') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900">
                    <h3 class="text-lg font-semibold mb-3">Filtri</h3>
                    <form method="GET" action="{{ route('admin.giocatori.index') }}" class="space-y-4 md:space-y-0 md:flex md:space-x-4 md:items-end">
                        <div>
                            <label for="tag_lista_inserimento" class="block text-sm font-medium text-gray-700">Stagione/Tag</label>
                            <select name="tag_lista_inserimento" id="tag_lista_inserimento" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                <option value="">Tutte</option>
                                @foreach ($tagsDisponibili as $tag)
                                    <option value="{{ $tag }}" {{ request('tag_lista_inserimento') == $tag ? 'selected' : '' }}>{{ $tag }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="ruolo" class="block text-sm font-medium text-gray-700">Ruolo</label>
                            <select name="ruolo" id="ruolo" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                <option value="">Tutti</option>
                                @foreach ($ruoliDisponibili as $ruolo)
                                    <option value="{{ $ruolo }}" {{ request('ruolo') == $ruolo ? 'selected' : '' }}>{{ $ruolo }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label for="squadra_serie_a" class="block text-sm font-medium text-gray-700">Squadra Serie A</label>
                            <input type="text" name="squadra_serie_a" id="squadra_serie_a" value="{{ request('squadra_serie_a') }}" class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm" placeholder="Nome squadra...">
                        </div>
                        <div>
                            <label for="attivo" class="block text-sm font-medium text-gray-700">Stato</label>
                            <select name="attivo" id="attivo" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                                <option value="">Tutti</option>
                                <option value="1" {{ request('attivo') === '1' ? 'selected' : '' }}>Attivo</option>
                                <option value="0" {{ request('attivo') === '0' ? 'selected' : '' }}>Non Attivo/Ceduto</option>
                            </select>
                        </div>
                        <div>
                            <x-primary-button type="submit">Filtra</x-primary-button>
                            <a href="{{ route('admin.giocatori.index') }}" class="ml-2 inline-flex items-center px-4 py-2 bg-gray-200 border border-transparent rounded-md font-semibold text-xs text-gray-700 uppercase tracking-widest hover:bg-gray-300 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 transition ease-in-out duration-150">Reset</a>
                        </div>
                    </form>
                </div>
            </div>

            {{-- ... inizio della vista ... --}}

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nome</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">R</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Squadra Serie A</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Qt.I</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Qt.A</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tag/Stagione</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Stato</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Acquirente</th>
                                    <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Prezzo Pagato</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($calciatori as $calciatore)
                                    {{-- Aggiungi una classe condizionale alla riga <tr> --}}
                                    <tr class="{{ !$calciatore->attivo ? 'bg-gray-100 dark:bg-gray-700 opacity-70' : '' }}">
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $calciatore->nome_completo }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $calciatore->ruolo }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $calciatore->squadra_serie_a }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $calciatore->quotazione_iniziale }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $calciatore->quotazione_attuale }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $calciatore->tag_lista_inserimento }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm">
                                            @if ($calciatore->attivo)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-200">Attivo</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 text-red-800 dark:bg-red-700 dark:text-red-200">Non Attivo</span>
                                            @endif
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $calciatore->nome_squadra_acquirente ?? '-' }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-300">{{ $calciatore->prezzo_acquisto ?? '-' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400 text-center">Nessun calciatore trovato con i filtri selezionati.</td>
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
</x-app-layout>