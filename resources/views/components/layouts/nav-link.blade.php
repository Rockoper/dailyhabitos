@props(['route', 'icon', 'active' => null])

@php
$isActive = $active ?? request()->routeIs($route.'*');
@endphp

<a
    href="{{ route($route) }}"
    {{ $attributes->merge([
        'class' => 'flex items-center gap-3 rounded-lg px-3 py-2 text-sm font-medium transition-colors '
            .($isActive
                ? 'bg-primary-container text-on-primary-container'
                : 'text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface'),
    ]) }}
>
    <x-icon :name="$icon" />
    <span>{{ $slot }}</span>
</a>
