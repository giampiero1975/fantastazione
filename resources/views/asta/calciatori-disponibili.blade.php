<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Calciatori Disponibili per l\'Asta') }}
            <span class="text-base text-indigo-600 dark:text-indigo-400 ml-2">
                (Fase: {{ $faseAstaCorrente ?? 'N/D' }}
                @if(isset($tagListaAttiva) && $tagListaAttiva)
                    - Lista: {{ $tagListaAttiva }}
                @endif
                )
            </span>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            @if (session('success'))
                <div class="mb-4 p-4 font-medium text-sm text-green-700 bg-green-100 dark:bg-green-800 dark:text-green-200 rounded-md">
                    {{ session('success') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-4 p-4 font-medium text-sm text-red-700 bg-red-100 dark:bg-red-800 dark:text-red-200 rounded-md">
                    {{ session('error') }}
                </div>
            @endif

            @if (isset($messaggio) && $messaggio)
                <div class="bg-yellow-100 dark:bg-yellow-800 border-l-4 border-yellow-500 dark:border-yellow-400 text-yellow-700 dark:text-yellow-200 p-4 mb-6 shadow-sm sm:rounded-lg" role="alert">
                    <p class="font-bold">{{ __('Attenzione') }}</p>
                    <p>{{ $messaggio }}</p>
                </div>
            @endif

            @if (!isset($messaggio) && isset($calciatori) && $calciatori->isNotEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg p-4 mb-6">
                    <form method="GET" action="{{ route('asta.calciatori.disponibili') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-4 items-end">
                        <div>
                            <x-input-label for="nome_calciatore_search" :value="__('Cerca Nome')" />
                            <x-text-input id="nome_calciatore_search" name="nome_calciatore_search" type="text" class="mt-1 block w-full" :value="request('nome_calciatore_search')" placeholder="Es. Rossi" />
                        </div>
                        <div>
                            <x-input-label for="squadra_serie_a_search" :value="__('Squadra Serie A')" />
                             <select name="squadra_serie_a_search" id="squadra_serie_a_search" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option value="">{{ __('Tutte le squadre') }}</option>
                                @if(isset($squadreSerieAUniche))
                                    @foreach ($squadreSerieAUniche as $squadra)
                                        <option value="{{ $squadra }}" {{ request('squadra_serie_a_search') == $squadra ? 'selected' : '' }}>{{ $squadra }}</option>
                                    @endforeach
                                @endif
                            </select>
                        </div>
                        <div class="flex items-center pt-5">
                            <x-primary-button type="submit">
                                {{ __('Filtra') }}
                            </x-primary-button>
                            <a href="{{ route('asta.calciatori.disponibili') }}" class="ms-3 inline-flex items-center px-4 py-2 bg-gray-200 dark:bg-gray-700 border border-transparent rounded-md font-semibold text-xs text-gray-700 dark:text-gray-300 uppercase tracking-widest hover:bg-gray-300 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-indigo-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Nome') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('R.') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Squadra') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Qt.I.') }}</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">{{ __('Azione') }}</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($calciatori as $calciatore)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $calciatore->nome_completo }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $calciatore->ruolo }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $calciatore->squadra_serie_a }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $calciatore->quotazione_iniziale }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
<td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
    @if(isset($impostazioniLega) && $impostazioniLega->modalita_asta === 'tap' && in_array($impostazioniLega->fase_asta_corrente, ['P','D','C','A']))
        <form method="POST" action="{{ route('asta.registra.chiamata', $calciatore->id) }}">
            @csrf
            @php
                // Determina se il pulsante deve essere disabilitato e quale title mostrare
                // La variabile $chiamataTapPossibile deve essere passata dal controller
                $isButtonDisabled = (isset($chiamataTapPossibile) && !$chiamataTapPossibile);
                $buttonTitle = $isButtonDisabled ? 
                                __('C\'è già un giocatore chiamato o un\'asta TAP in corso.') : 
                                __('Chiama questo giocatore per l\'asta TAP');
            @endphp
            <button type="submit" class="inline-flex items-center px-4 py-2 bg-green-500 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-green-600 active:bg-green-700 focus:outline-none focus:ring-2 focus:ring-green-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150 {{ (isset($chiamataTapPossibile) && !$chiamataTapPossibile) ? 'opacity-50 cursor-not-allowed' : '' }}"
    			{{ (isset($chiamataTapPossibile) && !$chiamataTapPossibile) ? 'disabled' : '' }}
    			title="{{ (isset($chiamataTapPossibile) && !$chiamataTapPossibile) ? 'C\'è già un giocatore chiamato o un\'asta TAP in corso.' : 'Chiama questo giocatore per l\'asta TAP' }}">
    			{{ __('Chiama per Asta TAP') }}
			</button>
        </form>
    @elseif(isset($impostazioniLega) && $impostazioniLega->modalita_asta === 'voce' && in_array($impostazioniLega->fase_asta_corrente, ['P','D','C','A']))
        <span class="text-xs text-gray-500 dark:text-gray-400 italic">
            {{ __('Asta a Voce') }}
        </span>
    @else
        <span class="text-xs text-gray-400 dark:text-gray-500">-</span>
    @endif
</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div class="mt-4">
                            {{ $calciatori->appends(request()->query())->links() }}
                        </div>
                    </div>
                </div>
            @elseif (!isset($messaggio) && isset($calciatori) && $calciatori->isEmpty())
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        {{ __('Nessun calciatore disponibile per la fase attuale') }} ({{ $faseAstaCorrente ?? 'N/D' }}) {{ __('o per la lista giocatori') }} "{{ $tagListaAttiva ?? 'N/D' }}".
                    </div>
                </div>
            @endif
            
            <div class="mt-6 text-center">
                <a href="{{ route('dashboard') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                    &larr; {{ __('Torna alla Dashboard Squadra') }}
                </a>
            </div>
        </div>
    </div>
</x-app-layout>