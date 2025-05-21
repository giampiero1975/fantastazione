{{-- layouts/navigation.blade.php --}}
<nav x-data="{ open: false }" class="bg-white dark:bg-gray-800 border-b border-gray-100 dark:border-gray-700">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}">
                        <x-application-logo class="block h-9 w-auto fill-current text-gray-800 dark:text-gray-200" />
                    </a>
                </div>

                <div class="hidden space-x-8 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        {{ __('Dashboard') }}
                    </x-nav-link>

                    {{-- ===== BLOCCO LINK ASTA LIVE (per tutti gli utenti, admin inclusi) ===== --}}
                    @auth {{-- Assicura che l'utente sia loggato --}}
                        @if (isset($mostraLinkAstaLiveGlobal) && $mostraLinkAstaLiveGlobal)
                            <x-nav-link :href="route('asta.live')" :active="request()->routeIs('asta.live')">
                                {{ __('Asta Live') }}
                            </x-nav-link>
                        @endif
                    @endauth
                    {{-- ===== FINE BLOCCO LINK ASTA LIVE ===== --}}

                    {{-- ===== BLOCCO LINK ADMIN (SCHERMI GRANDI) CON DROPDOWN ===== --}}
                    @if (Auth::check() && Auth::user()->is_admin)
                        {{-- Dropdown Gestione Lega --}}
                        <div class="hidden sm:flex sm:items-center sm:ms-6">
                            <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                    <button class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('admin.impostazioni.*') || request()->routeIs('admin.utenti.*') || request()->routeIs('admin.mia_squadra.dashboard') ? 'border-indigo-400 dark:border-indigo-600 text-gray-900 dark:text-gray-100' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-700' }} text-sm font-medium leading-5 focus:outline-none focus:text-gray-700 dark:focus:text-gray-300 focus:border-gray-300 dark:focus:border-gray-700 transition duration-150 ease-in-out">
                                        <div>{{ __('Gestione Lega') }}</div>
                                        <div class="ms-1">
                                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                        </div>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    <x-dropdown-link :href="route('admin.impostazioni.index')">
                                        {{ __('Impostazioni Lega/Asta') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.utenti.index')">
                                        {{ __('Gestione Squadre/Utenti') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.mia_squadra.dashboard')">
                                        {{ __('La Mia Squadra (Admin)') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>

                        {{-- Dropdown Gestione Asta & Giocatori --}}
                        <div class="hidden sm:flex sm:items-center sm:ms-6">
                             <x-dropdown align="left" width="48">
                                <x-slot name="trigger">
                                     <button class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('admin.giocatori.*') || request()->routeIs('admin.rose.*') || request()->routeIs('admin.asta.chiamate.*') ? 'border-indigo-400 dark:border-indigo-600 text-gray-900 dark:text-gray-100' : 'border-transparent text-gray-500 dark:text-gray-400 hover:text-gray-700 dark:hover:text-gray-300 hover:border-gray-300 dark:hover:border-gray-700' }} text-sm font-medium leading-5 focus:outline-none focus:text-gray-700 dark:focus:text-gray-300 focus:border-gray-300 dark:focus:border-gray-700 transition duration-150 ease-in-out">
                                        <div>{{ __('Asta & Giocatori') }}</div>
                                        <div class="ms-1">
                                            <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                                        </div>
                                    </button>
                                </x-slot>
                                <x-slot name="content">
                                    <x-dropdown-link :href="route('admin.giocatori.index')">
                                        {{ __('Elenco Calciatori') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.giocatori.import.show')">
                                        {{ __('Importa CSV Calciatori') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.giocatori.assegna.show')">
                                        {{ __('Assegna Giocatore Manualmente') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.rose.squadre.index')">
                                        {{ __('Visualizza Rose Squadre') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.asta.chiamate.gestione')">
                                        {{ __('Gestione Chiamate TAP') }}
                                    </x-dropdown-link>
                                    <x-dropdown-link :href="route('admin.rose.sostituisci.show')">
                                        {{ __('Sostituzione') }}
                                    </x-dropdown-link>
                                </x-slot>
                            </x-dropdown>
                        </div>
                    @endif
                    {{-- ===== FINE BLOCCO LINK ADMIN (SCHERMI GRANDI) ===== --}}
                </div>
            </div>

            <div class="hidden sm:flex sm:items-center sm:ms-6">
                @auth
                    <x-dropdown align="right" width="48">
                        <x-slot name="trigger">
                            <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 dark:text-gray-400 bg-white dark:bg-gray-800 hover:text-gray-700 dark:hover:text-gray-300 focus:outline-none transition ease-in-out duration-150">
                                <div>{{ Auth::user()->name }}</div>

                                <div class="ms-1">
                                    <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                        <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                    </svg>
                                </div>
                            </button>
                        </x-slot>

                        <x-slot name="content">
                            <x-dropdown-link :href="route('profile.edit')">
                                {{ __('Profile') }}
                            </x-dropdown-link>

                            <form method="POST" action="{{ route('logout') }}">
                                @csrf

                                <x-dropdown-link :href="route('logout')"
                                        onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                    {{ __('Log Out') }}
                                </x-dropdown-link>
                            </form>
                        </x-slot>
                    </x-dropdown>
                @endauth
            </div>

            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 dark:text-gray-500 hover:text-gray-500 dark:hover:text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-900 focus:outline-none focus:bg-gray-100 dark:focus:bg-gray-900 focus:text-gray-500 dark:focus:text-gray-400 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                {{ __('Dashboard') }}
            </x-responsive-nav-link>

            {{-- ===== BLOCCO LINK ASTA LIVE (MENU RESPONSIVE) ===== --}}
            @auth
                @if (isset($mostraLinkAstaLiveGlobal) && $mostraLinkAstaLiveGlobal)
                    <x-responsive-nav-link :href="route('asta.live')" :active="request()->routeIs('asta.live')">
                        {{ __('Asta Live') }}
                    </x-responsive-nav-link>
                @endif
            @endauth
            {{-- ===== FINE BLOCCO LINK ASTA LIVE (MENU RESPONSIVE) ===== --}}
        </div>

        {{-- ===== BLOCCO LINK ADMIN (MENU RESPONSIVE) ===== --}}
        @if (Auth::check() && Auth::user()->is_admin)
            {{-- Dropdown Gestione Lega - Responsive --}}
            <div class="mt-3 space-y-1 border-t border-gray-200 dark:border-gray-700 pt-3">
                <div class="px-4 font-medium text-base text-gray-800 dark:text-gray-200 mb-1">{{ __('Gestione Lega') }}</div>
                <x-responsive-nav-link :href="route('admin.impostazioni.index')" :active="request()->routeIs('admin.impostazioni.*')">
                    {{ __('Impostazioni Lega/Asta') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.utenti.index')" :active="request()->routeIs('admin.utenti.*')">
                    {{ __('Gestione Squadre/Utenti') }}
                </x-responsive-nav-link>
                 <x-responsive-nav-link :href="route('admin.mia_squadra.dashboard')" :active="request()->routeIs('admin.mia_squadra.dashboard')">
                    {{ __('La Mia Squadra (Admin)') }}
                </x-responsive-nav-link>
            </div>
            {{-- Dropdown Gestione Asta & Giocatori - Responsive --}}
            <div class="mt-3 space-y-1 border-t border-gray-200 dark:border-gray-700 pt-3">
                 <div class="px-4 font-medium text-base text-gray-800 dark:text-gray-200 mb-1">{{ __('Asta & Giocatori') }}</div>
                 <x-responsive-nav-link :href="route('admin.giocatori.index')" :active="request()->routeIs('admin.giocatori.index')">
                    {{ __('Elenco Calciatori') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.giocatori.import.show')" :active="request()->routeIs('admin.giocatori.import.show')">
                    {{ __('Importa CSV Calciatori') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.giocatori.assegna.show')" :active="request()->routeIs('admin.giocatori.assegna.show')">
                    {{ __('Assegna Giocatore Manualmente') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.rose.squadre.index')" :active="request()->routeIs('admin.rose.squadre.index')">
                    {{ __('Visualizza Rose Squadre') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.asta.chiamate.gestione')" :active="request()->routeIs('admin.asta.chiamate.gestione')">
                    {{ __('Gestione Chiamate TAP') }}
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('admin.rose.sostituisci.show')">
                    {{ __('Sostituzione') }}
                </x-responsive-nav-link>
            </div>
        @endif
        {{-- ===== FINE BLOCCO LINK ADMIN (MENU RESPONSIVE) ===== --}}

        @auth
            <div class="pt-4 pb-1 border-t border-gray-200 dark:border-gray-600">
                <div class="px-4">
                    <div class="font-medium text-base text-gray-800 dark:text-gray-200">{{ Auth::user()->name }}</div>
                    <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                </div>

                <div class="mt-3 space-y-1">
                    <x-responsive-nav-link :href="route('profile.edit')">
                        {{ __('Profile') }}
                    </x-responsive-nav-link>

                    <form method="POST" action="{{ route('logout') }}">
                        @csrf

                        <x-responsive-nav-link :href="route('logout')"
                                onclick="event.preventDefault();
                                            this.closest('form').submit();">
                            {{ __('Log Out') }}
                        </x-responsive-nav-link>
                    </form>
                </div>
            </div>
        @endauth
    </div>
</nav>