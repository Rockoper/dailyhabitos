<?php

namespace App\Livewire\Dashboard;

use App\Enums\LogStatus;
use App\Models\Habit;
use App\Services\Habits\HabitStatsService;
use App\Services\Habits\ScheduleResolver;
use App\Services\Habits\Support\HabitLogEvaluator;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class Overview extends Component
{
    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(Auth::user()->timezone ?: config('app.timezone'))->startOfDay();
    }

    public function render(HabitStatsService $stats, ScheduleResolver $resolver)
    {
        $today = $this->today();
        $habits = Auth::user()->habits()->active()->with(['logs', 'category'])->orderBy('position')->get();

        $expectedToday = $habits->filter(fn (Habit $habit) => $resolver->isExpected($habit, $today))->values();

        $todayItems = $expectedToday->map(fn (Habit $habit) => [
            'habit' => $habit,
            'summary' => $stats->summary($habit, $today),
            'log' => $habit->logs->first(fn ($log) => $log->date->isSameDay($today)),
        ]);

        $completedToday = $todayItems->filter(fn (array $item) => $item['log']?->status === LogStatus::Completed)->count();
        $totalToday = $todayItems->count();

        $topStreaks = $todayItems
            ->concat($habits->diff($expectedToday)->map(fn (Habit $habit) => [
                'habit' => $habit,
                'summary' => $stats->summary($habit, $today),
                'log' => null,
            ]))
            ->filter(fn (array $item) => $item['summary']['current_streak'] > 0)
            ->sortByDesc(fn (array $item) => $item['summary']['current_streak'])
            ->take(5)
            ->values();

        $atRisk = $todayItems
            ->filter(fn (array $item) => $stats->isAtRisk($item['habit'], $today))
            ->values();

        return view('livewire.dashboard.overview', [
            'todayItems' => $todayItems,
            'completedToday' => $completedToday,
            'totalToday' => $totalToday,
            'topStreaks' => $topStreaks,
            'atRisk' => $atRisk,
            'weeklyPercentage' => $this->weeklyPercentage($habits, $today, $resolver),
            'activityGrid' => $this->aggregateActivityGrid($habits, $today, $resolver),
            'today' => $today,
        ]);
    }

    /**
     * Rollup simplificado del cumplimiento de los últimos 7 días sobre todos los
     * hábitos activos. A diferencia de ConsistencyCalculator (por hábito), aquí
     * los hábitos semanales cuentan cada día como "esperado" para dar una cifra
     * agregada rápida en el dashboard, no la métrica oficial de ese hábito.
     */
    private function weeklyPercentage(Collection $habits, CarbonImmutable $today, ScheduleResolver $resolver): float
    {
        $weekStart = $today->subDays(6);
        $expected = 0;
        $completed = 0;

        foreach ($habits as $habit) {
            $evaluator = new HabitLogEvaluator($habit);
            foreach ($resolver->expectedDates($habit, $weekStart, $today) as $date) {
                $expected++;
                if ($evaluator->isCompleted($date)) {
                    $completed++;
                }
            }
        }

        return $expected > 0 ? round($completed / $expected * 100, 1) : 0.0;
    }

    /**
     * @return Collection<int, array{date: CarbonImmutable, level: string}>
     */
    private function aggregateActivityGrid(Collection $habits, CarbonImmutable $today, ScheduleResolver $resolver, int $weeks = 12): Collection
    {
        $end = $today->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay();
        $start = $end->subWeeks($weeks - 1)->startOfWeek(CarbonImmutable::MONDAY);

        $evaluators = $habits->mapWithKeys(fn (Habit $habit) => [$habit->id => new HabitLogEvaluator($habit)]);

        $cells = collect();
        $cursor = $start;

        while ($cursor->lte($end)) {
            $cells->push(['date' => $cursor, 'level' => $this->levelForDay($habits, $evaluators, $cursor, $today, $resolver)]);
            $cursor = $cursor->addDay();
        }

        return $cells;
    }

    private function levelForDay(Collection $habits, Collection $evaluators, CarbonImmutable $date, CarbonImmutable $today, ScheduleResolver $resolver): string
    {
        if ($date->gt($today)) {
            return 'rest';
        }

        $expected = $habits->filter(fn (Habit $habit) => $resolver->isExpected($habit, $date));
        if ($expected->isEmpty()) {
            return 'rest';
        }

        $completed = $expected->filter(fn (Habit $habit) => $evaluators[$habit->id]->isCompleted($date))->count();
        $ratio = $completed / $expected->count();

        if ($ratio >= 1) {
            return 'completed';
        }

        if ($date->isSameDay($today)) {
            return 'pending';
        }

        return $ratio > 0 ? 'partial' : 'failed';
    }
}
