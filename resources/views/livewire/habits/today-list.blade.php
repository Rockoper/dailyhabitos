@php
use App\Enums\HabitType;
use App\Enums\LogStatus;
@endphp

<div>
    <div class="mb-6 rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        <div class="flex items-center justify-between">
            <div>
                <p class="text-sm text-on-surface-variant">Progreso de hoy</p>
                <p class="font-heading text-2xl font-semibold text-on-surface">{{ $completedCount }} / {{ $totalCount }}</p>
            </div>
            <p class="font-heading text-2xl font-semibold text-primary">
                {{ $totalCount > 0 ? round($completedCount / $totalCount * 100) : 0 }}%
            </p>
        </div>
        <x-progress-bar :percentage="$totalCount > 0 ? $completedCount / $totalCount * 100 : 0" class="mt-3" />
    </div>

    @if ($items->isEmpty())
        <div class="rounded-xl border border-dashed border-outline-variant p-10 text-center">
            <p class="text-sm text-on-surface-variant">No tienes hábitos programados para hoy.</p>
            <a href="{{ route('habits.create') }}" wire:navigate class="mt-3 inline-block text-sm font-medium text-primary hover:underline">
                Crear un hábito
            </a>
        </div>
    @else
        <div class="space-y-3">
            @foreach ($items as $item)
                @php $habit = $item['habit']; $log = $item['log']; @endphp
                <div wire:key="today-{{ $habit->id }}" class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                    <div class="flex items-center gap-4">
                        <div
                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg"
                            style="background-color: {{ $habit->color }}26; color: {{ $habit->color }}"
                        >
                            <x-icon :name="$habit->icon" class="h-5 w-5" />
                        </div>

                        <div class="min-w-0 flex-1">
                            <a href="{{ route('habits.show', $habit) }}" wire:navigate class="block truncate font-medium text-on-surface hover:underline">
                                {{ $habit->is_private ? 'Hábito privado' : $habit->name }}
                            </a>
                            <div class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-on-surface-variant">
                                @if ($habit->category)
                                    <span>{{ $habit->category->name }}</span>
                                @endif
                                @if ($item['summary']['current_streak'] > 0)
                                    <x-streak-badge :days="$item['summary']['current_streak']" />
                                @endif
                                @if ($log)
                                    <x-status-badge :status="$log->status" />
                                @endif
                            </div>
                        </div>

                        @if ($habit->type !== HabitType::Quantity)
                            <button
                                type="button"
                                wire:click="toggleBinary({{ $habit->id }})"
                                class="flex h-11 w-11 shrink-0 items-center justify-center rounded-full border-2 transition-colors {{ $log?->status === LogStatus::Completed ? 'border-primary bg-primary text-on-primary' : 'border-outline-variant text-on-surface-variant hover:border-primary' }}"
                                aria-label="Marcar como cumplido"
                            >
                                <x-icon name="check" class="h-5 w-5" />
                            </button>
                        @endif
                    </div>

                    @if ($habit->type === HabitType::Quantity)
                        <div class="mt-3 flex items-center gap-2 pl-14">
                            <input
                                type="number" step="0.01" min="0"
                                wire:model="quantityInputs.{{ $habit->id }}"
                                class="w-28 rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm"
                                placeholder="0"
                            >
                            <span class="text-sm text-on-surface-variant">
                                {{ $habit->displayUnit() }}
                                @if ($habit->target_quantity)
                                    / meta {{ rtrim(rtrim((string) $habit->target_quantity, '0'), '.') }}
                                @endif
                            </span>
                            <x-button type="button" wire:click="logQuantity({{ $habit->id }})" variant="ghost" class="w-auto px-3 py-1.5 text-xs">
                                Guardar
                            </x-button>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
