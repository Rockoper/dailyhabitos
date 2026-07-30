<?php

namespace Tests\Unit\Services\Habits;

use App\Models\Habit;
use App\Services\Habits\ScheduleResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScheduleResolverTest extends TestCase
{
    use RefreshDatabase;

    private ScheduleResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new ScheduleResolver;
    }

    public function test_daily_frequency_expects_every_day(): void
    {
        $start = CarbonImmutable::parse('2026-01-01');
        $habit = Habit::factory()->create(['start_date' => $start]);

        $dates = $this->resolver->expectedDates($habit, $start, $start->addDays(6));

        $this->assertCount(7, $dates);
    }

    public function test_specific_days_only_expects_configured_weekdays(): void
    {
        $start = CarbonImmutable::parse('2026-01-05');
        $habit = Habit::factory()->specificDays([$start->isoWeekday()])->create(['start_date' => $start]);

        $dates = $this->resolver->expectedDates($habit, $start, $start->addDays(13));

        $this->assertCount(2, $dates);
        $this->assertTrue($dates->first()->isSameDay($start));
        $this->assertTrue($dates->last()->isSameDay($start->addDays(7)));
    }

    public function test_interval_frequency_expects_every_n_days_from_start(): void
    {
        $start = CarbonImmutable::parse('2026-01-01');
        $habit = Habit::factory()->interval(3)->create(['start_date' => $start]);

        $dates = $this->resolver->expectedDates($habit, $start, $start->addDays(9));

        $this->assertCount(4, $dates);
        $this->assertSame(
            ['2026-01-01', '2026-01-04', '2026-01-07', '2026-01-10'],
            $dates->map(fn ($date) => $date->toDateString())->all()
        );
    }

    public function test_expected_dates_respect_start_and_end_date_bounds(): void
    {
        $start = CarbonImmutable::parse('2026-01-10');
        $end = CarbonImmutable::parse('2026-01-12');
        $habit = Habit::factory()->create(['start_date' => $start, 'end_date' => $end]);

        $dates = $this->resolver->expectedDates($habit, CarbonImmutable::parse('2026-01-01'), CarbonImmutable::parse('2026-01-31'));

        $this->assertCount(3, $dates);
        $this->assertTrue($dates->first()->isSameDay($start));
        $this->assertTrue($dates->last()->isSameDay($end));
    }

    public function test_expected_weeks_groups_monday_to_sunday(): void
    {
        $start = CarbonImmutable::parse('2026-01-01'); // jueves
        $habit = Habit::factory()->weeklyCount(3)->create(['start_date' => $start]);

        $weeks = $this->resolver->expectedWeeks($habit, $start, $start->addDays(10));

        foreach ($weeks as $week) {
            $this->assertSame(1, $week['start']->isoWeekday());
            $this->assertSame(7, $week['end']->isoWeekday());
        }
    }
}
