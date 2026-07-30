<?php

namespace App\Services\Statistics;

use App\Models\Category;
use App\Models\Habit;
use App\Services\Habits\CalendarService;
use App\Services\Habits\StreakCalculator;
use App\Support\Number;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Orquesta el panel de Estadísticas a partir de los servicios ya existentes
 * de `app/Services/Habits/*` (no duplica cálculo de fechas esperadas, rachas
 * ni niveles de día). Ver docs/ARCHITECTURE.md.
 *
 * Función pura: recibe hábitos ya filtrados/autorizados/con `logs` y
 * `category` cargados por el llamador, y no toca la base de datos. Sin
 * caché por decisión documentada (ver resumen entregado al usuario) — a la
 * escala de un usuario personal el cálculo completo corre en memoria.
 */
class StatisticsService
{
    public function __construct(
        private readonly CalendarService $calendar,
        private readonly StreakCalculator $streakCalculator,
    ) {}

    /**
     * @param  Collection<int, Habit>  $habits  Hábitos ya filtrados/autorizados, con `logs` y `category` cargados.
     */
    public function build(
        Collection $habits,
        CarbonImmutable $from,
        CarbonImmutable $to,
        CarbonImmutable $today,
        string $granularity,
        string $chartMetric,
    ): array {
        $days = $this->calendar->buildDays($habits, $from, $to, $today);

        $periodLength = $from->diffInDays($to) + 1;
        $previousTo = $from->subDay();
        $previousFrom = $previousTo->subDays($periodLength - 1);
        $previousDays = $previousFrom->lte($previousTo)
            ? $this->calendar->buildDays($habits, $previousFrom, $previousTo, $today)
            : collect();

        $habitTally = $this->tallyByHabit($days);
        $habitPerformance = $this->buildHabitPerformance($habits, $habitTally, $today);
        $categoryBreakdown = $this->buildCategoryBreakdown($habitPerformance);

        $totals = $this->totals($days);
        $previousTotals = $this->totals($previousDays);

        $streaks = $this->buildStreaks($habits, $habitPerformance, $today);
        $consistency = $this->buildConsistency($days);
        $previousConsistency = $this->buildConsistency($previousDays);

        $totalLogsInPeriod = $this->countLogsInRange($habits, $from, $to);
        $previousTotalLogs = $previousFrom->lte($previousTo) ? $this->countLogsInRange($habits, $previousFrom, $previousTo) : null;

        $metrics = $this->buildMetrics($totals, $previousTotals, $streaks, $periodLength, $consistency, $previousDays->isNotEmpty() ? $previousConsistency : null, $totalLogsInPeriod, $previousTotalLogs);

        $evolution = $this->buildEvolution($days, $previousDays, $granularity, $chartMetric);

        $insights = $this->buildInsights($metrics, $habitPerformance, $consistency, $streaks);

        return [
            'metrics' => $metrics,
            'evolution' => $evolution,
            'habitPerformance' => $habitPerformance,
            'categoryBreakdown' => $categoryBreakdown,
            'streaks' => $streaks,
            'consistency' => $consistency,
            'activityMap' => $days,
            'insights' => $insights,
            'hasData' => $habits->isNotEmpty() && ($totalLogsInPeriod > 0 || $streaks['current_global'] > 0 || $streaks['best_global'] > 0),
        ];
    }

    /**
     * Un paso sobre los días del periodo, agregando por hábito. Evita volver
     * a llamar ScheduleResolver/HabitLogEvaluator por hábito: reutiliza los
     * `items` que `CalendarService::buildDays` ya calculó.
     *
     * @return Collection<int, array{habit: Habit, expected: int, completed: int, expected_first: int, completed_first: int, expected_second: int, completed_second: int}>
     */
    private function tallyByHabit(Collection $days): Collection
    {
        $ordered = $days->values();
        $midpoint = intdiv($ordered->count(), 2);
        $tally = collect();

        foreach ($ordered as $index => $day) {
            foreach ($day['items'] as $item) {
                $habit = $item['habit'];
                $row = $tally->get($habit->id) ?? [
                    'habit' => $habit,
                    'expected' => 0, 'completed' => 0,
                    'expected_first' => 0, 'completed_first' => 0,
                    'expected_second' => 0, 'completed_second' => 0,
                ];

                $row['expected']++;
                $row['completed'] += $item['completed'] ? 1 : 0;

                if ($index < $midpoint) {
                    $row['expected_first']++;
                    $row['completed_first'] += $item['completed'] ? 1 : 0;
                } else {
                    $row['expected_second']++;
                    $row['completed_second'] += $item['completed'] ? 1 : 0;
                }

                $tally->put($habit->id, $row);
            }
        }

        return $tally;
    }

