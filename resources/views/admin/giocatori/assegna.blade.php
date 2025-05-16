<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Assegna Manualmente Calciatore') }}
            @if(isset($tagAttivo) && $tagAttivo)
                <span class="text-sm text-gray-500 dark:text-gray-400">(Stagione/Tag: {{ $tagAttivo }})</span>
            @endif
            @if(isset($faseAstaCorrente) && in_array($faseAstaCorrente, ['P', 'D', 'C', 'A']))
                <span class="text-sm text-indigo-500 dark:text-indigo-400 ml-2">[Fase Asta: {{ $faseAstaCorrente }}]</span>
            @elseif(isset($faseAstaCorrente))
                 <span class="text-sm text-gray-500 dark:text-gray-400 ml-2">[Fase Asta: {{ $faseAstaCorrente }}]</span>
            @endif
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">

                    {{-- ... Messaggi Session ... --}}
                    @if (session('success')) <div class="mb-4 p-4 font-medium text-sm text-green-700 bg-green-100 dark:bg-green-800 dark:text-green-200 rounded-md">{{ session('success') }}</div> @endif
                    @if (session('error')) <div class="mb-4 p-4 font-medium text-sm text-red-700 bg-red-100 dark:bg-red-800 dark:text-red-200 rounded-md">{{ session('error') }}</div> @endif
                    @if ($errors->any()) <div class="mb-4 p-4 font-medium text-sm text-red-700 bg-red-100 dark:bg-red-800 dark:text-red-200 rounded-md"><strong>{{ __('Attenzione!') }}</strong><ul class="list-disc list-inside">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif
                    @if (session('aggregate')) <div class="mb-4 p-4 font-medium text-sm text-blue-700 bg-blue-100 dark:bg-blue-800 dark:text-blue-200 rounded-md">{{ session('aggregate') }}</div> @endif


                    <form method="POST" action="{{ route('admin.giocatori.assegna.handle') }}" id="assegnaForm">
                        @csrf
                        <div class="mb-4">
                            <x-input-label for="user_id" :value="__('Seleziona Squadra')" />
                            <select name="user_id" id="user_id" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required>
                                <option value="">-- {{ __('Seleziona una squadra') }} --</option>
                                @if(isset($squadre))
                                    @foreach ($squadre as $squadra)
                                        <option value="{{ $squadra->id }}" {{ old('user_id') == $squadra->id ? 'selected' : '' }}>
                                            {{ $squadra->name }} ({{ __('Crediti') }}: {{ $squadra->crediti_rimanenti }})
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                        </div>

                        <hr class="my-6 border-gray-300 dark:border-gray-700">

                        {{-- Filtro Ruolo (ora potrebbe essere nascosto o mostrare il ruolo della fase) --}}
                        @if(!in_array($faseAstaCorrente, ['P', 'D', 'C', 'A'])) {{-- Mostra solo se non in fase P,D,C,A --}}
                            <div class="mb-4">
                                <x-input-label for="filtro_ruolo" :value="__('1. Filtra per Ruolo (opzionale)')" />
                                <select id="filtro_ruolo" name="filtro_ruolo_input_per_js" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                    <option value="">-- {{ __('Tutti i Ruoli') }} --</option>
                                    @if(isset($ruoliDisponibili))
                                        @foreach ($ruoliDisponibili as $ruolo)
                                            <option value="{{ $ruolo }}" {{ (isset($ruoloDaFiltrare) && $ruoloDaFiltrare == $ruolo) ? 'selected' : '' }}>
                                                {{ $ruolo }}
                                            </option>
                                        @endforeach
                                    @endif
                                </select>
                            </div>
                        @else
                             <div class="mb-4">
                                <p class="text-sm text-gray-600 dark:text-gray-400">Ricerca giocatori per il ruolo della fase attuale: **{{ $faseAstaCorrente }}**</p>
                                {{-- Passiamo comunque il valore per il JS se necessario --}}
                                <input type="hidden" id="filtro_ruolo" value="{{ $faseAstaCorrente }}">
                             </div>
                        @endif

                        <div>
                            <x-input-label for="autocomplete_calciatore" :value="__('2. Cerca e Seleziona Calciatore')" />
                            <input id="autocomplete_calciatore" type="text" placeholder="{{ __('Inizia a digitare un nome...') }}" class="block mt-1 w-full"/>
                            <input type="hidden" name="calciatore_id" id="calciatore_id_hidden">
                            <x-input-error :messages="$errors->get('calciatore_id')" class="mt-2" />
                        </div>
                        
                        <div class="mb-4 mt-4">
                            <x-input-label for="prezzo_acquisto" :value="__('Prezzo di Acquisto')" />
                            <x-text-input id="prezzo_acquisto" class="block mt-1 w-full" type="number" name="prezzo_acquisto" :value="old('prezzo_acquisto', 1)" required min="0" />
                            <x-input-error :messages="$errors->get('prezzo_acquisto')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button type="submit">
                                {{ __('Assegna Calciatore') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

@push('scripts')
{{-- Assicurati che TomSelect JS e CSS siano caricati globalmente o qui --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    const filtroRuoloSelectEl = document.getElementById('filtro_ruolo'); // Questo è il <select> o <input type="hidden">
    const calciatoreIdHiddenInput = document.getElementById('calciatore_id_hidden');
    const autocompleteCalciatoreEl = document.getElementById('autocomplete_calciatore');
    const faseAstaAttuale = @json($faseAstaCorrente ?? ''); // Fase asta passata dal controller

    if (!autocompleteCalciatoreEl) return;

    let tomSelectInstance = new TomSelect(autocompleteCalciatoreEl, {
        valueField: 'value',
        labelField: 'text',
        searchField: ['text'],
        maxItems: 1,
        create: false,
        //dropdownParent: 'body', // Se serve ancora
        load: function(query, callback) {
            let ruoloPerQuery = '';
            if (['P', 'D', 'C', 'A'].includes(faseAstaAttuale)) {
                ruoloPerQuery = faseAstaAttuale; // Usa la fase asta se è P,D,C,A
            } else if (filtroRuoloSelectEl) {
                ruoloPerQuery = filtroRuoloSelectEl.value; // Altrimenti usa il filtro manuale (se esiste)
            }

            // Non caricare se query è troppo corta E nessun ruolo è effettivamente selezionato per il filtro
            // (es. faseAstaAttuale è PRE_ASTA e filtroRuoloSelectEl.value è vuoto)
            if ((!query || query.length < 1) && !ruoloPerQuery) {
                 return callback();
            }
            
            let url = `{{ route('admin.giocatori.autocomplete') }}?q=${encodeURIComponent(query)}`;
            if (ruoloPerQuery) {
                url += `&ruolo=${encodeURIComponent(ruoloPerQuery)}`;
            }

            fetch(url)
                .then(response => response.json())
                .then(json => { callback(json); })
                .catch(()=>{ callback(); });
        },
        onChange: function(value) {
            if (calciatoreIdHiddenInput) {
                calciatoreIdHiddenInput.value = value || '';
            }
        }
        // render: { ... } (come prima se vuoi personalizzare)
    });

    // Ricarica le opzioni di TomSelect quando il filtro ruolo manuale cambia
    // (solo se il filtro ruolo manuale è visibile e usato)
    if(filtroRuoloSelectEl && filtroRuoloSelectEl.tagName === 'SELECT') { // Controlla se è un select
        filtroRuoloSelectEl.addEventListener('change', function() {
            tomSelectInstance.clearOptions('silent');
            tomSelectInstance.load(''); // Forza ricaricamento con il nuovo ruolo dal filtro manuale
            tomSelectInstance.clear();
            if (calciatoreIdHiddenInput) calciatoreIdHiddenInput.value = '';
        });
    }
    
    // Popolamento iniziale se necessario o se una fase è già attiva
    if (['P', 'D', 'C', 'A'].includes(faseAstaAttuale)) {
        tomSelectInstance.load(''); // Tenta di caricare i giocatori per la fase corrente all'avvio
    }

});
</script>
@endpush
</x-app-layout>