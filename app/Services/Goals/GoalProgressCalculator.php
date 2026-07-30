<?php

namespace App\Services\Goals;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\Goal;
use App\Services\Habits\CalendarService;
use App\Services\Habits\ConsistencyCalculator;
use App\Services\Habits\StreakCalculator;
use App\Services\Habits\Support\HabitLogEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Calcula el progreso de un objetivo sin almacenar nada derivable: para
 * objetivos de hábito/racha/porcentaje, el valor actual siempre se lee en
 * vivo desde `habit_logs` vía los servicios ya existentes de
 * `app/Services/Habits/*` — si un registro de hábito cambia, el objetivo lo
 * refleja automáticamente sin trabajo adicional.
 *
 * Requiere que el `Goal` recibido tenga `habit.logs` y `progressEntries` ya
 * cargados por el llamador cuando aplique (evita N+1 al listar objetivos).
 */
class GoalProgressCalculator
{
    public function __construct(
        private readonly StreakCalculator $streakCalculator,
        private readonly ConsistencyCalculator $consistencyCalculator,
        private readonly CalendarService $calendarService,
    ) {}

    /**
     * @param  Collection|null  $allUserHabits  Hábitos activos del usuario con `logs` cargado — solo necesario para
     *                                          objetivos porcentuales sin hábito vinculado (agregado de todos los hábitos).
     *                                          El llamador debe cargarlo UNA sola vez y reutilizarlo entre objetivos.
     * @return array{
     *     current: ?float, target: ?float, unit: ?string, percentage: float, is_achieved: bool,
     *     display_status: string, days_remaining: ?int, time_elapsed_percentage: ?float,
     *     risk_level: ?string, current_streak: ?int, longest_streak: ?int,
     * }
     */
    public function compute(Goal $goal, CarbonImmutable $today, ?Collection $allUserHabits = null): array
    {
        [$current, $target, $unit] = match ($goal->type) {
            GoalType::Habit => $this->forHabit($goal, $today),
            GoalType::Streak => $this->forStreak($goal, $today),
            GoalType::Numeric => $this->forNumeric($goal),
            GoalType::Percentage => $this->forPercentage($goal, $today, $allUserHabits),
            GoalType::Deadline, GoalType::Manual => [null, null, null],
        };

        $percentage = $this->percentageFor($goal->type, $current, $target);
        $isAchieved = $this->isAchieved($goal, $current, $target);
        $displayStatus = $this->displayStatus($goal, $isAchieved, $today);
        $daysRemaining = $this->daysRemaining($goal, $today);
        $timeElapsedPercentage = $this->timeElapsedPercentage($goal, $today);

        return [
            'current' => $current,
            'target' => $target,
            'unit' => $unit,
            'percentage' => $percentage,
            'is_achieved' => $isAchieved,
            'display_status' => $displayStatus,
            'days_remaining' => $daysRemaining,
            'time_elapsed_percentage' => $timeElapsedPercentage,
            'risk_level' => $this->riskLevel($goal, $displayStatus, $percentage, $timeElapsedPercentage),
            'current_streak' => $goal->habit ? $this->streakCalculator->current($goal->habit, $today) : null,
            'longest_streak' => $goal->habit ? $this->streakCalculator->longest($goal->habit, $today) : null,
        ];
    }

    /**
     * @return array{0: float, 1: ?float, 2: string}
     */
    private function forHabit(Goal $goal, CarbonImmutable $today): array
    {
        $target = $goal->target_value !== null ? (float) $goal->target_value : null;

        if (! $goal->habit) {
            return [0.0, $target, 'veces'];
        }

        $from = CarbonImmutable::parse($goal->start_date)->startOfDay();
        $to = $this->effectiveEnd($goal, $today);
        $evaluator = new HabitLogEvaluator($goal->habit);

        $count = $goal->habit->logs
            ->filter(fn ($log) => $log->date->betweenIncluded($from, $to))
            ->filter(fn ($log) => $evaluator->isCompleted($log->date))
            ->count();

        return [(float) $count, $target, 'veces'];
    }

    /**
     * @return array{0: float, 1: ?float, 2: string}
     */
    private function forStreak(Goal $goal, CarbonImmutable $today): array
    {
        $target = $goal->target_value !== null ? (float) $goal->target_value : null;
        $current = $goal->habit ? $this->streakCalculator->current($goal->habit, $today) : 0;

        return [(float) $current, $target, 'días'];
    }

    /**
     * @return array{0: float, 1: ?float, 2: ?string}
     */
    private function forNumeric(Goal $goal): array
    {
        $initial = $goal->initial_value !== null ? (float) $goal->initial_value : 0.0;
        $entriesSum = $goal->progressEntries->sum(fn ($entry) => (float) $entry->value);

        return [$initial + $entriesSum, $goal->target_value !== null ? (float) $goal->target_value : null, $goal->unit];
    }

