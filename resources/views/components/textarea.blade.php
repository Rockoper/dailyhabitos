@props(['disabled' => false, 'rows' => 3])

<textarea
    rows="{{ $rows }}"
    {{ $disabled ? 'disabled' : '' }}
    {{ $attributes->merge(['class' => 'block w-full rounded-lg border border-outline-variant bg-surface-container-lowest px-3.5 py-2.5 text-sm text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30 disabled:opacity-60']) }}
>{{ $slot }}</textarea>
