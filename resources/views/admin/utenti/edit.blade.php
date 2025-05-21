<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Modifica Utente/Squadra: ') }} {{ $user->name }}
        </h2>
    </x-slot>

    <div class="py-12">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    <form method="POST" action="{{ route('admin.utenti.update', $user->id) }}">
                        @csrf
                        @method('PATCH')

                        {{-- Nome Squadra --}}
                        <div>
                            <x-input-label for="name" :value="__('Nome Squadra')" />
                            <x-text-input id="name" class="block mt-1 w-full" type="text" name="name" :value="old('name', $user->name)" required autofocus />
                            <x-input-error :messages="$errors->get('name')" class="mt-2" />
                        </div>

                        {{-- Nome Proprietario --}}
                        <div class="mt-4">
                            <x-input-label for="nome_proprietario" :value="__('Nome Proprietario (opzionale)')" />
                            <x-text-input id="nome_proprietario" class="block mt-1 w-full" type="text" name="nome_proprietario" :value="old('nome_proprietario', $user->nome_proprietario)" />
                            <x-input-error :messages="$errors->get('nome_proprietario')" class="mt-2" />
                        </div>

                        {{-- Email --}}
                        <div class="mt-4">
                            <x-input-label for="email" :value="__('Email')" />
                            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email', $user->email)" required />
                            <x-input-error :messages="$errors->get('email')" class="mt-2" />
                        </div>

                        {{-- Telefono --}}
                        <div class="mt-4">
                            <x-input-label for="phone_number" :value="__('Numero di Telefono (opzionale, per invio credenziali)')" />
                            <x-text-input id="phone_number" class="block mt-1 w-full" type="tel" name="phone_number" :value="old('phone_number', $user->phone_number)" placeholder="Es. +393331234567 o 3331234567" />
                            <x-input-error :messages="$errors->get('phone_number')" class="mt-2" />
                        </div>

                        {{-- Crediti --}}
                        <div class="mt-4">
                            <x-input-label for="crediti_iniziali_squadra" :value="__('Crediti Iniziali Squadra')" />
                            <x-text-input id="crediti_iniziali_squadra" class="block mt-1 w-full" type="number" name="crediti_iniziali_squadra" :value="old('crediti_iniziali_squadra', $user->crediti_iniziali_squadra)" required />
                            <x-input-error :messages="$errors->get('crediti_iniziali_squadra')" class="mt-2" />
                        </div>

                        <div class="mt-4">
                            <x-input-label for="crediti_rimanenti" :value="__('Crediti Rimanenti')" />
                            <x-text-input id="crediti_rimanenti" class="block mt-1 w-full" type="number" name="crediti_rimanenti" :value="old('crediti_rimanenti', $user->crediti_rimanenti)" required />
                            <x-input-error :messages="$errors->get('crediti_rimanenti')" class="mt-2" />
                        </div>

                        {{-- Ordine Chiamata --}}
                        <div class="mt-4">
                            <x-input-label for="ordine_chiamata" :value="__('Ordine di Chiamata (opzionale, numero progressivo)')" />
                            <x-text-input id="ordine_chiamata" class="block mt-1 w-full" type="number" name="ordine_chiamata" :value="old('ordine_chiamata', $user->ordine_chiamata)" min="1" />
                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Lasciare vuoto se non si usa l'ordine di chiamata o per questo utente.</p>
                            <x-input-error :messages="$errors->get('ordine_chiamata')" class="mt-2" />
                        </div>

                        {{-- Password --}}
                        <div class="mt-4">
                            <x-input-label for="password" :value="__('Nuova Password (lasciare vuoto per non modificare)')" />
                            <x-text-input id="password" class="block mt-1 w-full" type="password" name="password" />
                            <x-input-error :messages="$errors->get('password')" class="mt-2" />
                        </div>
                        <div class="mt-4">
                            <x-input-label for="password_confirmation" :value="__('Conferma Nuova Password')" />
                            <x-text-input id="password_confirmation" class="block mt-1 w-full" type="password" name="password_confirmation" />
                        </div>

                        {{-- Ruolo Admin --}}
                        <div class="block mt-4">
                            <label for="is_admin" class="inline-flex items-center">
                                <input id="is_admin" type="checkbox" class="rounded border-gray-300 dark:border-gray-700 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:focus:border-indigo-600 focus:ring-offset-gray-800" name="is_admin" value="1" {{ old('is_admin', $user->is_admin) ? 'checked' : '' }}>
                                <span class="ml-2 text-sm text-gray-600 dark:text-gray-400">{{ __('È Amministratore?') }}</span>
                            </label>
                        </div>

                        <div class="flex items-center justify-end mt-4">
                            <x-secondary-button type="button" onclick="window.history.back()" class="mr-3">
                                {{ __('Annulla') }}
                            </x-secondary-button>
                            <x-primary-button>
                                {{ __('Salva Modifiche') }}
                            </x-primary-button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>