    /**
     * @return Collection<int, array>
     */
    private function buildHabitPerformance(Collection $habits, Collection $tally, CarbonImmutable $today): Collection
    {
        return $habits->map(function (Habit $habit) use ($tally, $today) {
            $row = $tally->get($habit->id, [
                'expected' => 0, 'completed' => 0,
                'expected_first' => 0, 'completed_first' => 0,
                'expected_second' => 0, 'completed_second' => 0,
            ]);

            $percentage = $row['expected'] > 0 ? round($row['completed'] / $row['expected'] * 100, 1) : null;
            $firstPct = $row['expected_first'] > 0 ? $row['completed_first'] / $row['expected_first'] * 100 : null;
            $secondPct = $row['expected_second'] > 0 ? $row['completed_second'] / $row['expected_second'] * 100 : null;

            $trend = 'flat';
            if ($firstPct !== null && $secondPct !== null) {
                $trend = match (true) {
                    $secondPct - $firstPct >= 3 => 'up',
                    $firstPct - $secondPct >= 3 => 'down',
                    default => 'flat',
                };
            }

            return [
                'habit' => $habit,
                'percentage' => $percentage,
                'expected' => $row['expected'],
                'completed' => $row['completed'],
                'current_streak' => $this->streakCalculator->current($habit, $today),
                'longest_streak' => $this->streakCalculator->longest($habit, $today),
                'trend' => $trend,
            ];
        })->values();
    }

    /**
     * @return Collection<int, array>
     */
    private function buildCategoryBreakdown(Collection $habitPerformance): Collection
    {
        $grouped = $habitPerformance->groupBy(fn (array $row) => $row['habit']->category_id ?? 0);

        $categories = $grouped->map(function (Collection $rows) {
            /** @var ?Category $category */
            $category = $rows->first()['habit']->category;
            $expected = $rows->sum('expected');
            $completed = $rows->sum('completed');

            return [
                'id' => $category?->id,
                'name' => $category?->name ?? 'Sin categoría',
                'color' => $category?->color,
                'habit_count' => $rows->count(),
                'total_logs' => $completed,
                'percentage' => $expected > 0 ? round($completed / $expected * 100, 1) : 0.0,
            ];
        })->values();

        $bestCategory = $categories->filter(fn (array $c) => $c['habit_count'] > 0)->sortByDesc('percentage')->first();

        return collect([
            'items' => $categories->sortByDesc('percentage')->values(),
            'best' => $bestCategory['name'] ?? null,
        ]);
    }

    private function countLogsInRange(Collection $habits, CarbonImmutable $from, CarbonImmutable $to): int
    {
        return $habits->sum(
            fn (Habit $habit) => $habit->logs->filter(fn ($log) => $log->date->betweenIncluded($from, $to))->count()
        );
    }

    /**
     * @return array{expected: int, completed: int}
     */
    private function totals(Collection $days): array
    {
        $expected = 0;
        $completed = 0;

        foreach ($days as $day) {
            if ($day['percentage'] === null) {
                continue;
            }
            $expected += $day['items']->count();
            $completed += $day['items']->filter(fn (array $item) => $item['completed'])->count();
        }

        return ['expected' => $expected, 'completed' => $completed];
    }

    private function buildStreaks(Collection $habits, Collection $habitPerformance, CarbonImmutable $today): array
    {
        if ($habits->isEmpty()) {
            return [
                'current_global' => 0, 'best_global' => 0,
                'best_habit' => null, 'broken_count' => 0, 'activity_streak' => 0,
            ];
        }

        $historyStart = $habits->min(fn (Habit $habit) => CarbonImmutable::parse($habit->start_date)) ?? $today;
        $fullDays = $this->calendar->buildDays($habits, $historyStart, $today, $today);
        [$currentGlobal, $bestGlobal] = $this->calendar->streaks($fullDays->values(), $today);

        $bestHabitRow = $habitPerformance->sortByDesc('longest_streak')->first();
        $brokenCount = $habitPerformance->filter(
            fn (array $row) => $row['current_streak'] === 0 && $row['longest_streak'] > 0
        )->count();

        return [
            'current_global' => $currentGlobal,
            'best_global' => $bestGlobal,
            'best_habit' => $bestHabitRow && $bestHabitRow['longest_streak'] > 0 ? [
                'name' => $bestHabitRow['habit']->is_private ? 'Hábito privado' : $bestHabitRow['habit']->name,
                'streak' => $bestHabitRow['longest_streak'],
            ] : null,
            'broken_count' => $brokenCount,
            'activity_streak' => $this->activityStreak($fullDays->values(), $today),
        ];
    }

