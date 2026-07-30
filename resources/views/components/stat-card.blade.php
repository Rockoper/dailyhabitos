@props(['label', 'value', 'icon' => null, 'hint' => null])

<div {{ $attributes->merge(['class' => 'rounded-xl border border-outline-variant bg-surface-container-lowest p-4']) }}>
    <div class="flex items-center justify-between">
        <p class="text-xs font-medium text-on-surface-variant">{{ $label }}</p>
        @if ($icon)
            <x-icon :name="$icon" class="h-4 w-4 text-on-surface-variant" />
        @endif
    </div>
    <p class="mt-1.5 font-heading text-2xl font-semibold text-on-surface">{{ $value }}</p>
    @if ($hint)
        <p class="mt-0.5 text-xs text-on-surface-variant">{{ $hint }}</p>
    @endif
</div>
