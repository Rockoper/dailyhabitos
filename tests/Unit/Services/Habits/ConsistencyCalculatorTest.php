<?php

namespace Tests\Unit\Services\Habits;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Services\Habits\ConsistencyCalculator;
use App\Services\Habits\ScheduleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConsistencyCalculatorTest extends TestCase
{
    use RefreshDatabase;

    private ConsistencyCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new ConsistencyCalculator(new ScheduleResolver);
    }

    public function test_percentage_for_daily_habit_over_a_range(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);

        foreach ([1, 2, 3, 4] as $day) {
            HabitLog::factory()->forHabit($habit)->on(sprintf('2026-01-%02d', $day))->create();
        }

        $percentage = $this->calculator->percentageFor(
            $habit,
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-10')
        );

        // 4 de 10 días esperados.
        $this->assertSame(40.0, $percentage);
    }

    public function test_percentage_for_weekly_count_habit_uses_weeks_met(): void
    {
        $habit = Habit::factory()->weeklyCount(2)->create(['start_date' => '2026-01-01']);

        // Semana 1 (29 dic - 4 ene): 0 cumplidos.
        // Semana 2 (5 - 11 ene): 2 cumplidos -> cumple.
        HabitLog::factory()->forHabit($habit)->on('2026-01-05')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-06')->create();

        $percentage = $this->calculator->percentageFor(
            $habit,
            CarbonImmutable::parse('2026-01-01'),
            CarbonImmutable::parse('2026-01-11')
        );

        $this->assertSame(50.0, $percentage);
    }

    public function test_windows_returns_30_90_365_and_lifetime_keys(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2025-01-01']);

        $windows = $this->calculator->windows($habit, CarbonImmutable::parse('2026-01-10'));

        $this->assertArrayHasKey(30, $windows);
        $this->assertArrayHasKey(90, $windows);
        $this->assertArrayHasKey(365, $windows);
        $this->assertArrayHasKey('lifetime', $windows);
    }
}
