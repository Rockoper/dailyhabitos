<?php

namespace App\Services\Habits;

use App\Enums\LogStatus;
use App\Models\Habit;
use App\Services\Habits\Support\HabitLogEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Orquesta ScheduleResolver + StreakCalculator + ConsistencyCalculator para
 * presentar el resumen de un hábito en el dashboard, la lista de hoy y el detalle.
 */
class HabitStatsService
{
    public function __construct(
        private readonly ScheduleResolver $scheduleResolver,
        private readonly StreakCalculator $streakCalculator,
        private readonly ConsistencyCalculator $consistencyCalculator,
    ) {}

    /**
     * @return array{current_streak: int, longest_streak: int, consistency: array}
     */
    public function summary(Habit $habit, CarbonImmutable $today): array
    {
        return [
            'current_streak' => $this->streakCalculator->current($habit, $today),
            'longest_streak' => $this->streakCalculator->longest($habit, $today),
            'consistency' => $this->consistencyCalculator->windows($habit, $today),
        ];
    }

    /**
     * Un hábito está "en riesgo" si tiene una racha activa pero todavía no se ha
     * registrado hoy y hoy es una fecha esperada.
     */
    public function isAtRisk(Habit $habit, CarbonImmutable $today): bool
    {
        if (! $this->scheduleResolver->isExpected($habit, $today)) {
            return false;
        }

        $hasLogToday = $habit->logs->contains(fn ($log) => $log->date->isSameDay($today));

        return ! $hasLogToday && $this->streakCalculator->current($habit, $today) > 0;
    }

    /**
     * Rejilla de actividad estilo GitHub de las últimas N semanas (lunes a domingo),
     * para el mini-calendario del dashboard y el detalle del hábito.
     *
     * @return Collection<int, array{date: CarbonImmutable, level: string}>
     */
    public function activityGrid(Habit $habit, CarbonImmutable $today, int $weeks = 18): Collection
    {
        $end = $today->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay();
        $start = $end->subWeeks($weeks - 1)->startOfWeek(CarbonImmutable::MONDAY);

        $evaluator = new HabitLogEvaluator($habit);
        $cells = collect();
        $cursor = $start;

        while ($cursor->lte($end)) {
            $cells->push([
                'date' => $cursor,
                'level' => $this->levelFor($habit, $cursor, $today, $evaluator),
            ]);
            $cursor = $cursor->addDay();
        }

        return $cells;
    }

    private function levelFor(Habit $habit, CarbonImmutable $date, CarbonImmutable $today, HabitLogEvaluator $evaluator): string
    {
        if ($date->gt($today) || ! $this->scheduleResolver->isExpected($habit, $date)) {
            return 'rest';
        }

        if ($evaluator->isCompleted($date)) {
            return 'completed';
        }

        $log = $evaluator->logOn($date);
        if ($log?->status === LogStatus::Skipped) {
            return 'rest';
        }

        if ($date->isSameDay($today) && $log === null) {
            return 'pending';
        }

        return 'failed';
    }
}
