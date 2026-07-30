<?php

namespace App\Livewire\Statistics;

use App\Enums\HabitType;
use App\Services\Statistics\StatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Throwable;

class StatisticsDashboard extends Component
{
    public string $period = '30d';

    public ?string $customFrom = null;

    public ?string $customTo = null;

    public ?int $habitId = null;

    public ?int $categoryId = null;

    public ?string $habitType = null;

    public string $statusFilter = 'active';

    public string $sortBy = 'performance_desc';

    public string $granularity = 'daily';

    public string $chartMetric = 'percentage';

    public function mount(): void
    {
        $today = $this->today();
        $this->customFrom = $today->subDays(29)->toDateString();
        $this->customTo = $today->toDateString();
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(Auth::user()->timezone ?: config('app.timezone'))->startOfDay();
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolvePeriod(CarbonImmutable $today): array
    {
        return match ($this->period) {
            '7d' => [$today->subDays(6), $today],
            '90d' => [$today->subDays(89), $today],
            'month' => [$today->startOfMonth(), $today],
            'year' => [$today->startOfYear(), $today],
            'custom' => $this->resolveCustomRange($today),
            default => [$today->subDays(29), $today],
        };
    }

    /**
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    private function resolveCustomRange(CarbonImmutable $today): array
    {
        try {
            $from = CarbonImmutable::parse($this->customFrom ?: $today->subDays(29)->toDateString())->startOfDay();
            $to = CarbonImmutable::parse($this->customTo ?: $today->toDateString())->startOfDay();
        } catch (Throwable) {
            return [$today->subDays(29), $today];
        }

        if ($to->gt($today)) {
            $to = $today;
        }
        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }
        if ($from->diffInDays($to) > 365) {
            $from = $to->subDays(365);
        }

        return [$from, $to];
    }

    public function render(StatisticsService $statistics)
    {
        $user = Auth::user();
        $today = $this->today();
        [$from, $to] = $this->resolvePeriod($today);

        $query = $user->habits()->with(['logs', 'category'])->orderBy('position')->orderBy('name');

        match ($this->statusFilter) {
            'archived' => $query->archived(),
            'all' => null,
            default => $query->active(),
        };

        if ($this->habitId) {
            $query->where('id', $this->habitId);
        }
        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }
        if ($this->habitType) {
            $query->where('type', $this->habitType);
        }

        $habits = $query->get();

        $data = $statistics->build($habits, $from, $to, $today, $this->granularity, $this->chartMetric);
        $data['habitPerformance'] = $this->sortHabitPerformance($data['habitPerformance']);

        return view('livewire.statistics.statistics-dashboard', array_merge($data, [
            'from' => $from,
            'to' => $to,
            'today' => $today,
            'allHabits' => $user->habits()->orderBy('name')->get(['id', 'name', 'is_private']),
            'categories' => $user->categories()->orderBy('name')->get(),
            'habitTypes' => HabitType::cases(),
        ]));
    }

    private function sortHabitPerformance(Collection $rows): Collection
    {
        return match ($this->sortBy) {
            'performance_asc' => $rows->sortBy(fn (array $row) => $row['percentage'] ?? -1)->values(),
            'streak_desc' => $rows->sortByDesc(fn (array $row) => $row['current_streak'])->values(),
            'completed_desc' => $rows->sortByDesc(fn (array $row) => $row['completed'])->values(),
            'name' => $rows->sortBy(fn (array $row) => $row['habit']->is_private ? 'Hábito privado' : $row['habit']->name)->values(),
            default => $rows->sortByDesc(fn (array $row) => $row['percentage'] ?? -1)->values(),
        };
    }
}
