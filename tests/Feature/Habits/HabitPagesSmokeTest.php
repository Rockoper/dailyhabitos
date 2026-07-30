<?php

namespace Tests\Feature\Habits;

use App\Models\Habit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HabitPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_habit_pages_render_successfully_for_an_authenticated_user(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create(['start_date' => now()->subDay()]);

        $this->actingAs($user);

        $this->get(route('dashboard'))->assertOk()->assertSee('¡Hola, '.$user->name.'!');
        $this->get(route('habits.today'))->assertOk();
        $this->get(route('habits.index'))->assertOk()->assertSee($habit->name);
        $this->get(route('habits.create'))->assertOk();
        $this->get(route('habits.edit', $habit))->assertOk();
        $this->get(route('habits.show', $habit))->assertOk()->assertSee($habit->name);
    }
}
