<?php

namespace App\Services\Habits;

use App\Models\Habit;
use App\Services\Habits\Support\HabitLogEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Construye la vista de calendario anual a partir de ScheduleResolver +
 * HabitLogEvaluator: nivel de cumplimiento por día, agrupación por mes y
 * resumen anual. Ver docs/ARCHITECTURE.md (Livewire\Calendar\YearHeatmap).
 *
 * Niveles de día (los únicos 5 que distingue esta sección):
 * - none: no había hábitos esperados ese día.
 * - pending: había hábitos esperados y no se completó ninguno (0%).
 * - partial: se completó entre 0% y 100% (exclusive).
 * - completed: se completó el 100% de los hábitos esperados.
 * - future: fecha posterior a "hoy".
 *
 * Requiere que cada `Habit` de la colección tenga `logs` ya cargado
 * (evita N+1); el llamador es responsable del eager loading.
 */
class CalendarService
{
    public function __construct(private readonly ScheduleResolver $scheduleResolver) {}

    /**
     * @param  Collection<int, Habit>  $habits  Hábitos ya filtrados por el llamador.
     * @return array{days: Collection, months: Collection, summary: array}
     */
    public function buildYear(Collection $habits, int $year, CarbonImmutable $today, string $timezone): array
    {
        $yearStart = CarbonImmutable::create($year, 1, 1, 0, 0, 0, $timezone)->startOfDay();
        $yearEnd = CarbonImmutable::create($year, 12, 31, 0, 0, 0, $timezone)->startOfDay();

        $evaluators = $habits->mapWithKeys(fn (Habit $habit) => [$habit->id => new HabitLogEvaluator($habit)]);

        $days = collect();
        $cursor = $yearStart;
        while ($cursor->lte($yearEnd)) {
            $days->put($cursor->format('Y-m-d'), $this->buildDay($habits, $evaluators, $cursor, $today));
            $cursor = $cursor->addDay();
        }

        return [
            'days' => $days,
            'months' => $this->groupByMonth($days, $year),
            'summary' => $this->summarize($days, $today),
        ];
    }

    /**
     * @return array{date: CarbonImmutable, level: string, percentage: ?float, items: Collection}
     */
    private function buildDay(Collection $habits, Collection $evaluators, CarbonImmutable $date, CarbonImmutable $today): array
    {
        if ($date->gt($today)) {
            return ['date' => $date, 'level' => 'future', 'percentage' => null, 'items' => collect()];
        }

        $expected = $habits->filter(fn (Habit $habit) => $this->scheduleResolver->isExpected($habit, $date))->values();

        if ($expected->isEmpty()) {
            return ['date' => $date, 'level' => 'none', 'percentage' => null, 'items' => collect()];
        }

        $items = $expected->map(function (Habit $habit) use ($evaluators, $date) {
            $evaluator = $evaluators[$habit->id];

            return [
                'habit' => $habit,
                'log' => $evaluator->logOn($date),
                'completed' => $evaluator->isCompleted($date),
            ];
        });

        $completedCount = $items->filter(fn (array $item) => $item['completed'])->count();
        $percentage = round($completedCount / $items->count() * 100, 1);

        $level = match (true) {
            $percentage >= 100 => 'completed',
            $percentage > 0 => 'partial',
            default => 'pending',
        };

        return ['date' => $date, 'level' => $level, 'percentage' => $percentage, 'items' => $items];
    }

