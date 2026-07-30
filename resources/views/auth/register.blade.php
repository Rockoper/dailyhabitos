<x-layouts.guest :title="'Crear cuenta — '.config('app.name')">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-on-surface">Crea tu cuenta</h1>
        <p class="mt-1 text-sm text-on-surface-variant">Empieza a construir mejores hábitos hoy.</p>
    </div>

    <form method="POST" action="{{ route('register') }}" class="flex flex-col gap-5">
        @csrf

        <div>
            <x-input-label for="name" value="Nombre completo" />
            <x-text-input id="name" name="name" type="text" autofocus autocomplete="name" required :value="old('name')" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" name="email" type="email" autocomplete="username" required :value="old('email')" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input-label for="password" value="Contraseña" />
            <x-text-input id="password" name="password" type="password" autocomplete="new-password" required />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirmar contraseña" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <x-button type="submit">Crear cuenta</x-button>
    </form>

    <p class="text-center text-sm text-on-surface-variant">
        ¿Ya tienes una cuenta?
        <a href="{{ route('login') }}" class="font-medium text-primary hover:underline">Iniciar sesión</a>
    </p>
</x-layouts.guest>
