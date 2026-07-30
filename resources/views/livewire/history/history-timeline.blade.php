@php
use App\Services\History\DailyHistorySummary;
use App\Services\History\HistoryEvent;
use Illuminate\Support\Str;

$periodOptions = [
    'today' => 'Hoy',
    'yesterday' => 'Ayer',
    'last7' => 'Últimos 7 días',
    'last30' => 'Últimos 30 días',
    'this_month' => 'Este mes',
    'last_month' => 'Mes anterior',
    'this_year' => 'Este año',
    'custom' => 'Rango personalizado',
];
$typeOptions = [
    'all' => 'Todos',
    'habits' => 'Hábitos',
    'goals' => 'Objetivos',
    'reflections' => 'Reflexiones',
    'achievements' => 'Logros y rachas',
    'system' => 'Cambios del sistema',
];
$statusOptions = ['all' => 'Todos', 'completed' => 'Completado', 'partial' => 'Parcial', 'failed' => 'No cumplido', 'skipped' => 'Omitido'];
$colorClasses = [
    'primary' => 'bg-primary-container text-on-primary-container',
    'secondary' => 'bg-secondary-container text-on-secondary-container',
    'tertiary' => 'bg-tertiary-container text-on-tertiary-container',
    'error' => 'bg-error-container/40 text-error',
    'gold' => 'bg-amber-400/20 text-amber-600 dark:text-amber-400',
];
@endphp

