<?php

namespace Tests\Feature\Habits;

use App\Enums\LogStatus;
use App\Livewire\Habits\HabitDetail;
use App\Livewire\Habits\TodayList;
use App\Models\Habit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HabitLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_marking_a_binary_habit_complete_creates_a_completed_log(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);
        $habit = Habit::factory()->for($user)->create(['start_date' => now()->subDay()]);

        Livewire::actingAs($user)
            ->test(TodayList::class)
            ->call('toggleBinary', $habit->id);

        $this->assertDatabaseHas('habit_logs', [
            'habit_id' => $habit->id,
            'status' => LogStatus::Completed->value,
        ]);
    }

    public function test_toggling_a_completed_binary_habit_again_removes_the_log(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);
        $habit = Habit::factory()->for($user)->create(['start_date' => now()->subDay()]);

        $component = Livewire::actingAs($user)->test(TodayList::class);
        $component->call('toggleBinary', $habit->id);
        $this->assertDatabaseCount('habit_logs', 1);

        $component->call('toggleBinary', $habit->id);
        $this->assertDatabaseCount('habit_logs', 0);
    }

    public function test_logging_a_quantity_below_target_is_marked_partial(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);
        $habit = Habit::factory()->for($user)->quantity(30)->create(['start_date' => now()->subDay()]);

        Livewire::actingAs($user)
            ->test(TodayList::class)
            ->set('quantityInputs.'.$habit->id, 10)
            ->call('logQuantity', $habit->id);

        $this->assertDatabaseHas('habit_logs', [
            'habit_id' => $habit->id,
            'status' => LogStatus::Partial->value,
            'quantity_value' => 10,
        ]);
    }

    public function test_logging_a_quantity_meeting_target_is_marked_completed(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);
        $habit = Habit::factory()->for($user)->quantity(30)->create(['start_date' => now()->subDay()]);

        Livewire::actingAs($user)
            ->test(TodayList::class)
            ->set('quantityInputs.'.$habit->id, 45)
            ->call('logQuantity', $habit->id);

        $this->assertDatabaseHas('habit_logs', [
            'habit_id' => $habit->id,
            'status' => LogStatus::Completed->value,
            'quantity_value' => 45,
        ]);
    }

    public function test_habit_detail_shows_current_and_longest_streak(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);
        $habit = Habit::factory()->for($user)->create(['start_date' => now()->subDays(3)]);

        Livewire::actingAs($user)
            ->test(HabitDetail::class, ['habit' => $habit])
            ->call('toggleBinary')
            ->assertSet('habit.id', $habit->id);

        $this->assertDatabaseHas('habit_logs', ['habit_id' => $habit->id, 'status' => LogStatus::Completed->value]);
    }

    public function test_a_note_can_be_saved_along_with_a_log(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);
        $habit = Habit::factory()->for($user)->create(['start_date' => now()->subDay()]);

        Livewire::actingAs($user)
            ->test(HabitDetail::class, ['habit' => $habit])
            ->set('noteInput', 'Me costó pero lo logré')
            ->call('toggleBinary');

        $this->assertDatabaseHas('habit_logs', [
            'habit_id' => $habit->id,
            'note' => 'Me costó pero lo logré',
        ]);
    }
}
