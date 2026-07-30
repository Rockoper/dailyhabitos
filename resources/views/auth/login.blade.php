<x-layouts.guest :title="'Iniciar sesión — '.config('app.name')">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-on-surface">Bienvenido de nuevo</h1>
        <p class="mt-1 text-sm text-on-surface-variant">Continúa con tu progreso diario.</p>
    </div>

    @if (session('status'))
        <p class="rounded-lg bg-secondary-container px-3.5 py-2.5 text-sm text-on-secondary-container">
            {{ session('status') }}
        </p>
    @endif

    <form method="POST" action="{{ route('login') }}" class="flex flex-col gap-5">
        @csrf

        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" name="email" type="email" autofocus autocomplete="username" required :value="old('email')" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <div class="flex items-center justify-between">
                <x-input-label for="password" value="Contraseña" />
                @if (Route::has('password.request'))
                    <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary hover:underline">
                        ¿Olvidaste tu contraseña?
                    </a>
                @endif
            </div>
            <x-text-input id="password" name="password" type="password" autocomplete="current-password" required />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <label class="flex items-center gap-2 text-sm text-on-surface-variant">
            <input type="checkbox" name="remember" class="rounded border-outline-variant text-primary focus:ring-primary/30">
            Recordarme
        </label>

        <x-button type="submit">Iniciar sesión</x-button>
    </form>

    <p class="text-center text-sm text-on-surface-variant">
        ¿No tienes una cuenta?
        <a href="{{ route('register') }}" class="font-medium text-primary hover:underline">Crear cuenta</a>
    </p>
</x-layouts.guest>
