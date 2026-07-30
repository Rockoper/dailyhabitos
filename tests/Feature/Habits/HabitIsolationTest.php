<?php

namespace Tests\Feature\Habits;

use App\Livewire\Habits\HabitDetail;
use App\Livewire\Habits\HabitForm;
use App\Livewire\Habits\HabitIndex;
use App\Livewire\Habits\TodayList;
use App\Models\Habit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HabitIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_view_another_users_habit(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $habit = Habit::factory()->for($owner)->create();

        Livewire::actingAs($intruder)
            ->test(HabitDetail::class, ['habit' => $habit])
            ->assertForbidden();
    }

    public function test_a_user_cannot_edit_another_users_habit(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $habit = Habit::factory()->for($owner)->create();

        Livewire::actingAs($intruder)
            ->test(HabitForm::class, ['habit' => $habit])
            ->assertForbidden();
    }

    public function test_a_user_cannot_archive_another_users_habit(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $habit = Habit::factory()->for($owner)->create();

        Livewire::actingAs($intruder)
            ->test(HabitIndex::class)
            ->call('archive', $habit->id)
            ->assertForbidden();

        $this->assertFalse($habit->fresh()->is_archived);
    }

    public function test_a_user_cannot_log_another_users_habit(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $habit = Habit::factory()->for($owner)->create(['start_date' => now()->subDay()]);

        Livewire::actingAs($intruder)
            ->test(TodayList::class)
            ->call('toggleBinary', $habit->id)
            ->assertForbidden();

        $this->assertDatabaseCount('habit_logs', 0);
    }

    public function test_habits_index_only_lists_the_authenticated_users_habits(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Habit::factory()->for($user)->create(['name' => 'Mío']);
        Habit::factory()->for($otherUser)->create(['name' => 'De otro usuario']);

        Livewire::actingAs($user)
            ->test(HabitIndex::class)
            ->assertSee('Mío')
            ->assertDontSee('De otro usuario');
    }

    public function test_todays_habits_only_include_the_authenticated_users_habits(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Habit::factory()->for($user)->create(['name' => 'Mío', 'start_date' => now()->subDay()]);
        Habit::factory()->for($otherUser)->create(['name' => 'De otro usuario', 'start_date' => now()->subDay()]);

        Livewire::actingAs($user)
            ->test(TodayList::class)
            ->assertSee('Mío')
            ->assertDontSee('De otro usuario');
    }
}
