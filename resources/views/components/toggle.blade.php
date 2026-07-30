@props(['label', 'description' => null])

<label class="flex cursor-pointer items-start justify-between gap-4 py-2">
    <span>
        <span class="block text-sm font-medium text-on-surface">{{ $label }}</span>
        @if ($description)
            <span class="block text-xs text-on-surface-variant">{{ $description }}</span>
        @endif
    </span>
    <span class="relative mt-0.5 inline-flex shrink-0 items-center">
        <input type="checkbox" {{ $attributes->merge(['class' => 'peer sr-only']) }}>
        <span class="h-6 w-11 rounded-full bg-surface-container-high transition-colors peer-checked:bg-primary"></span>
        <span class="pointer-events-none absolute left-1 h-4 w-4 rounded-full bg-surface-container-lowest transition-transform peer-checked:translate-x-5"></span>
    </span>
</label>