    /**
     * @return array{0: float, 1: ?float, 2: string}
     */
    private function forPercentage(Goal $goal, CarbonImmutable $today, ?Collection $allUserHabits): array
    {
        $target = $goal->target_value !== null ? (float) $goal->target_value : null;
        $period = is_array($goal->settings) ? ($goal->settings['period'] ?? 'month') : 'month';
        [$from, $to] = $this->periodRange($period, $today);

        if ($goal->habit) {
            $percentage = $this->consistencyCalculator->percentageFor($goal->habit, $from, $to);

            return [$percentage, $target, '%'];
        }

        if ($allUserHabits === null || $allUserHabits->isEmpty()) {
            return [0.0, $target, '%'];
        }

        $days = $this->calendarService->buildDays($allUserHabits, $from, $to, $today);
        $expected = 0;
        $completed = 0;
        foreach ($days as $day) {
            if ($day['percentage'] === null) {
                continue;
            }
            $expected += $day['items']->count();
            $completed += $day['items']->filter(fn (array $item) => $item['completed'])->count();
        }

        return [$expected > 0 ? round($completed / $expected * 100, 1) : 0.0, $target, '%'];
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function periodRange(string $period, CarbonImmutable $today): array
    {
        return match ($period) {
            'week' => [$today->startOfWeek(CarbonImmutable::MONDAY), $today],
            'year' => [$today->startOfYear(), $today],
            default => [$today->startOfMonth(), $today],
        };
    }

    private function effectiveEnd(Goal $goal, CarbonImmutable $today): CarbonImmutable
    {
        if (! $goal->due_date) {
            return $today;
        }

        $due = CarbonImmutable::parse($goal->due_date)->startOfDay();

        return $due->lt($today) ? $due : $today;
    }

    private function percentageFor(GoalType $type, ?float $current, ?float $target): float
    {
        if ($type === GoalType::Percentage) {
            return $current !== null ? round(min(100, max(0, $current)), 1) : 0.0;
        }

        if (in_array($type, [GoalType::Deadline, GoalType::Manual], true)) {
            return 0.0;
        }

        if ($target === null || $target <= 0 || $current === null) {
            return 0.0;
        }

        return round(min(100, max(0, $current / $target * 100)), 1);
    }

    private function isAchieved(Goal $goal, ?float $current, ?float $target): bool
    {
        if (in_array($goal->type, [GoalType::Deadline, GoalType::Manual], true)) {
            return $goal->status === GoalStatus::Completed;
        }

        return $target !== null && $current !== null && $current >= $target;
    }

    private function displayStatus(Goal $goal, bool $isAchieved, CarbonImmutable $today): string
    {
        if ($goal->status === GoalStatus::Archived) {
            return 'archived';
        }
        if ($goal->status === GoalStatus::Cancelled) {
            return 'cancelled';
        }
        if ($goal->status === GoalStatus::Completed || $isAchieved) {
            return 'completed';
        }
        if ($goal->status === GoalStatus::Paused) {
            return 'paused';
        }
        if ($goal->due_date && CarbonImmutable::parse($goal->due_date)->lt($today)) {
            return 'overdue';
        }

        return 'active';
    }

    private function daysRemaining(Goal $goal, CarbonImmutable $today): ?int
    {
        if (! $goal->due_date) {
            return null;
        }

        $due = CarbonImmutable::parse($goal->due_date)->startOfDay();
        $todayStart = $today->startOfDay();
        $days = $todayStart->diffInDays($due, true);

        return $due->lt($todayStart) ? -$days : $days;
    }

    private function timeElapsedPercentage(Goal $goal, CarbonImmutable $today): ?float
    {
        if (! $goal->due_date) {
            return null;
        }

        $start = CarbonImmutable::parse($goal->start_date)->startOfDay();
        $due = CarbonImmutable::parse($goal->due_date)->startOfDay();
        $todayStart = $today->startOfDay();

        $totalDays = $start->diffInDays($due);
        if ($totalDays <= 0) {
            return $todayStart->gte($due) ? 100.0 : 0.0;
        }

        $clampedToday = match (true) {
            $todayStart->lt($start) => $start,
            $todayStart->gt($due) => $due,
            default => $todayStart,
        };

        $elapsed = $start->diffInDays($clampedToday);

        return round(min(100, max(0, $elapsed / $totalDays * 100)), 1);
    }

    /**
     * Nivel de riesgo determinístico según progreso vs. tiempo transcurrido.
     * Umbrales documentados: +10 puntos de progreso sobre el tiempo transcurrido
     * se considera "en buen camino"; hasta -15 puntos por debajo, "requiere
     * atención"; más atrás que eso, "en riesgo". Objetivos de fecha límite/manual
     * (sin métrica numérica) se evalúan solo por el tiempo transcurrido.
     */
    private function riskLevel(Goal $goal, string $displayStatus, float $percentage, ?float $timeElapsedPercentage): ?string
    {
        if (! $goal->due_date || $timeElapsedPercentage === null) {
            return null;
        }
        if ($displayStatus === 'overdue') {
            return 'overdue';
        }
        if ($displayStatus !== 'active') {
            return null;
        }

        if (in_array($goal->type, [GoalType::Deadline, GoalType::Manual], true)) {
            return match (true) {
                $timeElapsedPercentage >= 90 => 'at_risk',
                $timeElapsedPercentage >= 60 => 'attention',
                default => 'on_track',
            };
        }

        return match (true) {
            $percentage >= $timeElapsedPercentage + 10 => 'on_track',
            $percentage >= $timeElapsedPercentage - 15 => 'attention',
            default => 'at_risk',
        };
    }
}
