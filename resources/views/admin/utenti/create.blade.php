<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Crea Nuova Squadra/Utente') }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    {{-- Mostra errori di validazione generali, se presenti --}}
                    @if ($errors->any())
                        <div class="mb-4 p-4 font-medium text-sm text-red-700 bg-red-100 dark:bg-red-800 dark:text-red-200 rounded-md">
                            <strong>{{ __('Attenzione! Sono presenti errori di validazione:') }}</strong>
                            <ul class="list-disc list-inside mt-1">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                     @if (session('error'))
                        <div class="mb-4 p-4 font-medium text-sm text-red-700 bg-red-100 dark:bg-red-800 dark:text-red-200 rounded-md">
                            {{ session('error') }}
                        </div>
                    @endif


                    <form method="POST" action="{{ route('admin.utenti.store') }}">
                        @csrf

                        {{-- Nome Squadra --}}
                        <div>
                            <x-input-label for="name" :value="__('Nome Squadra (Login)')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name')" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        {{-- Nome Proprietario --}}
                        <div class="mt-4">
                            <x-input-label for="nome_proprietario" :value="__('Nome Proprietario (Reale, opzionale)')" />
                            <x-text-input id="nome_proprietario" class="block mt-1 w-full" type="text" name="nome_proprietario" :value="old('nome_proprietario')" />
                            <x-input-error :messages="$errors->get('nome_proprietario')" class="mt-2" />
                        </div>

                        {{-- Email --}}
                        <div class="mt-4">
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        {{-- Telefono --}}
                        <div class="mt-4">
                            <x-input-label for="phone_number" :value="__('Numero di Telefono (opzionale)')" />
                            <x-text-input id="phone_number" class="block mt-1 w-full" type="tel" name="phone_number" :value="old('phone_number')" placeholder="Es. +393331234567" />
                            <x-input-error :messages="$errors->get('phone_number')" class="mt-2" />
                        </div>

                        {{-- Password --}}
                        <div class="mt-4">
                            <x-input-label for="password" :value="__('Password')" />
                            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" required />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                        <div class="mt-4">
                            <x-input-label for="password_confirmation" :value="__('Conferma Password')" />
                            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" required />
                            {{-- L'errore di 'password_confirmation' viene gestito dalla regola 'confirmed' su 'password' --}}
                        </div>

                        {{-- Crediti --}}
                        <div class="mt-4">
                            <x-input-label for="crediti_iniziali_squadra" :value="__('Crediti Iniziali Squadra')" />
                            <x-text-input id="crediti_iniziali_squadra" class="block mt-1 w-full" type="number" name="crediti_iniziali_squadra" :value="old('crediti_iniziali_squadra', $defaultCrediti ?? 500)" required min="0" />
                            <x-input-error :messages="$errors->get('crediti_iniziali_squadra')" class="mt-2" />
                        </div>

                        {{-- Ordine Chiamata --}}
                        <div class="mt-4">
                            <x-input-label for="ordine_chiamata" :value="__('Ordine di Chiamata (opzionale, numero progressivo unico)')" />
                            <x-text-input id="ordine_chiamata" class="block mt-1 w-full" type="number" name="ordine_chiamata" :value="old('ordine_chiamata')" min="1" placeholder="Lascia vuoto se non usato"/>
                            <x-input-error :messages="$errors->get('ordine_chiamata')" class="mt-2" />
                        </div>

                        {{-- L'opzione 'is_admin' di solito non si imposta da qui per le squadre, quindi la ometto.
                             Viene impostata a false di default nel controller. --}}

                        <div class="flex items-center justify-end mt-6">
                            <x-secondary-button type="button" onclick="window.location='{{ route('admin.utenti.index') }}'" class="mr-3">
                                {{ __('Annulla') }}
                            </x-secondary-button>
                            <x-primary-button>
                                {{ __('Crea Squadra') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>