    /**
     * Semanas de lunes a domingo, con `null` de relleno antes del día 1 para
     * alinear la cuadrícula con el inicio de semana en lunes.
     *
     * @return Collection<int, array{number: int, name: string, weeks: Collection}>
     */
    private function groupByMonth(Collection $days, int $year): Collection
    {
        return collect(range(1, 12))->map(function (int $month) use ($days, $year) {
            $firstOfMonth = CarbonImmutable::create($year, $month, 1)->startOfDay();
            $daysInMonth = $firstOfMonth->daysInMonth;
            $leadingOffset = $firstOfMonth->dayOfWeekIso - 1;

            $cells = collect();
            for ($i = 0; $i < $leadingOffset; $i++) {
                $cells->push(null);
            }
            for ($d = 1; $d <= $daysInMonth; $d++) {
                $cells->push($days->get($firstOfMonth->setDay($d)->format('Y-m-d')));
            }

            return [
                'number' => $month,
                'name' => Str::ucfirst($firstOfMonth->translatedFormat('F')),
                'weeks' => $cells->chunk(7)->values(),
            ];
        });
    }

    /**
     * @return array{completed_days: int, annual_percentage: float, current_streak: int, longest_streak: int, completed_habits_total: int, best_month: ?string}
     */
    private function summarize(Collection $days, CarbonImmutable $today): array
    {
        $ordered = $days->values();

        $completedDays = $ordered->filter(fn (array $day) => $day['level'] === 'completed')->count();

        $expectedTotal = 0;
        $completedTotal = 0;
        foreach ($ordered as $day) {
            if ($day['percentage'] === null) {
                continue;
            }
            $expectedTotal += $day['items']->count();
            $completedTotal += $day['items']->filter(fn (array $item) => $item['completed'])->count();
        }

        [$currentStreak, $longestStreak] = $this->streaks($ordered, $today);

        return [
            'completed_days' => $completedDays,
            'annual_percentage' => $expectedTotal > 0 ? round($completedTotal / $expectedTotal * 100, 1) : 0.0,
            'current_streak' => $currentStreak,
            'longest_streak' => $longestStreak,
            'completed_habits_total' => $completedTotal,
            'best_month' => $this->bestMonth($ordered),
        ];
    }

    /**
     * Racha del calendario agregado (no confundir con StreakCalculator, que es
     * por hábito): días "completed" consecutivos, sin que los días "none"
     * (sin hábitos programados) la rompan, y sin que "hoy" la rompa si aún no
     * se resolvió (pending/partial). Se calcula dentro del año recibido.
     *
     * @return array{0: int, 1: int} [racha actual, mejor racha]
     */
    private function streaks(Collection $ordered, CarbonImmutable $today): array
    {
        $longest = 0;
        $run = 0;

        foreach ($ordered as $day) {
            if ($day['date']->gt($today)) {
                break;
            }
            if ($day['level'] === 'none') {
                continue;
            }
            if ($day['date']->isSameDay($today) && $day['level'] !== 'completed') {
                continue;
            }
            if ($day['level'] === 'completed') {
                $run++;
                $longest = max($longest, $run);

                continue;
            }
            $run = 0;
        }

        $current = 0;
        foreach ($ordered->reverse() as $day) {
            if ($day['date']->gt($today)) {
                continue;
            }
            if ($day['level'] === 'none') {
                continue;
            }
            if ($day['date']->isSameDay($today) && $day['level'] !== 'completed') {
                continue;
            }
            if ($day['level'] === 'completed') {
                $current++;

                continue;
            }
            break;
        }

        return [$current, $longest];
    }

    private function bestMonth(Collection $ordered): ?string
    {
        $best = null;
        $bestScore = -1.0;

        foreach ($ordered->groupBy(fn (array $day) => $day['date']->month) as $month => $daysInMonth) {
            $expected = $daysInMonth->sum(fn (array $day) => $day['percentage'] !== null ? $day['items']->count() : 0);
            if ($expected === 0) {
                continue;
            }

            $completed = $daysInMonth->sum(
                fn (array $day) => $day['percentage'] !== null ? $day['items']->filter(fn (array $item) => $item['completed'])->count() : 0
            );

            $score = $completed / $expected;
            if ($score > $bestScore) {
                $bestScore = $score;
                $best = CarbonImmutable::create(2000, (int) $month, 1)->translatedFormat('F');
            }
        }

        return $best ? Str::ucfirst($best) : null;
    }
}
