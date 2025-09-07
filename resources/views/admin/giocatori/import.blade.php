<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Importa Giocatori da CSV') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900">

                    {{-- Mostra messaggi di successo, errore e aggregato --}}
                    @if (session('success'))
                        <div class="mb-4 p-4 font-medium text-sm text-green-700 bg-green-100 rounded-md">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-4 p-4 font-medium text-sm text-red-700 bg-red-100 rounded-md">
                            {{ session('error') }}
                        </div>
                    @endif
                     @if (session('aggregate'))
                        <div class="mb-4 p-4 font-medium text-sm text-blue-700 bg-blue-100 rounded-md">
                            {{ session('aggregate') }}
                        </div>
                    @endif
                    {{-- Se vuoi mostrare gli errori per riga (passati come 'import_errors') --}}
                    {{-- @if (session('import_errors'))
                        <div class="mb-4 p-4 font-medium text-sm text-yellow-700 bg-yellow-100 rounded-md">
                            <strong>Dettaglio errori importazione:</strong>
                            <ul>
                                @foreach (session('import_errors') as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif --}}

<div class="alert alert-warning p-3 mb-4" role="alert">
  {{-- Titolo dell'avviso --}}
  <h4 class="alert-heading">
    ⚠️ Attenzione al Formato del File!
  </h4>

  {{-- Spiegazione del problema --}}
  <p>
    Per evitare errori con i caratteri speciali (come <strong>è, à, ò, ù</strong>), il file CSV deve essere salvato da Excel con la codifica corretta.
  </p>

  <hr>

  {{-- Istruzioni precise --}}
  <p class="mb-0">
    Segui questi passaggi in Excel: <br>
    <strong>File > Salva con nome > Dal menu a tendina "Salva come", scegli "CSV UTF-8 (Delimitato da virgole) (*.csv)"</strong>.
  </p>
</div>
                    <p class="mb-4 text-sm text-gray-600">
                        Carica il file CSV delle quotazioni. La stagione/tag e lo stato attivo/ceduto verranno determinati automaticamente dal nome del file (es. "Quotazioni_Fantacalcio_Stagione_2024_25.xlsx - Tutti.csv").
                    </p>

                    <form method="POST" action="{{ route('admin.giocatori.import.handle') }}" enctype="multipart/form-data">
                        @csrf

                        <div class="mb-4">
                            <x-input-label for="csv_file" :value="__('File CSV Giocatori')" />
                            <input id="csv_file" class="block mt-1 w-full border-gray-300 focus:border-indigo-500 focus:ring-indigo-500 rounded-md shadow-sm" type="file" name="csv_file" required accept=".csv,.txt">
                            <x-input-error :messages="$errors->get('csv_file')" class="mt-2" />
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <x-primary-button>
                                {{ __('Importa Giocatori') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>