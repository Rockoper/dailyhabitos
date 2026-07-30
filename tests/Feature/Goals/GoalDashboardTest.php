<?php

namespace Tests\Feature\Goals;

use App\Livewire\Goals\GoalDashboard;
use App\Models\Category;
use App\Models\Goal;
use App\Models\GoalProgressEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class GoalDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('goals.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_the_goals_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('goals.index'))
            ->assertOk()
            ->assertSee('Objetivos');
    }

    public function test_a_user_only_sees_their_own_goals(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Goal::factory()->for($user)->create(['name' => 'Mío']);
        Goal::factory()->for($otherUser)->create(['name' => 'De otro usuario']);

        Livewire::actingAs($user)
            ->test(GoalDashboard::class)
            ->assertSee('Mío')
            ->assertDontSee('De otro usuario');
    }

    public function test_empty_state_is_shown_when_the_user_has_no_goals(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(GoalDashboard::class)
            ->assertSee('Aún no has creado objetivos');
    }

    public function test_pausing_and_resuming_from_the_dashboard(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create();

        Livewire::actingAs($user)->test(GoalDashboard::class)->call('pause', $goal->id);
        $this->assertSame('paused', $goal->fresh()->status->value);

        Livewire::actingAs($user)->test(GoalDashboard::class)->call('resume', $goal->id);
        $this->assertSame('active', $goal->fresh()->status->value);
    }

    public function test_archiving_from_the_dashboard(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create();

        Livewire::actingAs($user)->test(GoalDashboard::class)->call('archive', $goal->id);

        $this->assertSame('archived', $goal->fresh()->status->value);
    }

    public function test_deleting_from_the_dashboard_soft_deletes(): void
    {
        $user = User::factory()->create();
        $goal = Goal::factory()->for($user)->create();

        Livewire::actingAs($user)->test(GoalDashboard::class)->call('delete', $goal->id);

        $this->assertSoftDeleted('goals', ['id' => $goal->id]);
    }

    public function test_a_user_cannot_transition_another_users_goal_from_the_dashboard(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        $goal = Goal::factory()->for($owner)->create();

        Livewire::actingAs($intruder)
            ->test(GoalDashboard::class)
            ->call('archive', $goal->id)
            ->assertForbidden();

        $this->assertSame('active', $goal->fresh()->status->value);
    }

    public function test_filtering_by_status_narrows_the_list(): void
    {
        // Se compara por la fila en la grilla (wire:key), no por texto plano:
        // la tarjeta KPI "Mejor objetivo en progreso" puede mostrar el nombre
        // de cualquier objetivo sin importar el filtro de estado aplicado.
        $user = User::factory()->create();
        $active = Goal::factory()->for($user)->create(['name' => 'Activo uno']);
        $paused = Goal::factory()->for($user)->paused()->create(['name' => 'Pausado uno']);

        $component = Livewire::actingAs($user)->test(GoalDashboard::class);
        $html = $component->html();
        $this->assertStringContainsString('wire:key="goal-'.$active->id.'"', $html);
        $this->assertStringNotContainsString('wire:key="goal-'.$paused->id.'"', $html);

        $component->set('statusFilter', 'paused');
        $html = $component->html();
        $this->assertStringNotContainsString('wire:key="goal-'.$active->id.'"', $html);
        $this->assertStringContainsString('wire:key="goal-'.$paused->id.'"', $html);
    }

    public function test_filtering_by_category(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Goal::factory()->for($user)->create(['category_id' => $category->id]);
        Goal::factory()->for($user)->create(['category_id' => null]);

        $html = Livewire::actingAs($user)
            ->test(GoalDashboard::class)
            ->set('statusFilter', 'all')
            ->set('categoryId', $category->id)
            ->html();

        $this->assertSame(1, substr_count($html, 'wire:key="goal-'));
    }

    public function test_sorting_by_priority_orders_high_priority_goals_first(): void
    {
        // Nombres distintos de las etiquetas "Baja"/"Alta" que ya aparecen en
        // el selector de prioridad del filtro, para no comparar contra esas.
        $user = User::factory()->create();
        $low = Goal::factory()->for($user)->create(['name' => 'Objetivo prioridad baja', 'priority' => 'low']);
        $high = Goal::factory()->for($user)->create(['name' => 'Objetivo prioridad alta', 'priority' => 'high']);

        $html = Livewire::actingAs($user)
            ->test(GoalDashboard::class)
            ->set('sortBy', 'priority')
            ->html();

        $this->assertLessThan(
            strpos($html, 'wire:key="goal-'.$low->id.'"'),
            strpos($html, 'wire:key="goal-'.$high->id.'"')
        );
    }

    public function test_rendering_the_dashboard_does_not_produce_n_plus_1_queries(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        foreach (range(1, 12) as $i) {
            $goal = Goal::factory()->for($user)->numeric(target: 100, unit: 'km')->create([
                'category_id' => $category->id,
                'name' => "Objetivo {$i}",
            ]);
            GoalProgressEntry::factory()->forGoal($goal)->count(3)->create();
        }

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        Livewire::actingAs($user)->test(GoalDashboard::class);

        $this->assertLessThan(
            20,
            $queryCount,
            "Se ejecutaron {$queryCount} consultas para 12 objetivos; el número de consultas no debe crecer con la cantidad de objetivos."
        );
    }
}
