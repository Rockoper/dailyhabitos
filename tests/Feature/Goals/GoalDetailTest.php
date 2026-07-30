<?php

namespace Tests\Feature\Goals;

use App\Livewire\Goals\GoalDetail;
use App\Models\Goal;
use App\Models\GoalProgressEntry;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class GoalDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_cannot_view_another_users_goal(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $goal = Goal::factory()->for($owner)->create();

        Livewire::actingAs($intruder)
            ->test(GoalDetail::class, ['goal' => $goal])
            ->assertForbidden();
    }

    public function test_the_route_returns_the_goal_detail_for_its_owner(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create(['name' => 'Terminar curso']);

        $this->actingAs($user)
            ->get(route('goals.show', $goal))
            ->assertOk()
            ->assertSee('Terminar curso');
    }

    public function test_manual_progress_entries_accumulate_the_current_value(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->numeric(target: 100, unit: 'km', initial: 0)->create();

        Livewire::actingAs($user)
            ->test(GoalDetail::class, ['goal' => $goal])
            ->set('progressValue', 12.5)
            ->set('progressDate', now()->toDateString())
            ->call('addProgress')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('goal_progress_entries', [
            'goal_id' => $goal->id,
            'user_id' => $user->id,
            'value' => 12.5,
        ]);
    }

    public function test_a_user_can_correct_or_delete_their_own_progress_entry(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->numeric(target: 100, unit: 'km')->create();
        $entry = GoalProgressEntry::factory()->forGoal($goal)->create(['value' => 5]);

        Livewire::actingAs($user)
            ->test(GoalDetail::class, ['goal' => $goal])
            ->call('editEntry', $entry->id)
            ->set('progressValue', 8)
            ->call('addProgress');

        $this->assertSame(8.0, (float) $entry->fresh()->value);

        Livewire::actingAs($user)
            ->test(GoalDetail::class, ['goal' => $goal])
            ->call('deleteEntry', $entry->id);

        $this->assertDatabaseMissing('goal_progress_entries', ['id' => $entry->id]);
    }

    public function test_a_habit_based_goal_progress_reflects_real_habit_logs_without_duplicating_them(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create(['name' => 'Gym', 'start_date' => '2026-01-01']);
        $goal = Goal::factory()->for($user)->habitBased($habit, 3)->create(['start_date' => '2026-01-01']);

        HabitLog::factory()->forHabit($habit)->on('2026-01-01')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-01-02')->create();

        $component = Livewire::actingAs($user)->test(GoalDetail::class, ['goal' => $goal]);
        $component->assertSeeHtml('66.7%');

        $this->assertDatabaseCount('goal_progress_entries', 0);
    }

    public function test_a_streak_goal_reflects_the_habits_current_streak(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create(['start_date' => now()->subDays(10)]);
        $goal = Goal::factory()->for($user)->streak($habit, 5)->create();

        foreach ([2, 1, 0] as $daysAgo) {
            HabitLog::factory()->forHabit($habit)->on(now()->subDays($daysAgo)->toDateString())->create();
        }

        $component = Livewire::actingAs($user)->test(GoalDetail::class, ['goal' => $goal]);
        $component->assertSeeHtml('3 / 5');
    }

    public function test_a_goal_is_automatically_completed_when_its_target_is_reached(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->numeric(target: 10, unit: 'libros')->create();
        GoalProgressEntry::factory()->forGoal($goal)->create(['value' => 10]);

        Livewire::actingAs($user)->test(GoalDetail::class, ['goal' => $goal]);

        $this->assertSame('completed', $goal->fresh()->status->value);
        $this->assertNotNull($goal->fresh()->completed_at);
    }

    public function test_a_goal_past_its_due_date_without_reaching_the_target_shows_as_overdue(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->numeric(target: 100, unit: 'km')->create([
            'start_date' => now()->subDays(20)->toDateString(),
            'due_date' => now()->subDays(1)->toDateString(),
        ]);
        GoalProgressEntry::factory()->forGoal($goal)->create(['value' => 10]);

        $component = Livewire::actingAs($user)->test(GoalDetail::class, ['goal' => $goal]);

        $component->assertSeeHtml('Vencido');
        // El estado persistido nunca se toca automáticamente para "vencido": sigue activo.
        $this->assertSame('active', $goal->fresh()->status->value);
    }

    public function test_pausing_and_resuming_a_goal(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create();

        Livewire::actingAs($user)->test(GoalDetail::class, ['goal' => $goal])->call('pause');
        $this->assertSame('paused', $goal->fresh()->status->value);

        Livewire::actingAs($user)->test(GoalDetail::class, ['goal' => $goal->fresh()])->call('resume');
        $this->assertSame('active', $goal->fresh()->status->value);
    }

    public function test_archiving_a_goal_does_not_delete_it(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create();

        Livewire::actingAs($user)->test(GoalDetail::class, ['goal' => $goal])->call('archive');

        $goal->refresh();
        $this->assertSame('archived', $goal->status->value);
        $this->assertNotNull($goal->archived_at);
        $this->assertDatabaseHas('goals', ['id' => $goal->id]);
    }

    public function test_deleting_a_goal_soft_deletes_it(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create();

        Livewire::actingAs($user)->test(GoalDetail::class, ['goal' => $goal])->call('delete');

        $this->assertSoftDeleted('goals', ['id' => $goal->id]);
    }

    public function test_goal_dates_are_evaluated_in_the_users_timezone(): void
    {
        $user = User::factory()->create(['timezone' => 'Pacific/Kiritimati']);
        $goal = Goal::factory()->for($user)->numeric(target: 10, unit: 'km')->create([
            'start_date' => \Carbon\CarbonImmutable::now('Pacific/Kiritimati')->toDateString(),
            'due_date' => \Carbon\CarbonImmutable::now('Pacific/Kiritimati')->addDays(9)->toDateString(),
        ]);

        $component = Livewire::actingAs($user)->test(GoalDetail::class, ['goal' => $goal]);

        // 9 días restantes: si la fecha se evaluara en la zona del servidor
        // (America/Bogota, UTC-5) en vez de Pacific/Kiritimati (UTC+14),
        // el cálculo de días restantes se correría.
        $component->assertSeeHtml('9');
    }
}
