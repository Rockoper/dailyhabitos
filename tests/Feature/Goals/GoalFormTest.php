<?php

namespace Tests\Feature\Goals;

use App\Livewire\Goals\GoalForm;
use App\Models\Category;
use App\Models\Goal;
use App\Models\Habit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GoalFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login_for_goal_routes(): void
    {
        $this->get(route('goals.index'))->assertRedirect(route('login'));
        $this->get(route('goals.create'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_create_a_manual_goal(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(GoalForm::class)
            ->set('name', 'Terminar un curso de React')
            ->set('type', 'manual')
            ->set('priority', 'high')
            ->set('start_date', '2026-01-01')
            ->call('save')
            ->assertHasNoErrors();

        $goal = Goal::where('name', 'Terminar un curso de React')->firstOrFail();
        $this->assertSame($user->id, $goal->user_id);
        $this->assertSame('manual', $goal->type->value);
        $this->assertSame('high', $goal->priority->value);
    }

    public function test_name_is_required(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(GoalForm::class)
            ->set('name', '')
            ->call('save')
            ->assertHasErrors(['name' => 'required']);
    }

    public function test_due_date_must_be_on_or_after_the_start_date(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(GoalForm::class)
            ->set('name', 'Objetivo con fecha inválida')
            ->set('start_date', '2026-06-10')
            ->set('due_date', '2026-06-01')
            ->call('save')
            ->assertHasErrors(['due_date']);
    }

    public function test_numeric_goal_requires_a_target_value(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(GoalForm::class)
            ->set('name', 'Ahorrar dinero')
            ->set('type', 'numeric')
            ->set('unit', 'COP')
            ->set('target_value', '')
            ->call('save')
            ->assertHasErrors(['target_value' => 'required']);
    }

    public function test_a_habit_based_goal_can_link_the_users_own_habit(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create(['name' => 'Gym']);

        Livewire::actingAs($user)
            ->test(GoalForm::class)
            ->set('name', 'Completar Gym 20 veces')
            ->set('type', 'habit')
            ->set('habit_id', $habit->id)
            ->set('target_value', 20)
            ->call('save')
            ->assertHasNoErrors();

        $goal = Goal::where('name', 'Completar Gym 20 veces')->firstOrFail();
        $this->assertSame($habit->id, $goal->habit_id);
    }

    public function test_a_user_cannot_link_another_users_habit(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignHabit = Habit::factory()->for($otherUser)->create();

        Livewire::actingAs($user)
            ->test(GoalForm::class)
            ->set('name', 'Objetivo con hábito ajeno')
            ->set('type', 'habit')
            ->set('habit_id', $foreignHabit->id)
            ->set('target_value', 10)
            ->call('save')
            ->assertHasErrors(['habit_id']);

        $this->assertDatabaseMissing('goals', ['name' => 'Objetivo con hábito ajeno']);
    }

    public function test_a_user_cannot_link_another_users_category(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $foreignCategory = Category::factory()->for($otherUser)->create();

        Livewire::actingAs($user)
            ->test(GoalForm::class)
            ->set('name', 'Objetivo con categoría ajena')
            ->set('category_id', $foreignCategory->id)
            ->call('save')
            ->assertHasErrors(['category_id']);
    }

    public function test_a_user_cannot_edit_another_users_goal(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $goal = Goal::factory()->for($owner)->create();

        Livewire::actingAs($intruder)
            ->test(GoalForm::class, ['goal' => $goal])
            ->assertForbidden();
    }

    public function test_user_can_edit_their_own_goal(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create(['name' => 'Original']);

        Livewire::actingAs($user)
            ->test(GoalForm::class, ['goal' => $goal])
            ->set('name', 'Actualizado')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Actualizado', $goal->fresh()->name);
    }
}
