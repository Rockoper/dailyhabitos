<?php

namespace Tests\Unit\Services\Statistics;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Services\Habits\CalendarService;
use App\Services\Habits\ScheduleResolver;
use App\Services\Habits\StreakCalculator;
use App\Services\Statistics\StatisticsService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class StatisticsServiceTest extends TestCase
{
    use RefreshDatabase;

    private StatisticsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new StatisticsService(
            new CalendarService(new ScheduleResolver),
            new StreakCalculator(new ScheduleResolver)
        );
    }

    /**
     * @param  array<int, Habit>  $habits
     */
    private function loaded(array $habits): Collection
    {
        return collect($habits)->map(fn (Habit $habit) => $habit->load(['logs', 'category']));
    }

    public function test_completion_percentage_is_calculated_over_the_selected_period(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);
        foreach ([1, 2, 3, 4] as $day) {
            HabitLog::factory()->forHabit($habit)->on(sprintf('2026-01-%02d', $day))->create();
        }

        $data = $this->service->build(
            $this->loaded([$habit]),
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-10'),
            CarbonImmutable::parse('2026-01-10'),
            'daily',
            'percentage'
        );

        // 4 de 10 días esperados.
        $this->assertSame(40.0, $data['metrics']['completion_percentage']['value']);
    }

    public function test_the_previous_equivalent_period_is_used_for_comparison(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2025-12-01']);
        // Periodo actual (2026-01-01..10, 10 días): 5 completados -> 50%.
        foreach (range(1, 5) as $day) {
            HabitLog::factory()->forHabit($habit)->on(sprintf('2026-01-%02d', $day))->create();
        }
        // Periodo anterior (2025-12-22..31, 10 días): 2 completados -> 20%.
        foreach (['2025-12-22', '2025-12-23'] as $date) {
            HabitLog::factory()->forHabit($habit)->on($date)->create();
        }

        $data = $this->service->build(
            $this->loaded([$habit]),
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-10'),
            CarbonImmutable::parse('2026-01-10'),
            'daily',
            'percentage'
        );

        $metric = $data['metrics']['completion_percentage'];
        $this->assertSame(50.0, $metric['value']);
        $this->assertSame(20.0, $metric['previous']);
        $this->assertSame(30.0, $metric['delta']);
        $this->assertSame('up', $metric['trend']);
    }

    public function test_current_streak_reflects_full_history_not_just_the_filtered_period(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2025-12-25']);
        // Racha de 5 días hasta "hoy" (2026-01-10), aunque el periodo filtrado sea más corto.
        foreach (range(6, 10) as $day) {
            HabitLog::factory()->forHabit($habit)->on(sprintf('2026-01-%02d', $day))->create();
        }

        $data = $this->service->build(
            $this->loaded([$habit]),
            CarbonImmutable::parse('2026-01-08'),
            CarbonImmutable::parse('2026-01-10'),
            CarbonImmutable::parse('2026-01-10'),
            'daily',
            'percentage'
        );

        $this->assertSame(5, $data['streaks']['current_global']);
        $this->assertSame(5, $data['metrics']['current_streak']['value']);
    }

    public function test_best_streak_is_the_longest_run_in_history(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);
        foreach (range(1, 4) as $day) {
            HabitLog::factory()->forHabit($habit)->on(sprintf('2026-01-%02d', $day))->create();
        }
        foreach (range(8, 9) as $day) {
            HabitLog::factory()->forHabit($habit)->on(sprintf('2026-01-%02d', $day))->create();
        }

        $data = $this->service->build(
            $this->loaded([$habit]),
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-10'),
            CarbonImmutable::parse('2026-01-10'),
            'daily',
            'percentage'
        );

        $this->assertSame(4, $data['streaks']['best_global']);
        $this->assertSame(4, $data['metrics']['best_streak']['value']);
    }

    public function test_perfect_days_are_counted_when_every_expected_habit_is_completed(): void
    {
        $habitA = Habit::factory()->create(['start_date' => '2026-01-01']);
        $habitB = Habit::factory()->create(['start_date' => '2026-01-01']);
        HabitLog::factory()->forHabit($habitA)->on('2026-01-05')->create();
        HabitLog::factory()->forHabit($habitB)->on('2026-01-05')->create();

        $data = $this->service->build(
            $this->loaded([$habitA, $habitB]),
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-10'),
            CarbonImmutable::parse('2026-01-10'),
            'daily',
            'percentage'
        );

        $this->assertSame(1, $data['consistency']['perfect']);
    }

    public function test_a_partial_day_is_not_counted_as_perfect(): void
    {
        $habitA = Habit::factory()->create(['start_date' => '2026-01-01']);
        $habitB = Habit::factory()->create(['start_date' => '2026-01-01']);
        HabitLog::factory()->forHabit($habitA)->on('2026-01-05')->create();
        // habitB no tiene registro el 2026-01-05.

        $data = $this->service->build(
            $this->loaded([$habitA, $habitB]),
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-10'),
            CarbonImmutable::parse('2026-01-10'),
            'daily',
            'percentage'
        );

        $this->assertSame(1, $data['consistency']['partial']);
        $this->assertSame(0, $data['consistency']['perfect']);
    }

    public function test_a_day_with_no_logs_at_all_is_counted_as_inactive(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);

        $data = $this->service->build(
            $this->loaded([$habit]),
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-10'),
            CarbonImmutable::parse('2026-01-10'),
            'daily',
            'percentage'
        );

        $this->assertSame(10, $data['consistency']['inactive']);
    }

    public function test_leap_year_is_handled_correctly_across_a_full_year_period(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2024-01-01']);
        HabitLog::factory()->forHabit($habit)->on('2024-02-29')->create();

        $data = $this->service->build(
            $this->loaded([$habit]),
            CarbonImmutable::parse('2024-01-01'),
            CarbonImmutable::parse('2024-12-31'),
            CarbonImmutable::parse('2024-12-31'),
            'monthly',
            'percentage'
        );

        $this->assertTrue($data['activityMap']->has('2024-02-29'));
        $this->assertCount(366, $data['activityMap']);
    }

    public function test_an_empty_habit_set_returns_a_safe_zeroed_result_without_errors(): void
    {
        $data = $this->service->build(
            collect(),
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-10'),
            CarbonImmutable::parse('2026-01-10'),
            'daily',
            'percentage'
        );

        $this->assertFalse($data['hasData']);
        $this->assertSame(0.0, $data['metrics']['completion_percentage']['value']);
        $this->assertSame(0, $data['streaks']['current_global']);
        $this->assertTrue($data['habitPerformance']->isEmpty());
    }
}
