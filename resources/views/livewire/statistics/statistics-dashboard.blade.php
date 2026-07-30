@php
use App\Support\Number;
use Illuminate\Support\Str;

$kpiCards = [
    ['key' => 'completion_percentage', 'label' => 'Cumplimiento', 'icon' => 'chart', 'unit' => '', 'tooltip' => 'Porcentaje de hábitos completados sobre los esperados en el periodo.'],
    ['key' => 'habits_completed', 'label' => 'Hábitos completados', 'icon' => 'check', 'unit' => '', 'tooltip' => 'Cantidad de registros marcados como cumplidos en el periodo.'],
    ['key' => 'perfect_days', 'label' => 'Días perfectos', 'icon' => 'star', 'unit' => '', 'tooltip' => 'Días en los que completaste el 100% de tus hábitos esperados.'],
    ['key' => 'current_streak', 'label' => 'Racha actual', 'icon' => 'flame', 'unit' => ' días', 'tooltip' => 'Días consecutivos con el 100% de tus hábitos cumplidos, a la fecha de hoy — no depende del periodo filtrado.'],
    ['key' => 'best_streak', 'label' => 'Mejor racha', 'icon' => 'trophy', 'unit' => ' días', 'tooltip' => 'La racha más larga registrada en tu historial completo.'],
    ['key' => 'avg_daily_completed', 'label' => 'Promedio diario', 'icon' => 'target', 'unit' => '', 'tooltip' => 'Hábitos completados por día, en promedio, durante el periodo.'],
    ['key' => 'total_logs', 'label' => 'Total de registros', 'icon' => 'note', 'unit' => '', 'tooltip' => 'Cantidad de registros guardados en el periodo (cumplidos, parciales o fallados).'],
    ['key' => 'variation', 'label' => 'Variación', 'icon' => 'bolt', 'unit' => '', 'tooltip' => 'Cambio en el % de cumplimiento frente al periodo inmediatamente anterior, de la misma duración.'],
];

$weekdayLetters = [1 => 'L', 2 => 'M', 3 => 'X', 4 => 'J', 5 => 'V', 6 => 'S', 7 => 'D'];

$dayLevelClasses = [
    'completed' => 'bg-primary',
    'partial' => 'bg-primary/40',
    'pending' => 'border-2 border-dashed border-outline-variant bg-surface-container-lowest',
    'none' => 'bg-surface-container',
    'future' => 'bg-surface-container/60',
];
@endphp

