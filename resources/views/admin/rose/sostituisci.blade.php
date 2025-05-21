<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Sostituzione Giocatore (Admin)') }}
            @if($tagAttivo)
                <span class="text-sm text-gray-500 dark:text-gray-400">(Lista: {{ $tagAttivo }})</span>
            @endif
            <span class="text-sm text-indigo-500 dark:text-indigo-400 ml-2">[Fase: {{ $faseAstaCorrente }}]</span>
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-4xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100"
                     x-data="{
                        selectedSquadraId: '{{ old('user_id') }}',
                        giocatoriSquadra: [], // Array degli oggetti acquisto per la squadra selezionata
                        calciatoriIdsInRosaSquadraSelezionata: [], // Array degli ID dei calciatori in rosa alla squadra selezionata
                        selectedGiocatoreOutId: '{{ old('giocatore_out_id') }}', // ID dell'acquisto del giocatore OUT
                        calciatoreIdOut: null, // ID del Calciatore (modello) che esce
                        prezzoGiocatoreOut: {{ old('prezzo_giocatore_out', 0) }},
                        calciatoriDisponibiliOriginal: {{ Js::from($calciatoriDisponibili) }},
                        calciatoriDisponibiliFiltrati: [],
                        selectedCalciatoreInId: '{{ old('calciatore_in_id_hidden_alpine') }}', // ID del Calciatore (modello) che entra
                        prezzoNuovoAcquisto: '{{ old('prezzo_nuovo_acquisto', ($modalitaSostituzione === 'CONCLUSA' || $modalitaSostituzione === 'STAGIONALE') ? (old('prezzo_giocatore_out',0)) : 1) }}',
                        ruoloGiocatoreOut: null,
                        creditiSquadraAttuali: {{ old('crediti_squadra_attuali', 0) }},
                        creditiSquadraDopoSostituzione: 0,
                        nomeSquadraSelezionata: '{{ old('user_id') ? (App\Models\User::find(old('user_id'))->name ?? '') : '' }}',
                        tomSelectCalciatoreIn: null,
                        isLoadingGiocatoriSquadra: false,
                        jsSostituzioneAbilitata: {{ Js::from($sostituzioneAbilitata) }},
                        jsPrezzoInBloccato: {{ Js::from($prezzoInBloccato) }},
                        jsModalitaSostituzione: '{{ $modalitaSostituzione ?? '' }}',

                        fetchGiocatoriSquadra() {
                            if (!this.selectedSquadraId) {
                                this.giocatoriSquadra = [];
                                this.calciatoriIdsInRosaSquadraSelezionata = []; // Resetta
                                this.selectedGiocatoreOutId = '';
                                this.calciatoreIdOut = null; // Resetta
                                this.prezzoGiocatoreOut = 0;
                                this.ruoloGiocatoreOut = null;
                                this.creditiSquadraAttuali = 0;
                                this.nomeSquadraSelezionata = '';
                                this.filterCalciatoriDisponibili();
                                this.updateCreditiDopoSostituzione();
                                return;
                            }
                            this.isLoadingGiocatoriSquadra = true;
                            const userId = this.selectedSquadraId;
                            const tagAttivo = '{{ urlencode($tagAttivo ?? '') }}';
                            let apiUrl = `{{ route('admin.api.squadra.rosa', ['user' => ':userId']) }}`.replace(':userId', userId);
                            if (tagAttivo) { apiUrl += `?tag_attivo=${tagAttivo}`; }

                            fetch(apiUrl)
                                .then(response => { if (!response.ok) { throw new Error('API Error: ' + response.status); } return response.json(); })
                                .then(data => {
                                    this.giocatoriSquadra = data.rosa || [];
                                    this.calciatoriIdsInRosaSquadraSelezionata = (data.rosa || []).map(acquisto => acquisto.calciatore ? acquisto.calciatore.id : null).filter(id => id !== null);
                                    this.creditiSquadraAttuali = parseInt(data.crediti_rimanenti) || 0;
                                    this.nomeSquadraSelezionata = data.nome_squadra || '';
                                    this.selectedGiocatoreOutId = ''; // Resetta selezione giocatore OUT
                                    this.calciatoreIdOut = null; // Resetta ID calciatore OUT
                                    this.prezzoGiocatoreOut = 0;
                                    this.ruoloGiocatoreOut = null;
                                    if (this.jsModalitaSostituzione === 'CONCLUSA' || this.jsModalitaSostituzione === 'STAGIONALE') {
                                        this.prezzoNuovoAcquisto = 0; // Inizia da 0, verrà aggiornato
                                    } else { this.prezzoNuovoAcquisto = 1; }
                                    this.filterCalciatoriDisponibili();
                                    this.updateCreditiDopoSostituzione();
                                })
                                .catch(error => {
                                    console.error('Fetch Error:', error);
                                    this.nomeSquadraSelezionata = 'Errore Caricamento Rosa';
                                    this.giocatoriSquadra = [];
                                    this.calciatoriIdsInRosaSquadraSelezionata = [];
                                })
                                .finally(() => { this.isLoadingGiocatoriSquadra = false; });
                        },
                        updateGiocatoreOutDetails() {
                            const acquistoOut = this.giocatoriSquadra.find(g => g.id == this.selectedGiocatoreOutId);
                            if (acquistoOut && acquistoOut.calciatore) {
                                this.prezzoGiocatoreOut = parseInt(acquistoOut.prezzo_acquisto) || 0;
                                this.ruoloGiocatoreOut = acquistoOut.calciatore.ruolo;
                                this.calciatoreIdOut = acquistoOut.calciatore.id; // ID del Calciatore che esce
                                if (this.jsModalitaSostituzione === 'CONCLUSA' || this.jsModalitaSostituzione === 'STAGIONALE') {
                                    this.prezzoNuovoAcquisto = this.prezzoGiocatoreOut; // Prezzo IN eredita da OUT
                                } else {
                                     this.prezzoNuovoAcquisto = (this.prezzoNuovoAcquisto === '' || this.prezzoNuovoAcquisto === 0) ? 1 : this.prezzoNuovoAcquisto;
                                }
                            } else {
                                this.prezzoGiocatoreOut = 0;
                                this.ruoloGiocatoreOut = null;
                                this.calciatoreIdOut = null;
                                this.prezzoNuovoAcquisto = (this.jsModalitaSostituzione === 'CONCLUSA' || this.jsModalitaSostituzione === 'STAGIONALE') ? 0 : 1;
                            }
                            this.selectedCalciatoreInId = ''; // Resetta giocatore IN quando OUT cambia
                            if(this.tomSelectCalciatoreIn) this.tomSelectCalciatoreIn.clear();
                            this.filterCalciatoriDisponibili();
                            this.updateCreditiDopoSostituzione();
                        },
                        initTomSelectCalciatoreIn() {
                            if (this.tomSelectCalciatoreIn) { this.tomSelectCalciatoreIn.destroy(); this.tomSelectCalciatoreIn = null; }
                            if (this.$refs.calciatoreInSelect && typeof TomSelect !== 'undefined') {
                                const options = this.calciatoriDisponibiliFiltrati.map(c => ({ id: c.id, nome_completo_display: `${c.nome_completo} (${c.ruolo} - ${c.squadra_serie_a} - Qt.I: ${c.quotazione_iniziale})`}));
                                this.tomSelectCalciatoreIn = new TomSelect(this.$refs.calciatoreInSelect, {
                                    valueField: 'id', labelField: 'nome_completo_display', searchField: ['nome_completo_display'],
                                    options: options, create: false, placeholder: this.ruoloGiocatoreOut ? 'Seleziona Calciatore da Acquistare...' : 'Prima seleziona giocatore da svincolare',
                                    onChange: (value) => { this.selectedCalciatoreInId = value; }
                                });
                                if (this.selectedCalciatoreInId && options.find(c => c.id == this.selectedCalciatoreInId)) {
                                     this.tomSelectCalciatoreIn.setValue(this.selectedCalciatoreInId, true); // true per non triggerare onChange
                                } else if (this.selectedCalciatoreInId) { // Se old value non è più tra le opzioni valide
                                     this.selectedCalciatoreInId = '';
                                     if(this.tomSelectCalciatoreIn) this.tomSelectCalciatoreIn.clear(true); // true per silent
                                }
                            }
                        },
                        filterCalciatoriDisponibili() {
                            if (!this.ruoloGiocatoreOut) {
                                this.calciatoriDisponibiliFiltrati = [];
                            } else {
                                const idCalciatoreCheEsce = this.calciatoreIdOut;
                                const idsCompagniAttuali = this.calciatoriIdsInRosaSquadraSelezionata || [];

                                this.calciatoriDisponibiliFiltrati = this.calciatoriDisponibiliOriginal.filter(c =>
                                    c.ruolo === this.ruoloGiocatoreOut &&
                                    (idCalciatoreCheEsce ? c.id != idCalciatoreCheEsce : true) &&
                                    !idsCompagniAttuali.includes(c.id)
                                );
                            }
                            // È importante che initTomSelectCalciatoreIn sia chiamato DOPO che calciatoriDisponibiliFiltrati è aggiornato
                            this.$nextTick(() => { this.initTomSelectCalciatoreIn(); });
                        },
                        updateCreditiDopoSostituzione() {
                            let pOut = parseInt(this.prezzoGiocatoreOut) || 0;
                            let pIn = parseInt(this.prezzoNuovoAcquisto) || 0;
                            let delta = 0;
                            if (this.jsModalitaSostituzione === 'CONCLUSA' || this.jsModalitaSostituzione === 'STAGIONALE') {
                                delta = 0; // Saldo invariato
                            } else {
                                delta = pOut - pIn;
                            }
                            this.creditiSquadraDopoSostituzione = (parseInt(this.creditiSquadraAttuali) || 0) + delta;
                        },
                        init() {
                            this.calciatoreIdOut = null;
                            this.calciatoriIdsInRosaSquadraSelezionata = [];

                            let attempts = 0; const max = 20; const interval = 100;
                            const tryInit = () => {
                                if (this.jsSostituzioneAbilitata && this.$refs.calciatoreInSelect && typeof TomSelect !== 'undefined') {
                                    if(this.selectedSquadraId) {
                                        // Se c'è una squadra selezionata da old(), fetcha i suoi dati
                                        this.fetchGiocatoriSquadra();
                                    } else {
                                        // Altrimenti, inizializza filtri e tomSelect con liste vuote
                                        this.filterCalciatoriDisponibili();
                                    }
                                    this.updateCreditiDopoSostituzione(); // Calcola crediti anche all'inizio
                                } else if (this.jsSostituzioneAbilitata && attempts < max) {
                                    attempts++; setTimeout(tryInit, interval);
                                } else if (this.jsSostituzioneAbilitata) { console.error('Alpine: TomSelect o $refs.calciatoreInSelect non disponibili dopo vari tentativi.'); }
                            };
                            if (this.jsSostituzioneAbilitata) tryInit();

                            this.$watch('selectedSquadraId', (newValue, oldValue) => {
                                if (newValue !== oldValue) { // Solo se l'ID squadra cambia effettivamente
                                    this.fetchGiocatoriSquadra();
                                }
                            });
                            this.$watch('selectedGiocatoreOutId', (newValue, oldValue) => {
                                if (newValue !== oldValue) {
                                   this.updateGiocatoreOutDetails();
                                }
                            });
                            this.$watch('prezzoNuovoAcquisto', () => this.updateCreditiDopoSostituzione());
                        }
                    }">

                    @if (session('success')) <div class="mb-4 p-4 font-medium text-sm text-green-700 bg-green-100 dark:bg-green-800 dark:text-green-200 rounded-md">{{ session('success') }}</div> @endif
                    @if (session('error')) <div class="mb-4 p-4 font-medium text-sm text-red-700 bg-red-100 dark:bg-red-800 dark:text-red-200 rounded-md">{{ session('error') }}</div> @endif
                    @if ($errors->any()) <div class="mb-4 p-4 font-medium text-sm text-red-700 bg-red-100 dark:bg-red-800 dark:text-red-200 rounded-md"><strong>{{ __('Attenzione!') }}</strong><ul class="list-disc list-inside">@foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div> @endif

                    @if (!$sostituzioneAbilitata && $messaggioBlocco)
                        <div class="mb-4 p-4 font-medium text-sm text-yellow-700 bg-yellow-100 dark:bg-yellow-800 dark:text-yellow-200 rounded-md">
                            <strong>Attenzione:</strong> {{ $messaggioBlocco }}
                        </div>
                    @endif

                    @if ($sostituzioneAbilitata)
                        <form method="POST" action="{{ route('admin.rose.sostituisci.handle') }}">
                            @csrf
                            <div class="mb-4">
                                <x-input-label for="user_id_select" :value="__('Seleziona Squadra')" />
                                <select name="user_id" id="user_id_select" x-model="selectedSquadraId" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required>
                                    <option value="">-- Seleziona Squadra --</option>
                                    @foreach ($squadre as $squadra)
                                        <option value="{{ $squadra->id }}" @if(old('user_id') == $squadra->id) selected @endif>{{ $squadra->name }}</option>
                                    @endforeach
                                </select>
                                <x-input-error :messages="$errors->get('user_id')" class="mt-2" />
                                <div x-show="isLoadingGiocatoriSquadra" class="mt-2 text-sm text-gray-500 dark:text-gray-400">Caricamento rosa...</div>
                                <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="selectedSquadraId && nomeSquadraSelezionata && !isLoadingGiocatoriSquadra">
                                    Squadra: <strong x-text="nomeSquadraSelezionata"></strong> - Crediti: <strong x-text="creditiSquadraAttuali"></strong>
                                </p>
                            </div>

                            <div x-show="selectedSquadraId && !isLoadingGiocatoriSquadra">
                                <hr class="my-6 border-gray-300 dark:border-gray-700">
                                <div class="mb-4">
                                    <x-input-label for="giocatore_out_id_select" :value="__('Giocatore da Svincolare (OUT)')" />
                                    <select name="giocatore_out_id" id="giocatore_out_id_select" x-model="selectedGiocatoreOutId" class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm" required :disabled="!giocatoriSquadra || giocatoriSquadra.length === 0">
                                        <option value="">-- Seleziona Giocatore da Svincolare --</option>
                                        <template x-for="acquisto in giocatoriSquadra" :key="acquisto.id">
                                            <option :value="acquisto.id" x-text="acquisto.calciatore ? `${acquisto.calciatore.nome_completo} (${acquisto.calciatore.ruolo} - ${acquisto.calciatore.squadra_serie_a}) - Acq: ${acquisto.prezzo_acquisto}cr` : 'Dati giocatore non disponibili'"></option>
                                        </template>
                                    </select>
                                    <x-input-error :messages="$errors->get('giocatore_out_id')" class="mt-2" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400" x-show="selectedGiocatoreOutId">
                                        Valore Svincolo: <strong x-text="prezzoGiocatoreOut"></strong> cr. Ruolo Sostituto: <strong x-text="ruoloGiocatoreOut || 'N/D'"></strong>
                                    </p>
                                </div>

                                <div class="mb-4" x-show="selectedGiocatoreOutId && ruoloGiocatoreOut">
                                    <x-input-label for="tomselect-calciatore-in-alpine">
                                        {{ __('Giocatore da Acquistare (IN) - Ruolo:') }} <span class="font-semibold" x-text="ruoloGiocatoreOut"></span>
                                    </x-input-label>
                                    <select id="tomselect-calciatore-in-alpine" x-ref="calciatoreInSelect" class="mt-1 block w-full" :disabled="!ruoloGiocatoreOut"></select>
                                    <input type="hidden" name="calciatore_in_id_hidden_alpine" x-model="selectedCalciatoreInId">
                                    <x-input-error :messages="$errors->get('calciatore_in_id_hidden_alpine')" class="mt-2" />
                                </div>

                                <div class="mb-6" x-show="selectedGiocatoreOutId">
                                    <x-input-label for="prezzo_nuovo_acquisto" :value="__('Prezzo/Valore Nuovo Giocatore')" />
                                    <input id="prezzo_nuovo_acquisto"
                                           class="block mt-1 w-full border-gray-300 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-300 focus:border-indigo-500 dark:focus:border-indigo-600 focus:ring-indigo-500 dark:focus:ring-indigo-600 rounded-md shadow-sm"
                                           :class="{ 'bg-gray-200 dark:bg-gray-700 !text-gray-500': jsPrezzoInBloccato }"
                                           type="number"
                                           name="prezzo_nuovo_acquisto"
                                           x-model.number="prezzoNuovoAcquisto"
                                           :readonly="jsPrezzoInBloccato"
                                           min="0"
                                           required />
                                    @if ($modalitaSostituzione === 'CONCLUSA')
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Fase 'Asta Conclusa': valore ereditato dal giocatore svincolato. Saldo crediti invariato.</p>
                                    @elseif ($modalitaSostituzione === 'STAGIONALE')
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Fase 'Sostituzioni Stagionali': valore ereditato dal giocatore svincolato. Saldo crediti invariato. Conta come 1 sostituzione.</p>
                                    @else
                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Inserisci il costo effettivo del nuovo giocatore.</p>
                                    @endif
                                    <x-input-error :messages="$errors->get('prezzo_nuovo_acquisto')" class="mt-2" />
                                </div>

                                <div class="mb-6 p-3 border rounded-md dark:border-gray-600 bg-gray-50 dark:bg-gray-700/50" x-show="selectedGiocatoreOutId && selectedCalciatoreInId && prezzoNuovoAcquisto !== ''">
                                    <h4 class="font-semibold text-sm mb-2 dark:text-gray-200">Riepilogo Sostituzione:</h4>
                                    <p class="text-sm dark:text-gray-300">Svincolo: <span x-text="giocatoriSquadra.find(g => g.id == selectedGiocatoreOutId)?.calciatore?.nome_completo || 'N/D'"></span> (<span x-text="prezzoGiocatoreOut"></span> cr)</p>
                                    <p class="text-sm dark:text-gray-300">Acquisto: <span x-text="calciatoriDisponibiliOriginal.find(c => c.id == selectedCalciatoreInId)?.nome_completo || 'N/D'"></span> (Costo: <span x-text="parseInt(prezzoNuovoAcquisto) || 0"></span> cr)</p>
                                    <hr class="my-1 dark:border-gray-500">
                                    <p class="text-sm font-semibold dark:text-gray-200">Crediti squadra dopo operazione: <strong x-text="creditiSquadraDopoSostituzione"></strong></p>
                                </div>
                            </div>

                            <div class="flex items-center justify-end mt-6">
                                <x-secondary-button type="button" onclick="window.location='{{ route('admin.rose.squadre.index') }}'" class="mr-3">
                                    {{ __('Annulla') }}
                                </x-secondary-button>
                                <x-primary-button ::disabled="!selectedSquadraId || !selectedGiocatoreOutId || !selectedCalciatoreInId || prezzoNuovoAcquisto === '' || isLoadingGiocatoriSquadra">
                                    {{ __('Conferma Sostituzione') }}
                                </x-primary-button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    </div>
</x-app-layout>