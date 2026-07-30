<?php

namespace Tests\Feature\Calendar;

use App\Livewire\Calendar\YearHeatmap;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class YearHeatmapTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_for_the_calendar_route(): void
    {
        $this->get(route('calendar.index'))->assertRedirect(route('login'));
    }

    public function test_the_calendar_route_renders_successfully_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('calendar.index'))
            ->assertOk()
            ->assertSee('Calendario anual de progreso');
    }

    public function test_empty_state_is_shown_when_the_user_has_no_habits(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(YearHeatmap::class)
            ->assertSee('Todavía no tienes hábitos');
    }

    public function test_a_user_cannot_see_another_users_habits_in_the_calendar(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Habit::factory()->for($user)->create(['name' => 'Mío']);
        Habit::factory()->for($otherUser)->create(['name' => 'De otro usuario']);

        Livewire::actingAs($user)
            ->test(YearHeatmap::class)
            ->assertSee('Mío')
            ->assertDontSee('De otro usuario');
    }

    public function test_selecting_a_day_opens_its_detail_panel_with_the_expected_habits(): void
    {
        $user = User::factory()->create();
        $today = CarbonImmutable::now($user->timezone ?: config('app.timezone'))->startOfDay();

        $habit = Habit::factory()->for($user)->create(['name' => 'Meditar', 'start_date' => $today->subDays(5)->toDateString()]);
        HabitLog::factory()->forHabit($habit)->on($today->toDateString())->create();

        Livewire::actingAs($user)
            ->test(YearHeatmap::class)
            ->call('selectDay', $today->toDateString())
            ->assertSee('Meditar')
            ->assertSee('Cumplido')
            ->assertSee('Cumplimiento del día');
    }

    public function test_a_day_with_no_habits_scheduled_shows_the_empty_day_message(): void
    {
        $user = User::factory()->create();
        $today = CarbonImmutable::now($user->timezone ?: config('app.timezone'))->startOfDay();

        Habit::factory()->for($user)->specificDays([1])->create(['start_date' => $today->subDays(60)->toDateString()]);

        $notExpectedDate = $today->subDays(30);
        while ($notExpectedDate->isoWeekday() === 1) {
            $notExpectedDate = $notExpectedDate->subDay();
        }

        Livewire::actingAs($user)
            ->test(YearHeatmap::class)
            ->call('selectDay', $notExpectedDate->toDateString())
            ->assertSee('Ningún hábito estaba programado este día');
    }

    public function test_filtering_by_a_specific_habit_only_shows_that_habit_in_the_day_detail(): void
    {
        $user = User::factory()->create();
        $today = CarbonImmutable::now($user->timezone ?: config('app.timezone'))->startOfDay();

        $meditar = Habit::factory()->for($user)->create(['name' => 'Meditar', 'start_date' => $today->subDays(5)->toDateString()]);
        Habit::factory()->for($user)->create(['name' => 'Leer', 'start_date' => $today->subDays(5)->toDateString()]);

        $unfiltered = Livewire::actingAs($user)
            ->test(YearHeatmap::class)
            ->call('selectDay', $today->toDateString());

        $this->assertSame(2, substr_count($unfiltered->html(), 'Ver hábito'));

        $filtered = Livewire::actingAs($user)
            ->test(YearHeatmap::class)
            ->set('habitId', $meditar->id)
            ->call('selectDay', $today->toDateString());

        $this->assertSame(1, substr_count($filtered->html(), 'Ver hábito'));
    }

    public function test_navigating_years_and_returning_to_today_works(): void
    {
        $user = User::factory()->create();
        $currentYear = CarbonImmutable::now($user->timezone ?: config('app.timezone'))->year;

        Livewire::actingAs($user)
            ->test(YearHeatmap::class)
            ->assertSet('year', $currentYear)
            ->call('previousYear')
            ->assertSet('year', $currentYear - 1)
            ->call('nextYear')
            ->assertSet('year', $currentYear)
            ->call('nextYear')
            ->assertSet('year', $currentYear + 1)
            ->call('goToToday')
            ->assertSet('year', $currentYear);
    }
}
