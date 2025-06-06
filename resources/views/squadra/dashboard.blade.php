<x-app-layout> {{-- Usa il layout di Breeze --}}
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('La Mia Dashboard Squadra') }} - {{ $squadra->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-2">Riepilogo Squadra</h3>
                    <p><strong>Crediti Rimanenti:</strong> {{ $squadra->crediti_rimanenti }} / {{ $squadra->crediti_iniziali_squadra }}</p>
                    <p><strong>Crediti Spesi:</strong> {{ $costoTotaleRosa }}</p>
                    <p class="flex items-center">
                        <strong class="mr-2">Giocatori in Rosa:</strong>
                        @php
                            $rosaCompleta = ($numeroGiocatoriInRosa == $limiteGiocatoriTotaliInRosa);
                        @endphp
                        <span class="px-3 py-1 text-xs font-semibold rounded-full
                            {{ $rosaCompleta ? 'bg-green-100 dark:bg-green-700 text-green-800 dark:text-green-100' : 'bg-red-100 dark:bg-red-700 text-red-800 dark:text-red-100' }}">
                            {{ $numeroGiocatoriInRosa }} / {{ $limiteGiocatoriTotaliInRosa }}
                        </span>
                    </p>

                    {{-- SEZIONE COMPOSIZIONE ROSA - TESTO COMPATTO SU UNA RIGA PER RUOLO --}}
                    <div class="text-sm mt-2 mb-2">
                        <h4 class="font-semibold mb-1 text-gray-700 dark:text-gray-200">Composizione Rosa Dettagliata:</h4>
                        <div class="space-y-1"> {{-- Manteniamo space-y-1 per separare verticalmente le righe dei ruoli --}}
                            @php
                                $ruoliDisplay = ['P', 'D', 'C', 'A'];
                            @endphp
                            @foreach($ruoliDisplay as $ruolo)
                                @php
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
                                @endphp
                                {{-- Ogni ruolo � un semplice div di testo. Il flexbox non � pi� necessario qui per questo layout. --}}
                                <div class="py-1 px-2 rounded {{ $loop->odd ? 'bg-gray-50 dark:bg-gray-700/50' : 'bg-white dark:bg-gray-800' }}">
                                    <strong class="font-bold {{ $coloreTesto }}">{{ $ruolo }}:</strong>
                                    <span class="{{ $coloreTesto }}">{{ $conteggio }}{{ $testoLimite }}</span>
                                    {{-- I costi sono ora direttamente accodati, con un leggero spazio --}}
                                    <span class="ml-2 text-xs text-gray-500 dark:text-gray-400">
                                        (Spesi: <strong class="font-medium text-gray-700 dark:text-gray-200">{{ $costoRuolo }} crd.</strong>)
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    {{-- FINE SEZIONE COMPOSIZIONE ROSA --}}

                    <p class="mt-4"><strong>Fase Asta Corrente:</strong> {{ $impostazioniLega->fase_asta_corrente }}</p>
                    <p><strong>Lista Calciatori Attiva:</strong> {{ $impostazioniLega->tag_lista_attiva ?? 'Non definita' }}</p>
                </div>
            </div>

            {{-- Resto della dashboard (tabella rosa, ecc.) --}}
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">La Mia Rosa</h3>
                    @if($rosa->isNotEmpty())
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
                                    @foreach($rosa->sortBy(function($acquisto){ return match(optional($acquisto->calciatore)->ruolo ?? '') {'P'=>1, 'D'=>2, 'C'=>3, 'A'=>4, default=>5}; }) as $acquisto)
                                        <tr>
                                            <td class="px-4 py-2 whitespace-nowrap">{{ optional($acquisto->calciatore)->ruolo ?? 'N/D' }}</td>
                                            <td class="px-4 py-2 whitespace-nowrap font-medium">{{ optional($acquisto->calciatore)->nome_completo ?? 'N/D' }}</td>
                                            <td class="px-4 py-2 whitespace-nowrap">{{ optional($acquisto->calciatore)->squadra_serie_a ?? 'N/D' }}</td>
                                            <td class="px-4 py-2 whitespace-nowrap">{{ $acquisto->prezzo_acquisto }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p>Non hai ancora acquistato nessun giocatore.</p>
                    @endif
                    <div class="mt-6">
                        <a href="{{ route('asta.calciatori.disponibili') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                            Visualizza Calciatori Disponibili per l'Asta
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>