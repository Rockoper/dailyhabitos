<?php

namespace App\Livewire\Calendar;

use App\Enums\FrequencyType;
use App\Services\Habits\CalendarService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class YearHeatmap extends Component
{
    public int $year;

    public ?int $habitId = null;

    public ?int $categoryId = null;

    public ?string $frequencyType = null;

    public ?string $statusFilter = null;

    public ?string $selectedDate = null;

    public function mount(): void
    {
        $this->year = $this->today()->year;
    }

    public function previousYear(): void
    {
        $this->year--;
        $this->selectedDate = null;
    }

    public function nextYear(): void
    {
        $this->year++;
        $this->selectedDate = null;
    }

    public function goToToday(): void
    {
        $this->year = $this->today()->year;
        $this->selectedDate = null;
    }

    public function updatingHabitId(): void
    {
        $this->selectedDate = null;
    }

    public function updatingCategoryId(): void
    {
        $this->selectedDate = null;
    }

    public function updatingFrequencyType(): void
    {
        $this->selectedDate = null;
    }

    public function updatingStatusFilter(): void
    {
        $this->selectedDate = null;
    }

    public function selectDay(string $date): void
    {
        $this->selectedDate = $date;
    }

    public function closeDay(): void
    {
        $this->selectedDate = null;
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(Auth::user()->timezone ?: config('app.timezone'))->startOfDay();
    }

    public function render(CalendarService $calendar)
    {
        $user = Auth::user();
        $timezone = $user->timezone ?: config('app.timezone');
        $today = $this->today();

        $query = $user->habits()->active()->with(['logs', 'category'])->orderBy('position')->orderBy('name');

        if ($this->habitId) {
            $query->where('id', $this->habitId);
        }
        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }
        if ($this->frequencyType) {
            $query->where('frequency_type', $this->frequencyType);
        }

        $habits = $query->get();

        $data = $calendar->buildYear($habits, $this->year, $today, $timezone);

        return view('livewire.calendar.year-heatmap', [
            'year' => $this->year,
            'months' => $data['months'],
            'summary' => $data['summary'],
            'selectedDay' => $this->selectedDate ? $data['days']->get($this->selectedDate) : null,
            'today' => $today,
            'allHabits' => $user->habits()->active()->orderBy('name')->get(['id', 'name', 'is_private']),
            'categories' => $user->categories()->orderBy('name')->get(),
            'frequencyTypes' => FrequencyType::cases(),
            'hasAnyHabit' => $user->habits()->exists(),
        ]);
    }
}
