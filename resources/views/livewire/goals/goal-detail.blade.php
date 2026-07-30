@php
use App\Support\Number;
use Illuminate\Support\Str;

$statusLabels = [
    'active' => 'Activo', 'paused' => 'Pausado', 'completed' => 'Completado',
    'overdue' => 'Vencido', 'archived' => 'Archivado', 'cancelled' => 'Cancelado',
];
$radius = 54;
$circumference = 2 * M_PI * $radius;
$offset = $circumference * (1 - min(100, max(0, $progress['percentage'])) / 100);
@endphp

<div x-data="{ confirmingDelete: false }" class="mx-auto max-w-4xl space-y-6">
    <div class="flex items-center gap-2 text-sm text-on-surface-variant">
        <a href="{{ route('goals.index') }}" wire:navigate class="hover:text-on-surface">Objetivos</a>
        <span aria-hidden="true">/</span>
        <span class="truncate text-on-surface">{{ $goal->is_private ? 'Objetivo privado' : $goal->name }}</span>
    </div>

    @if (session('status'))
        <p class="rounded-lg bg-secondary-container px-3.5 py-2.5 text-sm text-on-secondary-container">{{ session('status') }}</p>
    @endif

    <div class="flex flex-col gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5 sm:flex-row sm:items-start sm:justify-between">
        <div class="flex items-start gap-4">
            <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-lg" style="background-color: {{ $goal->color }}26; color: {{ $goal->color }}">
                <x-icon :name="$goal->icon" class="h-6 w-6" />
            </span>
            <div>
                <h1 class="font-heading text-xl font-semibold text-on-surface">{{ $goal->is_private ? 'Objetivo privado' : $goal->name }}</h1>
                <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-sm text-on-surface-variant">
                    <span>{{ $goal->type->label() }}</span>
                    @if ($goal->category)
                        <span>· {{ $goal->category->name }}</span>
                    @endif
                    <span class="rounded-full bg-surface-container-high px-2 py-0.5 text-xs">{{ $statusLabels[$progress['display_status']] }}</span>
                </div>
                @if ($goal->description && ! $goal->is_private)
                    <p class="mt-2 text-sm text-on-surface-variant">{{ $goal->description }}</p>
                @endif
            </div>
        </div>

        <div class="flex shrink-0 flex-wrap items-center gap-1">
            <a href="{{ route('goals.edit', $goal) }}" wire:navigate class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Editar" aria-label="Editar objetivo">
                <x-icon name="edit" class="h-4 w-4" />
            </a>

            @if ($progress['display_status'] === 'active' || $progress['display_status'] === 'overdue')
                <button type="button" wire:click="complete" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Marcar como completado" aria-label="Marcar como completado">
                    <x-icon name="check" class="h-4 w-4" />
                </button>
                <button type="button" wire:click="pause" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Pausar" aria-label="Pausar objetivo">
                    <x-icon name="dot-circle" class="h-4 w-4" />
                </button>
            @elseif ($progress['display_status'] === 'paused')
                <button type="button" wire:click="resume" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Reanudar" aria-label="Reanudar objetivo">
                    <x-icon name="check" class="h-4 w-4" />
                </button>
            @elseif ($progress['display_status'] === 'completed')
                <button type="button" wire:click="reopen" wire:confirm="¿Reabrir este objetivo completado?" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Reabrir" aria-label="Reabrir objetivo completado">
                    <x-icon name="unarchive" class="h-4 w-4" />
                </button>
            @endif

            @if ($progress['display_status'] !== 'archived')
                <button type="button" wire:click="archive" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Archivar" aria-label="Archivar objetivo">
                    <x-icon name="archive" class="h-4 w-4" />
                </button>
            @else
                <button type="button" wire:click="unarchive" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Desarchivar" aria-label="Desarchivar objetivo">
                    <x-icon name="unarchive" class="h-4 w-4" />
                </button>
            @endif

            <template x-if="!confirmingDelete">
                <button type="button" @click="confirmingDelete = true" class="rounded-lg p-2 text-on-surface-variant hover:bg-error-container/40 hover:text-error" title="Eliminar" aria-label="Eliminar objetivo">
                    <x-icon name="trash" class="h-4 w-4" />
                </button>
            </template>
            <template x-if="confirmingDelete">
                <div class="flex items-center gap-1">
                    <button type="button" wire:click="delete" class="rounded-lg bg-error px-2 py-1 text-xs font-medium text-on-error">Confirmar</button>
                    <button type="button" @click="confirmingDelete = false" class="rounded-lg px-2 py-1 text-xs text-on-surface-variant">Cancelar</button>
                </div>
            </template>
        </div>
    </div>

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="flex flex-col items-center justify-center gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-6">
            <div class="relative flex h-32 w-32 items-center justify-center">
                <svg viewBox="0 0 120 120" class="h-32 w-32 -rotate-90" role="img" aria-label="Progreso: {{ $progress['percentage'] }}% completado">
                    <circle cx="60" cy="60" r="{{ $radius }}" stroke-width="10" fill="none" class="stroke-surface-container-high" />
                    <circle
                        cx="60" cy="60" r="{{ $radius }}" stroke-width="10" fill="none" stroke-linecap="round"
                        class="stroke-primary transition-all duration-300"
                        stroke-dasharray="{{ $circumference }}"
                        stroke-dashoffset="{{ $offset }}"
                    />
                </svg>
                <span class="absolute font-heading text-2xl font-semibold text-on-surface">{{ $progress['percentage'] }}%</span>
            </div>
            <p class="text-center text-sm text-on-surface-variant">
                @if ($progress['current'] !== null && $progress['target'] !== null)
                    {{ Number::trim($progress['current']) }} / {{ Number::trim($progress['target']) }} {{ $progress['unit'] }}
                @else
                    {{ $progress['is_achieved'] ? 'Completado' : 'Pendiente de completar' }}
                @endif
            </p>

            <div class="grid w-full grid-cols-2 gap-3 border-t border-outline-variant pt-4 text-center text-xs">
                <div>
                    <p class="text-on-surface-variant">Inicio</p>
                    <p class="font-medium text-on-surface">{{ Str::ucfirst($goal->start_date->translatedFormat('j M Y')) }}</p>
                </div>
                <div>
                    <p class="text-on-surface-variant">Fecha límite</p>
                    <p class="font-medium text-on-surface">{{ $goal->due_date ? Str::ucfirst($goal->due_date->translatedFormat('j M Y')) : 'Sin fecha límite' }}</p>
                </div>
                @if ($progress['days_remaining'] !== null)
                    <div class="col-span-2">
                        <p class="text-on-surface-variant">Días restantes</p>
                        <p class="font-medium text-on-surface">{{ $progress['days_remaining'] < 0 ? abs($progress['days_remaining']).' días de retraso' : $progress['days_remaining'] }}</p>
                    </div>
                @endif
                <div class="col-span-2">
                    <p class="text-on-surface-variant">Prioridad</p>
                    <p class="font-medium text-on-surface">{{ $goal->priority->label() }}</p>
                </div>
            </div>
        </div>

        <div class="space-y-4 lg:col-span-2">
            @if ($goal->habit)
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Hábito vinculado</h2>
                    <div class="flex items-center justify-between">
                        <a href="{{ route('habits.show', $goal->habit) }}" wire:navigate class="flex items-center gap-2 text-sm font-medium text-on-surface hover:underline">
                            <x-icon :name="$goal->habit->icon" class="h-4 w-4" />
                            {{ $goal->habit->is_private ? 'Hábito privado' : $goal->habit->name }}
                        </a>
                        <div class="flex items-center gap-3 text-xs text-on-surface-variant">
                            <span>Racha actual: <strong class="text-on-surface">{{ $progress['current_streak'] }}</strong></span>
                            <span>Mejor racha: <strong class="text-on-surface">{{ $progress['longest_streak'] }}</strong></span>
                        </div>
                    </div>
                </div>
            @endif

            @if ($insight)
                <div class="flex items-start gap-2 rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                    <x-icon name="sparkle" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                    <p class="text-sm text-on-surface">{{ $insight }}</p>
                </div>
            @endif

            @if ($goal->notes)
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                    <h2 class="mb-2 text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Notas</h2>
                    <p class="whitespace-pre-line text-sm text-on-surface">{{ $goal->notes }}</p>
                </div>
            @endif

            @if ($isNumeric)
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-on-surface-variant">
                        {{ $editingEntryId ? 'Editar registro' : 'Registrar avance' }}
                    </h2>
                    <form wire:submit="addProgress" class="flex flex-wrap items-end gap-3">
                        <div class="w-32">
                            <x-input-label for="progressValue" value="Cantidad añadida" />
                            <x-text-input id="progressValue" wire:model="progressValue" type="number" step="0.01" />
                            <x-input-error :messages="$errors->get('progressValue')" />
                        </div>
                        <div class="w-40">
                            <x-input-label for="progressDate" value="Fecha" />
                            <x-text-input id="progressDate" wire:model="progressDate" type="date" />
                            <x-input-error :messages="$errors->get('progressDate')" />
                        </div>
                        <div class="min-w-[10rem] flex-1">
                            <x-input-label for="progressNote" value="Nota (opcional)" />
                            <x-text-input id="progressNote" wire:model="progressNote" type="text" placeholder="¿Cómo te sentiste?" />
                            <x-input-error :messages="$errors->get('progressNote')" />
                        </div>
                        <x-button type="submit" class="w-auto px-4">{{ $editingEntryId ? 'Guardar' : 'Añadir' }}</x-button>
                        @if ($editingEntryId)
                            <button type="button" wire:click="cancelEditEntry" class="text-sm text-on-surface-variant hover:text-on-surface">Cancelar</button>
                        @endif
                    </form>
                </div>

                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Historial de registros</h2>
                    @if ($entries->isEmpty())
                        <p class="text-sm text-on-surface-variant">Todavía no hay registros de progreso.</p>
                    @else
                        <ul class="space-y-3">
                            @foreach ($entries as $entry)
                                <li class="flex items-start justify-between gap-3 border-b border-outline-variant pb-3 last:border-0 last:pb-0">
                                    <div class="min-w-0">
                                        <p class="text-sm font-medium text-on-surface">
                                            {{ $entry->value > 0 ? '+' : '' }}{{ Number::trim($entry->value) }} {{ $goal->unit }}
                                            <span class="ml-1 text-xs font-normal text-on-surface-variant">{{ Str::ucfirst($entry->recorded_at->translatedFormat('j M Y')) }}</span>
                                        </p>
                                        @if ($entry->note)
                                            <p class="mt-0.5 text-xs text-on-surface-variant">{{ $entry->note }}</p>
                                        @endif
                                    </div>
                                    <div class="flex shrink-0 items-center gap-1">
                                        <button type="button" wire:click="editEntry({{ $entry->id }})" class="rounded-lg p-1.5 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Corregir" aria-label="Corregir registro">
                                            <x-icon name="edit" class="h-3.5 w-3.5" />
                                        </button>
                                        <button type="button" wire:click="deleteEntry({{ $entry->id }})" wire:confirm="¿Eliminar este registro de progreso?" class="rounded-lg p-1.5 text-on-surface-variant hover:bg-error-container/40 hover:text-error" title="Eliminar" aria-label="Eliminar registro">
                                            <x-icon name="trash" class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>
            @endif
        </div>
    </div>
</div>
