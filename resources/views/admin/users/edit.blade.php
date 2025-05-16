<x-app-layout> <x-slot name="header">
<h2 class="font-semibold text-xl text-gray-800 leading-tight">{{
	__('Modifica Utente/Squadra: ') }} {{ $user->name }}</h2>
</x-slot>

<div class="py-12">
	<div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
		<div class="bg-white overflow-hidden shadow-sm sm:rounded-lg">
			<div class="p-6 text-gray-900">
				<form method="POST"
					action="{{ route('admin.users.update', $user->id) }}">
					@csrf @method('PATCH')

					<div>
						<x-input-label for="name" :value="__('Nome Squadra/Utente')" />
						<x-text-input id="name" class="block mt-1 w-full" type="text"
							name="name" :value="old('name', $user->name)" required autofocus />
						<x-input-error :messages="$errors->get('name')" class="mt-2" />
					</div>

					<div class="mt-4">
						<x-input-label for="email" :value="__('Email')" />
						<x-text-input id="email" class="block mt-1 w-full" type="email"
							name="email" :value="old('email', $user->email)" required />
						<x-input-error :messages="$errors->get('email')" class="mt-2" />
					</div>

					<div class="mt-4">
						<x-input-label for="crediti_iniziali_squadra"
							:value="__('Crediti Iniziali Squadra')" />
						<x-text-input id="crediti_iniziali_squadra"
							class="block mt-1 w-full" type="number"
							name="crediti_iniziali_squadra"
							:value="old('crediti_iniziali_squadra', $user->crediti_iniziali_squadra)"
							required />
						<x-input-error
							:messages="$errors->get('crediti_iniziali_squadra')" class="mt-2" />
					</div>

					<div class="mt-4">
						<x-input-label for="crediti_rimanenti"
							:value="__('Crediti Rimanenti')" />
						<x-text-input id="crediti_rimanenti" class="block mt-1 w-full"
							type="number" name="crediti_rimanenti"
							:value="old('crediti_rimanenti', $user->crediti_rimanenti)"
							required />
						<x-input-error :messages="$errors->get('crediti_rimanenti')"
							class="mt-2" />
					</div>

					<div class="mt-4">
						<x-input-label for="password"
							:value="__('Nuova Password (lasciare vuoto per non modificare)')" />
						<x-text-input id="password" class="block mt-1 w-full"
							type="password" name="password" />
						<x-input-error :messages="$errors->get('password')" class="mt-2" />
					</div>
					<div class="mt-4">
						<x-input-label for="password_confirmation"
							:value="__('Conferma Nuova Password')" />
						<x-text-input id="password_confirmation" class="block mt-1 w-full"
							type="password" name="password_confirmation" />
					</div>


					<div class="block mt-4">
						<label for="is_admin" class="inline-flex items-center"> <input
							id="is_admin" type="checkbox"
							class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
							name="is_admin" value="1" {{ old('is_admin', $user->is_admin) ?
							'checked' : '' }}> <span class="ml-2 text-sm text-gray-600">{{
								__('È Amministratore?') }}</span>
						</label>
					</div>

					<div class="flex items-center justify-end mt-4">
						<x-primary-button> {{ __('Salva Modifiche') }} </x-primary-button>
					</div>
				</form>
			</div>
		</div>
	</div>
</div>
</x-app-layout>