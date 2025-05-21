{{-- resources/views/admin/users/index.blade.php --}}
<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 dark:text-gray-200 leading-tight">
            {{ __('Gestione Utenti/Squadre') }}
        </h2>
    </x-slot>

    <div class="py-12">
    <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">
        {{-- Bottone per creare nuova squadra --}}
            <div class="mb-4 flex justify-end">
                <a href="{{ route('admin.utenti.create') }}">
                    <x-primary-button>
                        {{ __('Crea Nuova Squadra') }}
                    </x-primary-button>
                </a>
            </div>
            {{-- Fine Bottone --}}
        
            <div class="bg-white dark:bg-gray-800 overflow-hidden shadow-sm sm:rounded-lg">
                <div class="p-6 text-gray-900 dark:text-gray-100">
                    @if (session('success'))
                        <div class="mb-4 p-4 font-medium text-sm text-green-700 bg-green-100 dark:bg-green-800 dark:text-green-200 rounded-md">
                            {{ session('success') }}
                        </div>
                    @endif
                    @if (session('error'))
                        <div class="mb-4 p-4 font-medium text-sm text-red-700 bg-red-100 dark:bg-red-800 dark:text-red-200 rounded-md">
                            {{ session('error') }}
                        </div>
                    @endif

                    <h3 class="text-lg font-medium mb-4">Elenco Utenti Registrati</h3>
                    <div class="overflow-x-auto">
                        <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                            <thead class="bg-gray-50 dark:bg-gray-700">
                                <tr>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nome Squadra</th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nome Proprietario</th> {{-- NUOVO --}}
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Email</th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Telefono</th> {{-- NUOVO --}}
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ruolo</th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Crediti (Rim. / Iniz.)</th>
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ord. Chiamata</th> {{-- NUOVO --}}
                                    <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Azioni</th>
                                </tr>
                            </thead>
                            <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                @forelse ($utenti as $utente)
                                    <tr>
                                        <td class="px-4 py-3 text-sm font-medium">{{ $utente->id }}</td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">{{ $utente->name }}</td>
                                        <td class="px-4 py-3 text-sm font-medium">{{ $utente->nome_proprietario ?? '-' }}</td> {{-- NUOVO --}}
                                        <td class="px-4 py-3 text-sm font-medium">{{ $utente->email }}</td>
                                        <td class="px-4 py-3 text-sm font-medium">{{ $utente->phone_number ?? '-' }}</td> {{-- NUOVO --}}
                                        <td class="px-4 py-3 text-sm font-medium">
                                            @if ($utente->is_admin)
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-red-100 dark:bg-red-700 text-red-800 dark:text-red-100">Admin</span>
                                            @else
                                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-green-100 dark:bg-green-700 text-green-800 dark:text-green-100">Squadra</span>
                                            @endif
                                        </td>
                                        <td class="px-4 py-3 text-sm font-medium">{{ $utente->crediti_rimanenti }} / {{ $utente->crediti_iniziali_squadra }}</td>
                                        <td class="px-4 py-3 text-sm font-medium">{{ $utente->ordine_chiamata ?? '-' }}</td> {{-- NUOVO --}}
                                        <td class="px-4 py-3 text-sm font-medium">
    <div class="flex items-center space-x-2">
        <a href="{{ route('admin.utenti.edit', $utente->id) }}" class="text-indigo-600 dark:text-indigo-400 hover:text-indigo-700 dark:hover:text-indigo-500">Modifica</a>
        
        @if ($utente->phone_number)
            @php
                $nomeSalutare = $utente->nome_proprietario ?: $utente->name;
                $numeroTelefonoRaw = $utente->phone_number;
                $prefissoInternazionaleDefault = '39'; // Per l'Italia

                // Logica pulizia per WhatsApp: solo cifre, anteponi prefisso se manca
                $numeroPulitoPerWA = preg_replace('/[^0-9]/', '', $numeroTelefonoRaw);
                if (strlen($numeroPulitoPerWA) === 10 && !str_starts_with($numeroPulitoPerWA, $prefissoInternazionaleDefault) && substr($numeroPulitoPerWA, 0, 1) !== '0') {
                    // Tipico numero mobile italiano a 10 cifre (es. 3331234567)
                    $numeroWhatsAppFinal = $prefissoInternazionaleDefault . $numeroPulitoPerWA;
                } elseif (str_starts_with($numeroPulitoPerWA, $prefissoInternazionaleDefault)) {
                    // Già contiene il prefisso, usalo così (es. 39333...)
                    $numeroWhatsAppFinal = $numeroPulitoPerWA;
                } elseif (str_starts_with($numeroPulitoPerWA, '00' . $prefissoInternazionaleDefault)) {
                    // Formato 0039... -> rimuovi 00
                    $numeroWhatsAppFinal = substr($numeroPulitoPerWA, 2);
                } else {
                    // Caso generico, potrebbe essere già completo o un formato diverso
                    // Se inizia con '+', wa.me lo gestisce, ma è meglio rimuoverlo per coerenza
                    $numeroWhatsAppFinal = ltrim($numeroPulitoPerWA, '+');
                    // Se dopo aver rimosso il + non inizia con il prefisso e ha una lunghezza "nazionale"
                    if (!str_starts_with($numeroWhatsAppFinal, $prefissoInternazionaleDefault) && strlen($numeroWhatsAppFinal) >= 9 && strlen($numeroWhatsAppFinal) <= 11) {
                         // Potrebbe essere un numero locale senza prefisso, prova ad aggiungerlo
                         // Questa è una euristica, la validazione/formattazione dei numeri è complessa
                         // $numeroWhatsAppFinal = $prefissoInternazionaleDefault . $numeroWhatsAppFinal;
                         // Per ora, lo lasciamo così se non corrisponde ai pattern più ovvi.
                         // La cosa migliore è che l'admin inserisca il numero con prefisso internazionale.
                    }
                }


                // Per SMS, il numero può essere più tollerante, ma pulire da spazi non fa male
                $numeroSMSFinal = preg_replace('/\s+/', '', $numeroTelefonoRaw); // Rimuovi spazi
                // Potresti anche usare $numeroWhatsAppFinal se vuoi la stessa logica di pulizia,
                // ma l'URL sms: a volte preferisce il +

                $testoBaseMessaggio = "Ciao " . $nomeSalutare . "! Puoi accedere alla tua squadra Fantastazione (" . $utente->name . ") su " . route('login') . " con la tua email: {$utente->email}. Se hai bisogno di impostare/resettare la password, usa l'opzione 'Password dimenticata?' sulla pagina di login.";
                
                $testoMessaggioWhatsApp = urlencode($testoBaseMessaggio);
                $testoMessaggioSMS = rawurlencode($testoBaseMessaggio); // rawurlencode è più sicuro per SMS
            @endphp

            <a href="https://wa.me/{{ $numeroWhatsAppFinal }}?text={{ $testoMessaggioWhatsApp }}" target="_blank" class="text-green-500 hover:text-green-700 dark:text-green-400 dark:hover:text-green-600" title="Invia credenziali via WhatsApp">
                <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path d="M13.581 1.203a7.343 7.343 0 00-7.161 0c-2.956.884-5.055 3.572-5.055 6.722 0 1.42.472 2.798 1.393 3.915l-1.05 3.851 3.99-1.033a8.353 8.353 0 004.096.992h.014c3.773 0 6.831-2.907 6.831-6.488.001-3.028-1.916-5.648-4.958-6.616l-.001.001zm-7.223 11.645l-.22-.136c-1.134-.696-1.86-1.86-1.86-3.168 0-2.352 1.628-4.418 4.018-5.115a.313.313 0 01.197-.001c2.463.68 4.188 2.76 4.188 5.115a5.003 5.003 0 01-2.73 4.45l-.237.135-2.362.613.622-2.27.114-.415-.08-.19a5.135 5.135 0 01-1.533-3.348c0-.307.03-.606.087-.897l.076-.393-.327-.142a5.309 5.309 0 01-3.281.189l-.24.06zm3.808 1.534c.996 0 1.948-.192 2.798-.55l.142-.06.927.24 1.555.402-1.58-1.54-.182-.178.072-.2a5.903 5.903 0 001.06-3.213c0-2.87-2.055-5.33-4.913-5.33a6.08 6.08 0 00-2.968.77l-.22.112c-2.16 1.086-3.483 3.303-3.483 5.792 0 1.48.603 2.86 1.65 3.9l.155.154-.42 1.548-1.448.377.384-1.402.1-.365-.153-.091a6.147 6.147 0 00-1.27-2.924c.036-.27.055-.546.055-.825a5.309 5.309 0 013.28-.189l.24-.06.014.001z"></path></svg>
            </a>
        @endif
    </div>
</td>
                                    </tr>
                                @empty
                                    {{-- ... --}}
                                @endforelse
                            </tbody>
                        </table>
                        {{-- ... --}}
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>