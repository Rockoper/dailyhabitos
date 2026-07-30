<?php

namespace Tests\Unit\Services\Reflections;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use App\Services\Habits\CalendarService;
use App\Services\Habits\ScheduleResolver;
use App\Services\Reflections\ReflectionSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReflectionSummaryServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReflectionSummaryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReflectionSummaryService(new CalendarService(new ScheduleResolver));
    }

    public function test_summary_counts_expected_and_completed_habits(): void
    {
        $user = User::factory()->create();
        $habitA = Habit::factory()->for($user)->create(['start_date' => '2026-07-01']);
        $habitB = Habit::factory()->for($user)->create(['start_date' => '2026-07-01']);
        HabitLog::factory()->forHabit($habitA)->on('2026-07-30')->create();

        $summary = $this->service->daySummary($user->fresh(), CarbonImmutable::parse('2026-07-30'));

        $this->assertSame(2, $summary['expected']);
        $this->assertSame(1, $summary['completed']);
        $this->assertSame(50.0, $summary['percentage']);
        $this->assertSame('partial', $summary['level']);
        $this->assertSame('Día parcial', $summary['day_label']);
    }

    public function test_summary_handles_a_user_with_no_habits(): void
    {
        $user = User::factory()->create();

        $summary = $this->service->daySummary($user, CarbonImmutable::parse('2026-07-30'));

        $this->assertSame(0, $summary['expected']);
        $this->assertSame(0, $summary['completed']);
        $this->assertNull($summary['percentage']);
        $this->assertSame('none', $summary['level']);
        $this->assertSame(0, $summary['current_streak']);
    }

    public function test_last_activity_reflects_the_most_recent_log_that_day(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create(['start_date' => '2026-07-01']);
        HabitLog::factory()->forHabit($habit)->on('2026-07-30')->create();

        $summary = $this->service->daySummary($user->fresh(), CarbonImmutable::parse('2026-07-30'));

        $this->assertNotNull($summary['last_activity_at']);
    }

    public function test_current_streak_is_computed_as_of_the_reflection_date_not_real_today(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create(['start_date' => '2026-07-25']);

        foreach (['2026-07-25', '2026-07-26', '2026-07-27'] as $date) {
            HabitLog::factory()->forHabit($habit)->on($date)->create();
        }
        // 2026-07-28 sin registro: rompe la racha después de esa fecha.
        HabitLog::factory()->forHabit($habit)->on('2026-07-29')->create();

        $summaryAt27 = $this->service->daySummary($user->fresh(), CarbonImmutable::parse('2026-07-27'));
        $this->assertSame(3, $summaryAt27['current_streak']);

        $summaryAt29 = $this->service->daySummary($user->fresh(), CarbonImmutable::parse('2026-07-29'));
        $this->assertSame(1, $summaryAt29['current_streak']);
    }
}
