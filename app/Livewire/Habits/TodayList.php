<?php

namespace App\Livewire\Habits;

use App\Enums\LogStatus;
use App\Models\Habit;
use App\Services\Habits\HabitLogger;
use App\Services\Habits\HabitStatsService;
use App\Services\Habits\ScheduleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class TodayList extends Component
{
    /** @var array<int, float|string|null> */
    public array $quantityInputs = [];

    /** @var array<int, string|null> */
    public array $noteInputs = [];

    public function mount(ScheduleResolver $resolver): void
    {
        $today = $this->today();

        Auth::user()->habits()->active()->with('logs')->get()
            ->filter(fn (Habit $habit) => $resolver->isExpected($habit, $today))
            ->each(function (Habit $habit) use ($today) {
                $log = $habit->logs->first(fn ($log) => $log->date->isSameDay($today));
                if ($log) {
                    $this->quantityInputs[$habit->id] = $log->quantity_value !== null ? (float) $log->quantity_value : null;
                    $this->noteInputs[$habit->id] = $log->note;
                }
            });
    }

    public function toggleBinary(Habit $habit, HabitLogger $logger): void
    {
        $this->authorize('log', $habit);
        $logger->toggleBinary($habit, $this->today(), $this->noteInputs[$habit->id] ?? null);
    }

    public function logQuantity(Habit $habit, HabitLogger $logger): void
    {
        $this->authorize('log', $habit);
        $value = (float) ($this->quantityInputs[$habit->id] ?? 0);
        $logger->logQuantity($habit, $this->today(), $value, $this->noteInputs[$habit->id] ?? null);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(Auth::user()->timezone ?: config('app.timezone'))->startOfDay();
    }

    public function render(HabitStatsService $stats, ScheduleResolver $resolver)
    {
        $today = $this->today();

        $items = Auth::user()->habits()->active()->with(['logs', 'category'])->orderBy('position')->get()
            ->filter(fn (Habit $habit) => $resolver->isExpected($habit, $today))
            ->map(fn (Habit $habit) => [
                'habit' => $habit,
                'summary' => $stats->summary($habit, $today),
                'log' => $habit->logs->first(fn ($log) => $log->date->isSameDay($today)),
            ])
            ->values();

        $completedCount = $items->filter(fn (array $item) => $item['log']?->status === LogStatus::Completed)->count();

        return view('livewire.habits.today-list', [
            'items' => $items,
            'completedCount' => $completedCount,
            'totalCount' => $items->count(),
            'today' => $today,
        ]);
    }
}