<div class="space-y-6">
    {{-- Encabezado, periodo y filtros --}}
    <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="font-heading text-xl font-semibold text-on-surface">Estadísticas</h1>
                <p class="mt-1 text-sm text-on-surface-variant">
                    {{ Str::ucfirst($from->translatedFormat('j \d\e M')) }} – {{ $to->translatedFormat('j \d\e M \d\e Y') }}
                </p>
            </div>

            <div class="flex flex-wrap gap-1.5" role="group" aria-label="Seleccionar periodo">
                @foreach (['7d' => '7 días', '30d' => '30 días', '90d' => '90 días', 'month' => 'Este mes', 'year' => 'Este año', 'custom' => 'Personalizado'] as $value => $label)
                    <button
                        type="button"
                        wire:click="$set('period', '{{ $value }}')"
                        aria-pressed="{{ $period === $value ? 'true' : 'false' }}"
                        class="rounded-full border px-3 py-1.5 text-xs font-medium transition-colors focus:outline-none focus:ring-2 focus:ring-primary/40 {{ $period === $value ? 'border-primary bg-primary text-on-primary' : 'border-outline-variant text-on-surface-variant hover:bg-surface-container-high' }}"
                    >
                        {{ $label }}
                    </button>
                @endforeach
            </div>
        </div>

        @if ($period === 'custom')
            <div class="mt-4 flex flex-wrap items-end gap-3">
                <div>
                    <label for="stats-from" class="mb-1 block text-xs text-on-surface-variant">Desde</label>
                    <input type="date" id="stats-from" wire:model.live="customFrom" max="{{ $today->toDateString() }}" class="rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                </div>
                <div>
                    <label for="stats-to" class="mb-1 block text-xs text-on-surface-variant">Hasta</label>
                    <input type="date" id="stats-to" wire:model.live="customTo" max="{{ $today->toDateString() }}" class="rounded-lg border border-outline-variant bg-surface-container-lowest px-3 py-2 text-sm text-on-surface focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/30">
                </div>
            </div>
        @endif

        <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="stats-habit" class="sr-only">Filtrar por hábito</label>
                <x-select id="stats-habit" wire:model.live="habitId">
                    <option value="">Todos los hábitos</option>
                    @foreach ($allHabits as $habit)
                        <option value="{{ $habit->id }}">{{ $habit->is_private ? 'Hábito privado' : $habit->name }}</option>
                    @endforeach
                </x-select>
            </div>
            <div>
                <label for="stats-category" class="sr-only">Filtrar por categoría</label>
                <x-select id="stats-category" wire:model.live="categoryId" :disabled="$categories->isEmpty()">
                    <option value="">Todas las categorías</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}">{{ $category->name }}</option>
                    @endforeach
                </x-select>
            </div>
            <div>
                <label for="stats-type" class="sr-only">Filtrar por tipo de hábito</label>
                <x-select id="stats-type" wire:model.live="habitType">
                    <option value="">Todos los tipos</option>
                    @foreach ($habitTypes as $type)
                        <option value="{{ $type->value }}">{{ $type->label() }}</option>
                    @endforeach
                </x-select>
            </div>
            <div>
                <label for="stats-status" class="sr-only">Filtrar por estado</label>
                <x-select id="stats-status" wire:model.live="statusFilter">
                    <option value="active">Activos</option>
                    <option value="archived">Archivados</option>
                    <option value="all">Todos</option>
                </x-select>
            </div>
        </div>
    </div>

    <div wire:loading.flex class="hidden items-center gap-2 text-sm text-on-surface-variant" aria-live="polite">
        <span class="h-2 w-2 animate-pulse rounded-full bg-primary"></span> Actualizando estadísticas…
    </div>

    @unless ($hasData)
        <div class="flex flex-col items-center justify-center gap-3 rounded-xl border border-dashed border-outline-variant bg-surface-container-lowest px-6 py-10 text-center">
            <p class="font-heading text-base font-semibold text-on-surface">Aún no hay suficientes datos para generar estadísticas</p>
            <p class="max-w-md text-sm text-on-surface-variant">Registra tus hábitos durante unos días y esta sección se llenará con tu progreso real.</p>
            <a href="{{ route('habits.create') }}" wire:navigate>
                <x-button type="button" class="w-auto px-4">
                    <x-icon name="plus" class="mr-1.5 h-4 w-4" /> Crear hábito
                </x-button>
            </a>
        </div>
    @endunless

    <div wire:loading.class="opacity-50" class="space-y-6 transition-opacity">
        {{-- KPIs --}}
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ($kpiCards as $card)
                @php $m = $metrics[$card['key']]; @endphp
                <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-4" title="{{ $card['tooltip'] }}">
                    <div class="flex items-center justify-between">
                        <p class="text-xs font-medium text-on-surface-variant">{{ $card['label'] }}</p>
                        <x-icon :name="$card['icon']" class="h-4 w-4 text-on-surface-variant" />
                    </div>
                    <p class="mt-1.5 font-heading text-2xl font-semibold text-on-surface">{{ $m['value'] }}{{ $m['suffix'] }}{{ $card['unit'] }}</p>
                    @if ($m['delta'] !== null)
                        <p class="mt-0.5 inline-flex items-center gap-1 text-xs {{ $m['trend'] === 'up' ? 'text-primary' : ($m['trend'] === 'down' ? 'text-error' : 'text-on-surface-variant') }}">
                            <span aria-hidden="true">{{ $m['trend'] === 'up' ? '▲' : ($m['trend'] === 'down' ? '▼' : '—') }}</span>
                            <span>{{ $m['delta'] > 0 ? '+' : '' }}{{ $m['delta'] }}{{ $m['suffix'] }} vs. periodo anterior</span>
                        </p>
                    @else
                        <p class="mt-0.5 text-xs text-on-surface-variant">{{ in_array($card['key'], ['current_streak', 'best_streak']) ? 'Histórico, no depende del periodo' : 'Sin datos del periodo anterior' }}</p>
                    @endif
                </div>
            @endforeach
        </div>

        {{-- Evolución --}}
        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
            <div class="mb-4 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <h2 class="font-heading text-base font-semibold text-on-surface">Evolución</h2>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex rounded-lg border border-outline-variant p-0.5 text-xs" role="group" aria-label="Granularidad de la gráfica">
                        @foreach (['daily' => 'Diaria', 'weekly' => 'Semanal', 'monthly' => 'Mensual'] as $value => $label)
                            <button type="button" wire:click="$set('granularity', '{{ $value }}')" aria-pressed="{{ $granularity === $value ? 'true' : 'false' }}" class="rounded-md px-2.5 py-1 focus:outline-none focus:ring-2 focus:ring-primary/40 {{ $granularity === $value ? 'bg-primary text-on-primary' : 'text-on-surface-variant' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                    <div class="inline-flex rounded-lg border border-outline-variant p-0.5 text-xs" role="group" aria-label="Métrica de la gráfica">
                        @foreach (['percentage' => '% cumplimiento', 'count' => 'Hábitos completados'] as $value => $label)
                            <button type="button" wire:click="$set('chartMetric', '{{ $value }}')" aria-pressed="{{ $chartMetric === $value ? 'true' : 'false' }}" class="rounded-md px-2.5 py-1 focus:outline-none focus:ring-2 focus:ring-primary/40 {{ $chartMetric === $value ? 'bg-primary text-on-primary' : 'text-on-surface-variant' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                </div>
            </div>

            @php
                $points = collect($evolution['points'])->values();
                $comparisonPoints = $evolution['comparisonPoints'] ? collect($evolution['comparisonPoints'])->values() : null;
                $maxValue = max(1, $points->max('value') ?? 0, $comparisonPoints?->max('value') ?? 0);
                $chartW = 800;
                $chartH = 220;
                $stepX = $points->count() > 1 ? $chartW / ($points->count() - 1) : 0;
                $toPoints = fn ($series) => $series->values()->map(fn ($p, $i) => round($i * $stepX, 1).','.round($chartH - ($p['value'] / $maxValue) * $chartH, 1))->implode(' ');
                $unit = $chartMetric === 'count' ? '' : '%';
                $labelStep = max(1, (int) ceil($points->count() / 8));
            @endphp

            @if ($points->isEmpty())
                <p class="text-sm text-on-surface-variant">No hay datos para graficar en este periodo.</p>
            @else
                <div class="overflow-x-auto">
                    <svg viewBox="0 0 {{ $chartW }} {{ $chartH }}" preserveAspectRatio="none" class="h-56 w-full min-w-[480px]" role="img" aria-label="Gráfica de evolución de {{ $chartMetric === 'count' ? 'hábitos completados' : 'porcentaje de cumplimiento' }} por periodo, {{ $granularity === 'daily' ? 'vista diaria' : ($granularity === 'weekly' ? 'vista semanal' : 'vista mensual') }}">
                        <line x1="0" y1="{{ $chartH }}" x2="{{ $chartW }}" y2="{{ $chartH }}" class="stroke-outline-variant" stroke-width="1" />
                        <line x1="0" y1="{{ $chartH / 2 }}" x2="{{ $chartW }}" y2="{{ $chartH / 2 }}" class="stroke-outline-variant" stroke-width="1" stroke-dasharray="4 4" />

                        @if ($comparisonPoints && $comparisonPoints->isNotEmpty())
                            <polyline points="{{ $toPoints($comparisonPoints) }}" class="fill-none stroke-on-surface-variant" stroke-width="2" stroke-dasharray="5 5" opacity="0.6" />
                        @endif

                        <polyline points="{{ $toPoints($points) }}" class="fill-none stroke-primary" stroke-width="2.5" />

                        @foreach ($points as $i => $point)
                            <circle cx="{{ round($i * $stepX, 1) }}" cy="{{ round($chartH - ($point['value'] / $maxValue) * $chartH, 1) }}" r="3" class="fill-primary">
                                <title>{{ $point['label'] }}: {{ Number::trim($point['value']) }}{{ $unit }}</title>
                            </circle>
                        @endforeach
                    </svg>
                </div>

                <div class="mt-1 flex justify-between text-[10px] text-on-surface-variant" aria-hidden="true">
                    @foreach ($points as $i => $point)
                        @if ($i % $labelStep === 0 || $i === $points->count() - 1)
                            <span>{{ $point['label'] }}</span>
                        @endif
                    @endforeach
                </div>

                <details class="mt-3">
                    <summary class="cursor-pointer text-xs font-medium text-primary hover:underline">Ver datos como tabla</summary>
                    <div class="mt-2 overflow-x-auto">
                        <table class="w-full min-w-[320px] text-left text-xs">
                            <caption class="sr-only">Valores de la gráfica de evolución</caption>
                            <thead>
                                <tr class="text-on-surface-variant">
                                    <th scope="col" class="pb-1 font-medium">Fecha</th>
                                    <th scope="col" class="pb-1 font-medium">Valor</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                @foreach ($points as $point)
                                    <tr>
                                        <td class="py-1 text-on-surface">{{ $point['label'] }}</td>
                                        <td class="py-1 text-on-surface-variant">{{ $point['value'] }}{{ $unit }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </details>
            @endif
        </div>

        {{-- Rendimiento por hábito + categorías --}}
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5 lg:col-span-2">
                <div class="mb-3 flex flex-wrap items-center justify-between gap-3">
                    <h2 class="font-heading text-base font-semibold text-on-surface">Rendimiento por hábito</h2>
                    <div>
                        <label for="stats-sort" class="sr-only">Ordenar hábitos</label>
                        <x-select id="stats-sort" wire:model.live="sortBy" class="w-auto">
                            <option value="performance_desc">Mejor rendimiento</option>
                            <option value="performance_asc">Peor rendimiento</option>
                            <option value="streak_desc">Mayor racha</option>
                            <option value="completed_desc">Más completados</option>
                            <option value="name">Nombre</option>
                        </x-select>
                    </div>
                </div>

                @if ($habitPerformance->isEmpty())
                    <p class="text-sm text-on-surface-variant">No hay hábitos que coincidan con los filtros.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[520px] text-left text-sm">
                            <caption class="sr-only">Rendimiento de cada hábito en el periodo seleccionado</caption>
                            <thead>
                                <tr class="text-xs text-on-surface-variant">
                                    <th scope="col" class="pb-2 font-medium">Hábito</th>
                                    <th scope="col" class="pb-2 font-medium">Cumplimiento</th>
                                    <th scope="col" class="pb-2 font-medium">Racha</th>
                                    <th scope="col" class="pb-2 font-medium">Tendencia</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-outline-variant">
                                @foreach ($habitPerformance as $row)
                                    <tr wire:key="perf-{{ $row['habit']->id }}">
                                        <td class="py-2.5">
                                            <div class="flex items-center gap-2">
                                                <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg" style="background-color: {{ $row['habit']->color }}26; color: {{ $row['habit']->color }}">
                                                    <x-icon :name="$row['habit']->icon" class="h-3.5 w-3.5" />
                                                </span>
                                                <div class="min-w-0">
                                                    <p class="truncate font-medium text-on-surface">{{ $row['habit']->is_private ? 'Hábito privado' : $row['habit']->name }}</p>
                                                    <p class="truncate text-xs text-on-surface-variant">
                                                        {{ $row['habit']->category?->name ?? 'Sin categoría' }}
                                                        @if ($row['habit']->is_archived) · Archivado @endif
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-2.5">
                                            @if ($row['percentage'] === null)
                                                <span class="text-xs text-on-surface-variant">Sin días esperados</span>
                                            @else
                                                <div class="flex items-center gap-2">
                                                    <x-progress-bar :percentage="$row['percentage']" class="w-24" />
                                                    <span class="text-xs text-on-surface-variant">{{ $row['percentage'] }}%</span>
                                                </div>
                                            @endif
                                        </td>
                                        <td class="py-2.5"><x-streak-badge :days="$row['current_streak']" /></td>
                                        <td class="py-2.5">
                                            @php $trendLabel = ['up' => 'al alza', 'down' => 'a la baja', 'flat' => 'estable'][$row['trend']]; @endphp
                                            <span class="inline-flex items-center gap-1 text-xs {{ $row['trend'] === 'up' ? 'text-primary' : ($row['trend'] === 'down' ? 'text-error' : 'text-on-surface-variant') }}" aria-label="Tendencia: {{ $trendLabel }}">
                                                <span aria-hidden="true">{{ ['up' => '▲', 'down' => '▼', 'flat' => '—'][$row['trend']] }}</span>
                                                {{ $trendLabel }}
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>

            <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
                <h2 class="mb-3 font-heading text-base font-semibold text-on-surface">Categorías</h2>
                @if ($categoryBreakdown['items']->isEmpty())
                    <p class="text-sm text-on-surface-variant">No hay categorías con hábitos en este periodo.</p>
                @else
                    <ul class="space-y-3">
                        @foreach ($categoryBreakdown['items'] as $cat)
                            <li>
                                <div class="mb-1 flex items-center justify-between gap-2 text-sm">
                                    <span class="inline-flex min-w-0 items-center gap-1.5 truncate text-on-surface">
                                        <span class="h-2.5 w-2.5 shrink-0 rounded-full" style="background-color: {{ $cat['color'] ?? '#7b7984' }}"></span>
                                        <span class="truncate">{{ $cat['name'] }}</span>
                                    </span>
                                    <span class="shrink-0 text-xs text-on-surface-variant">{{ $cat['habit_count'] }} {{ Str::plural('hábito', $cat['habit_count']) }} · {{ $cat['total_logs'] }} reg.</span>
                                </div>
                                <x-progress-bar :percentage="$cat['percentage']" />
                            </li>
                        @endforeach
                    </ul>
                    @if ($categoryBreakdown['best'])
                        <p class="mt-4 text-xs text-on-surface-variant">Mejor categoría: <span class="font-medium text-on-surface">{{ $categoryBreakdown['best'] }}</span></p>
                    @endif
                @endif
            </div>
        </div>

        {{-- Rachas --}}
        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
            <h2 class="mb-3 font-heading text-base font-semibold text-on-surface">Rachas</h2>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <x-stat-card label="Racha actual global" :value="$streaks['current_global'].' días'" icon="flame" />
                <x-stat-card label="Mejor racha global" :value="$streaks['best_global'].' días'" icon="trophy" />
                <x-stat-card label="Hábito con mayor racha" :value="$streaks['best_habit']['name'] ?? '—'" :hint="$streaks['best_habit'] ? $streaks['best_habit']['streak'].' días' : null" icon="star" />
                <x-stat-card label="Rachas interrumpidas" :value="$streaks['broken_count']" icon="x-circle" />
            </div>
            <p class="mt-3 text-xs text-on-surface-variant">
                Llevas {{ $streaks['activity_streak'] }} {{ Str::plural('día', $streaks['activity_streak']) }} consecutivos completando al menos un hábito.
            </p>
        </div>

        {{-- Consistencia --}}
        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
            <h2 class="mb-3 font-heading text-base font-semibold text-on-surface">Días perfectos y consistencia</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <p class="text-xs text-on-surface-variant">Días perfectos</p>
                    <p class="font-heading text-xl font-semibold text-primary">{{ $consistency['perfect'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-on-surface-variant">Días parciales</p>
                    <p class="font-heading text-xl font-semibold text-on-surface">{{ $consistency['partial'] }}</p>
                </div>
                <div>
                    <p class="text-xs text-on-surface-variant">Días sin actividad</p>
                    <p class="font-heading text-xl font-semibold text-on-surface">{{ $consistency['inactive'] }}</p>
                </div>
            </div>
            <x-progress-bar :percentage="$consistency['percentage']" label="Consistencia del periodo" class="mt-4" />

            <h3 class="mb-2 mt-5 text-xs font-semibold uppercase tracking-wide text-on-surface-variant">Rendimiento por día de la semana</h3>
            <div
                class="flex items-end gap-2"
                role="img"
                aria-label="Rendimiento promedio por día de la semana: {{ collect($consistency['weekday'])->map(fn ($w) => $w['label'].' '.($w['average'] ?? 0).'%')->implode(', ') }}"
            >
                @foreach ($consistency['weekday'] as $w)
                    <div class="flex flex-1 flex-col items-center gap-1">
                        <div class="flex h-20 w-full items-end overflow-hidden rounded bg-surface-container-high">
                            <div
                                class="w-full {{ $w['label'] === $consistency['bestWeekday'] ? 'bg-primary' : ($w['label'] === $consistency['worstWeekday'] ? 'bg-error/60' : 'bg-primary/50') }}"
                                style="height: {{ $w['average'] ?? 0 }}%"
                            ></div>
                        </div>
                        <span class="text-[10px] font-medium text-on-surface-variant">{{ $weekdayLetters[$w['iso']] }}</span>
                    </div>
                @endforeach
            </div>
            <p class="mt-2 text-xs text-on-surface-variant">
                @if ($consistency['bestWeekday']) Mejor día: <span class="font-medium text-on-surface">{{ $consistency['bestWeekday'] }}</span>. @endif
                @if ($consistency['worstWeekday']) Día más flojo: <span class="font-medium text-on-surface">{{ $consistency['worstWeekday'] }}</span>. @endif
                @if ($consistency['mostFrequentHour']) Hora más frecuente de cumplimiento: <span class="font-medium text-on-surface">{{ $consistency['mostFrequentHour'] }}</span>. @endif
            </p>
        </div>

        {{-- Mapa de actividad --}}
        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
            <h2 class="mb-3 font-heading text-base font-semibold text-on-surface">Mapa de actividad</h2>
            <div class="overflow-x-auto">
                <div class="inline-flex gap-1 py-1">
                    @foreach ($activityMap->chunk(7) as $week)
                        <div class="flex flex-col gap-1">
                            @foreach ($week as $day)
                                @php
                                    $dayLabel = Str::ucfirst($day['date']->translatedFormat('j \d\e M')).': '.($day['percentage'] !== null ? $day['percentage'].'% cumplido' : ($day['level'] === 'future' ? 'Día futuro' : 'Sin hábitos programados'));
                                @endphp
                                <div class="h-3 w-3 rounded-sm {{ $dayLevelClasses[$day['level']] }}" title="{{ $dayLabel }}" role="img" aria-label="{{ $dayLabel }}"></div>
                            @endforeach
                        </div>
                    @endforeach
                </div>
            </div>
            <a href="{{ route('calendar.index') }}" wire:navigate class="mt-2 inline-block text-xs font-medium text-primary hover:underline">Ver calendario completo</a>
        </div>

        {{-- Tendencias y conclusiones --}}
        <div class="rounded-xl border border-outline-variant bg-surface-container-lowest p-5">
            <h2 class="mb-3 font-heading text-base font-semibold text-on-surface">Tendencias y conclusiones</h2>
            @if (empty($insights))
                <p class="text-sm text-on-surface-variant">Todavía no hay suficientes datos en este periodo para generar conclusiones.</p>
            @else
                <ul class="space-y-2">
                    @foreach ($insights as $insight)
                        <li class="flex items-start gap-2 text-sm text-on-surface">
                            <x-icon name="sparkle" class="mt-0.5 h-4 w-4 shrink-0 text-primary" />
                            <span>{{ $insight }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </div>
</div>
