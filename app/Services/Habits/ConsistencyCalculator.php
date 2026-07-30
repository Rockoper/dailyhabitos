<?php

namespace App\Services\Habits;

use App\Enums\FrequencyType;
use App\Enums\LogStatus;
use App\Models\Habit;
use App\Services\Habits\Support\HabitLogEvaluator;
use Carbon\CarbonImmutable;

/**
 * % de cumplimiento de un hábito en un periodo, independiente de la racha.
 * Ver docs/ARCHITECTURE.md §5.4.
 *
 * Requiere que `$habit->logs` ya esté cargado por el llamador (evita N+1).
 */
class ConsistencyCalculator
{
    public function __construct(private readonly ScheduleResolver $scheduleResolver) {}

    /**
     * @return array{30: float, 90: float, 365: float, lifetime: float}
     */
    public function windows(Habit $habit, CarbonImmutable $today): array
    {
        return [
            30 => $this->percentageFor($habit, $today->subDays(29), $today),
            90 => $this->percentageFor($habit, $today->subDays(89), $today),
            365 => $this->percentageFor($habit, $today->subDays(364), $today),
            'lifetime' => $this->percentageFor($habit, CarbonImmutable::parse($habit->start_date), $today),
        ];
    }

    public function percentageFor(Habit $habit, CarbonImmutable $from, CarbonImmutable $to): float
    {
        if ($habit->frequency_type === FrequencyType::WeeklyCount) {
            return $this->percentageForWeekly($habit, $from, $to);
        }

        $dates = $this->scheduleResolver->expectedDates($habit, $from, $to);
        if ($dates->isEmpty()) {
            return 0.0;
        }

        $evaluator = new HabitLogEvaluator($habit);
        $completed = $dates->filter(fn (CarbonImmutable $date) => $evaluator->isCompleted($date))->count();

        return round($completed / $dates->count() * 100, 1);
    }

    private function percentageForWeekly(Habit $habit, CarbonImmutable $from, CarbonImmutable $to): float
    {
        $weeks = $this->scheduleResolver->expectedWeeks($habit, $from, $to);
        if ($weeks->isEmpty()) {
            return 0.0;
        }

        $target = max(1, (int) ($habit->frequency_config['times_per_week'] ?? 1));

        $met = $weeks->filter(function (array $week) use ($habit, $target) {
            $completed = $habit->logs
                ->filter(fn ($log) => $log->status === LogStatus::Completed
                    && $log->date->betweenIncluded($week['start'], $week['end']))
                ->count();

            return $completed >= $target;
        })->count();

        return round($met / $weeks->count() * 100, 1);
    }
}
