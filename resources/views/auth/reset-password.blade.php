<x-layouts.guest :title="'Restablecer contraseña — '.config('app.name')">
    <div>
        <h1 class="font-heading text-2xl font-semibold text-on-surface">Restablecer contraseña</h1>
        <p class="mt-1 text-sm text-on-surface-variant">Elige una nueva contraseña para tu cuenta.</p>
    </div>

    <form method="POST" action="{{ route('password.update') }}" class="flex flex-col gap-5">
        @csrf

        <input type="hidden" name="token" value="{{ $request->route('token') }}">

        <div>
            <x-input-label for="email" value="Correo electrónico" />
            <x-text-input id="email" name="email" type="email" autofocus autocomplete="username" required :value="old('email', $request->email)" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div>
            <x-input-label for="password" value="Nueva contraseña" />
            <x-text-input id="password" name="password" type="password" autocomplete="new-password" required />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div>
            <x-input-label for="password_confirmation" value="Confirmar nueva contraseña" />
            <x-text-input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <x-button type="submit">Restablecer contraseña</x-button>
    </form>
</x-layouts.guest>
