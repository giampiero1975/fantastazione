<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Visualizzazione Rose Squadre') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8"> {{-- max-w-full per usare più spazio --}}
            @if(isset($squadreConDettagli) && $squadreConDettagli->isNotEmpty())
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6"> {{-- Griglia per le card delle squadre --}}
                    @foreach($squadreConDettagli as $squadraDettaglio)
                        @php
                            // Limite totale per la squadra corrente
                            $limiteGiocatoriTotaliInRosaPerSquadra = 0;
                            if($squadraDettaglio['limiti_ruoli']) {
                                $limiteGiocatoriTotaliInRosaPerSquadra = array_sum($squadraDettaglio['limiti_ruoli']);
                            }
                            $rosaCompletaPerSquadra = ($squadraDettaglio['numero_giocatori_in_rosa'] == $limiteGiocatoriTotaliInRosaPerSquadra && $limiteGiocatoriTotaliInRosaPerSquadra > 0);
                        @endphp
                        <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                            <div class="p-6 text-gray-900 dark:text-gray-100">
                                <h3 class="text-lg font-bold text-indigo-600 dark:text-indigo-400 mb-1">{{ $squadraDettaglio['name'] }}
                                    @if($squadraDettaglio['is_admin']) <span class="text-xs px-2 py-0.5 bg-red-200 text-red-800 rounded-full ml-2">Admin</span> @endif
                                </h3>
                                <div class="text-sm mb-1">
                                    <strong>Crediti:</strong> {{ $squadraDettaglio['crediti_rimanenti'] }} / {{ $squadraDettaglio['crediti_iniziali_squadra'] }}
                                    <span class="mx-2 text-gray-300 dark:text-gray-600">|</span>
                                    <strong>Spesi:</strong> {{ $squadraDettaglio['costo_totale_rosa'] }}
                                </div>
                                <div class="text-sm mb-1 flex items-center">
                                    <strong class="mr-2">Giocatori:</strong>
                                    <span class="px-3 py-1 text-xs font-semibold rounded-full
                                        {{ $rosaCompletaPerSquadra ? 'bg-green-100 dark:bg-green-700 text-green-800 dark:text-green-100' : 'bg-red-100 dark:bg-red-700 text-red-800 dark:text-red-100' }}">
                                        {{ $squadraDettaglio['numero_giocatori_in_rosa'] }} / {{ $limiteGiocatoriTotaliInRosaPerSquadra > 0 ? $limiteGiocatoriTotaliInRosaPerSquadra : 'N/A' }}
                                    </span>
                                </div>

                                {{-- SEZIONE COMPOSIZIONE ROSA DETTAGLIATA (STILE LISTA STRETTA) --}}
                                <div class="text-xs mt-2 mb-3"> {{-- Ridotto text-sm a text-xs per più compattezza --}}
                                    <h5 class="font-semibold mb-1 text-gray-600 dark:text-gray-300">Dettaglio Reparti:</h5>
                                    <div class="max-w-xs"> {{-- Contenitore con larghezza massima --}}
                                        @php
                                            $ruoliDisplay = ['P', 'D', 'C', 'A'];
                                        @endphp
                                        @foreach($ruoliDisplay as $ruolo)
                                            @php
                                                $conteggio = $squadraDettaglio['conteggio_ruoli']->get($ruolo, 0);
                                                $costoRuolo = $squadraDettaglio['costi_per_ruolo']->get($ruolo, 0);
                                                $limite = $squadraDettaglio['limiti_ruoli'][$ruolo] ?? 0;

                                                $coloreTesto = 'text-gray-700 dark:text-gray-300';
                                                if ($limite > 0) {
                                                    if ($conteggio == $limite) {
                                                        $coloreTesto = 'text-green-500 dark:text-green-400';
                                                    } elseif ($conteggio < $limite) {
                                                        $coloreTesto = 'text-red-500 dark:text-red-400';
                                                    } else {
                                                        $coloreTesto = 'text-yellow-500 dark:text-yellow-400';
                                                    }
                                                } elseif ($conteggio > 0 && $limite == 0) {
                                                    $coloreTesto = 'text-blue-500 dark:text-blue-400';
                                                }
                                                $testoLimite = ($limite > 0) ? "/{$limite}" : ' ill.';
                                            @endphp
                                            <div class="py-0.5 px-1.5 rounded {{ $loop->odd ? 'bg-gray-50 dark:bg-gray-700/30' : 'bg-white dark:bg-gray-800/30' }}">
                                                <strong class="font-bold {{ $coloreTesto }}">{{ $ruolo }}:</strong>
                                                <span class="{{ $coloreTesto }}">{{ $conteggio }}{{ $testoLimite }}</span>
                                                <span class="ml-2 text-gray-500 dark:text-gray-400">
                                                    (Spesi: <strong class="font-medium text-gray-700 dark:text-gray-200">{{ $costoRuolo }} crd.</strong>)
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                                {{-- FINE SEZIONE COMPOSIZIONE ROSA --}}


                                @if($squadraDettaglio['rosa_giocatori']->isNotEmpty())
                                    <div class="mt-3 overflow-x-auto">
                                        <h5 class="text-xs font-semibold text-gray-600 dark:text-gray-300 mb-1">Giocatori:</h5>
                                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700 text-xs">
                                            <thead class="bg-gray-50 dark:bg-gray-700">
                                                <tr>
                                                    <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">R.</th>
                                                    <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nome</th>
                                                    <th class="px-2 py-1 text-left font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Prezzo</th>
                                                </tr>
                                            </thead>
                                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                                @foreach($squadraDettaglio['rosa_giocatori']->sortBy(function($acquisto){ return match(optional($acquisto->calciatore)->ruolo ?? $acquisto->ruolo_al_momento_acquisto) {'P'=>1, 'D'=>2, 'C'=>3, 'A'=>4, default=>5}; }) as $acquisto)
                                                    <tr>
                                                        <td class="px-2 py-1 whitespace-nowrap">{{ optional($acquisto->calciatore)->ruolo ?? $acquisto->ruolo_al_momento_acquisto }}</td>
                                                        <td class="px-2 py-1 whitespace-nowrap font-medium">{{ optional($acquisto->calciatore)->nome_completo ?? 'N/D' }}</td>
                                                        <td class="px-2 py-1 whitespace-nowrap text-right">{{ $acquisto->prezzo_acquisto }}</td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @else
                                    <p class="mt-3 text-xs text-gray-500 dark:text-gray-400">Questa squadra non ha ancora acquistato giocatori.</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                 <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        Nessuna squadra trovata o dati non disponibili.
                    </div>
                </div>
            @endif
        </div>
    </div>
</x-app-layout>