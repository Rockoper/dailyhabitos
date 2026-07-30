<x-layouts.guest :title="'Recuperar contraseña — '.config('app.name')">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-on-surface">¿Olvidaste tu contraseña?</h1>
        <p class="mt-1 text-sm text-on-surface-variant">
            Escribe tu correo y te enviaremos un enlace para restablecerla.
        </p>
    </div>

    @if (session('status'))
        <p class="rounded-lg bg-secondary-container px-3.5 py-2.5 text-sm text-on-secondary-container">
            {{ session('status') }}
        </p>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="flex flex-col gap-5">
        @csrf

        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" name="email" type="email" autofocus autocomplete="username" required :value="old('email')" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <x-button type="submit">Enviar enlace de recuperación</x-button>
    </form>

    <p class="text-center text-sm text-on-surface-variant">
        <a href="{{ route('login') }}" class="font-medium text-primary hover:underline">Volver a iniciar sesión</a>
    </p>
</x-layouts.guest>
