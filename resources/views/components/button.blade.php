@props(['type' => 'submit', 'variant' => 'primary'])

@php
$variants = [
    'primary' => 'bg-primary text-on-primary hover:opacity-90 focus:ring-primary/40',
    'ghost' => 'bg-transparent text-on-surface border border-outline-variant hover:bg-surface-container-high focus:ring-outline/30',
];
@endphp

<button
    type="{{ $type }}"
    {{ $attributes->merge(['class' => 'inline-flex w-full items-center justify-center rounded-lg px-4 py-2.5 text-sm font-semibold transition-colors focus:outline-none focus:ring-2 disabled:cursor-not-allowed disabled:opacity-60 '.($variants[$variant] ?? $variants['primary'])]) }}
>
    {{ $slot }}
</button>
