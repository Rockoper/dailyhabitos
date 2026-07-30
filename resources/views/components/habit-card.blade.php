@props(['habit', 'currentStreak' => null, 'href' => null])

<div {{ $attributes->merge(['class' => 'flex items-center gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-4']) }}>
    <div
        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
        style="background-color: {{ $habit->color }}26; color: {{ $habit->color }}"
    >
        <x-icon :name="$habit->icon" class="h-5 w-5" />
    </div>

    <div class="min-w-0 flex-1">
        <a href="{{ $href ?? route('habits.show', $habit) }}" class="block truncate font-medium text-on-surface hover:underline">
            {{ $habit->is_private ? 'Hábito privado' : $habit->name }}
        </a>
        <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-on-surface-variant">
            @if ($habit->category)
                <span>{{ $habit->category->name }}</span>
            @endif
            @if (! is_null($currentStreak) && $currentStreak > 0)
                <x-streak-badge :days="$currentStreak" />
            @endif
        </div>
    </div>

    @isset($action)
        <div class="shrink-0">
            {{ $action }}
        </div>
    @endisset
</div>
