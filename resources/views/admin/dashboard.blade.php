<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Dashboard Amministratore') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
			<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6"> {{-- mb-6 se vuoi forzare un margine sotto su mobile --}}
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="text-lg font-semibold mb-3">Stato Asta</h3>
                        @if($impostazioniLega)
                            <p><strong>Fase Corrente:</strong> <span class="font-bold text-indigo-600 dark:text-indigo-400">{{ $impostazioniLega->fase_asta_corrente }}</span></p>
                            <p><strong>Lista Calciatori Attiva:</strong> {{ $impostazioniLega->tag_lista_attiva ?? 'Non definita' }}</p>
                            <p><strong>Crediti Iniziali Lega:</strong> {{ $impostazioniLega->crediti_iniziali_lega }}</p>
                            <div class="mt-4">
                                <a href="{{ route('admin.impostazioni.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Modifica Impostazioni &raquo;</a>
                            </div>
                        @else
                            <p class="text-red-500 dark:text-red-400">Impostazioni della lega non ancora configurate.</p>
                             <a href="{{ route('admin.impostazioni.index') }}" class="text-sm text-indigo-600 dark:text-indigo-400 hover:underline">Configura ora &raquo;</a>
                        @endif
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mb-6">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="text-lg font-semibold mb-3">Riepilogo Dati</h3>
                        <p><strong>Numero Squadre Partecipanti:</strong> {{ $numeroSquadre ?? 0 }}</p>
                        <p><strong>Numero Calciatori nel Database:</strong> {{ $numeroCalciatoriImportati ?? 0 }}</p>
                    </div>
                </div>

                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg md:col-span-2 lg:col-span-1">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="text-lg font-semibold mb-3">Collegamenti Rapidi</h3>
                        <ul class="space-y-2">
                            <li><a href="{{ route('admin.giocatori.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Gestione Calciatori</a></li>
                            <li><a href="{{ route('admin.giocatori.import.show') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Importa Calciatori da CSV</a></li>
                            <li><a href="{{ route('admin.giocatori.assegna.show') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Assegna Giocatore Manualmente</a></li>
                            <li><a href="{{ route('admin.utenti.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Gestione Utenti/Squadre</a></li>
                            <li><a href="{{ route('admin.rose.squadre.index') }}" class="text-indigo-600 dark:text-indigo-400 hover:underline">Visualizza Rose Squadre</a></li>
                            <li><a href="{{ route('admin.impostazioni.index') }}" class="font-bold text-green-600 dark:text-green-400 hover:underline">Vai a Impostazioni Lega/Asta</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            {{-- SEZIONE PER "LA MIA SQUADRA" PER L'ADMIN --}}
            @auth
                @if(Auth::user()->is_admin)
                <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg mt-6">
                    <div class="p-6 text-gray-900 dark:text-gray-100">
                        <h3 class="text-lg font-semibold mb-3">La Tua Squadra Partecipante</h3>
                        <p>Visualizza e gestisci la tua squadra "{{ Auth::user()->name }}" come se fossi un partecipante.</p>
                        <div class="mt-4">
                            <a href="{{ route('admin.mia_squadra.dashboard') }}" class="inline-flex items-center px-4 py-2 bg-blue-600 border border-transparent rounded-md font-semibold text-xs text-white uppercase tracking-widest hover:bg-blue-500 active:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-offset-2 dark:focus:ring-offset-gray-800 transition ease-in-out duration-150">
                                Vai alla Dashboard della Tua Squadra
                            </a>
                        </div>
                    </div>
                </div>
                @endif
            @endauth
            {{-- FINE SEZIONE "LA MIA SQUADRA" --}}

        </div>
    </div>
</x-app-layout>