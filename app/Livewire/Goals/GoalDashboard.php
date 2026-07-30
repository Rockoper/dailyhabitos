<?php

namespace App\Livewire\Goals;

use App\Enums\GoalPriority;
use App\Enums\GoalType;
use App\Models\Goal;
use App\Services\Goals\GoalProgressCalculator;
use App\Services\Goals\GoalService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GoalDashboard extends Component
{
    public string $statusFilter = 'active';

    public ?string $priorityFilter = null;

    public ?int $categoryId = null;

    public ?string $typeFilter = null;

    public ?int $habitId = null;

    public string $dueDateFilter = 'all';

    public string $sortBy = 'progress_desc';

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(Auth::user()->timezone ?: config('app.timezone'))->startOfDay();
    }

    public function archive(Goal $goal, GoalService $goalService): void
    {
        $this->authorize('transition', $goal);
        $goalService->archive($goal);
    }

    public function unarchive(Goal $goal, GoalService $goalService): void
    {
        $this->authorize('transition', $goal);
        $goalService->unarchive($goal);
    }

    public function pause(Goal $goal, GoalService $goalService): void
    {
        $this->authorize('transition', $goal);
        $goalService->pause($goal);
    }

    public function resume(Goal $goal, GoalService $goalService): void
    {
        $this->authorize('transition', $goal);
        $goalService->resume($goal);
    }

    public function complete(Goal $goal, GoalService $goalService): void
    {
        $this->authorize('transition', $goal);
        $goalService->complete($goal);
    }

    public function delete(Goal $goal, GoalService $goalService): void
    {
        $this->authorize('delete', $goal);
        $goalService->delete($goal);
    }

    public function render(GoalProgressCalculator $calculator, GoalService $goalService)
    {
        $user = Auth::user();
        $today = $this->today();

        $query = $user->goals()->with(['habit.logs', 'category', 'progressEntries'])->latest();

        if ($this->categoryId) {
            $query->where('category_id', $this->categoryId);
        }
        if ($this->typeFilter) {
            $query->where('type', $this->typeFilter);
        }
        if ($this->habitId) {
            $query->where('habit_id', $this->habitId);
        }
        if ($this->priorityFilter) {
            $query->where('priority', $this->priorityFilter);
        }
        if ($this->dueDateFilter === 'with_due') {
            $query->whereNotNull('due_date');
        } elseif ($this->dueDateFilter === 'without_due') {
            $query->whereNull('due_date');
        }

        $goals = $query->get();

        $needsAggregateHabits = $goals->contains(fn (Goal $goal) => $goal->type === GoalType::Percentage && $goal->habit_id === null);
        $allUserHabits = $needsAggregateHabits
            ? $user->habits()->active()->with('logs')->get()
            : null;

        $rows = $goals->map(function (Goal $goal) use ($calculator, $goalService, $today, $allUserHabits) {
            $progress = $calculator->compute($goal, $today, $allUserHabits);
            $goalService->syncAutoCompletion($goal, $progress);

            return ['goal' => $goal, 'progress' => $progress];
        });

        $summary = $this->buildSummary($rows, $today);
        $upcoming = $this->buildUpcoming($rows);
        $recentlyCompleted = $rows
            ->filter(fn (array $row) => $row['progress']['display_status'] === 'completed')
            ->sortByDesc(fn (array $row) => $row['goal']->completed_at)
            ->take(5)
            ->values();

        $filtered = $this->statusFilter === 'all'
            ? $rows
            : $rows->filter(fn (array $row) => $row['progress']['display_status'] === $this->statusFilter);

        $sorted = $this->sortRows($filtered->values());

        return view('livewire.goals.goal-dashboard', [
            'rows' => $sorted,
            'summary' => $summary,
            'upcoming' => $upcoming,
            'recentlyCompleted' => $recentlyCompleted,
            'insights' => $this->buildInsights($rows, $today),
            'hasAnyGoal' => $goals->isNotEmpty(),
            'allHabits' => $user->habits()->orderBy('name')->get(['id', 'name', 'is_private']),
            'categories' => $user->categories()->orderBy('name')->get(),
            'goalTypes' => GoalType::cases(),
            'priorities' => GoalPriority::cases(),
        ]);
    }

    /**
     * @param  Collection<int, array{goal: Goal, progress: array}>  $rows
     */
    private function buildSummary(Collection $rows, CarbonImmutable $today): array
    {
        $active = $rows->filter(fn (array $r) => $r['progress']['display_status'] === 'active');
        $overdue = $rows->filter(fn (array $r) => $r['progress']['display_status'] === 'overdue');
        $completed = $rows->filter(fn (array $r) => $r['progress']['display_status'] === 'completed');
        $ongoing = $rows->filter(fn (array $r) => in_array($r['progress']['display_status'], ['active', 'overdue', 'paused'], true));

        $upcomingCount = $active->filter(fn (array $r) => $r['progress']['days_remaining'] !== null && $r['progress']['days_remaining'] <= 7)->count();

        $bestOngoing = $ongoing
            ->filter(fn (array $r) => ! $r['progress']['is_achieved'])
            ->sortByDesc(fn (array $r) => $r['progress']['percentage'])
            ->first();

        return [
            'active_count' => $active->count(),
            'completed_count' => $completed->count(),
            'average_progress' => $ongoing->isNotEmpty() ? round($ongoing->avg(fn (array $r) => $r['progress']['percentage']), 1) : 0.0,
            'upcoming_count' => $upcomingCount,
            'overdue_count' => $overdue->count(),
            'best_ongoing' => $bestOngoing ? [
                'name' => $bestOngoing['goal']->is_private ? 'Objetivo privado' : $bestOngoing['goal']->name,
                'percentage' => $bestOngoing['progress']['percentage'],
            ] : null,
            'total_achieved' => Auth::user()->goals()->whereNotNull('completed_at')->count(),
        ];
    }

    /**
     * @param  Collection<int, array{goal: Goal, progress: array}>  $rows
     */
    private function buildUpcoming(Collection $rows): Collection
    {
        return $rows
            ->filter(fn (array $r) => $r['progress']['risk_level'] !== null
                && in_array($r['progress']['display_status'], ['active', 'overdue'], true)
                && ($r['progress']['risk_level'] === 'overdue' || $r['progress']['days_remaining'] <= 7))
            ->sortBy(fn (array $r) => $r['progress']['days_remaining'])
            ->take(5)
            ->values();
    }

    /**
     * @param  Collection<int, array{goal: Goal, progress: array}>  $rows
     */
    private function sortRows(Collection $rows): Collection
    {
        return match ($this->sortBy) {
            'due_date_asc' => $rows->sortBy(fn (array $r) => $r['progress']['days_remaining'] ?? PHP_INT_MAX)->values(),
            'priority' => $rows->sortByDesc(fn (array $r) => $r['goal']->priority->weight())->values(),
            'newest' => $rows->sortByDesc(fn (array $r) => $r['goal']->created_at)->values(),
            'oldest' => $rows->sortBy(fn (array $r) => $r['goal']->created_at)->values(),
            'name' => $rows->sortBy(fn (array $r) => $r['goal']->is_private ? 'Objetivo privado' : $r['goal']->name)->values(),
            'upcoming' => $rows->sortBy(fn (array $r) => $r['progress']['days_remaining'] ?? PHP_INT_MAX)->values(),
            default => $rows->sortByDesc(fn (array $r) => $r['progress']['percentage'])->values(), // progress_desc
        };
    }

    /**
     * @param  Collection<int, array{goal: Goal, progress: array}>  $rows
     */
    private function buildInsights(Collection $rows, CarbonImmutable $today): array
    {
        $insights = [];

        $completedThisMonth = $rows->filter(
            fn (array $r) => $r['goal']->completed_at && $r['goal']->completed_at->isSameMonth($today)
        )->count();
        if ($completedThisMonth > 0) {
            $insights[] = sprintf('Has completado %d %s este mes.', $completedThisMonth, $completedThisMonth === 1 ? 'objetivo' : 'objetivos');
        }

        $ongoing = $rows->filter(fn (array $r) => in_array($r['progress']['display_status'], ['active', 'overdue'], true) && ! $r['progress']['is_achieved']);
        $best = $ongoing->sortByDesc(fn (array $r) => $r['progress']['percentage'])->first();
        if ($best && $best['progress']['percentage'] > 0) {
            $name = $best['goal']->is_private ? 'Objetivo privado' : $best['goal']->name;
            $insights[] = sprintf('Tu objetivo con mayor progreso es "%s".', $name);
        }

        $atRisk = $rows->filter(fn (array $r) => $r['progress']['risk_level'] === 'at_risk')->count();
        if ($atRisk > 0) {
            $insights[] = sprintf('Tienes %d %s en riesgo de no cumplirse a tiempo.', $atRisk, $atRisk === 1 ? 'objetivo' : 'objetivos');
        }

        return array_slice($insights, 0, 4);
    }
}
