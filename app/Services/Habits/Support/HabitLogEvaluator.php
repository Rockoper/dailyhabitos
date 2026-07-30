<?php

namespace App\Services\Habits\Support;

use App\Enums\HabitType;
use App\Enums\LogStatus;
use App\Models\Habit;
use App\Models\HabitLog;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Resuelve, a partir de los logs ya cargados de un hábito, si una fecha concreta
 * cuenta como cumplida. Centraliza la regla de "completado" (status = completed,
 * o quantity_value >= target_quantity para hábitos de cantidad) para que
 * ScheduleResolver, StreakCalculator y ConsistencyCalculator no la dupliquen.
 */
class HabitLogEvaluator
{
    /** @var Collection<string, HabitLog> */
    private Collection $logsByDate;

    public function __construct(private readonly Habit $habit, ?Collection $logs = null)
    {
        $this->logsByDate = ($logs ?? $habit->logs)
            ->keyBy(fn (HabitLog $log) => $log->date->format('Y-m-d'));
    }

    public function logOn(CarbonInterface $date): ?HabitLog
    {
        return $this->logsByDate->get($date->format('Y-m-d'));
    }

    public function isCompleted(CarbonInterface $date): bool
    {
        $log = $this->logOn($date);

        if (! $log) {
            return false;
        }

        if ($log->status === LogStatus::Completed) {
            return true;
        }

        if ($this->habit->type === HabitType::Quantity
            && $this->habit->target_quantity !== null
            && $log->quantity_value !== null) {
            return (float) $log->quantity_value >= (float) $this->habit->target_quantity;
        }

        return false;
    }
}
