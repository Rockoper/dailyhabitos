<?php

namespace Tests\Feature\Habits;

use App\Livewire\Habits\HabitForm;
use App\Livewire\Habits\HabitIndex;
use App\Models\Habit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HabitCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_for_habit_routes(): void
    {
        $this->get(route('habits.index'))->assertRedirect(route('login'));
        $this->get(route('habits.today'))->assertRedirect(route('login'));
        $this->get(route('habits.create'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_create_a_binary_daily_habit(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(HabitForm::class)
            ->set('name', 'Leer 10 páginas')
            ->set('type', 'binary')
            ->set('frequency_type', 'daily')
            ->set('start_date', '2026-01-01')
            ->call('save')
            ->assertHasNoErrors();

        $habit = Habit::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('Leer 10 páginas', $habit->name);
        $this->assertSame('binary', $habit->type->value);
        $this->assertSame('daily', $habit->frequency_type->value);
    }

    public function test_creating_a_specific_days_habit_stores_the_selected_days(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(HabitForm::class)
            ->set('name', 'Gimnasio')
            ->set('type', 'binary')
            ->set('frequency_type', 'specific_days')
            ->set('specific_days', [1, 3, 5])
            ->set('start_date', '2026-01-01')
            ->call('save')
            ->assertHasNoErrors();

        $habit = Habit::where('user_id', $user->id)->firstOrFail();

        $this->assertSame([1, 3, 5], $habit->frequency_config['days']);
    }

    public function test_creating_a_weekly_habit_forces_weekly_count_frequency(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(HabitForm::class)
            ->set('name', 'Correr')
            ->set('type', 'weekly')
            ->set('times_per_week', 3)
            ->set('start_date', '2026-01-01')
            ->call('save')
            ->assertHasNoErrors();

        $habit = Habit::where('user_id', $user->id)->firstOrFail();

        $this->assertSame('weekly_count', $habit->frequency_type->value);
        $this->assertSame(3, $habit->frequency_config['times_per_week']);
    }

    public function test_name_is_required_to_create_a_habit(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(HabitForm::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_user_can_edit_their_own_habit(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create(['name' => 'Original']);

        Livewire::actingAs($user)
            ->test(HabitForm::class, ['habit' => $habit])
            ->set('name', 'Actualizado')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Actualizado', $habit->fresh()->name);
    }

    public function test_user_can_archive_and_unarchive_a_habit_from_the_index(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(HabitIndex::class)
            ->call('archive', $habit->id);

        $this->assertTrue($habit->fresh()->is_archived);

        Livewire::actingAs($user)
            ->test(HabitIndex::class)
            ->call('unarchive', $habit->id);

        $this->assertFalse($habit->fresh()->is_archived);
    }

    public function test_user_can_delete_a_habit_from_the_index(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create();

        Livewire::actingAs($user)
            ->test(HabitIndex::class)
            ->call('delete', $habit->id);

        $this->assertSoftDeleted('habits', ['id' => $habit->id]);
    }
}
