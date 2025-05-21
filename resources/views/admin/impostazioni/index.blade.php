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
                    {{-- Messaggi di sessione e errori --}}
                    @if (session('success'))
                        <div class="mb-4 p-4 font-medium text-sm text-green-700 bg-green-100 dark:bg-green-800 dark:text-green-200 rounded-md">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-4 p-4 font-medium text-sm text-red-700 bg-red-100 dark:bg-red-800 dark:text-red-200 rounded-md">
                            <strong>{{ __('Attenzione!') }}</strong> {{ session('error') }}
                        </div>
                    @endif
                    @if ($errors->any())
                        <div class="mb-4 p-4 font-medium text-sm text-red-700 bg-red-100 dark:bg-red-800 dark:text-red-200 rounded-md">
                            <strong>{{ __('Attenzione! Sono presenti errori di validazione:') }}</strong>
                            <ul class="list-disc list-inside">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <form method="POST" action="{{ route('admin.impostazioni.update') }}"> {{-- Assicurati che la rotta sia admin.impostazioni.update o admin.settings.update --}}
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Colonna Sinistra --}}
                            <div>
                                <h3 class="text-lg font-semibold mb-4 text-gray-700 dark:text-gray-300">Stato e Fasi Asta</h3>
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
                                <h4 class="text-md font-semibold mb-2 text-indigo-600 dark:text-indigo-400">Configurazione Modalità Asta</h4>

                                <div class="mt-4">
                                    <x-input-label for="modalita_asta" :value="__('Modalità Svolgimento Asta')" />
                                    <select name="modalita_asta" id="modalita_asta" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                        <option value="voce" {{ (old('modalita_asta', $impostazioni->modalita_asta ?? 'voce') == 'voce') ? 'selected' : '' }}>A Voce (Admin assegna manualmente)</option>
                                        <option value="tap" {{ (old('modalita_asta', $impostazioni->modalita_asta ?? 'voce') == 'tap') ? 'selected' : '' }}>A Rilancio "TAP" (con countdown)</option>
                                    </select>
                                    <x-input-error :messages="$errors->get('modalita_asta')" class="mt-2" />
                                </div>

                                <div class="mt-4">
                                    <x-input-label for="tipo_base_asta" :value="__('Tipo Base d\'Asta')" />
                                    <select name="tipo_base_asta" id="tipo_base_asta" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                        <option value="quotazione_iniziale" {{ (old('tipo_base_asta', $impostazioni->tipo_base_asta ?? 'quotazione_iniziale') == 'quotazione_iniziale') ? 'selected' : '' }}>
                                            {{ __('Usa Quotazione Iniziale del Giocatore') }}
                                        </option>
                                        <option value="credito_singolo" {{ (old('tipo_base_asta', $impostazioni->tipo_base_asta ?? 'quotazione_iniziale') == 'credito_singolo') ? 'selected' : '' }}>
                                            {{ __('Base d\'Asta Fissa a 1 Credito') }}
                                        </option>
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Scegli se la base d'asta per i giocatori deve essere la loro quotazione iniziale o sempre 1 credito.
                                    </p>
                                    <x-input-error :messages="$errors->get('tipo_base_asta')" class="mt-2" />
                                </div>

                                <div class="mt-4">
                                    <x-input-label for="durata_countdown_secondi" :value="__('Durata Countdown Asta TAP (secondi)')" />
                                    <x-text-input id="durata_countdown_secondi" class="block mt-1 w-full" type="number" name="durata_countdown_secondi" :value="old('durata_countdown_secondi', $impostazioni->durata_countdown_secondi ?? 60)" min="10" max="300" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Applicabile solo se modalità "A Rilancio TAP". Min 10s, Max 300s.</p>
                                    <x-input-error :messages="$errors->get('durata_countdown_secondi')" class="mt-2" />
                                </div>

                                <div class="mt-4" x-data="{ enabled: {{ old('asta_tap_approvazione_admin', $impostazioni->asta_tap_approvazione_admin ?? true) ? 'true' : 'false' }} }">
                                    <x-input-label for="asta_tap_approvazione_admin_toggle" :value="__('Approvazione Admin per Asta TAP (dopo chiamata utente)')" />
                                    <div class="flex items-center mt-1">
                                        <span class="text-sm text-gray-500 dark:text-gray-400 mr-3" x-text="enabled ? 'Sì, richiesta approvazione' : 'No, avvio automatico'"></span>
                                        <button type="button" class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" :class="enabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'" @click="enabled = !enabled" role="switch" :aria-checked="enabled.toString()">
                                            <span class="sr-only">Usa toggle</span>
                                            <span aria-hidden="true" class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200" :class="enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                                        </button>
                                    </div>
                                    <input type="hidden" name="asta_tap_approvazione_admin" :value="enabled ? '1' : '0'">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se "Sì", l'admin deve approvare la chiamata prima che l'asta TAP inizi. Se "No", l'asta TAP parte automaticamente.</p>
                                    <x-input-error :messages="$errors->get('asta_tap_approvazione_admin')" class="mt-2" />
                                </div>
                            </div>

                            {{-- Colonna Destra --}}
                            <div>
                                <h3 class="text-lg font-semibold mb-4 text-gray-700 dark:text-gray-300">Regole Rosa e Crediti</h3>
                                <div class="mt-4">
                                    <x-input-label for="crediti_iniziali_lega" :value="__('Crediti Iniziali per Squadra')" />
                                    <x-text-input id="crediti_iniziali_lega" class="block mt-1 w-full" type="number" name="crediti_iniziali_lega" :value="old('crediti_iniziali_lega', $impostazioni->crediti_iniziali_lega ?? 500)" required min="1" />
                                    <x-input-error :messages="$errors->get('crediti_iniziali_lega')" class="mt-2" />
                                </div>

                                <h5 class="mt-6 mb-2 text-sm font-semibold text-gray-600 dark:text-gray-400">Limiti Giocatori per Ruolo:</h5>
                                <div class="grid grid-cols-2 gap-4">
                                    <div>
                                        <x-input-label for="num_portieri" :value="__('Portieri (P)')" />
                                        <x-text-input id="num_portieri" class="block mt-1 w-full" type="number" name="num_portieri" :value="old('num_portieri', $impostazioni->num_portieri ?? 3)" required min="0"/>
                                        <x-input-error :messages="$errors->get('num_portieri')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="num_difensori" :value="__('Difensori (D)')" />
                                        <x-text-input id="num_difensori" class="block mt-1 w-full" type="number" name="num_difensori" :value="old('num_difensori', $impostazioni->num_difensori ?? 8)" required min="0"/>
                                        <x-input-error :messages="$errors->get('num_difensori')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="num_centrocampisti" :value="__('Centrocampisti (C)')" />
                                        <x-text-input id="num_centrocampisti" class="block mt-1 w-full" type="number" name="num_centrocampisti" :value="old('num_centrocampisti', $impostazioni->num_centrocampisti ?? 8)" required min="0"/>
                                        <x-input-error :messages="$errors->get('num_centrocampisti')" class="mt-2" />
                                    </div>
                                    <div>
                                        <x-input-label for="num_attaccanti" :value="__('Attaccanti (A)')" />
                                        <x-text-input id="num_attaccanti" class="block mt-1 w-full" type="number" name="num_attaccanti" :value="old('num_attaccanti', $impostazioni->num_attaccanti ?? 6)" required min="0"/>
                                        <x-input-error :messages="$errors->get('num_attaccanti')" class="mt-2" />
                                    </div>
                                </div>

                                {{-- NUOVI CAMPI PER MERCATO STAGIONALE/RIPARAZIONE --}}
                                <hr class="my-6 border-gray-300 dark:border-gray-700">
                                <h4 class="text-md font-semibold mb-2 text-indigo-600 dark:text-indigo-400">Regole Mercato Stagionale/Riparazione</h4>

                                <div class="mt-4">
                                    <x-input-label for="max_sostituzioni_stagionali" :value="__('Max Sostituzioni Stagionali per Squadra')" />
                                    <x-text-input id="max_sostituzioni_stagionali" class="block mt-1 w-full" type="number" name="max_sostituzioni_stagionali" :value="old('max_sostituzioni_stagionali', $impostazioni->max_sostituzioni_stagionali ?? 5)" required min="0" max="99" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Numero di sostituzioni 1-a-1 permesse durante la fase 'Svincoli Stagionali'.</p>
                                    <x-input-error :messages="$errors->get('max_sostituzioni_stagionali')" class="mt-2" />
                                </div>

                                <div class="mt-4">
                                    <x-input-label for="percentuale_crediti_svincolo_riparazione" :value="__('Percentuale Crediti Restituiti (Svincoli Asta Riparazione %)')" />
                                    <x-text-input id="percentuale_crediti_svincolo_riparazione" class="block mt-1 w-full" type="number" name="percentuale_crediti_svincolo_riparazione" :value="old('percentuale_crediti_svincolo_riparazione', $impostazioni->percentuale_crediti_svincolo_riparazione ?? 50)" required min="0" max="100" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Percentuale del prezzo d'acquisto originale restituita alla squadra quando svincola un giocatore prima dell'Asta di Riparazione.</p>
                                    <x-input-error :messages="$errors->get('percentuale_crediti_svincolo_riparazione')" class="mt-2" />
                                </div>
                                {{-- FINE NUOVI CAMPI --}}


                                <hr class="my-6 border-gray-300 dark:border-gray-700">
                                <h4 class="text-md font-semibold mb-2 text-indigo-600 dark:text-indigo-400">Ordine di Chiamata</h4>

                                <div class="mt-4" x-data="{ enabled: {{ old('usa_ordine_chiamata', $impostazioni->usa_ordine_chiamata ?? false) ? 'true' : 'false' }} }">
                                   {{-- ... (toggle usa_ordine_chiamata come prima) ... --}}
                                    <x-input-label for="usa_ordine_chiamata_toggle" :value="__('Abilita Ordine di Chiamata Fisso')" />
                                    <div class="flex items-center mt-1">
                                        <span class="text-sm text-gray-500 dark:text-gray-400 mr-3" x-text="enabled ? 'Sì, attivo' : 'No, disattivato (chiamata libera)'"></span>
                                        <button type="button" class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" :class="enabled ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'" @click="enabled = !enabled" role="switch" :aria-checked="enabled.toString()">
                                            <span class="sr-only">Usa toggle</span>
                                            <span aria-hidden="true" class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200" :class="enabled ? 'translate-x-5' : 'translate-x-0'"></span>
                                        </button>
                                    </div>
                                    <input type="hidden" name="usa_ordine_chiamata" :value="enabled ? '1' : '0'">
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se attivo, le squadre chiameranno i giocatori per l'asta TAP secondo un ordine prestabilito.</p>
                                    <x-input-error :messages="$errors->get('usa_ordine_chiamata')" class="mt-2" />
                                </div>

                                <div class="mt-6">
                                    <h5 class="text-md font-semibold mb-3 text-gray-700 dark:text-gray-300">Definizione Ordine Chiamata Squadre</h5>
                                    {{-- ... (lista squadre con input ordine come prima) ... --}}
                                    @if(isset($squadrePerOrdinamento) && $squadrePerOrdinamento->count() > 0)
                                        <div class="space-y-3">
                                            @foreach($squadrePerOrdinamento as $squadra)
                                                <div class="flex items-center justify-between p-2 border rounded-md dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                                                    <label for="ordine_squadra_{{ $squadra->id }}" class="text-sm text-gray-700 dark:text-gray-300 w-3/4">{{ $squadra->name }}</label>
                                                    <input type="number"
                                                           name="ordine_squadre[{{ $squadra->id }}]"
                                                           id="ordine_squadra_{{ $squadra->id }}"
                                                           value="{{ old('ordine_squadre.'.$squadra->id, $squadra->ordine_chiamata) }}"
                                                           class="w-24 text-sm border-gray-300 dark:border-gray-600 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm"
                                                           min="1"
                                                           placeholder="N/A">
                                                </div>
                                                @error('ordine_squadre.'.$squadra->id)
                                                    <p class="text-xs text-red-600 dark:text-red-400 mt-1">{{ $message }}</p>
                                                @enderror
                                            @endforeach
                                        </div>
                                        @error('ordine_squadre')
                                            <p class="text-xs text-red-600 dark:text-red-400 mt-2">{{ $message }}</p>
                                        @enderror
                                    @else
                                        <p class="text-sm text-gray-500 dark:text-gray-400">Nessuna squadra trovata per definire l'ordine.</p>
                                    @endif
                                </div>

                                <div class="mt-4">
                                    <x-input-label for="prossimo_turno_chiamata_user_id" :value="__('Prossima Squadra a Chiamare (se ordine attivo)')" />
                                    {{-- ... (dropdown prossimo_turno_chiamata_user_id come prima, usando $utentiPerSelezioneProssimo) ... --}}
                                    <select name="prossimo_turno_chiamata_user_id" id="prossimo_turno_chiamata_user_id" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                        <option value="">-- {{ __('Nessuno / Ordine non attivo o da iniziare') }} --</option>
                                        @if(isset($utentiPerSelezioneProssimo))
                                            @foreach ($utentiPerSelezioneProssimo as $utente)
                                                <option value="{{ $utente->id }}" {{ (old('prossimo_turno_chiamata_user_id', $impostazioni->prossimo_turno_chiamata_user_id ?? '') == $utente->id) ? 'selected' : '' }}>
                                                    {{ $utente->name }}
                                                </option>
                                            @endforeach
                                        @endif
                                    </select>
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se l'ordine di chiamata è attivo, indica chi è il prossimo. Viene aggiornato automaticamente durante l'asta o può essere forzato qui.</p>
                                    <x-input-error :messages="$errors->get('prossimo_turno_chiamata_user_id')" class="mt-2" />
                                </div>
                            </div>
                        </div>

                        <div class="block mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                            <h4 class="text-md font-semibold mb-2 text-red-600 dark:text-red-400">{{ __('Azioni per Nuova Asta') }}</h4>
                            {{-- ... (checkbox reset_asta_completo come prima) ... --}}
                            <label for="reset_asta_completo" class="inline-flex items-center">
                                <input id="reset_asta_completo" type="checkbox" class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600" name="reset_asta_completo" value="1">
                                <span class="ms-2 text-sm text-gray-700 dark:text-gray-300 font-medium">{{ __('PREPARA NUOVA SESSIONE D\'ASTA COMPLETA') }}</span>
                            </label>
                            <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                <strong>Attenzione:</strong> Selezionando questa opzione e salvando, verranno resettati i crediti di tutte le squadre al valore "Crediti Iniziali per Squadra" definito, TUTTE le rose attuali verranno CANCELLATE (per il tag lista attivo), il conteggio delle sostituzioni stagionali usate verrà azzerato, e la fase dell'asta verrà impostata a Portieri (P) o Pre-Asta. Usare solo per iniziare una nuova stagione/asta da zero. L'ordine di chiamata, se attivo, ripartirà dal primo.
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