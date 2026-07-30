<?php

namespace App\Services\Habits;

use App\Enums\LogStatus;
use App\Models\Habit;
use App\Models\HabitLog;
use Carbon\CarbonImmutable;

/**
 * Crea, edita o elimina el registro (HabitLog) de un hábito en una fecha dada.
 * Único punto de entrada para mutar `habit_logs` — nunca se escribe directo
 * desde controladores/Livewire.
 */
class HabitLogger
{
    public function toggleBinary(Habit $habit, CarbonImmutable $date, ?string $note = null): ?HabitLog
    {
        $existing = $habit->logs()->whereDate('date', $date->toDateString())->first();

        if ($existing && $existing->status === LogStatus::Completed) {
            $existing->delete();

            return null;
        }

        return $habit->logs()->updateOrCreate(
            ['date' => $date->toDateString()],
            ['user_id' => $habit->user_id, 'status' => LogStatus::Completed, 'note' => $note]
        );
    }

    public function logQuantity(Habit $habit, CarbonImmutable $date, float $value, ?string $note = null): HabitLog
    {
        return $habit->logs()->updateOrCreate(
            ['date' => $date->toDateString()],
            [
                'user_id' => $habit->user_id,
                'status' => $this->statusForQuantity($habit, $value),
                'quantity_value' => $value,
                'note' => $note,
            ]
        );
    }

    public function markStatus(Habit $habit, CarbonImmutable $date, LogStatus $status, ?string $note = null): HabitLog
    {
        return $habit->logs()->updateOrCreate(
            ['date' => $date->toDateString()],
            ['user_id' => $habit->user_id, 'status' => $status, 'note' => $note]
        );
    }

    public function deleteLog(Habit $habit, CarbonImmutable $date): void
    {
        $habit->logs()->whereDate('date', $date->toDateString())->delete();
    }

    private function statusForQuantity(Habit $habit, float $value): LogStatus
    {
        $target = $habit->target_quantity !== null ? (float) $habit->target_quantity : null;

        if ($target === null) {
            return $value > 0 ? LogStatus::Completed : LogStatus::Failed;
        }

        if ($value >= $target) {
            return LogStatus::Completed;
        }

        return $value > 0 ? LogStatus::Partial : LogStatus::Failed;
    }
}
