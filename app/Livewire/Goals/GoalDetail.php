<?php

namespace App\Livewire\Goals;

use App\Enums\GoalType;
use App\Models\Goal;
use App\Services\Goals\GoalProgressCalculator;
use App\Services\Goals\GoalService;
use App\Support\Number;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GoalDetail extends Component
{
    public Goal $goal;

    public ?float $progressValue = null;

    public ?string $progressDate = null;

    public ?string $progressNote = null;

    public ?int $editingEntryId = null;

    public function mount(Goal $goal): void
    {
        $this->authorize('view', $goal);
        $this->goal = $goal;
        $this->progressDate = $this->today()->toDateString();
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(Auth::user()->timezone ?: config('app.timezone'))->startOfDay();
    }

    public function complete(GoalService $goalService): void
    {
        $this->authorize('transition', $this->goal);
        $goalService->complete($this->goal);
        $this->goal->refresh();
    }

    public function reopen(GoalService $goalService): void
    {
        $this->authorize('transition', $this->goal);
        $goalService->reopen($this->goal);
        $this->goal->refresh();
    }

    public function pause(GoalService $goalService): void
    {
        $this->authorize('transition', $this->goal);
        $goalService->pause($this->goal);
        $this->goal->refresh();
    }

    public function resume(GoalService $goalService): void
    {
        $this->authorize('transition', $this->goal);
        $goalService->resume($this->goal);
        $this->goal->refresh();
    }

    public function archive(GoalService $goalService): void
    {
        $this->authorize('transition', $this->goal);
        $goalService->archive($this->goal);
        $this->goal->refresh();
    }

    public function unarchive(GoalService $goalService): void
    {
        $this->authorize('transition', $this->goal);
        $goalService->unarchive($this->goal);
        $this->goal->refresh();
    }

    public function delete(GoalService $goalService): void
    {
        $this->authorize('delete', $this->goal);
        $goalService->delete($this->goal);

        session()->flash('status', 'Objetivo eliminado.');
        $this->redirectRoute('goals.index', navigate: true);
    }

    public function addProgress(GoalService $goalService): void
    {
        $this->authorize('manageProgress', $this->goal);

        $validated = $this->validate([
            'progressValue' => ['required', 'numeric'],
            'progressDate' => ['required', 'date'],
            'progressNote' => ['nullable', 'string', 'max:500'],
        ], attributes: ['progressValue' => 'cantidad', 'progressDate' => 'fecha']);

        if ($this->editingEntryId) {
            $entry = $this->goal->progressEntries()->findOrFail($this->editingEntryId);
            $goalService->updateProgressEntry(
                $entry,
                (float) $validated['progressValue'],
                CarbonImmutable::parse($validated['progressDate']),
                $validated['progressNote'] ?? null,
            );
        } else {
            $goalService->logProgress(
                $this->goal,
                (float) $validated['progressValue'],
                CarbonImmutable::parse($validated['progressDate']),
                $validated['progressNote'] ?? null,
            );
        }

        $this->reset(['progressValue', 'progressNote', 'editingEntryId']);
        $this->progressDate = $this->today()->toDateString();
        $this->goal->refresh();
    }

    public function editEntry(int $entryId): void
    {
        $entry = $this->goal->progressEntries()->findOrFail($entryId);
        $this->authorize('manageProgress', $this->goal);

        $this->editingEntryId = $entry->id;
        $this->progressValue = (float) $entry->value;
        $this->progressDate = $entry->recorded_at->toDateString();
        $this->progressNote = $entry->note;
    }

    public function cancelEditEntry(): void
    {
        $this->reset(['progressValue', 'progressNote', 'editingEntryId']);
        $this->progressDate = $this->today()->toDateString();
    }

    public function deleteEntry(int $entryId, GoalService $goalService): void
    {
        $this->authorize('manageProgress', $this->goal);
        $entry = $this->goal->progressEntries()->findOrFail($entryId);
        $goalService->deleteProgressEntry($entry);
        $this->goal->refresh();
    }

    public function render(GoalProgressCalculator $calculator, GoalService $goalService)
    {
        $this->goal->loadMissing(['habit.logs', 'category', 'progressEntries']);
        $today = $this->today();

        $allUserHabits = ($this->goal->type === GoalType::Percentage && $this->goal->habit_id === null)
            ? Auth::user()->habits()->active()->with('logs')->get()
            : null;

        $progress = $calculator->compute($this->goal, $today, $allUserHabits);
        $goalService->syncAutoCompletion($this->goal, $progress);

        $entries = $this->goal->progressEntries->sortByDesc('recorded_at')->values();

        return view('livewire.goals.goal-detail', [
            'progress' => $progress,
            'entries' => $entries,
            'insight' => $this->buildInsight($progress),
            'isNumeric' => $this->goal->type === GoalType::Numeric,
        ]);
    }

    private function buildInsight(array $progress): ?string
    {
        if ($progress['display_status'] === 'completed') {
            return '¡Objetivo completado! Puedes reabrirlo si necesitas seguir registrando avances.';
        }

        if ($progress['display_status'] === 'overdue') {
            return 'La fecha límite ya pasó y la meta no se alcanzó. Puedes ajustar la fecha o marcarlo como completado si corresponde.';
        }

        if ($progress['current'] !== null && $progress['target'] !== null && $progress['target'] > $progress['current']) {
            $remaining = Number::trim($progress['target'] - $progress['current']);
            $unit = $progress['unit'] ?? '';
            $sentence = "Te faltan {$remaining} {$unit} para completar tu objetivo.";

            if ($progress['time_elapsed_percentage'] !== null) {
                $sentence .= sprintf(
                    ' Llevas el %s%% y queda el %s%% del tiempo.',
                    Number::trim($progress['percentage']),
                    Number::trim(100 - $progress['time_elapsed_percentage'])
                );
            }

            return $sentence;
        }

        if ($progress['risk_level'] === 'on_track') {
            return 'Este objetivo está adelantado respecto al tiempo transcurrido.';
        }

        return null;
    }
}
