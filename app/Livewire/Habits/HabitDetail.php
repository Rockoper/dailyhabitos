<?php

namespace App\Livewire\Habits;

use App\Enums\HabitType;
use App\Models\Habit;
use App\Services\Habits\HabitLogger;
use App\Services\Habits\HabitStatsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class HabitDetail extends Component
{
    public Habit $habit;

    public ?float $quantityInput = null;

    public ?string $noteInput = null;

    public function mount(Habit $habit): void
    {
        $this->authorize('view', $habit);
        $this->habit = $habit;

        $today = $this->today();
        $log = $habit->logs()->whereDate('date', $today->toDateString())->first();

        if ($log) {
            $this->quantityInput = $log->quantity_value !== null ? (float) $log->quantity_value : null;
            $this->noteInput = $log->note;
        }
    }

    public function toggleBinary(HabitLogger $logger): void
    {
        $this->authorize('log', $this->habit);
        $logger->toggleBinary($this->habit, $this->today(), $this->noteInput);
        $this->habit->load('logs');
    }

    public function logQuantity(HabitLogger $logger): void
    {
        $this->authorize('log', $this->habit);
        $logger->logQuantity($this->habit, $this->today(), (float) ($this->quantityInput ?? 0), $this->noteInput);
        $this->habit->load('logs');
    }

    public function archive(): void
    {
        $this->authorize('archive', $this->habit);
        $this->habit->update(['is_archived' => true, 'archived_at' => now()]);
    }

    public function unarchive(): void
    {
        $this->authorize('archive', $this->habit);
        $this->habit->update(['is_archived' => false, 'archived_at' => null]);
    }

    public function delete(): void
    {
        $this->authorize('delete', $this->habit);
        $this->habit->delete();

        session()->flash('status', 'Hábito eliminado.');
        $this->redirectRoute('habits.index', navigate: true);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(Auth::user()->timezone ?: config('app.timezone'))->startOfDay();
    }

    public function render(HabitStatsService $stats)
    {
        $this->habit->loadMissing(['logs', 'category']);
        $today = $this->today();

        return view('livewire.habits.habit-detail', [
            'summary' => $stats->summary($this->habit, $today),
            'grid' => $stats->activityGrid($this->habit, $today, 18),
            'recentLogs' => $this->habit->logs->sortByDesc('date')->take(10)->values(),
            'todayLog' => $this->habit->logs->first(fn ($log) => $log->date->isSameDay($today)),
            'isQuantity' => $this->habit->type === HabitType::Quantity,
        ]);
    }
}
