@props(['status'])

@php
$styles = [
    'completed' => 'bg-primary-container text-on-primary-container',
    'failed' => 'bg-error-container/40 text-error',
    'skipped' => 'bg-surface-container-high text-on-surface-variant',
    'partial' => 'bg-secondary-container text-on-secondary-container',
];
$icons = [
    'completed' => 'check',
    'failed' => 'x-circle',
    'skipped' => 'dot-circle',
    'partial' => 'dot-circle',
];
$key = $status instanceof \App\Enums\LogStatus ? $status->value : $status;
$label = $status instanceof \App\Enums\LogStatus ? $status->label() : $key;
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium '.($styles[$key] ?? $styles['skipped'])]) }}>
    <x-icon :name="$icons[$key] ?? 'dot-circle'" class="h-3.5 w-3.5" />
    {{ $label }}
</span>
