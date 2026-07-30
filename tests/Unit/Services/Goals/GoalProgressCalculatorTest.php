<?php

namespace Tests\Unit\Services\Goals;

use App\Enums\GoalStatus;
use App\Models\Goal;
use App\Models\GoalProgressEntry;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Services\Goals\GoalProgressCalculator;
use App\Services\Habits\CalendarService;
use App\Services\Habits\ConsistencyCalculator;
use App\Services\Habits\ScheduleResolver;
use App\Services\Habits\StreakCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GoalProgressCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private GoalProgressCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $resolver = new ScheduleResolver;
        $this->calculator = new GoalProgressCalculator(
            new StreakCalculator($resolver),
            new ConsistencyCalculator($resolver),
            new CalendarService($resolver),
        );
    }

    private function reload(Goal $goal): Goal
    {
        return $goal->load(['habit.logs', 'progressEntries']);
    }

    public function test_habit_goal_counts_completions_within_the_goal_window(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);
        $goal = Goal::factory()->habitBased($habit, 3)->create(['start_date' => '2026-01-01']);

        foreach (['2026-01-01', '2026-01-02', '2026-01-03'] as $date) {
            HabitLog::factory()->forHabit($habit)->on($date)->create();
        }
        // Fuera de la ventana del objetivo (antes de start_date del objetivo).
        HabitLog::factory()->forHabit($habit)->on('2025-12-31')->create();

        $data = $this->calculator->compute($this->reload($goal), CarbonImmutable::parse('2026-01-10'));

        $this->assertSame(3.0, $data['current']);
        $this->assertSame(3.0, $data['target']);
        $this->assertSame(100.0, $data['percentage']);
        $this->assertTrue($data['is_achieved']);
    }

    public function test_streak_goal_uses_the_habit_current_streak(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);
        $goal = Goal::factory()->streak($habit, 5)->create(['start_date' => '2026-01-01']);

        foreach (['2026-01-08', '2026-01-09', '2026-01-10'] as $date) {
            HabitLog::factory()->forHabit($habit)->on($date)->create();
        }

        $data = $this->calculator->compute($this->reload($goal), CarbonImmutable::parse('2026-01-10'));

        $this->assertSame(3.0, $data['current']);
        $this->assertSame(5.0, $data['target']);
        $this->assertSame(60.0, $data['percentage']);
        $this->assertFalse($data['is_achieved']);
    }

    public function test_numeric_goal_sums_initial_value_and_progress_entries(): void
    {
        $goal = Goal::factory()->numeric(target: 100, unit: 'páginas', initial: 10)->create();
        GoalProgressEntry::factory()->forGoal($goal)->create(['value' => 20]);
        GoalProgressEntry::factory()->forGoal($goal)->create(['value' => 15]);

        $data = $this->calculator->compute($this->reload($goal), CarbonImmutable::now());

        $this->assertSame(45.0, $data['current']); // 10 + 20 + 15
        $this->assertSame(100.0, $data['target']);
        $this->assertSame(45.0, $data['percentage']);
        $this->assertFalse($data['is_achieved']);
    }

    public function test_numeric_goal_is_achieved_when_target_is_reached(): void
    {
        $goal = Goal::factory()->numeric(target: 50, unit: 'km')->create();
        GoalProgressEntry::factory()->forGoal($goal)->create(['value' => 50]);

        $data = $this->calculator->compute($this->reload($goal), CarbonImmutable::now());

        $this->assertTrue($data['is_achieved']);
        $this->assertSame(100.0, $data['percentage']);
    }

    public function test_percentage_goal_reads_the_habits_consistency_for_the_period(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);
        $goal = Goal::factory()->percentage(90, $habit)->create(['start_date' => '2026-01-01']);

        // Enero: 31 días esperados, 10 completados.
        foreach (range(1, 10) as $day) {
            HabitLog::factory()->forHabit($habit)->on(sprintf('2026-01-%02d', $day))->create();
        }

        $data = $this->calculator->compute($this->reload($goal), CarbonImmutable::parse('2026-01-31'));

        $this->assertEqualsWithDelta(32.3, $data['current'], 0.1); // 10/31
        $this->assertSame(90.0, $data['target']);
    }

    public function test_deadline_goal_has_no_numeric_progress_and_is_achieved_only_when_completed(): void
    {
        $goal = Goal::factory()->deadline('2026-12-31')->create();

        $data = $this->calculator->compute($this->reload($goal), CarbonImmutable::parse('2026-06-01'));
        $this->assertNull($data['current']);
        $this->assertFalse($data['is_achieved']);
        $this->assertSame(0.0, $data['percentage']);

        $goal->update(['status' => GoalStatus::Completed]);
        $data = $this->calculator->compute($this->reload($goal), CarbonImmutable::parse('2026-06-01'));
        $this->assertTrue($data['is_achieved']);
    }

    public function test_a_goal_past_its_due_date_and_not_achieved_is_displayed_as_overdue(): void
    {
        $goal = Goal::factory()->numeric(target: 100, unit: 'km')->create([
            'start_date' => '2026-01-01',
            'due_date' => '2026-01-10',
        ]);
        GoalProgressEntry::factory()->forGoal($goal)->create(['value' => 10]);

        $data = $this->calculator->compute($this->reload($goal), CarbonImmutable::parse('2026-01-15'));

        $this->assertSame('overdue', $data['display_status']);
        $this->assertSame('overdue', $data['risk_level']);
        $this->assertSame(-5, $data['days_remaining']);
    }

    public function test_days_remaining_and_time_elapsed_are_computed_from_start_and_due_dates(): void
    {
        $goal = Goal::factory()->numeric(target: 100, unit: 'km')->create([
            'start_date' => '2026-01-01',
            'due_date' => '2026-01-11',
        ]);

        $data = $this->calculator->compute($this->reload($goal), CarbonImmutable::parse('2026-01-06'));

        $this->assertSame(5, $data['days_remaining']);
        $this->assertSame(50.0, $data['time_elapsed_percentage']);
    }

    public function test_risk_level_is_on_track_when_progress_outpaces_time_elapsed(): void
    {
        $goal = Goal::factory()->numeric(target: 100, unit: 'km')->create([
            'start_date' => '2026-01-01',
            'due_date' => '2026-01-11',
        ]);
        // 50% del tiempo transcurrido, 80% completado -> adelantado.
        GoalProgressEntry::factory()->forGoal($goal)->create(['value' => 80]);

        $data = $this->calculator->compute($this->reload($goal), CarbonImmutable::parse('2026-01-06'));

        $this->assertSame('on_track', $data['risk_level']);
    }

    public function test_risk_level_is_at_risk_when_far_behind_time_elapsed(): void
    {
        $goal = Goal::factory()->numeric(target: 100, unit: 'km')->create([
            'start_date' => '2026-01-01',
            'due_date' => '2026-01-11',
        ]);
        // 90% del tiempo transcurrido, apenas 10% completado -> en riesgo.
        GoalProgressEntry::factory()->forGoal($goal)->create(['value' => 10]);

        $data = $this->calculator->compute($this->reload($goal), CarbonImmutable::parse('2026-01-10'));

        $this->assertSame('at_risk', $data['risk_level']);
    }

    public function test_a_goal_without_a_habit_or_target_returns_a_safe_zeroed_result(): void
    {
        $goal = Goal::factory()->create(['type' => \App\Enums\GoalType::Habit, 'habit_id' => null, 'target_value' => 10]);

        $data = $this->calculator->compute($this->reload($goal), CarbonImmutable::now());

        $this->assertSame(0.0, $data['current']);
        $this->assertSame(0.0, $data['percentage']);
        $this->assertFalse($data['is_achieved']);
    }
}
