@props(['title' => null])
<!DOCTYPE html>
<html lang="es" class="h-full">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $title ?? config('app.name') }}</title>

    <script>
        // Evita el parpadeo de tema incorrecto: se aplica antes de pintar la página.
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
<body class="h-full bg-background font-sans text-on-surface">
    <div class="flex min-h-screen">
        <div class="flex w-full flex-col justify-center px-6 py-12 lg:w-1/2 lg:px-16">
            <div class="mx-auto flex w-full max-w-sm flex-col gap-8">
                <div class="flex items-center justify-between">
                    <a href="{{ url('/') }}" class="font-heading text-lg font-semibold">DailyHábitos</a>
                    <x-theme-toggle />
                </div>

                {{ $slot }}
            </div>
        </div>

        <div class="relative hidden overflow-hidden bg-primary lg:flex lg:w-1/2 lg:items-center lg:justify-center">
            <div class="absolute inset-0 bg-gradient-to-br from-primary via-primary to-secondary opacity-90"></div>
            <div class="relative w-full max-w-sm rounded-xl bg-surface/10 p-6 text-on-primary backdrop-blur">
                <p class="font-heading text-sm font-medium text-on-primary/80">Racha activa</p>
                <p class="font-heading text-4xl font-semibold">12 días</p>
                <div class="mt-6 grid grid-cols-7 gap-1.5">
                    @for ($i = 0; $i < 28; $i++)
                        <span
                            class="h-3 w-3 rounded-sm"
                            style="background-color: rgba(255,255,255,{{ [0.15, 0.3, 0.5, 0.75, 0.95][$i % 5] }})"
                        ></span>
                    @endfor
                </div>
                <p class="mt-6 text-sm text-on-primary/80">
                    Constancia, no perfección. Cada día registrado cuenta.
                </p>
            </div>
        </div>
    </div>

    @livewireScripts
</body>
</html>
