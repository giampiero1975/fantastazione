{{-- File: resources/views/admin/impostazioni/index.blade.php --}}

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

                    <form method="POST" action="{{ route('admin.impostazioni.update') }}"
                          x-data="{
                              faseAsta: '{{ old('fase_asta_corrente', $impostazioni->fase_asta_corrente) }}',
                              resetCompleto: {{ old('reset_asta_completo', '0') === '1' ? 'true' : 'false' }},
                              modalitaAsta: '{{ old('modalita_asta', $impostazioni->modalita_asta ?? 'voce') }}',
                              usaOrdineChiamata: {{ old('usa_ordine_chiamata', $impostazioni->usa_ordine_chiamata ?? false) ? 'true' : 'false' }},
                              tagListaAttivaValue: '{{ old('tag_lista_attiva', $impostazioni->tag_lista_attiva ?? '') }}', // Per tracciare il valore del select
                              isPreAsta() { return this.faseAsta === 'PRE_ASTA'; }
                          }">
                        @csrf

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            {{-- Colonna Sinistra --}}
                            <div>
                                <fieldset class="border border-gray-300 dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-md font-semibold px-2 text-gray-700 dark:text-gray-300">Stato e Fasi Asta</legend>
                                    <div class="mb-4">
                                        <x-input-label for="fase_asta_corrente" :value="__('Fase Asta Corrente')" />
                                        <select name="fase_asta_corrente" id="fase_asta_corrente" x-model="faseAsta" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                            @foreach ($fasiPossibili as $key => $value)
                                                <option value="{{ $key }}">{{ $value }}</option>
                                            @endforeach
                                        </select>
                                        <x-input-error :messages="$errors->get('fase_asta_corrente')" class="mt-2" />
                                    </div>

                                    <div class="mb-4">
                                        <x-input-label for="tag_lista_attiva">
                                            <span>{{__('Tag Lista Calciatori Attiva per l\'Asta')}}</span>
                                            <span x-show="resetCompleto && !tagListaAttivaValue" class="text-red-500 text-xs ml-1">({{__('Obbligatorio con Reset Completo')}})</span>
                                        </x-input-label>
                                        <select name="tag_lista_attiva" id="tag_lista_attiva" x-model="tagListaAttivaValue" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
                                            <option value="">-- {{ __('Nessuna / Usa ultima importata') }} --</option>
                                            @if(isset($tagsCalciatoriDisponibili))
                                                @foreach ($tagsCalciatoriDisponibili as $tag)
                                                    <option value="{{ $tag }}">{{ $tag }}</option>
                                                @endforeach
                                            @endif
                                        </select>
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Seleziona la lista giocatori da usare per l'asta corrente. Obbligatorio se la fase è P, D, C, A o se si effettua un reset completo.</p>
                                        <x-input-error :messages="$errors->get('tag_lista_attiva')" class="mt-2" />
                                    </div>
                                </fieldset>

                                <fieldset class="border border-gray-300 dark:border-gray-700 p-4 rounded-md mt-6">
                                    <legend class="text-md font-semibold px-2 text-indigo-600 dark:text-indigo-400">Configurazione Modalità Asta</legend>
                                    <div class="mt-4">
                                        <x-input-label for="modalita_asta" :value="__('Modalità Svolgimento Asta')" />
                                        <select name="modalita_asta" id="modalita_asta" x-model="modalitaAsta" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm">
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

                                    <div class="mt-4" x-show="modalitaAsta === 'tap'">
                                        <x-input-label for="durata_countdown_secondi" :value="__('Durata Countdown Asta TAP (secondi)')" />
                                        <x-text-input id="durata_countdown_secondi" class="block mt-1 w-full" type="number" name="durata_countdown_secondi" :value="old('durata_countdown_secondi', $impostazioni->durata_countdown_secondi ?? 60)" min="10" max="300" />
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Applicabile solo se modalità "A Rilancio TAP". Min 10s, Max 300s.</p>
                                        <x-input-error :messages="$errors->get('durata_countdown_secondi')" class="mt-2" />
                                    </div>

                                    <div class="mt-4" x-show="modalitaAsta === 'tap'" x-data="{ enabledTapAdmin: {{ old('asta_tap_approvazione_admin', $impostazioni->asta_tap_approvazione_admin ?? true) ? 'true' : 'false' }} }">
                                        <x-input-label for="asta_tap_approvazione_admin_toggle" :value="__('Approvazione Admin per Asta TAP (dopo chiamata utente)')" />
                                        <div class="flex items-center mt-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 mr-3" x-text="enabledTapAdmin ? 'Sì, richiesta approvazione' : 'No, avvio automatico'"></span>
                                            <button type="button" class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" :class="enabledTapAdmin ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'" @click="enabledTapAdmin = !enabledTapAdmin" role="switch" :aria-checked="enabledTapAdmin.toString()">
                                                <span class="sr-only">Usa toggle</span>
                                                <span aria-hidden="true" class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200" :class="enabledTapAdmin ? 'translate-x-5' : 'translate-x-0'"></span>
                                            </button>
                                        </div>
                                        <input type="hidden" name="asta_tap_approvazione_admin" :value="enabledTapAdmin ? '1' : '0'">
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se "Sì", l'admin deve approvare la chiamata prima che l'asta TAP inizi. Se "No", l'asta TAP parte automaticamente.</p>
                                        <x-input-error :messages="$errors->get('asta_tap_approvazione_admin')" class="mt-2" />
                                    </div>
                                </fieldset>
                            </div>

                            {{-- Colonna Destra --}}
                            <div>
                                <fieldset class="border border-gray-300 dark:border-gray-700 p-4 rounded-md">
                                    <legend class="text-md font-semibold px-2 text-gray-700 dark:text-gray-300">Regole Rosa e Crediti</legend>
                                    <div class="mt-4">
                                        <x-input-label for="crediti_iniziali_lega" :value="__('Crediti Iniziali per Squadra')" />
                                        <x-text-input id="crediti_iniziali_lega" class="block mt-1 w-full" type="number"
                                        name="crediti_iniziali_lega"
                                        :value="old('crediti_iniziali_lega', $impostazioni->crediti_iniziali_lega ?? 500)"
                                        required min="1"
                                        ::disabled="!isPreAsta() && !resetCompleto" />
                                        <p x-show="!isPreAsta() && !resetCompleto" class="mt-1 text-xs text-orange-500 dark:text-orange-400">Modificabile solo in PRE_ASTA o con Reset Completo.</p>
                                        <x-input-error :messages="$errors->get('crediti_iniziali_lega')" class="mt-2" />
                                    </div>

                                    <h5 class="mt-6 mb-2 text-sm font-semibold text-gray-600 dark:text-gray-400">Limiti Giocatori per Ruolo:</h5>
                                    <div class="grid grid-cols-2 gap-4">
                                        <div>
                                            <x-input-label for="num_portieri" :value="__('Portieri (P)')" />
                                            <x-text-input id="num_portieri" class="block mt-1 w-full" type="number" name="num_portieri" :value="old('num_portieri', $impostazioni->num_portieri ?? 3)" required min="0" ::disabled="!isPreAsta() && !resetCompleto" />
                                            <x-input-error :messages="$errors->get('num_portieri')" class="mt-2" />
                                        </div>
                                        <div>
                                            <x-input-label for="num_difensori" :value="__('Difensori (D)')" />
                                            <x-text-input id="num_difensori" class="block mt-1 w-full" type="number" name="num_difensori" :value="old('num_difensori', $impostazioni->num_difensori ?? 8)" required min="0" ::disabled="!isPreAsta() && !resetCompleto"/>
                                            <x-input-error :messages="$errors->get('num_difensori')" class="mt-2" />
                                        </div>
                                        <div>
                                            <x-input-label for="num_centrocampisti" :value="__('Centrocampisti (C)')" />
                                            <x-text-input id="num_centrocampisti" class="block mt-1 w-full" type="number" name="num_centrocampisti" :value="old('num_centrocampisti', $impostazioni->num_centrocampisti ?? 8)" required min="0" ::disabled="!isPreAsta() && !resetCompleto"/>
                                            <x-input-error :messages="$errors->get('num_centrocampisti')" class="mt-2" />
                                        </div>
                                        <div>
                                            <x-input-label for="num_attaccanti" :value="__('Attaccanti (A)')" />
                                            <x-text-input id="num_attaccanti" class="block mt-1 w-full" type="number" name="num_attaccanti" :value="old('num_attaccanti', $impostazioni->num_attaccanti ?? 6)" required min="0" ::disabled="!isPreAsta() && !resetCompleto"/>
                                            <x-input-error :messages="$errors->get('num_attaccanti')" class="mt-2" />
                                        </div>
                                    </div>
                                    <p x-show="!isPreAsta() && !resetCompleto" class="mt-1 text-xs text-orange-500 dark:text-orange-400">I limiti per ruolo sono modificabili solo in PRE_ASTA o con Reset Completo.</p>
                                </fieldset>

                                <fieldset class="border border-gray-300 dark:border-gray-700 p-4 rounded-md mt-6">
                                    <legend class="text-md font-semibold px-2 text-indigo-600 dark:text-indigo-400">Regole Mercato Stagionale/Riparazione</legend>
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
                                </fieldset>


                                <fieldset class="border border-gray-300 dark:border-gray-700 p-4 rounded-md mt-6">
                                    <legend class="text-md font-semibold px-2 text-indigo-600 dark:text-indigo-400">Ordine di Chiamata</legend>
                                    <div class="mt-4" x-data="{ enabledOrdineToggle: {{ old('usa_ordine_chiamata', $impostazioni->usa_ordine_chiamata ?? false) ? 'true' : 'false' }} }" x-init="usaOrdineChiamata = enabledOrdineToggle">
                                        <x-input-label for="usa_ordine_chiamata_toggle" :value="__('Abilita Ordine di Chiamata Fisso')" />
                                        <div class="flex items-center mt-1">
                                            <span class="text-sm text-gray-500 dark:text-gray-400 mr-3" x-text="enabledOrdineToggle ? 'Sì, attivo' : 'No, disattivato (chiamata libera)'"></span>
                                            <button type="button" class="relative inline-flex flex-shrink-0 h-6 w-11 border-2 border-transparent rounded-full cursor-pointer transition-colors ease-in-out duration-200 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800" :class="enabledOrdineToggle ? 'bg-indigo-600' : 'bg-gray-200 dark:bg-gray-700'" @click="enabledOrdineToggle = !enabledOrdineToggle; usaOrdineChiamata = enabledOrdineToggle" role="switch" :aria-checked="enabledOrdineToggle.toString()">
                                                <span class="sr-only">Usa toggle</span>
                                                <span aria-hidden="true" class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow transform ring-0 transition ease-in-out duration-200" :class="enabledOrdineToggle ? 'translate-x-5' : 'translate-x-0'"></span>
                                            </button>
                                        </div>
                                        <input type="hidden" name="usa_ordine_chiamata" :value="enabledOrdineToggle ? '1' : '0'">
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Se attivo, le squadre chiameranno i giocatori per l'asta TAP secondo un ordine prestabilito.</p>
                                        <x-input-error :messages="$errors->get('usa_ordine_chiamata')" class="mt-2" />
                                    </div>

                                    <div class="mt-6" x-show="usaOrdineChiamata">
                                        <h5 class="text-md font-semibold mb-3 text-gray-700 dark:text-gray-300">Definizione Ordine Chiamata Squadre</h5>
                                        @if(isset($squadrePerOrdinamento) && $squadrePerOrdinamento->count() > 0)
                                            <div class="space-y-3">
                                                @foreach($squadrePerOrdinamento as $squadra)
                                                    <div class="flex items-center justify-between p-2 border rounded-md dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
                                                        <label for="ordine_squadra_{{ $squadra->id }}" class="text-sm text-gray-700 dark:text-gray-300 w-3/4">
                                                            {{ $squadra->name }}
                                                            @if($squadra->is_admin) <span class="text-xs text-red-500">(Admin)</span> @endif
                                                        </label>
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
                                            @error('ordine_squadre') {{-- Errore generale per duplicati --}}
                                                <p class="text-xs text-red-600 dark:text-red-400 mt-2">{{ $message }}</p>
                                            @enderror
                                        @else
                                            <p class="text-sm text-gray-500 dark:text-gray-400">Nessuna squadra trovata per definire l'ordine.</p>
                                        @endif
                                    </div>

                                    <div class="mt-4" x-show="usaOrdineChiamata">
                                        <x-input-label for="prossimo_turno_chiamata_user_id" :value="__('Prossima Squadra a Chiamare (se ordine attivo)')" />
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
                                </fieldset>
                            </div>
                        </div>

                        <div class="block mt-6 pt-6 border-t border-gray-200 dark:border-gray-700">
                             <fieldset class="border border-red-400 dark:border-red-600 p-4 rounded-md">
                                <legend class="text-md font-semibold px-2 text-red-600 dark:text-red-400">{{ __('Azioni Pericolose') }}</legend>
                                <div class="mt-2">
                                    <label for="reset_asta_completo" class="inline-flex items-center">
                                        <input id="reset_asta_completo" type="checkbox"
                                            x-model="resetCompleto"
                                            class="rounded border-gray-300 dark:border-gray-600 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:ring-indigo-600"
                                            name="reset_asta_completo" value="1"
                                            x-on:change="if(resetCompleto && !tagListaAttivaValue){ alert('ATTENZIONE: Per resettare l\'asta, devi prima selezionare un \"Tag Lista Calciatori Attiva\"!'); resetCompleto = false; $event.target.checked = false; return; } if(resetCompleto && !confirm('CONFERMA RESET ASTA:\n\nStai per resettare completamente l\'asta per il TAG LISTA: \'' + (tagListaAttivaValue || 'NON SELEZIONATO') + '\'.\n\nQuesta azione implica:\n- I crediti di TUTTE le squadre verranno riportati al valore \"Crediti Iniziali per Squadra\".\n- TUTTE le rose verranno CANCELLATE per il tag lista selezionato.\n- Il conteggio delle sostituzioni stagionali usate verrà AZZERATO.\n- Le chiamate TAP in corso o in attesa (per il tag) verranno annullate.\n- La fase dell\'asta verrà impostata a Portieri (P) o Pre-Asta.\n\nL\'operazione è IRREVERSIBILE per i dati del tag selezionato.\n\nSei assolutamente sicuro di voler procedere?')) { resetCompleto = false; $event.target.checked = false; }">
                                        <span class="ms-2 text-sm text-gray-700 dark:text-gray-300 font-medium">{{ __('PREPARA NUOVA SESSIONE D\'ASTA COMPLETA') }}</span>
                                    </label>
                                    <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                        <strong>Attenzione:</strong> Selezionando questa opzione e salvando, verranno resettati i crediti di tutte le squadre, le rose (per il tag lista attivo), le sostituzioni usate, e la fase dell'asta. Usare solo per iniziare una nuova stagione/asta da zero. **Assicurati di aver selezionato il corretto "Tag Lista Calciatori Attiva" prima di procedere.**
                                    </p>
                                     <div x-show="resetCompleto && !tagListaAttivaValue" class="mt-2 p-2 text-sm text-red-700 bg-red-100 dark:bg-red-700 dark:text-red-100 rounded-md">
                                        Selezionare un "Tag Lista Calciatori Attiva" è obbligatorio per poter resettare l'asta.
                                    </div>
                                    <x-input-error :messages="$errors->get('reset_asta_completo')" class="mt-2" />
                                    <x-input-error :messages="$errors->get('tag_lista_attiva')" class="mt-2" /> {{-- Mostra errore tag_lista_attiva se fallisce validazione server con reset --}}

                                </div>
                            </fieldset>
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