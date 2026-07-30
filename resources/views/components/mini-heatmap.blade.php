@props(['cells'])

@php
$levelClasses = [
    'completed' => 'bg-primary',
    'partial' => 'bg-primary/40',
    'pending' => 'border border-outline-variant bg-surface-container-lowest',
    'failed' => 'bg-error/60',
    'rest' => 'bg-surface-container',
];
$columns = $cells->chunk(7);
@endphp

<div {{ $attributes->merge(['class' => 'overflow-x-auto']) }}>
    <div class="inline-flex gap-1 py-1">
        @foreach ($columns as $column)
            <div class="flex flex-col gap-1">
                @foreach ($column as $cell)
                    <div
                        class="h-3 w-3 rounded-sm {{ $levelClasses[$cell['level']] ?? $levelClasses['rest'] }}"
                        title="{{ $cell['date']->translatedFormat('j \d\e F Y') }}"
                    ></div>
                @endforeach
            </div>
        @endforeach
    </div>
    <div class="mt-2 flex items-center gap-1.5 text-xs text-on-surface-variant">
        <span>Menos</span>
        <span class="h-3 w-3 rounded-sm {{ $levelClasses['rest'] }}"></span>
        <span class="h-3 w-3 rounded-sm {{ $levelClasses['partial'] }}"></span>
        <span class="h-3 w-3 rounded-sm {{ $levelClasses['completed'] }}"></span>
        <span class="h-3 w-3 rounded-sm {{ $levelClasses['failed'] }}"></span>
        <span>Más</span>
    </div>
</div>
