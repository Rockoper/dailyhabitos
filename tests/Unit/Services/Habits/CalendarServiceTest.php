<?php

namespace Tests\Unit\Services\Habits;

use App\Models\Habit;
use App\Models\HabitLog;
use App\Services\Habits\CalendarService;
use App\Services\Habits\ScheduleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;

class CalendarServiceTest extends TestCase
{
    use RefreshDatabase;

    private CalendarService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CalendarService(new ScheduleResolver);
    }

    /**
     * @param  array<int, Habit>  $habits
     */
    private function withLoadedLogs(array $habits): Collection
    {
        return collect($habits)->map(fn (Habit $habit) => $habit->load('logs'));
    }

    public function test_a_day_with_no_expected_habits_is_marked_as_none(): void
    {
        $habit = Habit::factory()->specificDays([1])->create(['start_date' => '2026-01-01']);

        $data = $this->service->buildYear(
            $this->withLoadedLogs([$habit]),
            2026,
            CarbonImmutable::parse('2026-01-10'),
            'America/Bogota'
        );

        // 2026-01-07 es miércoles (día ISO 3), no lunes: no está programado.
        $day = $data['days']->get('2026-01-07');

        $this->assertSame('none', $day['level']);
        $this->assertNull($day['percentage']);
    }

    public function test_a_fully_completed_day_is_marked_as_completed(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);
        HabitLog::factory()->forHabit($habit)->on('2026-01-05')->create();

        $data = $this->service->buildYear(
            $this->withLoadedLogs([$habit]),
            2026,
            CarbonImmutable::parse('2026-01-10'),
            'America/Bogota'
        );

        $day = $data['days']->get('2026-01-05');

        $this->assertSame('completed', $day['level']);
        $this->assertSame(100.0, $day['percentage']);
    }

    public function test_a_partially_completed_day_is_marked_as_partial(): void
    {
        $habitA = Habit::factory()->create(['start_date' => '2026-01-01']);
        $habitB = Habit::factory()->create(['start_date' => '2026-01-01']);
        HabitLog::factory()->forHabit($habitA)->on('2026-01-05')->create();
        // habitB no tiene registro el 2026-01-05.

        $data = $this->service->buildYear(
            $this->withLoadedLogs([$habitA, $habitB]),
            2026,
            CarbonImmutable::parse('2026-01-10'),
            'America/Bogota'
        );

        $day = $data['days']->get('2026-01-05');

        $this->assertSame('partial', $day['level']);
        $this->assertSame(50.0, $day['percentage']);
    }

    public function test_a_past_day_with_nothing_completed_is_marked_as_pending(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);

        $data = $this->service->buildYear(
            $this->withLoadedLogs([$habit]),
            2026,
            CarbonImmutable::parse('2026-01-10'),
            'America/Bogota'
        );

        $day = $data['days']->get('2026-01-05');

        $this->assertSame('pending', $day['level']);
        $this->assertSame(0.0, $day['percentage']);
    }

    public function test_a_day_after_today_is_marked_as_future(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);

        $data = $this->service->buildYear(
            $this->withLoadedLogs([$habit]),
            2026,
            CarbonImmutable::parse('2026-01-10'),
            'America/Bogota'
        );

        $day = $data['days']->get('2026-06-15');

        $this->assertSame('future', $day['level']);
        $this->assertNull($day['percentage']);
    }

    public function test_leap_year_includes_february_29(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2024-01-01']);

        $data = $this->service->buildYear(
            $this->withLoadedLogs([$habit]),
            2024,
            CarbonImmutable::parse('2024-12-31'),
            'America/Bogota'
        );

        $this->assertTrue($data['days']->has('2024-02-29'));
        $this->assertCount(366, $data['days']);

        $february = $data['months']->firstWhere('number', 2);
        $daysInFebruary = $february['weeks']->flatten(1)->filter(fn ($day) => $day !== null)->count();
        $this->assertSame(29, $daysInFebruary);
    }

    public function test_annual_summary_computes_percentage_completed_days_and_streaks(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);
        HabitLog::factory()->forHabit($habit)->on('2026-01-01')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-02')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-03')->create();
        // 2026-01-04 a 2026-01-10: sin registro.

        $data = $this->service->buildYear(
            $this->withLoadedLogs([$habit]),
            2026,
            CarbonImmutable::parse('2026-01-10'),
            'America/Bogota'
        );

        $summary = $data['summary'];

        $this->assertSame(3, $summary['completed_days']);
        $this->assertSame(30.0, $summary['annual_percentage']);
        $this->assertSame(3, $summary['completed_habits_total']);
        $this->assertSame(3, $summary['longest_streak']);
        // La racha actual se rompió el 4 de enero (día pasado sin registro).
        $this->assertSame(0, $summary['current_streak']);
        $this->assertSame('Enero', $summary['best_month']);
    }

    public function test_current_streak_includes_days_up_to_yesterday_when_today_is_still_pending(): void
    {
        $habit = Habit::factory()->create(['start_date' => '2026-01-01']);
        HabitLog::factory()->forHabit($habit)->on('2026-01-08')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-09')->create();
        // 2026-01-10 (hoy) todavía no tiene registro.

        $data = $this->service->buildYear(
            $this->withLoadedLogs([$habit]),
            2026,
            CarbonImmutable::parse('2026-01-10'),
            'America/Bogota'
        );

        $this->assertSame(2, $data['summary']['current_streak']);
    }
}
