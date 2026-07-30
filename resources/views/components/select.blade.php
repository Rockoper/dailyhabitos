@props(['disabled' => false])

<select
    {{ $disabled ? 'disabled' : '' }}
    {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:opacity-60']) }}
>
    {{ $slot }}
</select>
