{{-- In resources/views/admin/settings/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Impostazioni Lega e Asta') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    {{-- Messaggi di sessione e errori (come li avevi già) --}}
                    @if (session('success')) <div class="mb-4 p-4 font-medium text-sm text-green-700 bg-green-100 dark:bg-green-800 dark:text-green-200 rounded-md">{{ session('success') }}</div> @endif
                    @if ($errors->any()) <div class="mb-4 p-4 font-medium text-sm text-red-700 bg-red-100 dark:bg-red-800 dark:text-red-200 rounded-md"><strong>Attenzione!</strong><ul>@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif

                    <form method="POST" action="{{ route('admin.settings.update') }}">
                        @csrf

                        <div class="mb-4">
                            <x-input-label for="fase_asta_corrente" :value="__('Fase Asta Corrente')" />
                            <select name="fase_asta_corrente" id="fase_asta_corrente" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                @foreach ($fasiPossibili as $key => $value)
                                    <option value="{{ $key }}" {{ (old('fase_asta_corrente', $impostazioni->fase_asta_corrente ?? 'PRE_ASTA') == $key) ? 'selected' : '' }}>
                                        {{ $value }}
                                    </option>
                                @endforeach
                            </select>
                            <x-input-error :messages="$errors->get('fase_asta_corrente')" class="mt-2" />
                        </div>

                        <div class="mb-4">
                            <x-input-label for="tag_lista_attiva" :value="__('Tag Lista Calciatori Attiva per l\'Asta')" />
                            <select name="tag_lista_attiva" id="tag_lista_attiva" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option value="">-- {{ __('Nessuna / Usa ultima importata') }} --</option>
                                @if(isset($tagsCalciatoriDisponibili))
                                    @foreach ($tagsCalciatoriDisponibili as $tag)
                                        <option value="{{ $tag }}" {{ (old('tag_lista_attiva', $impostazioni->tag_lista_attiva ?? '') == $tag) ? 'selected' : '' }}>
                                            {{ $tag }}
                                        </option>
                                    @endforeach
                                @endif
                            </select>
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Seleziona la lista giocatori da usare per l'asta corrente.</p>
                            <x-input-error :messages="$errors->get('tag_lista_attiva')" class="mt-2" />
                        </div>
                        
                        <hr class="my-6 border-gray-300 dark:border-gray-700">
                        <h4 class="text-md font-semibold mb-2 text-indigo-600 dark:text-indigo-400">Configurazione Asta</h4>


                        <div class="mt-4">
                            <x-input-label for="modalita_asta" :value="__('Modalità Svolgimento Asta')" />
                            <select name="modalita_asta" id="modalita_asta" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                <option value="voce" {{ (old('modalita_asta', $impostazioni->modalita_asta ?? 'voce') == 'voce') ? 'selected' : '' }}>A Voce (Admin assegna manualmente)</option>
                                <option value="tap" {{ (old('modalita_asta', $impostazioni->modalita_asta ?? 'voce') == 'tap') ? 'selected' : '' }}>A Rilancio "TAP" (con countdown)</option>
                            </select>
                            <x-input-error :messages="$errors->get('modalita_asta')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="durata_countdown_secondi" :value="__('Durata Countdown Asta TAP (secondi)')" />
                            <x-text-input id="durata_countdown_secondi" class="block mt-1 w-full" type="number" name="durata_countdown_secondi" :value="old('durata_countdown_secondi', $impostazioni->durata_countdown_secondi ?? 60)" min="10" max="300" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Applicabile solo se la modalità è "A Rilancio TAP". Min 10s, Max 300s.</p>
                            <x-input-error :messages="$errors->get('durata_countdown_secondi')" class="mt-2" />
                        </div>

                        <div class="mt-4" x-data="{ enabled: {{ old('asta_tap_approvazione_admin', $impostazioni->asta_tap_approvazione_admin ?? true) ? 'true' : 'false' }} }">
    <x-input-label for="asta_tap_approvazione_admin_toggle" :value="__('Approvazione Admin per Asta TAP (dopo chiamata utente)')" />
    <div class="flex items-center mt-1">
        <span class="text-sm text-gray-500 dark:text-gray-400 mr-3" x-text="enabled ? 'Sì, richiesta' : 'No, automatica'"></span>
        <button 
            type="button"
            class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800"
            :class="enabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'"
            @click="enabled = !enabled"
            role="switch"
            :aria-checked="enabled.toString()"
        >
            <span class="sr-only">Usa toggle</span>
            <span
                aria-hidden="true"
                class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200"
                :class="enabled ? 'translate-x-5' : 'translate-x-0'"
            ></span>
        </button>
    </div>
    {{-- Input hidden per inviare il valore al backend --}}
    <input type="hidden" name="asta_tap_approvazione_admin" :value="enabled ? '1' : '0'">
    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se "Sì", l'admin deve approvare la chiamata prima che l'asta TAP inizi. Se "No", l'asta TAP parte automaticamente dopo la chiamata utente.</p>
    <x-input-error :messages="$errors->get('asta_tap_approvazione_admin')" class="mt-2" />
</div>


                        <hr class="my-6 border-gray-300 dark:border-gray-700">
                        <h4 class="text-md font-semibold mb-2 text-indigo-600 dark:text-indigo-400">Regole Rosa e Crediti</h4>

                        <div class="mt-4">
                            <x-input-label for="crediti_iniziali_lega" :value="__('Crediti Iniziali per Squadra')" />
                            <x-text-input id="crediti_iniziali_lega" class="block mt-1 w-full" type="number" name="crediti_iniziali_lega" :value="old('crediti_iniziali_lega', $impostazioni->crediti_iniziali_lega ?? 500)" required />
                            <x-input-error :messages="$errors->get('crediti_iniziali_lega')" class="mt-2" />
                        </div>
                        
                        <h5 class="mt-6 mb-2 text-sm font-semibold">Limiti Giocatori per Ruolo:</h5>
                        {{-- Assicurati che questi blocchi siano presenti e corretti --}}

                        <div class="mt-4">
                            <x-input-label for="num_portieri" :value="__('Numero Portieri')" />
                            <x-text-input id="num_portieri" class="block mt-1 w-full" type="number" name="num_portieri" :value="old('num_portieri', $impostazioni->num_portieri ?? 6)" required />
                            <x-input-error :messages="$errors->get('num_portieri')" class="mt-2" />
                        </div>
                        
                        <div class="mt-4">
                            <x-input-label for="num_difensori" :value="__('Numero Difensori')" />
                            <x-text-input id="num_difensori" class="block mt-1 w-full" type="number" name="num_difensori" :value="old('num_difensori', $impostazioni->num_difensori ?? 8)" required />
                            <x-input-error :messages="$errors->get('num_difensori')" class="mt-2" />
                        </div>
                        
                        <div class="mt-4">
                            <x-input-label for="num_centrocampisti" :value="__('Numero Centrocampisti')" />
                            <x-text-input id="num_centrocampisti" class="block mt-1 w-full" type="number" name="num_centrocampisti" :value="old('num_centrocampisti', $impostazioni->num_centrocampisti ?? 8)" required />
                            <x-input-error :messages="$errors->get('num_centrocampisti')" class="mt-2" />
                        </div>
                        
                        <div class="mt-4">
                            <x-input-label for="num_attaccanti" :value="__('Numero Attaccanti')" />
                            <x-text-input id="num_attaccanti" class="block mt-1 w-full" type="number" name="num_attaccanti" :value="old('num_attaccanti', $impostazioni->num_attaccanti ?? 6)" required />
                            <x-input-error :messages="$errors->get('num_attaccanti')" class="mt-2" />
                        </div>

                        <div class="block mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h4 class="text-md font-semibold mb-2 text-red-600 dark:text-red-400">{{ __('Azioni per Nuova Asta') }}</h4>
                            <label for="reset_asta_completo" class="inline-flex items-center">
                                <input id="reset_asta_completo" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600" name="reset_asta_completo" value="1">
                                <span class="ms-2 text-sm text-gray-700 dark:text-gray-300 font-medium">{{ __('PREPARA NUOVA SESSIONE D\'ASTA COMPLETA') }}</span>
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <strong>Attenzione:</strong> Selezionando questa opzione e salvando, verranno resettati i crediti di tutte le squadre al valore "Crediti Iniziali per Squadra" sopra definito, e TUTTE le rose attuali (giocatori acquistati) verranno CANCELLATE. La fase dell'asta verrà impostata a "Portieri (P)". Usare solo per iniziare una nuova stagione d'asta o un'asta di riparazione da zero. Questa azione è irreversibile per le rose.
                            </p>
                        </div>

                        <div class="flex items-center justify-end mt-6">
                            <x-primary-button>
                                {{ __('Salva Impostazioni') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>