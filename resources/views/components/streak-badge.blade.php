@props(['days', 'icon' => 'flame'])

<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 rounded-full bg-primary-container px-2.5 py-1 text-xs font-semibold text-on-primary-container']) }}>
    <x-icon :name="$icon" class="h-3.5 w-3.5" />
    {{ $days }} {{ Str::plural('día', $days) }}
</span>
