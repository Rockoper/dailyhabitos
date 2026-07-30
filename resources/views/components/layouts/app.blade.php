@props(['title' => null])
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ? $title.' — '.config('app.name') : config('app.name') }}</title>

    <script>
        (function () {
            const stored = localStorage.getItem('theme');
            const prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
            if (stored === 'dark' || (stored !== 'light' && prefersDark)) {
                document.documentElement.classList.add('dark');
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @livewireStyles
</head>
<body class="h-full bg-background font-sans text-on-surface" x-data="{ mobileNavOpen: false }">
    <div class="flex min-h-screen">
        {{-- Navegación lateral fija (escritorio) --}}
        <aside class="hidden w-64 shrink-0 flex-col border-r border-outline-variant bg-surface-container-lowest lg:flex">
            <div class="flex h-16 items-center px-6">
                <a href="{{ route('dashboard') }}" class="font-heading text-lg font-semibold">DailyHábitos</a>
            </div>
            <nav class="flex-1 space-y-1 px-3 py-4">
                <x-layouts.nav-link route="dashboard" icon="dashboard">Dashboard</x-layouts.nav-link>
                <x-layouts.nav-link route="habits.today" icon="today">Hábitos de hoy</x-layouts.nav-link>
                <x-layouts.nav-link route="habits.index" icon="habits">Todos los hábitos</x-layouts.nav-link>
                <x-layouts.nav-link route="calendar.index" icon="calendar">Calendario</x-layouts.nav-link>
                <x-layouts.nav-link route="stats.index" icon="stats">Estadísticas</x-layouts.nav-link>
                <x-layouts.nav-link route="goals.index" icon="goals">Objetivos</x-layouts.nav-link>
                <x-layouts.nav-link route="reflections.index" icon="reflection">Reflexión diaria</x-layouts.nav-link>
                <x-layouts.nav-link route="history.index" icon="history">Historial</x-layouts.nav-link>
            </nav>
            <div class="border-t border-outline-variant p-3">
                <x-layouts.nav-link route="profile.edit" icon="profile">Perfil y configuración</x-layouts.nav-link>
            </div>
        </aside>

        <div class="flex min-w-0 flex-1 flex-col">
            {{-- Barra superior --}}
            <header class="flex h-16 items-center justify-between border-b border-outline-variant bg-surface-container-lowest px-4 lg:px-8">
                <button type="button" class="text-on-surface-variant lg:hidden" @click="mobileNavOpen = true" aria-label="Abrir navegación">
                    <x-icon name="menu" />
                </button>

                <div class="hidden lg:block">
                    @if ($title)
                        <h1 class="font-heading text-lg font-semibold text-on-surface">{{ $title }}</h1>
                    @endif
                </div>

                <div class="flex items-center gap-2">
                    <x-theme-toggle />

                    <div class="relative" x-data="{ open: false }" @click.outside="open = false">
                        <button type="button" @click="open = !open" class="flex h-9 w-9 items-center justify-center rounded-full bg-primary-container text-sm font-semibold text-on-primary-container">
                            {{ Str::of(auth()->user()->name)->substr(0, 1)->upper() }}
                        </button>
                        <div
                            x-show="open"
                            x-transition
                            x-cloak
                            class="absolute right-0 mt-2 w-48 rounded-lg border border-outline-variant bg-surface-container-lowest py-1 shadow-lg"
                        >
                            <a href="{{ route('profile.edit') }}" class="block px-4 py-2 text-sm text-on-surface hover:bg-surface-container-high">Perfil y configuración</a>
                            <form method="POST" action="{{ route('logout') }}">
                                @csrf
                                <button type="submit" class="flex w-full items-center gap-2 px-4 py-2 text-left text-sm text-on-surface hover:bg-surface-container-high">
                                    <x-icon name="logout" class="h-4 w-4" />
                                    Cerrar sesión
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 lg:px-8 lg:py-8">
                @if ($title)
                    <h1 class="mb-6 font-heading text-xl font-semibold text-on-surface lg:hidden">{{ $title }}</h1>
                @endif

                {{ $slot }}
            </main>
        </div>
    </div>

    {{-- Navegación móvil (drawer) --}}
    <div
        x-show="mobileNavOpen"
        x-cloak
        class="fixed inset-0 z-40 lg:hidden"
    >
        <div class="fixed inset-0 bg-on-surface/40" @click="mobileNavOpen = false"></div>
        <nav class="fixed inset-y-0 left-0 flex w-64 flex-col bg-surface-container-lowest p-4">
            <div class="mb-4 flex items-center justify-between">
                <span class="font-heading text-lg font-semibold">DailyHábitos</span>
                <button type="button" @click="mobileNavOpen = false" aria-label="Cerrar navegación">
                    <x-icon name="close" />
                </button>
            </div>
            <div class="space-y-1" @click="mobileNavOpen = false">
                <x-layouts.nav-link route="dashboard" icon="dashboard">Dashboard</x-layouts.nav-link>
                <x-layouts.nav-link route="habits.today" icon="today">Hábitos de hoy</x-layouts.nav-link>
                <x-layouts.nav-link route="habits.index" icon="habits">Todos los hábitos</x-layouts.nav-link>
                <x-layouts.nav-link route="calendar.index" icon="calendar">Calendario</x-layouts.nav-link>
                <x-layouts.nav-link route="stats.index" icon="stats">Estadísticas</x-layouts.nav-link>
                <x-layouts.nav-link route="goals.index" icon="goals">Objetivos</x-layouts.nav-link>
                <x-layouts.nav-link route="reflections.index" icon="reflection">Reflexión diaria</x-layouts.nav-link>
                <x-layouts.nav-link route="history.index" icon="history">Historial</x-layouts.nav-link>
                <x-layouts.nav-link route="profile.edit" icon="profile">Perfil y configuración</x-layouts.nav-link>
            </div>
        </nav>
    </div>

    @livewireScripts
</body>
</html>
