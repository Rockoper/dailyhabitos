@php
use App\Support\Number;
use Illuminate\Support\Str;

$statusLabels = [
    'active' => 'Activo',
    'paused' => 'Pausado',
    'completed' => 'Completado',
    'overdue' => 'Vencido',
    'archived' => 'Archivado',
    'cancelled' => 'Cancelado',
];
$statusClasses = [
    'active' => 'bg-primary-container text-on-primary-container',
    'paused' => 'bg-surface-container-high text-on-surface-variant',
    'completed' => 'bg-primary text-on-primary',
    'overdue' => 'bg-error-container/40 text-error',
    'archived' => 'bg-surface-container-high text-on-surface-variant',
    'cancelled' => 'bg-surface-container-high text-on-surface-variant',
];
$priorityClasses = [
    'low' => 'border-outline-variant text-on-surface-variant',
    'medium' => 'border-secondary text-secondary',
    'high' => 'border-error text-error',
];
$riskLabels = [
    'on_track' => 'En buen camino',
    'attention' => 'Requiere atención',
    'at_risk' => 'En riesgo',
    'overdue' => 'Vencido',
];
$riskClasses = [
    'on_track' => 'bg-primary-container text-on-primary-container',
    'attention' => 'bg-secondary-container text-on-secondary-container',
    'at_risk' => 'bg-error-container/40 text-error',
    'overdue' => 'bg-error-container/40 text-error',
];
@endphp

