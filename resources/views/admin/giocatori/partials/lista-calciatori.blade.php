<div class="overflow-x-auto">
    <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
        <thead class="bg-gray-50 dark:bg-gray-700">
            <tr>
                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">ID</th>
                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Nome Calciatore</th>
                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ruolo</th>
                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Squadra</th>
                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Qt. I.</th>
                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Tag Lista</th>
                {{-- NUOVE COLONNE --}}
                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Acquistato Da</th>
                <th scope="col" class="px-3 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Prezzo</th>
            </tr>
        </thead>
        <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
            @forelse ($calciatori as $calciatore)
                <tr>
                    <td class="px-4 py-3 text-sm font-medium">{{ $calciatore->id }}</td>
                    <td class="px-4 py-3 text-sm font-medium text-gray-900 dark:text-gray-100">{{ $calciatore->nome_completo }}</td>
                    <td class="px-4 py-3 text-sm font-medium">{{ $calciatore->ruolo }}</td>
                    <td class="px-4 py-3 text-sm font-medium">{{ $calciatore->squadra_serie_a }}</td>
                    <td class="px-4 py-3 text-sm font-medium">{{ $calciatore->quotazione_iniziale }}</td>
                    <td class="px-4 py-3 text-sm font-medium">{{ $calciatore->tag_lista_inserimento }}</td>
                    
                    {{-- NUOVE CELLE CON LOGICA --}}
                    <td class="px-4 py-3 text-sm font-medium">
                        @if ($calciatore->acquisto && $calciatore->acquisto->user)
                            <span class="font-semibold text-green-600 dark:text-green-400">{{ $calciatore->acquisto->user->name }}</span>
                        @else
                            <span class="text-gray-400">Svincolato</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-sm font-medium">
                        @if ($calciatore->acquisto)
                            {{ $calciatore->acquisto->prezzo_acquisto }}
                        @else
                            -
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="px-6 py-4 text-center text-sm text-gray-500 dark:text-gray-400">
                        Nessun calciatore trovato con i criteri di ricerca.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>
</div>
<div class="mt-4">
    {{ $calciatori->appends(request()->query())->links() }}
</div>