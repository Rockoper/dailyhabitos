@php
use Illuminate\Support\Str;

$dayLevelClasses = [
    'completed' => 'bg-primary',
    'partial' => 'bg-primary/40',
    'pending' => 'border-2 border-dashed border-outline-variant bg-surface-container-lowest',
    'none' => 'bg-surface-container',
    'future' => 'bg-surface-container/60',
];
$dayLevelLabels = [
    'none' => 'Sin actividad',
    'pending' => 'Pendiente',
    'partial' => 'Parcial',
    'completed' => 'Completado',
    'future' => 'Día futuro',
];
@endphp

<div class="space-y-6" x-data x-on:keydown.escape.window="$wire.closeDay()">
    {{-- Encabezado y selector de año --}}
    <div class="flex flex-col gap-4 rounded-xl border border-outline-variant bg-surface-container-lowest p-5 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h1 class="font-heading text-xl font-semibold text-on-surface">Calendario anual de progreso</h1>
            <p class="mt-1 text-sm text-on-surface-variant">Cumplimiento de tus hábitos día a día, semana a semana, mes a mes.</p>
        </div>

        <div class="flex items-center gap-2">
            <button type="button" wire:click="previousYear" aria-label="Año anterior" class="rounded-lg border border-outline-variant p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40">
                <x-icon name="chevron-left" class="h-4 w-4" />
            </button>

            <span class="min-w-[4.5rem] text-center font-heading text-lg font-semibold text-on-surface" aria-live="polite">{{ $year }}</span>

            <button type="button" wire:click="nextYear" aria-label="Año siguiente" class="rounded-lg border border-outline-variant p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40">
                <x-icon name="chevron-right" class="h-4 w-4" />
            </button>

            <button type="button" wire:click="goToToday" aria-label="Ir al año actual" class="rounded-lg border border-outline-variant px-3 py-2 text-sm font-medium text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40">
                Hoy
            </button>
        </div>
    </div>

    @if (! $hasAnyHabit)
        <div class="flex flex-col items-center justify-center rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-20 text-center">
            <p class="font-heading text-lg font-semibold text-on-surface">Todavía no tienes hábitos</p>
            <p class="mt-2 max-w-md text-sm text-on-surface-variant">Crea tu primer hábito para empezar a ver tu progreso en este calendario.</p>
            <a href="{{ route('habits.create') }}" wire:navigate class="mt-4 inline-block text-sm font-medium text-primary hover:underline">Crear tu primer hábito</a>
        </div>
    @else
        {{-- Resumen anual --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <x-stat-card label="Días completados" :value="$summary['completed_days']" icon="check" />
            <x-stat-card label="Cumplimiento anual" :value="$summary['annual_percentage'].'%'" icon="chart" />
            <x-stat-card label="Mejor racha del año" :value="$summary['longest_streak'].' '.Str::plural('día', $summary['longest_streak'])" icon="trophy" />
            <x-stat-card label="Racha actual" :value="$summary['current_streak'].' '.Str::plural('día', $summary['current_streak'])" icon="flame" />
            <x-stat-card label="Hábitos completados" :value="$summary['completed_habits_total']" icon="target" />
            <x-stat-card label="Mejor mes" :value="$summary['best_month'] ?? '—'" icon="star" />
        </div>

        {{-- Filtros --}}
        <div class="grid gap-3 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="calendar-habit" class="sr-only">Filtrar por hábito</label>
                <x-select id="calendar-habit" wire:model.live="habitId">
                    <option value="">Todos los hábitos</option>
                    @foreach ($allHabits as $habit)
                        <option value="{{ $habit->id }}">{{ $habit->is_private ? 'Hábito privado' : $habit->name }}</option>
                    @endforeach
                </x-select>
            </div>

            <div>
                <label for="calendar-category" class="sr-only">Filtrar por categoría</label>
                <x-select id="calendar-category" wire:model.live="categoryId" :disabled="$categories->isEmpty()">
                    <option value="">Todas las categorías</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </x-select>
            </div>

            <div>
                <label for="calendar-status" class="sr-only">Filtrar por estado</label>
                <x-select id="calendar-status" wire:model.live="statusFilter">
                    <option value="">Todos los estados</option>
                    <option value="completed">Completados</option>
                    <option value="partial">Parciales</option>
                    <option value="pending">Pendientes</option>
                </x-select>
            </div>

            <div>
                <label for="calendar-frequency" class="sr-only">Filtrar por frecuencia</label>
                <x-select id="calendar-frequency" wire:model.live="frequencyType">
                    <option value="">Todas las frecuencias</option>
                    @foreach ($frequencyTypes as $freq)
                        <option value="{{ $freq->value }}">{{ $freq->label() }}</option>
                    @endforeach
                </x-select>
            </div>
        </div>

        <div wire:loading.flex wire:target="previousYear,nextYear,goToToday,habitId,categoryId,frequencyType,statusFilter" class="hidden items-center gap-2 text-sm text-on-surface-variant">
            <span class="h-2 w-2 animate-pulse rounded-full bg-primary"></span> Actualizando calendario…
        </div>

        {{-- Cuadrícula anual --}}
        <div wire:loading.class="opacity-50" wire:target="previousYear,nextYear,goToToday,habitId,categoryId,frequencyType,statusFilter" class="grid gap-4 transition-opacity sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
            @foreach ($months as $month)
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4">
                    <h3 class="mb-2 text-center font-heading text-sm font-semibold text-on-surface">{{ $month['name'] }}</h3>

                    <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-medium text-on-surface-variant" aria-hidden="true">
                        <span>L</span><span>M</span><span>X</span><span>J</span><span>V</span><span>S</span><span>D</span>
                    </div>

                    <div class="mt-1 space-y-1">
                        @foreach ($month['weeks'] as $week)
                            <div class="grid grid-cols-7 gap-1">
                                @foreach ($week as $day)
                                    @if (is_null($day))
                                        <div aria-hidden="true"></div>
                                    @else
                                        @php
                                            $isToday = $day['date']->isSameDay($today);
                                            $isSelected = $selectedDay && $selectedDay['date']->isSameDay($day['date']);
                                            $isDimmed = $statusFilter && ! in_array($day['level'], ['none', 'future'], true) && $day['level'] !== $statusFilter;
                                            $label = $day['date']->translatedFormat('l j \d\e F').': '.$dayLevelLabels[$day['level']]
                                                .($day['percentage'] !== null ? ' · '.rtrim(rtrim((string) $day['percentage'], '0'), '.').'% cumplido' : '');
                                        @endphp
                                        <button
                                            type="button"
                                            id="day-{{ $day['date']->format('Y-m-d') }}"
                                            wire:click="selectDay('{{ $day['date']->format('Y-m-d') }}')"
                                            aria-label="{{ $label }}"
                                            title="{{ $label }}"
                                            class="aspect-square w-full rounded-sm transition focus:outline-none focus:ring-2 focus:ring-primary/60 {{ $dayLevelClasses[$day['level']] }} {{ $isDimmed ? 'opacity-25' : '' }} {{ $isToday ? 'ring-1 ring-primary' : '' }} {{ $isSelected ? 'ring-2 ring-primary' : '' }}"
                                        ></button>
                                    @endif
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>

        {{-- Leyenda --}}
        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 rounded-xl border border-outline-variant bg-surface-container-lowest p-4 text-xs text-on-surface-variant">
            @foreach ($dayLevelLabels as $key => $label)
                <span class="inline-flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded-sm {{ $dayLevelClasses[$key] }}"></span>
                    {{ $label }}
                </span>
            @endforeach
        </div>
    @endif

    {{-- Panel de detalle del día --}}
    @if ($selectedDay)
        <div class="fixed inset-0 z-50 flex justify-end">
            <div class="fixed inset-0 bg-on-surface/40" wire:click="closeDay" aria-hidden="true"></div>

            <div
                role="dialog"
                aria-modal="true"
                aria-label="Detalle del {{ $selectedDay['date']->translatedFormat('l j \d\e F') }}"
                x-init="$nextTick(() => $refs.calendarCloseBtn.focus())"
                class="relative flex h-full w-full max-w-sm flex-col overflow-y-auto border-l border-outline-variant bg-surface-container-lowest p-5 shadow-xl"
            >
                <div class="mb-4 flex items-start justify-between gap-3">
                    <h2 class="font-heading text-base font-semibold text-on-surface">
                        {{ Str::ucfirst($selectedDay['date']->translatedFormat('l j \d\e F \d\e Y')) }}
                    </h2>
                    <button
                        type="button"
                        x-ref="calendarCloseBtn"
                        wire:click="closeDay"
                        aria-label="Cerrar detalle del día"
                        class="shrink-0 rounded-lg p-2 text-on-surface-variant hover:bg-surface-container-high hover:text-on-surface focus:outline-none focus:ring-2 focus:ring-primary/40"
                    >
                        <x-icon name="close" class="h-4 w-4" />
                    </button>
                </div>

                @if ($selectedDay['percentage'] !== null)
                    <x-progress-bar :percentage="$selectedDay['percentage']" label="Cumplimiento del día" class="mb-5" />
                @else
                    <p class="mb-5 text-sm text-on-surface-variant">
                        {{ $selectedDay['level'] === 'future' ? 'Este día todavía no llega.' : 'Ningún hábito estaba programado este día.' }}
                    </p>
                @endif

                @if ($selectedDay['items']->isNotEmpty())
                    <h3 class="mb-2 text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Hábitos de este día</h3>
                    <ul class="space-y-2">
                        @foreach ($selectedDay['items'] as $item)
                            <li class="flex items-start justify-between gap-2 rounded-lg border border-outline-variant p-3">
                                <div class="min-w-0">
                                    <p class="truncate text-sm font-medium text-on-surface">
                                        {{ $item['habit']->is_private ? 'Hábito privado' : $item['habit']->name }}
                                    </p>
                                    <p class="mt-0.5 text-xs text-on-surface-variant">
                                        @if ($item['log'])
                                            {{ $item['log']->status->label() }} · {{ $item['log']->updated_at->translatedFormat('g:i a') }}
                                        @else
                                            Sin registro
                                        @endif
                                    </p>
                                    @if ($item['log']?->note)
                                        <p class="mt-1 flex items-start gap-1 text-xs text-on-surface-variant">
                                            <x-icon name="note" class="mt-0.5 h-3.5 w-3.5 shrink-0" />
                                            <span>{{ $item['log']->note }}</span>
                                        </p>
                                    @endif
                                </div>
                                <a href="{{ route('habits.show', $item['habit']) }}" wire:navigate class="shrink-0 text-xs font-medium text-primary hover:underline">Ver hábito</a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($selectedDay['date']->isSameDay($today))
                    <a href="{{ route('habits.today') }}" wire:navigate class="mt-5">
                        <x-button type="button" class="w-full">Ir a hábitos de hoy</x-button>
                    </a>
                @endif
            </div>
        </div>
    @endif
</div>
