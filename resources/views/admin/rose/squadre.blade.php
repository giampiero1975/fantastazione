<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Visualizzazione Rose Squadre') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-full mx-auto sm:px-6 lg:px-8">
            @if(isset($squadreConDettagli) && $squadreConDettagli->isNotEmpty())
                @foreach($squadreConDettagli as $squadra)
                    @php
                        // Calcoliamo il limite totale di giocatori per la rosa
                        $limiteGiocatoriTotaliInRosa = 0;
                        if($squadra['limiti_ruoli']) {
                            $limiteGiocatoriTotaliInRosa = $squadra['limiti_ruoli']['P'] + $squadra['limiti_ruoli']['D'] + $squadra['limiti_ruoli']['C'] + $squadra['limiti_ruoli']['A'];
                        }
                        $rosaCompleta = ($squadra['numero_giocatori_in_rosa'] == $limiteGiocatoriTotaliInRosa);
                    @endphp
                    <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                        <div class="p-6 text-gray-900 dark:text-gray-100">
                            <h3 class="text-lg font-bold text-indigo-600 dark:text-indigo-400 mb-1">{{ $squadra['name'] }}
                                @if($squadra['is_admin']) <span class="text-xs px-2 py-0.5 bg-red-200 text-red-800 rounded-full ml-2">Admin</span> @endif
                            </h3>
                            <div class="text-sm mb-1">
                                <strong>Crediti Rimanenti:</strong> {{ $squadra['crediti_rimanenti'] }} / {{ $squadra['crediti_iniziali_squadra'] }}
                                <span class="mx-2">|</span>
                                <strong>Spesi:</strong> {{ $squadra['costo_totale_rosa'] }}
                            </div>
                            <div class="text-sm mb-1 flex items-center">
                                <strong class="mr-2">Giocatori in Rosa:</strong>
                                <span class="px-3 py-1 text-xs font-semibold rounded-full
                                    {{ $rosaCompleta ? 'bg-green-100 dark:bg-green-700 text-green-800 dark:text-green-100' : 'bg-red-100 dark:bg-red-700 text-red-800 dark:text-red-100' }}">
                                    {{ $squadra['numero_giocatori_in_rosa'] }} / {{ $limiteGiocatoriTotaliInRosa }}
                                </span>
                            </div>
                            <div class="text-sm mb-4">
                                <strong>Composizione Rosa:</strong>
                                @if($squadra['limiti_ruoli'])
                                    @php
                                        $ruoli = ['P', 'D', 'C', 'A'];
                                        $outputRuoli = [];
                                        foreach($ruoli as $ruolo) {
                                            $conteggio = $squadra['conteggio_ruoli']->get($ruolo, 0);
                                            $limite = $squadra['limiti_ruoli'][$ruolo];
                                            $coloreBadge = ($conteggio == $limite) ? 'bg-green-100 dark:bg-green-700 text-green-800 dark:text-green-100' : 'bg-red-100 dark:bg-red-700 text-red-800 dark:text-red-100';
                                            if ($limite == 0 && $conteggio == 0) { // Se un ruolo ha limite 0 e 0 giocatori, non lo mostriamo come errore
                                                $coloreBadge = 'bg-gray-100 dark:bg-gray-700 text-gray-800 dark:text-gray-100';
                                            }
                                             $outputRuoli[] = "<span class='px-2 py-0.5 text-xs font-semibold rounded-full mr-1 {$coloreBadge}'>{$ruolo}: {$conteggio}/{$limite}</span>";
                                        }
                                    @endphp
                                    {!! implode(' | ', $outputRuoli) !!}
                                @else
                                    Conteggio ruoli non disponibile.
                                @endif
                            </div>

                            @if($squadra['rosa_giocatori']->isNotEmpty())
                                <div class="overflow-x-auto mt-4">
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
                                            @foreach($squadra['rosa_giocatori']->sortBy(function($acquisto){ return match($acquisto->calciatore->ruolo ?? $acquisto->ruolo_al_momento_acquisto) {'P'=>1, 'D'=>2, 'C'=>3, 'A'=>4, default=>5}; }) as $acquisto)
                                                <tr>
                                                    <td class="px-4 py-2 whitespace-nowrap">{{ $acquisto->calciatore->ruolo ?? $acquisto->ruolo_al_momento_acquisto }}</td>
                                                    <td class="px-4 py-2 whitespace-nowrap font-medium">{{ $acquisto->calciatore->nome_completo ?? 'N/D' }}</td>
                                                    <td class="px-4 py-2 whitespace-nowrap">{{ $acquisto->calciatore->squadra_serie_a ?? 'N/D' }}</td>
                                                    <td class="px-4 py-2 whitespace-nowrap">{{ $acquisto->prezzo_acquisto }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <p class="text-sm text-gray-500 dark:text-gray-400">Questa squadra non ha ancora acquistato giocatori.</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            @else
                 <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        Nessuna squadra trovata.
                    </div>
                </div>
            @endif

            {{-- Eventuale paginazione se $squadreConDettagli fosse paginato dal controller --}}
            {{-- <div class="mt-4">
                {{ $squadreConDettagli->links() }}
            </div> --}}
        </div>
    </div>
</x-app-layout>