<div class="space-y-6" x-data="{ filtersOpen: false }">
    {{-- Encabezado --}}
    <div class="flex flex-col gap-4">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <h1 class="font-heading text-xl font-semibold text-on-surface">Historial</h1>
                <p class="mt-1 text-sm text-on-surface-variant">Revisa tu actividad, progreso y reflexiones a lo largo del tiempo.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <div class="relative">
                    <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-on-surface-variant" />
                    <input
                        type="search"
                        wire:model.live.debounce.400ms="search"
                        placeholder="Buscar hábito, objetivo o reflexión..."
                        aria-label="Buscar en el historial"
                        class="w-56 rounded-lg border border-outline-variant bg-surface-container-lowest py-2 pl-9 pr-3 text-sm text-on-surface placeholder:text-on-surface-variant/70 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30"
                    >
                </div>

                <x-select wire:model.live="period" aria-label="Selector de periodo" class="w-auto">
                    @foreach ($periodOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </x-select>

                <button type="button" wire:click="goToday" aria-label="Ver la actividad de hoy" class="rounded-lg border border-outline-variant px-3 py-2 text-sm font-medium text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40">
                    Hoy
                </button>

                @if ($hasActiveFilters)
                    <button type="button" wire:click="clearFilters" class="rounded-lg px-3 py-2 text-sm font-medium text-primary hover:underline focus:outline-none focus:ring-2 focus:ring-primary/40">
                        Limpiar filtros
                    </button>
                @endif

                <button type="button" x-on:click="filtersOpen = ! filtersOpen" class="rounded-lg border border-outline-variant p-2 text-on-surface-variant hover:bg-surface-container-high lg:hidden" aria-label="Mostrar u ocultar filtros" :aria-expanded="filtersOpen">
                    <x-icon name="filter" class="h-4 w-4" />
                </button>
            </div>
        </div>

        @if ($period === 'custom')
            <div class="flex flex-wrap items-center gap-2 text-sm">
                <x-input-label for="customFrom" value="Desde" class="mb-0" />
                <x-text-input id="customFrom" type="date" wire:model.live="customFrom" class="w-auto" />
                <x-input-label for="customTo" value="Hasta" class="mb-0" />
                <x-text-input id="customTo" type="date" wire:model.live="customTo" class="w-auto" />
            </div>
        @endif

        {{-- Filtros por tipo --}}
        <div class="flex flex-wrap gap-2" role="group" aria-label="Filtrar por tipo de actividad">
            @foreach ($typeOptions as $value => $label)
                <button type="button" wire:click="$set('typeFilter', '{{ $value }}')"
                    class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary/40 {{ $typeFilter === $value ? 'border-primary bg-primary-container text-on-primary-container' : 'border-outline-variant text-on-surface-variant hover:bg-surface-container-high' }}"
                    aria-pressed="{{ $typeFilter === $value ? 'true' : 'false' }}">
                    {{ $label }}
                </button>
            @endforeach
        </div>

        {{-- Filtros avanzados: siempre visibles en escritorio, plegables en móvil --}}
        <div :class="filtersOpen ? '' : 'hidden lg:flex'" class="flex flex-col gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 lg:flex-row lg:flex-wrap lg:items-center lg:border-0 lg:bg-transparent lg:p-0">
            <x-select wire:model.live="statusFilter" aria-label="Estado del hábito" class="lg:w-auto">
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">Estado: {{ $label }}</option>
                @endforeach
            </x-select>

            <x-select wire:model.live="habitId" aria-label="Filtrar por hábito" class="lg:w-auto">
                <option value="">Todos los hábitos</option>
                @foreach ($this->habits as $habit)
                    <option value="{{ $habit->id }}">{{ $habit->is_private ? 'Hábito privado' : $habit->name }}</option>
                @endforeach
            </x-select>

            <x-select wire:model.live="goalId" aria-label="Filtrar por objetivo" class="lg:w-auto">
                <option value="">Todos los objetivos</option>
                @foreach ($this->goals as $goal)
                    <option value="{{ $goal->id }}">{{ $goal->is_private ? 'Objetivo privado' : $goal->name }}</option>
                @endforeach
            </x-select>

            <x-select wire:model.live="categoryId" aria-label="Filtrar por categoría" class="lg:w-auto">
                <option value="">Todas las categorías</option>
                @foreach ($this->categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </x-select>

            <label class="flex items-center gap-2 text-xs text-on-surface-variant">
                <input type="checkbox" wire:model.live="onlyPerfectDays" class="rounded border-outline-variant text-primary focus:ring-primary/40">
                Solo días perfectos
            </label>
            <label class="flex items-center gap-2 text-xs text-on-surface-variant">
                <input type="checkbox" wire:model.live="onlyWithReflection" class="rounded border-outline-variant text-primary focus:ring-primary/40">
                Solo días con reflexión
            </label>
            <label class="flex items-center gap-2 text-xs text-on-surface-variant">
                <input type="checkbox" wire:model.live="onlyManual" class="rounded border-outline-variant text-primary focus:ring-primary/40">
                Solo actividad manual
            </label>
            <label class="flex items-center gap-2 text-xs text-on-surface-variant">
                <input type="checkbox" wire:model.live="onlyAutomatic" class="rounded border-outline-variant text-primary focus:ring-primary/40">
                Solo actividad automática
            </label>
        </div>
    </div>

    @if (! $hasAnyActivityEver)
        {{-- Estado vacío: nunca hubo actividad --}}
        <div class="flex flex-col items-center justify-center gap-4 rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-16 text-center">
            <x-icon name="history" class="h-10 w-10 text-on-surface-variant" />
            <div>
                <p class="font-heading text-lg font-semibold text-on-surface">Aún no tienes actividad registrada</p>
                <p class="mt-2 max-w-md text-sm text-on-surface-variant">Cuando completes hábitos, avances en objetivos o escribas reflexiones, aparecerán aquí.</p>
            </div>
            <div class="flex flex-wrap justify-center gap-3">
                <a href="{{ route('habits.create') }}" wire:navigate>
                    <x-button type="button" class="w-auto px-5">Crear hábito</x-button>
                </a>
                <a href="{{ route('reflections.index') }}" wire:navigate>
                    <x-button type="button" variant="ghost" class="w-auto px-5">Escribir reflexión</x-button>
                </a>
            </div>
        </div>
    @else
        {{-- KPIs compactos --}}
        <div class="grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-6">
            @foreach ([
                ['label' => 'Eventos', 'value' => $kpis['events'], 'icon' => 'chart'],
                ['label' => 'Hábitos completados', 'value' => $kpis['habits_completed'], 'icon' => 'check'],
                ['label' => 'Objetivos con progreso', 'value' => $kpis['goals_with_progress'], 'icon' => 'target'],
                ['label' => 'Reflexiones', 'value' => $kpis['reflections'], 'icon' => 'note'],
                ['label' => 'Días perfectos', 'value' => $kpis['perfect_days'], 'icon' => 'star'],
                ['label' => 'Racha de actividad', 'value' => $kpis['activity_streak'].' días', 'icon' => 'flame'],
            ] as $kpi)
                <div class="rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2.5">
                    <div class="flex items-center gap-1.5 text-on-surface-variant">
                        <x-icon :name="$kpi['icon']" class="h-3.5 w-3.5" />
                        <span class="text-[11px] font-medium leading-tight">{{ $kpi['label'] }}</span>
                    </div>
                    <p class="mt-1 font-heading text-lg font-semibold text-on-surface">{{ $kpi['value'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Navegación por mes --}}
        <div class="flex items-center justify-center gap-3">
            <button type="button" wire:click="goToPreviousMonth" aria-label="Mes anterior" class="flex h-8 w-8 items-center justify-center rounded-md border border-outline-variant text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40">
                <x-icon name="chevron-left" class="h-4 w-4" />
            </button>
            <span class="min-w-[8rem] text-center text-sm font-semibold text-on-surface">{{ $monthLabel }}</span>
            <button type="button" wire:click="goToNextMonth" @disabled(! $canGoNextMonth) aria-label="Mes siguiente" class="flex h-8 w-8 items-center justify-center rounded-md border border-outline-variant text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40 disabled:cursor-not-allowed disabled:opacity-40">
                <x-icon name="chevron-right" class="h-4 w-4" />
            </button>
            <button type="button" wire:click="goToCurrentMonth" class="rounded-md px-2.5 py-1 text-xs font-medium text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface">
                Hoy
            </button>
        </div>

        <div class="grid gap-6 lg:grid-cols-[1fr_18rem]">
            {{-- Línea de tiempo --}}
            <div wire:loading.class="opacity-50" wire:target="period,customFrom,customTo,typeFilter,statusFilter,habitId,goalId,categoryId,onlyPerfectDays,onlyWithReflection,onlyManual,onlyAutomatic,search,loadMore" aria-live="polite">
                @if ($groups->isEmpty())
                    <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-14 text-center">
                        <p class="font-heading text-base font-semibold text-on-surface">No encontramos actividad con estos filtros</p>
                        <button type="button" wire:click="clearFilters" class="text-sm font-semibold text-primary hover:underline">Limpiar filtros</button>
                    </div>
                @else
                    <ol class="relative space-y-8 border-l border-outline-variant pl-6" aria-label="Línea de tiempo de actividad">
                        @foreach ($groups as $group)
                            <li wire:key="day-{{ $group->date->format('Y-m-d') }}">
                                <div class="absolute -left-[7px] mt-1.5 h-3 w-3 rounded-full border-2 border-surface bg-primary"></div>

                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="font-heading text-sm font-semibold text-on-surface">
                                        {{ Str::ucfirst($group->date->translatedFormat('l, j \d\e F \d\e Y')) }}
                                        @if ($group->isToday)
                                            · Hoy
                                        @elseif ($group->isYesterday)
                                            · Ayer
                                        @endif
                                    </h3>
                                    @if ($group->expectedHabits > 0)
                                        <span class="rounded-full px-2 py-0.5 text-[11px] font-medium {{ $group->dayLevel === 'completed' ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-high text-on-surface-variant' }}">
                                            {{ $group->dayLabel() }}
                                        </span>
                                    @endif
                                    <span class="text-xs text-on-surface-variant">{{ $group->events->count() }} {{ $group->events->count() === 1 ? 'evento' : 'eventos' }}</span>
                                </div>

                                <div class="mt-3 space-y-2">
                                    @foreach ($group->events as $event)
                                        <div wire:key="event-{{ $group->date->format('Y-m-d') }}-{{ $loop->index }}" class="flex items-start gap-3 rounded-lg border border-outline-variant bg-surface-container-lowest p-3">
                                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full {{ $colorClasses[$event->color] ?? $colorClasses['secondary'] }}">
                                                <x-icon :name="$event->icon" class="h-4 w-4" />
                                            </span>
                                            <div class="min-w-0 flex-1">
                                                <div class="flex flex-wrap items-baseline gap-x-2">
                                                    @if ($event->occurredAt)
                                                        <span class="text-xs font-medium tabular-nums text-on-surface-variant">{{ $event->occurredAt->format('g:i a') }}</span>
                                                    @endif
                                                    <p class="text-sm font-medium text-on-surface">{{ $event->title }}</p>
                                                </div>
                                                @if ($event->description)
                                                    <p class="mt-0.5 text-xs text-on-surface-variant">{{ $event->description }}</p>
                                                @endif
                                            </div>
                                            @if ($event->route)
                                                <a href="{{ route($event->route, $event->routeParams) }}" wire:navigate aria-label="Abrir {{ $event->title }}" class="shrink-0 text-xs font-semibold text-primary hover:underline">
                                                    Abrir
                                                </a>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            </li>
                        @endforeach
                    </ol>

                    @if ($canLoadMore)
                        <button type="button" wire:click="loadMore" wire:loading.attr="disabled" wire:target="loadMore" class="mt-6 flex w-full items-center justify-center gap-2 rounded-lg border border-outline-variant py-2.5 text-sm font-medium text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40 disabled:opacity-60">
                            <span wire:loading.remove wire:target="loadMore">Cargar actividad anterior</span>
                            <span wire:loading wire:target="loadMore">Cargando…</span>
                        </button>
                    @endif
                @endif
            </div>

            {{-- Observaciones --}}
            @if (! empty($insights))
                <div class="h-fit rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                    <h2 class="font-heading text-sm font-semibold text-on-surface">Observaciones del periodo</h2>
                    <ul class="mt-3 space-y-2">
                        @foreach ($insights as $insight)
                            <li class="flex items-start gap-2 text-xs text-on-surface">
                                <x-icon name="sparkle" class="mt-0.5 h-3.5 w-3.5 shrink-0 text-primary" />
                                {{ $insight }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </div>
    @endif
</div>
