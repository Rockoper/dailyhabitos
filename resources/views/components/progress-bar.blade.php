@props(['percentage' => 0, 'label' => null])

@php
$clamped = max(0, min(100, (float) $percentage));
@endphp

<div {{ $attributes }}>
    @if ($label)
        <div class="mb-1.5 flex items-center justify-between text-xs text-on-surface-variant">
            <span>{{ $label }}</span>
            <span class="font-medium text-on-surface">{{ rtrim(rtrim(number_format($clamped, 1), '0'), '.') }}%</span>
        </div>
    @endif
    <div class="h-2 w-full overflow-hidden rounded-full bg-surface-container-high">
        <div class="h-full rounded-full bg-primary transition-all duration-200" style="width: {{ $clamped }}%"></div>
    </div>
</div>
