{{-- File: resources/views/admin/asta/chiamate.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Gestione Chiamate Asta TAP') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

            {{-- Messaggi di Sessione --}}
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

            @if(isset($impostazioniLega) && ($impostazioniLega->modalita_asta !== 'tap' || !$impostazioniLega->asta_tap_approvazione_admin))
                <div class="mb-6 p-4 text-sm text-orange-700 bg-orange-100 dark:bg-orange-800 dark:text-orange-200 rounded-md">
                    {{ __('Attualmente la modalità asta non è "TAP con approvazione admin". Le chiamate TAP (se la modalità è TAP) partiranno automaticamente o la modalità asta è "A Voce". Questa pagina mostrerà le chiamate in attesa solo se la configurazione lo richiede.') }}
                </div>
            @endif

            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <h3 class="text-lg font-semibold mb-4">Chiamate Giocatori in Attesa di Approvazione per Asta TAP</h3>

                    @if(isset($chiamateInAttesa) && $chiamateInAttesa->isNotEmpty())
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700">
                                    <tr>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Calciatore</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">R.</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Squadra Serie A</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Qt.I.</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Chiamato da</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tag Lista</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Data Chiamata</th>
                                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Azione</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    @foreach ($chiamateInAttesa as $chiamata)
                                        <tr>
                                            <td class="px-6 py-4 whitespace-nowrap font-medium text-gray-900 dark:text-gray-100">{{ $chiamata->calciatore->nome_completo ?? 'N/D' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $chiamata->calciatore->ruolo ?? 'N/D' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $chiamata->calciatore->squadra_serie_a ?? 'N/D' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $chiamata->calciatore->quotazione_iniziale ?? 'N/D' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $chiamata->utenteChiamante->name ?? 'N/D' }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $chiamata->tag_lista_calciatori }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{ $chiamata->created_at->format('d/m/Y H:i') }}</td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm">
                                                <div class="flex space-x-2">
                                                    @if (isset($impostazioniLega) && $impostazioniLega->modalita_asta === 'tap' && $impostazioniLega->asta_tap_approvazione_admin && (!isset($astaTapLiveEsistente) || !$astaTapLiveEsistente))
                                                        <form method="POST" action="{{ route('admin.asta.avvia.tap', $chiamata->id) }}" class="inline">
                                                            @csrf
                                                            <x-primary-button type="submit" class="bg-blue-500 hover:bg-blue-600 text-xs">
                                                                {{ __('Avvia Asta') }}
                                                            </x-primary-button>
                                                        </form>
                                                    @elseif (isset($astaTapLiveEsistente) && $astaTapLiveEsistente)
                                                         <span class="text-xs text-yellow-600 dark:text-yellow-400 italic py-1 px-2">{{ __('Asta TAP già in corso') }}</span>
                                                    @else
                                                        {{-- <span class="text-xs text-gray-400 dark:text-gray-500 italic py-1 px-2">{{ __('Non avviabile') }}</span> --}}
                                                    @endif

                                                    {{-- NUOVO PULSANTE ANNULLA CHIAMATA --}}
                                                    @if (in_array($chiamata->stato_chiamata, ['in_attesa_admin'])) {{-- Mostra solo se è in attesa di admin --}}
                                                        <form method="POST" action="{{ route('admin.asta.chiamata.annulla', $chiamata->id) }}" onsubmit="return confirm('Sei sicuro di voler annullare questa chiamata per {{ $chiamata->calciatore->nome_completo ?? 'questo giocatore' }}?');" class="inline">
                                                            @csrf
                                                            {{-- Laravel non supporta DELETE nativamente nei form HTML se non con JS o un campo _method.
                                                                 Dato che la rotta è POST, non serve _method('DELETE') qui. Se la rotta fosse DELETE, servirebbe. --}}
                                                            <x-danger-button type="submit" class="text-xs">
                                                                {{ __('Annulla Chiamata') }}
                                                            </x-danger-button>
                                                        </form>
                                                    @endif
                                                    {{-- FINE NUOVO PULSANTE --}}
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p>{{ __('Nessuna chiamata giocatore in attesa di approvazione.') }}</p>
                    @endif
                </div>
            </div>

            {{-- TODO: Potresti voler aggiungere qui una sezione per visualizzare l'eventuale Asta TAP *Live* in corso,
                 con un pulsante per annullarla se necessario (usando lo stesso metodo annullaChiamataTap) --}}
             @if(isset($astaTapLiveAttiva) && $astaTapLiveAttiva) {{-- Dovrai passare $astaTapLiveAttiva dal controller --}}
                <div class="mt-6 bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="text-lg font-semibold mb-2 text-orange-500">Asta TAP Attualmente Live</h3>
                        <p>
                            Calciatore: <strong>{{ $astaTapLiveAttiva->calciatore->nome_completo ?? 'N/D' }}</strong><br>
                            Prezzo Attuale: <strong>{{ $astaTapLiveAttiva->prezzo_attuale_tap ?? 'N/D' }} cr.</strong><br>
                            Miglior Offerente: <strong>{{ $astaTapLiveAttiva->migliorOfferenteTap->name ?? ( $astaTapLiveAttiva->utenteChiamante->name ?? 'N/D') }}</strong><br>
                            Termine Previsto: {{ $astaTapLiveAttiva->timestamp_fine_tap_prevista ? \Carbon\Carbon::parse($astaTapLiveAttiva->timestamp_fine_tap_prevista)->format('H:i:s') : 'N/A' }}
                        </p>
                        <form method="POST" action="{{ route('admin.asta.chiamata.annulla', $astaTapLiveAttiva->id) }}" onsubmit="return confirm('Sei sicuro di voler ANNULLARE l\'asta LIVE per {{ $astaTapLiveAttiva->calciatore->nome_completo ?? 'questo giocatore' }}?');" class="mt-3">
                            @csrf
                            <x-danger-button type="submit">
                                {{ __('Annulla Asta LIVE') }}
                            </x-danger-button>
                        </form>
                    </div>
                </div>
             @endif

        </div>
    </div>
</x-app-layout>