<?php

namespace Tests\Unit\Services\History;

use App\Models\DailyReflection;
use App\Models\Goal;
use App\Models\GoalProgressEntry;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use App\Services\Habits\CalendarService;
use App\Services\Habits\ScheduleResolver;
use App\Services\History\HistoryService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryServiceTest extends TestCase
{
    use RefreshDatabase;

    private HistoryService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new HistoryService(new ScheduleResolver, new CalendarService(new ScheduleResolver));
    }

    private function baseFilters(array $overrides = []): array
    {
        return array_merge([
            'type' => 'all', 'status' => 'all', 'habitId' => null, 'goalId' => null, 'categoryId' => null,
            'onlyPerfectDays' => false, 'onlyWithReflection' => false, 'onlyManual' => false, 'onlyAutomatic' => false,
            'search' => '',
        ], $overrides);
    }

    public function test_builds_a_habit_completed_event_with_streak_metadata(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create(['name' => 'Gym', 'start_date' => '2026-07-25']);
        foreach (['2026-07-25', '2026-07-26', '2026-07-27', '2026-07-28', '2026-07-29', '2026-07-30'] as $date) {
            HabitLog::factory()->forHabit($habit)->on($date)->create();
        }

        $result = $this->service->build($user, CarbonImmutable::parse('2026-07-30'), CarbonImmutable::parse('2026-07-30'), CarbonImmutable::parse('2026-07-30'), $this->baseFilters());

        $event = $result['groups']->first()->events->firstWhere('type', 'habit_completed');
        $this->assertNotNull($event);
        $this->assertSame('Hábito "Gym" completado', $event->title);
    }

    public function test_marks_missing_days_with_expected_habits_correctly(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create(['start_date' => '2026-07-01']);
        HabitLog::factory()->forHabit($habit)->failed()->on('2026-07-30')->create();

        $result = $this->service->build($user, CarbonImmutable::parse('2026-07-30'), CarbonImmutable::parse('2026-07-30'), CarbonImmutable::parse('2026-07-30'), $this->baseFilters());

        $group = $result['groups']->first();
        $this->assertSame('pending', $group->dayLevel);
        $this->assertSame(1, $group->expectedHabits);
        $this->assertSame(0, $group->completedHabits);
    }

    public function test_streak_milestone_fires_exactly_on_the_seventh_day(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create(['name' => 'Racha', 'start_date' => '2026-07-24']);
        foreach (range(24, 30) as $day) {
            HabitLog::factory()->forHabit($habit)->on("2026-07-{$day}")->create();
        }

        $result = $this->service->build($user, CarbonImmutable::parse('2026-07-24'), CarbonImmutable::parse('2026-07-30'), CarbonImmutable::parse('2026-07-30'), $this->baseFilters());

        $milestoneEvents = $result['groups']->flatMap(fn ($g) => $g->events)->where('type', 'streak_milestone');
        $this->assertCount(1, $milestoneEvents);
        $this->assertSame('2026-07-30', $milestoneEvents->first()->date->format('Y-m-d'));
    }

    public function test_goal_progress_computes_before_and_after_values(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create(['name' => 'Ahorro', 'initial_value' => 10, 'target_value' => 100]);
        GoalProgressEntry::factory()->forGoal($goal)->on('2026-07-29')->create(['value' => 20]);
        GoalProgressEntry::factory()->forGoal($goal)->on('2026-07-30')->create(['value' => 30]);

        $result = $this->service->build($user, CarbonImmutable::parse('2026-07-30'), CarbonImmutable::parse('2026-07-30'), CarbonImmutable::parse('2026-07-30'), $this->baseFilters());

        $event = $result['groups']->first()->events->firstWhere('type', 'goal_progress');
        $this->assertStringContainsString('30 → 60', $event->description);
    }

    public function test_reflection_snippet_is_truncated_and_never_contains_full_text(): void
    {
        $user = User::factory()->create();
        $long = str_repeat('palabra ', 50);
        DailyReflection::factory()->for($user)->onDate('2026-07-30')->create(['went_well' => $long]);

        $result = $this->service->build($user, CarbonImmutable::parse('2026-07-30'), CarbonImmutable::parse('2026-07-30'), CarbonImmutable::parse('2026-07-30'), $this->baseFilters());

        $event = $result['groups']->first()->events->firstWhere('type', 'reflection');
        $this->assertLessThan(strlen($long), strlen($event->description));
    }

    public function test_type_filter_excludes_other_categories(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create(['start_date' => '2026-07-01']);
        HabitLog::factory()->forHabit($habit)->on('2026-07-30')->create();
        DailyReflection::factory()->for($user)->onDate('2026-07-30')->create();

        $result = $this->service->build($user, CarbonImmutable::parse('2026-07-30'), CarbonImmutable::parse('2026-07-30'), CarbonImmutable::parse('2026-07-30'), $this->baseFilters(['type' => 'reflections']));

        $types = $result['groups']->first()->events->pluck('type')->unique()->values()->all();
        $this->assertSame(['reflection'], $types);
    }

    public function test_no_activity_ever_is_reported_correctly(): void
    {
        $user = User::factory()->create();

        $result = $this->service->build($user, CarbonImmutable::parse('2026-07-01'), CarbonImmutable::parse('2026-07-30'), CarbonImmutable::parse('2026-07-30'), $this->baseFilters());

        $this->assertFalse($result['hasAnyActivityEver']);
        $this->assertTrue($result['groups']->isEmpty());
    }
}
