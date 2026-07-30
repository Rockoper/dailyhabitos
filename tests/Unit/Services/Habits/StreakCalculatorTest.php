<?php

namespace Tests\Unit\Services\Habits;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Services\Habits\ScheduleResolver;
use App\Services\Habits\StreakCalculator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StreakCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private StreakCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new StreakCalculator(new ScheduleResolver);
    }

    public function test_current_streak_counts_consecutive_completed_days_up_to_today(): void
    {
        $today = CarbonImmutable::parse('2026-01-10');
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);

        foreach ([6, 7, 8, 9] as $day) {
            HabitLog::factory()->forHabit($habit)->on("2026-01-{$day}")->create();
        }

        $this->assertSame(4, $this->calculator->current($habit, $today));
    }

    public function test_today_pending_does_not_break_the_current_streak(): void
    {
        $today = CarbonImmutable::parse('2026-01-10');
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);

        foreach ([7, 8, 9] as $day) {
            HabitLog::factory()->forHabit($habit)->on("2026-01-{$day}")->create();
        }
        // Sin registro para 2026-01-10 (hoy): no debe romper la racha.

        $this->assertSame(3, $this->calculator->current($habit, $today));
    }

    public function test_a_failed_past_day_breaks_the_streak_without_never_fail_twice(): void
    {
        $today = CarbonImmutable::parse('2026-01-10');
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);

        HabitLog::factory()->forHabit($habit)->on('2026-01-09')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-08')->failed()->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-07')->create();

        $this->assertSame(1, $this->calculator->current($habit, $today));
    }

    public function test_never_fail_twice_forgives_every_isolated_failure(): void
    {
        // Dos fallos aislados y no consecutivos entre sí: ambos se perdonan.
        $today = CarbonImmutable::parse('2026-01-10');
        $habit = Habit::factory()->neverFailTwice()->create(['start_date' => '2026-01-05']);

        HabitLog::factory()->forHabit($habit)->on('2026-01-09')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-08')->failed()->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-07')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-06')->failed()->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-05')->create();

        $this->assertSame(3, $this->calculator->current($habit, $today));
    }

    public function test_never_fail_twice_still_breaks_on_two_consecutive_failures(): void
    {
        $today = CarbonImmutable::parse('2026-01-10');
        $habit = Habit::factory()->neverFailTwice()->create(['start_date' => '2026-01-01']);

        HabitLog::factory()->forHabit($habit)->on('2026-01-09')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-08')->failed()->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-07')->failed()->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-06')->create();

        $this->assertSame(1, $this->calculator->current($habit, $today));
    }

    public function test_quantity_habit_counts_as_completed_when_target_is_reached(): void
    {
        $today = CarbonImmutable::parse('2026-01-05');
        $habit = Habit::factory()->quantity(30)->create(['start_date' => '2026-01-01']);

        HabitLog::factory()->forHabit($habit)->create(['date' => '2026-01-04', 'status' => 'partial', 'quantity_value' => 35]);
        HabitLog::factory()->forHabit($habit)->create(['date' => '2026-01-03', 'status' => 'partial', 'quantity_value' => 10]);

        $this->assertSame(1, $this->calculator->current($habit, $today));
    }

    public function test_weekly_count_streak_counts_consecutive_weeks_meeting_the_target(): void
    {
        $today = CarbonImmutable::parse('2026-01-19'); // lunes
        $habit = Habit::factory()->weeklyCount(2)->create(['start_date' => '2026-01-01']);

        // Semana del 5 al 11 de enero: 2 registros -> cumple.
        HabitLog::factory()->forHabit($habit)->on('2026-01-05')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-07')->create();

        // Semana del 12 al 18 de enero: 2 registros -> cumple.
        HabitLog::factory()->forHabit($habit)->on('2026-01-12')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-14')->create();

        $this->assertSame(2, $this->calculator->current($habit, $today));
    }

    public function test_longest_streak_scans_full_history_not_just_current_run(): void
    {
        $today = CarbonImmutable::parse('2026-01-11');
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);

        foreach ([1, 2, 3, 4, 5] as $day) {
            HabitLog::factory()->forHabit($habit)->on(sprintf('2026-01-%02d', $day))->create();
        }
        HabitLog::factory()->forHabit($habit)->on('2026-01-06')->failed()->create();
        foreach ([10, 11] as $day) {
            HabitLog::factory()->forHabit($habit)->on(sprintf('2026-01-%02d', $day))->create();
        }

        $this->assertSame(5, $this->calculator->longest($habit, $today));
        $this->assertSame(2, $this->calculator->current($habit, $today));
    }
}
