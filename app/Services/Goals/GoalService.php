<?php

namespace App\Services\Goals;

use App\Enums\GoalStatus;
use App\Models\Goal;
use App\Models\GoalProgressEntry;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Único punto de entrada para mutar un `Goal` o sus registros de progreso —
 * nunca se escribe directo desde Livewire. Cada operación va en transacción.
 */
class GoalService
{
    public function complete(Goal $goal): void
    {
        DB::transaction(function () use ($goal) {
            $goal->update(['status' => GoalStatus::Completed, 'completed_at' => now()]);
        });
    }

    /**
     * Reabre un objetivo completado. La confirmación del usuario se pide en
     * la interfaz (wire:confirm) antes de llamar a esta acción.
     */
    public function reopen(Goal $goal): void
    {
        DB::transaction(function () use ($goal) {
            $goal->update(['status' => GoalStatus::Active, 'completed_at' => null]);
        });
    }

    public function pause(Goal $goal): void
    {
        DB::transaction(function () use ($goal) {
            $goal->update(['status' => GoalStatus::Paused]);
        });
    }

    public function resume(Goal $goal): void
    {
        DB::transaction(function () use ($goal) {
            $goal->update(['status' => GoalStatus::Active]);
        });
    }

    public function archive(Goal $goal): void
    {
        DB::transaction(function () use ($goal) {
            $goal->update(['status' => GoalStatus::Archived, 'archived_at' => now()]);
        });
    }

    public function unarchive(Goal $goal): void
    {
        DB::transaction(function () use ($goal) {
            $goal->update(['status' => GoalStatus::Active, 'archived_at' => null]);
        });
    }

    public function cancel(Goal $goal): void
    {
        DB::transaction(function () use ($goal) {
            $goal->update(['status' => GoalStatus::Cancelled, 'cancelled_at' => now()]);
        });
    }

    public function delete(Goal $goal): void
    {
        DB::transaction(function () use ($goal) {
            $goal->delete();
        });
    }

    /**
     * Marca el objetivo como completado si el cálculo de progreso ya lo
     * detecta alcanzado y todavía está activo — se llama cada vez que se
     * renderiza el listado/detalle, no requiere un job programado.
     *
     * @param  array{is_achieved: bool}  $progress
     */
    public function syncAutoCompletion(Goal $goal, array $progress): void
    {
        if ($goal->status === GoalStatus::Active
            && $goal->type->isAutoTrackable()
            && $progress['is_achieved']) {
            $this->complete($goal);
        }
    }

    public function logProgress(Goal $goal, float $value, CarbonImmutable $recordedAt, ?string $note): GoalProgressEntry
    {
        return DB::transaction(fn () => $goal->progressEntries()->create([
            'user_id' => $goal->user_id,
            'value' => $value,
            'recorded_at' => $recordedAt->toDateString(),
            'note' => $note,
        ]));
    }

    public function updateProgressEntry(GoalProgressEntry $entry, float $value, CarbonImmutable $recordedAt, ?string $note): void
    {
        DB::transaction(function () use ($entry, $value, $recordedAt, $note) {
            $entry->update(['value' => $value, 'recorded_at' => $recordedAt->toDateString(), 'note' => $note]);
        });
    }

    public function deleteProgressEntry(GoalProgressEntry $entry): void
    {
        DB::transaction(function () use ($entry) {
            $entry->delete();
        });
    }
}
