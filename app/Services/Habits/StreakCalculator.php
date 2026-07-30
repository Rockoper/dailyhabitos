<?php

namespace App\Services\Habits;

use App\Enums\FrequencyType;
use App\Enums\LogStatus;
use App\Models\Habit;
use App\Services\Habits\Support\HabitLogEvaluator;
use Carbon\CarbonImmutable;

/**
 * Racha actual y mejor racha de un hábito. Ver docs/ARCHITECTURE.md §5.2, §5.3 y §5.5.
 *
 * Requiere que `$habit->logs` ya esté cargado por el llamador (evita N+1).
 *
 * Regla "nunca fallar dos veces" (`never_fail_twice`): cualquier fallo aislado
 * (no consecutivo con otro fallo) se perdona y no rompe la racha; se rompe
 * únicamente cuando ocurren dos fallos en fechas/semanas esperadas consecutivas.
 * Sin la regla activa, el umbral es 1: cualquier fallo rompe la racha.
 */
class StreakCalculator
{
    public function __construct(private readonly ScheduleResolver $scheduleResolver) {}

    public function current(Habit $habit, CarbonImmutable $today): int
    {
        return $habit->frequency_type === FrequencyType::WeeklyCount
            ? $this->currentWeekly($habit, $today)
            : $this->currentDaily($habit, $today);
    }

    public function longest(Habit $habit, CarbonImmutable $today): int
    {
        return $habit->frequency_type === FrequencyType::WeeklyCount
            ? $this->longestWeekly($habit, $today)
            : $this->longestDaily($habit, $today);
    }

    private function currentDaily(Habit $habit, CarbonImmutable $today): int
    {
        $dates = $this->scheduleResolver->expectedDates($habit, CarbonImmutable::parse($habit->start_date), $today);
        if ($dates->isEmpty()) {
            return 0;
        }

        $evaluator = new HabitLogEvaluator($habit);
        $failureThreshold = $habit->never_fail_twice ? 2 : 1;
        $count = 0;
        $consecutiveFailures = 0;

        foreach ($dates->reverse() as $date) {
            if ($date->isSameDay($today) && $evaluator->logOn($date) === null) {
                // Hoy aún no termina: pendiente, no rompe ni cuenta la racha.
                continue;
            }

            if ($evaluator->isCompleted($date)) {
                $count++;
                $consecutiveFailures = 0;

                continue;
            }

            $consecutiveFailures++;
            if ($consecutiveFailures >= $failureThreshold) {
                break;
            }
        }

        return $count;
    }

    private function longestDaily(Habit $habit, CarbonImmutable $today): int
    {
        $dates = $this->scheduleResolver->expectedDates($habit, CarbonImmutable::parse($habit->start_date), $today);
        $evaluator = new HabitLogEvaluator($habit);
        $failureThreshold = $habit->never_fail_twice ? 2 : 1;

        $best = 0;
        $run = 0;
        $consecutiveFailures = 0;

        foreach ($dates as $date) {
            if ($date->isSameDay($today) && $evaluator->logOn($date) === null) {
                // Día en curso sin registro: no cuenta para el historial cerrado.
                continue;
            }

            if ($evaluator->isCompleted($date)) {
                $run++;
                $consecutiveFailures = 0;
                $best = max($best, $run);

                continue;
            }

            $consecutiveFailures++;
            if ($consecutiveFailures >= $failureThreshold) {
                $run = 0;
                $consecutiveFailures = 0;
            }
        }

        return $best;
    }

    private function currentWeekly(Habit $habit, CarbonImmutable $today): int
    {
        $weeks = $this->scheduleResolver->expectedWeeks($habit, CarbonImmutable::parse($habit->start_date), $today);
        if ($weeks->isEmpty()) {
            return 0;
        }

        $target = max(1, (int) ($habit->frequency_config['times_per_week'] ?? 1));
        $failureThreshold = $habit->never_fail_twice ? 2 : 1;
        $count = 0;
        $consecutiveFailures = 0;

        foreach ($weeks->reverse() as $week) {
            $completedInWeek = $this->completedCountInWeek($habit, $week['start'], $week['end']);
            $isCurrentWeek = $today->betweenIncluded($week['start'], $week['end']);

            if ($completedInWeek >= $target) {
                $count++;
                $consecutiveFailures = 0;

                continue;
            }

            if ($isCurrentWeek && $today->lt($week['end'])) {
                // Semana en curso: todavía puede alcanzar la meta con los días restantes.
                continue;
            }

            $consecutiveFailures++;
            if ($consecutiveFailures >= $failureThreshold) {
                break;
            }
        }

        return $count;
    }

    private function longestWeekly(Habit $habit, CarbonImmutable $today): int
    {
        $weeks = $this->scheduleResolver->expectedWeeks($habit, CarbonImmutable::parse($habit->start_date), $today);
        $target = max(1, (int) ($habit->frequency_config['times_per_week'] ?? 1));
        $failureThreshold = $habit->never_fail_twice ? 2 : 1;

        $best = 0;
        $run = 0;
        $consecutiveFailures = 0;

        foreach ($weeks as $week) {
            $isCurrentWeek = $today->betweenIncluded($week['start'], $week['end']);
            if ($isCurrentWeek && $today->lt($week['end'])) {
                continue;
            }

            $completedInWeek = $this->completedCountInWeek($habit, $week['start'], $week['end']);

            if ($completedInWeek >= $target) {
                $run++;
                $consecutiveFailures = 0;
                $best = max($best, $run);

                continue;
            }

            $consecutiveFailures++;
            if ($consecutiveFailures >= $failureThreshold) {
                $run = 0;
                $consecutiveFailures = 0;
            }
        }

        return $best;
    }

    private function completedCountInWeek(Habit $habit, CarbonImmutable $start, CarbonImmutable $end): int
    {
        return $habit->logs
            ->filter(fn ($log) => $log->status === LogStatus::Completed
                && $log->date->betweenIncluded($start, $end))
            ->count();
    }
}