<div class="space-y-6" x-data>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="font-heading text-xl font-semibold text-on-surface">Objetivos</h1>
            <p class="mt-1 text-sm text-on-surface-variant">Metas personales medidas con tus hábitos, cantidades o fechas límite.</p>
        </div>
        <a href="{{ route('goals.create') }}" wire:navigate>
            <x-button type="button" class="w-auto px-4">
                <x-icon name="plus" class="mr-1.5 h-4 w-4" /> Nuevo objetivo
            </x-button>
        </a>
    </div>

    @if (session('status'))
        <p class="rounded-lg bg-secondary-container px-3.5 py-2.5 text-sm text-on-secondary-container">{{ session('status') }}</p>
    @endif

    @if (! $hasAnyGoal)
        <div class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-16 text-center">
            <x-icon name="target" class="h-10 w-10 text-on-surface-variant" />
            <div>
                <p class="font-heading text-lg font-semibold text-on-surface">Aún no has creado objetivos</p>
                <p class="mt-2 max-w-md text-sm text-on-surface-variant">Define metas concretas — con fecha, cantidad o vinculadas a un hábito — y sigue tu progreso real aquí.</p>
            </div>

            <div class="grid w-full max-w-2xl gap-3 sm:grid-cols-3">
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 text-left">
                    <x-icon name="flame" class="h-5 w-5 text-primary" />
                    <p class="mt-2 text-sm font-medium text-on-surface">Mantener una racha de 30 días</p>
                    <p class="mt-0.5 text-xs text-on-surface-variant">Objetivo de racha</p>
                </div>
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 text-left">
                    <x-icon name="book" class="h-5 w-5 text-primary" />
                    <p class="mt-2 text-sm font-medium text-on-surface">Leer 12 libros este año</p>
                    <p class="mt-0.5 text-xs text-on-surface-variant">Objetivo numérico</p>
                </div>
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 text-left">
                    <x-icon name="coin" class="h-5 w-5 text-primary" />
                    <p class="mt-2 text-sm font-medium text-on-surface">Ahorrar 5.000.000 COP</p>
                    <p class="mt-0.5 text-xs text-on-surface-variant">Objetivo numérico</p>
                </div>
            </div>

            <a href="{{ route('goals.create') }}" wire:navigate>
                <x-button type="button" class="w-auto px-6">Crear mi primer objetivo</x-button>
            </a>
        </div>
    @else
        {{-- Resumen --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-stat-card label="Objetivos activos" :value="$summary['active_count']" icon="target" />
            <x-stat-card label="Completados" :value="$summary['completed_count']" icon="check" />
            <x-stat-card label="Progreso promedio" :value="$summary['average_progress'].'%'" icon="chart" />
            <x-stat-card label="Próximos a vencer" :value="$summary['upcoming_count']" icon="bell" title="Vencen en los próximos 7 días" />
            <x-stat-card label="Vencidos" :value="$summary['overdue_count']" icon="x-circle" />
            <x-stat-card label="Mejor objetivo en progreso" :value="$summary['best_ongoing']['name'] ?? '—'" :hint="$summary['best_ongoing'] ? $summary['best_ongoing']['percentage'].'%' : null" icon="star" />
            <x-stat-card label="Metas alcanzadas en total" :value="$summary['total_achieved']" icon="trophy" />
        </div>

        {{-- Filtros --}}
        <div class="space-y-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
            <div class="flex flex-wrap gap-1.5" role="group" aria-label="Filtrar por estado">
                @foreach (['all' => 'Todos', 'active' => 'Activos', 'completed' => 'Completados', 'paused' => 'Pausados', 'overdue' => 'Vencidos', 'archived' => 'Archivados'] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('statusFilter', '{{ $value }}')"
                        aria-pressed="{{ $statusFilter === $value ? 'true' : 'false' }}"
                        class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary/40 {{ $statusFilter === $value ? 'border-primary bg-primary text-on-primary' : 'border-outline-variant text-on-surface-variant hover:bg-surface-container-high' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>

            <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-5">
                <x-select wire:model.live="priorityFilter" aria-label="Filtrar por prioridad">
                    <option value="">Toda prioridad</option>
                    @foreach ($priorities as $priority)
                        <option value="{{ $priority->value }}">{{ $priority->label() }}</option>
                    @endforeach
                </x-select>
                <x-select wire:model.live="categoryId" aria-label="Filtrar por categoría" :disabled="$categories->isEmpty()">
                    <option value="">Toda categoría</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </x-select>
                <x-select wire:model.live="typeFilter" aria-label="Filtrar por tipo">
                    <option value="">Todo tipo</option>
                    @foreach ($goalTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </x-select>
                <x-select wire:model.live="habitId" aria-label="Filtrar por hábito vinculado">
                    <option value="">Todo hábito</option>
                    @foreach ($allHabits as $habit)
                        <option value="{{ $habit->id }}">{{ $habit->is_private ? 'Hábito privado' : $habit->name }}</option>
                    @endforeach
                </x-select>
                <x-select wire:model.live="sortBy" aria-label="Ordenar objetivos">
                    <option value="progress_desc">Progreso</option>
                    <option value="due_date_asc">Fecha límite</option>
                    <option value="upcoming">Próximos a vencer</option>
                    <option value="priority">Prioridad</option>
                    <option value="newest">Más recientes</option>
                    <option value="oldest">Más antiguos</option>
                    <option value="name">Nombre</option>
                </x-select>
            </div>
        </div>

        <div wire:loading.flex class="hidden items-center gap-2 text-sm text-on-surface-variant" aria-live="polite">
            <span class="h-2 w-2 animate-pulse rounded-full bg-primary"></span> Actualizando objetivos…
        </div>

        <div wire:loading.class="opacity-50" class="space-y-6 transition-opacity">
            {{-- Próximos vencimientos --}}
            @if ($upcoming->isNotEmpty())
                <div>
                    <h2 class="mb-3 font-heading text-base font-semibold text-on-surface">Próximos vencimientos</h2>
                    <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($upcoming as $row)
                            <a href="{{ route('goals.show', $row['goal']) }}" wire:navigate class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4 hover:border-primary">
                                <p class="truncate text-sm font-medium text-on-surface">{{ $row['goal']->is_private ? 'Objetivo privado' : $row['goal']->name }}</p>
                                <p class="mt-1 text-xs text-on-surface-variant">
                                    {{ $row['progress']['days_remaining'] < 0 ? abs($row['progress']['days_remaining']).' días de retraso' : $row['progress']['days_remaining'].' días restantes' }}
                                </p>
                                <span class="mt-2 inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium {{ $riskClasses[$row['progress']['risk_level']] ?? '' }}">
                                    {{ $riskLabels[$row['progress']['risk_level']] ?? '' }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Listado principal --}}
            @if ($rows->isEmpty())
                <div class="rounded-xl border border-dashed border-outline-variant p-10 text-center">
                    <p class="text-sm text-on-surface-variant">Ningún objetivo coincide con los filtros seleccionados.</p>
                </div>
            @else
                <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                    @foreach ($rows as $row)
                        @php [$goal, $progress] = [$row['goal'], $row['progress']]; @endphp
                        <div wire:key="goal-{{ $goal->id }}" x-data="{ confirmingDelete: false }" class="flex flex-col gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex min-w-0 items-center gap-2">
                                    <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg" style="background-color: {{ $goal->color }}26; color: {{ $goal->color }}">
                                        <x-icon :name="$goal->icon" class="h-4.5 w-4.5" />
                                    </span>
                                    <div class="min-w-0">
                                        <a href="{{ route('goals.show', $goal) }}" wire:navigate class="block truncate font-medium text-on-surface hover:underline">
                                            {{ $goal->is_private ? 'Objetivo privado' : $goal->name }}
                                        </a>
                                        <p class="truncate text-xs text-on-surface-variant">{{ $goal->category?->name ?? 'Sin categoría' }} · {{ $goal->type->label() }}</p>
                                    </div>
                                </div>
                                <span class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide {{ $priorityClasses[$goal->priority->value] }}">
                                    {{ $goal->priority->label() }}
                                </span>
                            </div>

                            <div>
                                <div class="mb-1 flex items-center justify-between text-xs text-on-surface-variant">
                                    <span class="inline-flex items-center rounded-full px-2 py-0.5 font-medium {{ $statusClasses[$progress['display_status']] }}">
                                        {{ $statusLabels[$progress['display_status']] }}
                                    </span>
                                    <span>{{ $progress['percentage'] }}%</span>
                                </div>
                                <div class="h-2 w-full overflow-hidden rounded-full bg-surface-container-high" role="progressbar" aria-valuenow="{{ $progress['percentage'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Progreso de {{ $goal->is_private ? 'objetivo privado' : $goal->name }}">
                                    <div class="h-full rounded-full bg-primary transition-all duration-200" style="width: {{ $progress['percentage'] }}%"></div>
                                </div>
                                @if ($progress['current'] !== null && $progress['target'] !== null)
                                    <p class="mt-1 text-xs text-on-surface-variant">{{ Number::trim($progress['current']) }} / {{ Number::trim($progress['target']) }} {{ $progress['unit'] }}</p>
                                @endif
                            </div>

                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-on-surface-variant">
                                @if ($goal->due_date)
                                    <span>{{ Str::ucfirst($goal->due_date->translatedFormat('j M Y')) }}</span>
                                    <span>{{ $progress['days_remaining'] < 0 ? abs($progress['days_remaining']).' días de retraso' : $progress['days_remaining'].' días restantes' }}</span>
                                @endif
                                @if ($goal->habit)
                                    <span class="inline-flex items-center gap-1"><x-icon name="flame" class="h-3.5 w-3.5" /> {{ $goal->habit->is_private ? 'Hábito privado' : $goal->habit->name }}</span>
                                @endif
                            </div>

                            <div class="mt-auto flex flex-wrap items-center gap-1 border-t border-outline-variant pt-3">
                                <a href="{{ route('goals.show', $goal) }}" wire:navigate class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Ver detalle" aria-label="Ver detalle de {{ $goal->is_private ? 'objetivo privado' : $goal->name }}">
                                    <x-icon name="chart" class="h-4 w-4" />
                                </a>
                                <a href="{{ route('goals.edit', $goal) }}" wire:navigate class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Editar" aria-label="Editar objetivo">
                                    <x-icon name="edit" class="h-4 w-4" />
                                </a>

                                @if ($progress['display_status'] === 'active' || $progress['display_status'] === 'overdue')
                                    <button type="button" wire:click="complete({{ $goal->id }})" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Marcar como completado" aria-label="Marcar como completado">
                                        <x-icon name="check" class="h-4 w-4" />
                                    </button>
                                    <button type="button" wire:click="pause({{ $goal->id }})" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Pausar" aria-label="Pausar objetivo">
                                        <x-icon name="dot-circle" class="h-4 w-4" />
                                    </button>
                                @elseif ($progress['display_status'] === 'paused')
                                    <button type="button" wire:click="resume({{ $goal->id }})" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Reanudar" aria-label="Reanudar objetivo">
                                        <x-icon name="check" class="h-4 w-4" />
                                    </button>
                                @endif

                                @if ($progress['display_status'] !== 'archived')
                                    <button type="button" wire:click="archive({{ $goal->id }})" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Archivar" aria-label="Archivar objetivo">
                                        <x-icon name="archive" class="h-4 w-4" />
                                    </button>
                                @else
                                    <button type="button" wire:click="unarchive({{ $goal->id }})" class="rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface" title="Desarchivar" aria-label="Desarchivar objetivo">
                                        <x-icon name="unarchive" class="h-4 w-4" />
                                    </button>
                                @endif

                                <template x-if="!confirmingDelete">
                                    <button type="button" @click="confirmingDelete = true" class="ml-auto rounded-lg p-2 text-on-surface-variant hover:bg-error-container/40 hover:text-error" title="Eliminar" aria-label="Eliminar objetivo">
                                        <x-icon name="trash" class="h-4 w-4" />
                                    </button>
                                </template>
                                <template x-if="confirmingDelete">
                                    <div class="ml-auto flex items-center gap-1">
                                        <button type="button" wire:click="delete({{ $goal->id }})" class="rounded-lg bg-error px-2 py-1 text-xs font-medium text-on-error">Confirmar</button>
                                        <button type="button" @click="confirmingDelete = false" class="rounded-lg px-2 py-1 text-xs text-on-surface-variant">Cancelar</button>
                                    </div>
                                </template>
                            </div>
                        </div>
                    @endforeach
                </div>
            @endif

            {{-- Completados recientemente --}}
            @if ($recentlyCompleted->isNotEmpty())
                <div>
                    <h2 class="mb-3 font-heading text-base font-semibold text-on-surface">Objetivos completados recientemente</h2>
                    <div class="space-y-2 opacity-80">
                        @foreach ($recentlyCompleted as $row)
                            <a href="{{ route('goals.show', $row['goal']) }}" wire:navigate class="flex items-center justify-between gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-3 hover:border-primary">
                                <span class="flex items-center gap-2 truncate text-sm text-on-surface">
                                    <x-icon name="check" class="h-4 w-4 text-primary" />
                                    {{ $row['goal']->is_private ? 'Objetivo privado' : $row['goal']->name }}
                                </span>
                                <span class="shrink-0 text-xs text-on-surface-variant">
                                    Completado el {{ $row['goal']->completed_at?->translatedFormat('j M Y') }}
                                </span>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            {{-- Conclusiones --}}
            @if (! empty($insights))
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
                    <h2 class="mb-3 font-heading text-base font-semibold text-on-surface">Tendencias y conclusiones</h2>
                    <ul class="space-y-2">
                        @foreach ($insights as $insight)
                            <li class="flex items-start gap-2 text-sm text-on-surface">
                                <x-icon name="sparkle" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                                <span>{{ $insight }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</div>