    private function activityStreak(Collection $orderedFullDays, CarbonImmutable $today): int
    {
        $current = 0;

        foreach ($orderedFullDays->reverse() as $day) {
            if ($day['date']->gt($today)) {
                continue;
            }
            if ($day['level'] === 'none') {
                continue;
            }

            $hasActivity = $day['items']->contains(fn (array $item) => $item['completed']);

            if ($day['date']->isSameDay($today) && ! $hasActivity) {
                continue;
            }
            if ($hasActivity) {
                $current++;

                continue;
            }
            break;
        }

        return $current;
    }

    /**
     * @return array{perfect: int, partial: int, inactive: int, evaluable: int, percentage: float, weekday: array, bestWeekday: ?string, worstWeekday: ?string, mostFrequentHour: ?string}
     */
    private function buildConsistency(Collection $days): array
    {
        $perfect = $days->filter(fn (array $day) => $day['level'] === 'completed')->count();
        $partial = $days->filter(fn (array $day) => $day['level'] === 'partial')->count();
        $inactive = $days->filter(fn (array $day) => $day['level'] === 'pending')->count();
        $evaluable = $perfect + $partial + $inactive;

        $weekdayNames = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
        $byWeekday = $days->filter(fn (array $day) => $day['percentage'] !== null)->groupBy(fn (array $day) => $day['date']->isoWeekday());

        $weekday = collect($weekdayNames)->map(function (string $name, int $iso) use ($byWeekday) {
            $daysForWeekday = $byWeekday->get($iso, collect());
            $avg = $daysForWeekday->isNotEmpty() ? round($daysForWeekday->avg('percentage'), 1) : null;

            return ['iso' => $iso, 'label' => $name, 'average' => $avg];
        })->values();

        $withData = $weekday->filter(fn (array $w) => $w['average'] !== null);
        $best = $withData->sortByDesc('average')->first();
        $worst = $withData->sortBy('average')->first();

        $hourCounts = collect();
        foreach ($days as $day) {
            foreach ($day['items'] as $item) {
                if ($item['completed'] && $item['log']?->updated_at) {
                    $hour = (int) $item['log']->updated_at->format('G');
                    $hourCounts->put($hour, ($hourCounts->get($hour, 0)) + 1);
                }
            }
        }
        $mostFrequentHour = null;
        if ($hourCounts->isNotEmpty()) {
            $topHour = $hourCounts->sortByDesc(fn ($count) => $count)->keys()->first();
            $mostFrequentHour = CarbonImmutable::createFromTime((int) $topHour, 0)->translatedFormat('g A');
        }

        return [
            'perfect' => $perfect,
            'partial' => $partial,
            'inactive' => $inactive,
            'evaluable' => $evaluable,
            'percentage' => $evaluable > 0 ? round($perfect / $evaluable * 100, 1) : 0.0,
            'weekday' => $weekday,
            'bestWeekday' => $best && $best['average'] > 0 ? $best['label'] : null,
            'worstWeekday' => $worst && $worst !== $best ? $worst['label'] : null,
            'mostFrequentHour' => $mostFrequentHour,
        ];
    }

    private function buildMetrics(
        array $totals,
        array $previousTotals,
        array $streaks,
        int $periodLength,
        array $consistency,
        ?array $previousConsistency,
        int $totalLogs,
        ?int $previousTotalLogs,
    ): array {
        $percentage = $totals['expected'] > 0 ? round($totals['completed'] / $totals['expected'] * 100, 1) : 0.0;
        $previousPercentage = $previousTotals['expected'] > 0 ? round($previousTotals['completed'] / $previousTotals['expected'] * 100, 1) : null;

        $avgDaily = $periodLength > 0 ? round($totals['completed'] / $periodLength, 1) : 0.0;
        $previousAvgDaily = $periodLength > 0 && $previousTotals['expected'] > 0 ? round($previousTotals['completed'] / $periodLength, 1) : null;

        return [
            'completion_percentage' => $this->metric($percentage, $previousPercentage, '%'),
            'habits_completed' => $this->metric($totals['completed'], $previousTotals['expected'] > 0 ? $previousTotals['completed'] : null),
            'perfect_days' => $this->metric($consistency['perfect'], $previousConsistency['perfect'] ?? null),
            'avg_daily_completed' => $this->metric($avgDaily, $previousAvgDaily),
            'total_logs' => $this->metric($totalLogs, $previousTotalLogs),
            'current_streak' => ['value' => $streaks['current_global'], 'previous' => null, 'delta' => null, 'trend' => null, 'suffix' => ''],
            'best_streak' => ['value' => $streaks['best_global'], 'previous' => null, 'delta' => null, 'trend' => null, 'suffix' => ''],
            'variation' => $this->metric($percentage, $previousPercentage, '%'),
        ];
    }

    private function metric(float|int $value, float|int|null $previous, string $suffix = ''): array
    {
        $delta = $previous !== null ? round($value - $previous, 1) : null;
        $trend = match (true) {
            $delta === null => null,
            $delta > 0 => 'up',
            $delta < 0 => 'down',
            default => 'flat',
        };

        return ['value' => $value, 'previous' => $previous, 'delta' => $delta, 'trend' => $trend, 'suffix' => $suffix];
    }

    /**
     * @return array{points: array, comparisonPoints: ?array}
     */
    private function buildEvolution(Collection $days, Collection $previousDays, string $granularity, string $chartMetric): array
    {
        return [
            'points' => $this->bucketize($days, $granularity, $chartMetric),
            'comparisonPoints' => $previousDays->isNotEmpty() ? $this->bucketize($previousDays, $granularity, $chartMetric) : null,
        ];
    }

    /**
     * @return array<int, array{label: string, value: float}>
     */
    private function bucketize(Collection $days, string $granularity, string $chartMetric): array
    {
        $ordered = $days->values();

        $groups = match ($granularity) {
            'weekly' => $ordered->groupBy(fn (array $day) => $day['date']->startOfWeek(CarbonImmutable::MONDAY)->format('Y-m-d')),
            'monthly' => $ordered->groupBy(fn (array $day) => $day['date']->format('Y-m')),
            default => $ordered->groupBy(fn (array $day) => $day['date']->format('Y-m-d')),
        };

        return $groups->map(function (Collection $bucketDays, string $key) use ($granularity, $chartMetric) {
            $expected = $bucketDays->sum(fn (array $day) => $day['percentage'] !== null ? $day['items']->count() : 0);
            $completed = $bucketDays->sum(fn (array $day) => $day['percentage'] !== null ? $day['items']->filter(fn (array $item) => $item['completed'])->count() : 0);

            $label = match ($granularity) {
                'weekly' => Str::ucfirst(CarbonImmutable::parse($key)->translatedFormat('j M')),
                'monthly' => Str::ucfirst(CarbonImmutable::parse($key.'-01')->translatedFormat('M Y')),
                default => CarbonImmutable::parse($key)->translatedFormat('j M'),
            };

            $value = $chartMetric === 'count'
                ? (float) $completed
                : ($expected > 0 ? round($completed / $expected * 100, 1) : 0.0);

            return ['label' => $label, 'value' => $value, 'completed' => $completed, 'expected' => $expected];
        })->values()->all();
    }

    private function buildInsights(array $metrics, Collection $habitPerformance, array $consistency, array $streaks): array
    {
        $insights = [];

        if ($metrics['completion_percentage']['delta'] !== null) {
            $delta = $metrics['completion_percentage']['delta'];
            if (abs($delta) >= 1) {
                $direction = $delta > 0 ? 'aumentó' : 'bajó';
                $insights[] = sprintf('Tu cumplimiento %s %s%% frente al periodo anterior.', $direction, number_format(abs($delta), 1));
            }
        }

        if ($streaks['activity_streak'] >= 2) {
            $insights[] = sprintf(
                'Llevas %d días consecutivos completando al menos un hábito.',
                $streaks['activity_streak']
            );
        }

        if ($consistency['bestWeekday']) {
            $insights[] = sprintf('Los %s son tu día más consistente.', $consistency['bestWeekday']);
        }

        $withPercentage = $habitPerformance->filter(fn (array $row) => $row['percentage'] !== null && $row['expected'] > 0);
        $best = $withPercentage->sortByDesc('percentage')->first();
        if ($best && $best['percentage'] >= 70) {
            $name = $best['habit']->is_private ? 'Hábito privado' : $best['habit']->name;
            $insights[] = sprintf('%s es tu hábito con mejor rendimiento este periodo (%s%%).', $name, Number::trim($best['percentage']));
        }

        $worst = $withPercentage->sortBy('percentage')->first();
        if ($worst && $worst['percentage'] < 50 && (! $best || $worst['habit']->id !== $best['habit']->id)) {
            $name = $worst['habit']->is_private ? 'Hábito privado' : $worst['habit']->name;
            $insights[] = sprintf('%s necesita más atención (%s%% de cumplimiento).', $name, Number::trim($worst['percentage']));
        }

        return array_slice($insights, 0, 5);
    }
}
