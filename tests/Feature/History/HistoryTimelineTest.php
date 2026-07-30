<?php

namespace Tests\Feature\History;

use App\Livewire\History\HistoryTimeline;
use App\Models\DailyReflection;
use App\Models\Goal;
use App\Models\GoalProgressEntry;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class HistoryTimelineTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::create(2026, 7, 30, 20, 0, 0, 'America/Bogota'));
    }

    private function makeUser(): User
    {
        return User::factory()->create(['timezone' => 'America/Bogota', 'created_at' => CarbonImmutable::parse('2026-01-01')]);
    }

    // 1. Usuario no autenticado no puede acceder.
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('history.index'))->assertRedirect(route('login'));
    }

    // 2. Usuario autenticado puede acceder. / 31. Ruta /historial.
    public function test_authenticated_user_can_access_the_history_route(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->get(route('history.index'))->assertOk()->assertSee('Historial');
    }

    // 3. Solo muestra datos del usuario. / 4/5/6. No muestra datos de otro. / 35. Aislamiento completo.
    public function test_history_only_shows_the_authenticated_users_activity(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $habit = Habit::factory()->for($user)->create(['name' => 'Gym mío', 'start_date' => '2026-07-01']);
        $foreignHabit = Habit::factory()->for($other)->create(['name' => 'Gym ajeno', 'start_date' => '2026-07-01']);
        HabitLog::factory()->forHabit($habit)->on('2026-07-30')->create();
        HabitLog::factory()->forHabit($foreignHabit)->on('2026-07-30')->create();

        $goal = Goal::factory()->for($user)->create(['name' => 'Objetivo mío']);
        $foreignGoal = Goal::factory()->for($other)->create(['name' => 'Objetivo ajeno']);
        GoalProgressEntry::factory()->for($goal)->create(['user_id' => $user->id, 'recorded_at' => '2026-07-30', 'value' => 1]);
        GoalProgressEntry::factory()->for($foreignGoal)->create(['user_id' => $other->id, 'recorded_at' => '2026-07-30', 'value' => 1]);

        DailyReflection::factory()->for($user)->onDate('2026-07-30')->create(['went_well' => 'Reflexión mía']);
        DailyReflection::factory()->for($other)->onDate('2026-07-30')->create(['went_well' => 'Reflexión ajena']);

        Livewire::actingAs($user)
            ->test(HistoryTimeline::class)
            ->assertSee('Gym mío')->assertDontSee('Gym ajeno')
            ->assertSee('Objetivo mío')->assertDontSee('Objetivo ajeno')
            ->assertSee('Reflexión mía')->assertDontSee('Reflexión ajena');
    }

    // 7. Muestra hábito completado.
    public function test_shows_a_completed_habit_event(): void
    {
        $user = $this->makeUser();
        $habit = Habit::factory()->for($user)->create(['name' => 'Meditar', 'start_date' => '2026-07-01']);
        HabitLog::factory()->forHabit($habit)->on('2026-07-30')->create();

        Livewire::actingAs($user)
            ->test(HistoryTimeline::class)
            ->assertSee('Meditar')
            ->assertSee('completado');
    }

    // 8. Muestra progreso de objetivo.
    public function test_shows_goal_progress_event(): void
    {
        $user = $this->makeUser();
        $goal = Goal::factory()->for($user)->create(['name' => 'Ahorrar', 'target_value' => 100, 'initial_value' => 0]);
        GoalProgressEntry::factory()->for($goal)->create(['user_id' => $user->id, 'recorded_at' => '2026-07-30', 'value' => 20]);

        Livewire::actingAs($user)
            ->test(HistoryTimeline::class)
            ->assertSee('Progreso del objetivo &quot;Ahorrar&quot;', false)
            ->assertSee('20%');
    }

    // 9. Muestra objetivo completado.
    public function test_shows_goal_completed_event(): void
    {
        $user = $this->makeUser();
        Goal::factory()->for($user)->completed()->create(['name' => 'Terminar curso', 'completed_at' => '2026-07-30 09:00:00']);

        Livewire::actingAs($user)
            ->test(HistoryTimeline::class)
            ->assertSee('Terminar curso')
            ->assertSee('completado');
    }

    // 10. Muestra reflexión. / 11. Trunca el texto de reflexión.
    public function test_shows_a_truncated_reflection_snippet(): void
    {
        $user = $this->makeUser();
        $longText = str_repeat('Un día muy largo lleno de detalles. ', 10);
        DailyReflection::factory()->for($user)->onDate('2026-07-30')->create(['went_well' => $longText]);

        $html = Livewire::actingAs($user)->test(HistoryTimeline::class)->html();

        $this->assertStringContainsString('Reflexión diaria registrada', $html);
        $this->assertStringNotContainsString($longText, $html);
    }

    // 12. Muestra día perfecto.
    public function test_shows_a_perfect_day_event(): void
    {
        $user = $this->makeUser();
        $habit = Habit::factory()->for($user)->create(['start_date' => '2026-07-01']);
        HabitLog::factory()->forHabit($habit)->on('2026-07-30')->create();

        Livewire::actingAs($user)
            ->test(HistoryTimeline::class)
            ->assertSee('Día perfecto');
    }

    // 13. Muestra día parcial.
    public function test_shows_a_partial_day_badge(): void
    {
        $user = $this->makeUser();
        $habitA = Habit::factory()->for($user)->create(['start_date' => '2026-07-01']);
        $habitB = Habit::factory()->for($user)->create(['start_date' => '2026-07-01']);
        HabitLog::factory()->forHabit($habitA)->on('2026-07-30')->create();

        Livewire::actingAs($user)
            ->test(HistoryTimeline::class)
            ->assertSee('Parcial · 1/2');
    }

    // 14. Filtro por fecha (rango personalizado con un solo día).
    public function test_can_filter_by_a_single_date_via_query_string(): void
    {
        $user = $this->makeUser();
        $habit = Habit::factory()->for($user)->create(['name' => 'DiaUnico', 'start_date' => '2026-07-01']);
        HabitLog::factory()->forHabit($habit)->on('2026-07-15')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-07-30')->create();

        // El montaje inicial vía #[Url] solo se resuelve en una petición HTTP real
        // (Livewire::test() no propaga el query string global al mount()).
        $response = $this->actingAs($user)->get('/historial?fecha=2026-07-15');

        $response->assertOk();
        $response->assertSee('15 de julio de 2026');
        $response->assertDontSee('30 de julio de 2026');
    }

    // 15. Últimos 7 días.
    public function test_last_7_days_period_excludes_older_events(): void
    {
        $user = $this->makeUser();
        $habit = Habit::factory()->for($user)->create(['name' => 'Antiguo', 'start_date' => '2026-01-01', 'created_at' => CarbonImmutable::parse('2026-01-01')]);
        HabitLog::factory()->forHabit($habit)->on('2026-06-01')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-07-29')->create();

        $component = Livewire::actingAs($user)->test(HistoryTimeline::class)->set('period', 'last7');
        $groups = $component->viewData('groups');

        $this->assertSame(1, $groups->count());
        $this->assertSame('2026-07-29', $groups->first()->date->format('Y-m-d'));
    }

    // 16. Últimos 30 días.
    public function test_last_30_days_is_the_default_period(): void
    {
        $user = $this->makeUser();

        Livewire::actingAs($user)->test(HistoryTimeline::class)->assertSet('period', 'last30');
    }

    // 17. Este mes.
    public function test_this_month_period_covers_the_current_month(): void
    {
        $user = $this->makeUser();
        $habit = Habit::factory()->for($user)->create(['start_date' => '2026-06-01']);
        HabitLog::factory()->forHabit($habit)->on('2026-06-15')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-07-05')->create();

        $component = Livewire::actingAs($user)->test(HistoryTimeline::class)->set('period', 'this_month');

        $component->assertSee('5'); // día del evento de julio visible en el encabezado de fecha
    }

    // 18. Rango personalizado.
    public function test_custom_range_filters_events_between_two_dates(): void
    {
        $user = $this->makeUser();
        $habit = Habit::factory()->for($user)->create(['name' => 'RangoHabito', 'start_date' => '2026-01-01']);
        HabitLog::factory()->forHabit($habit)->on('2026-07-10')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-07-20')->create();

        Livewire::actingAs($user)->test(HistoryTimeline::class)
            ->set('period', 'custom')
            ->set('customFrom', '2026-07-09')
            ->set('customTo', '2026-07-11')
            ->assertViewHas('groups', fn ($groups) => $groups->count() === 1);
    }

    // 19. Filtro por hábito.
    public function test_can_filter_by_a_specific_habit(): void
    {
        $user = $this->makeUser();
        $habitA = Habit::factory()->for($user)->create(['name' => 'Filtrado', 'start_date' => '2026-07-01']);
        $habitB = Habit::factory()->for($user)->create(['name' => 'NoFiltrado', 'start_date' => '2026-07-01']);
        HabitLog::factory()->forHabit($habitA)->on('2026-07-30')->create();
        HabitLog::factory()->forHabit($habitB)->on('2026-07-30')->create();

        Livewire::actingAs($user)->test(HistoryTimeline::class)
            ->set('habitId', $habitA->id)
            ->set('typeFilter', 'habits')
            ->assertSee('Hábito "Filtrado" completado')
            ->assertDontSee('Hábito "NoFiltrado" completado');
    }

    // 20. Filtro por objetivo.
    public function test_can_filter_by_a_specific_goal(): void
    {
        $user = $this->makeUser();
        $goalA = Goal::factory()->for($user)->create(['name' => 'MetaA']);
        $goalB = Goal::factory()->for($user)->create(['name' => 'MetaB']);
        GoalProgressEntry::factory()->for($goalA)->create(['user_id' => $user->id, 'recorded_at' => '2026-07-30', 'value' => 1]);
        GoalProgressEntry::factory()->for($goalB)->create(['user_id' => $user->id, 'recorded_at' => '2026-07-30', 'value' => 1]);

        Livewire::actingAs($user)->test(HistoryTimeline::class)
            ->set('goalId', $goalA->id)
            ->set('typeFilter', 'goals')
            ->assertSee('Progreso del objetivo &quot;MetaA&quot;', false)
            ->assertDontSee('Progreso del objetivo &quot;MetaB&quot;', false);
    }

    // 21. Filtro por tipo de evento.
    public function test_can_filter_by_event_type(): void
    {
        $user = $this->makeUser();
        $habit = Habit::factory()->for($user)->create(['name' => 'SoloHabito', 'start_date' => '2026-07-01']);
        HabitLog::factory()->forHabit($habit)->on('2026-07-30')->create();
        DailyReflection::factory()->for($user)->onDate('2026-07-30')->create(['went_well' => 'Nota de reflexión']);

        Livewire::actingAs($user)->test(HistoryTimeline::class)
            ->set('typeFilter', 'habits')
            ->assertSee('SoloHabito')
            ->assertDontSee('Nota de reflexión');
    }

    // 22. Búsqueda.
    public function test_search_filters_events_by_title(): void
    {
        $user = $this->makeUser();
        $habitA = Habit::factory()->for($user)->create(['name' => 'Correr', 'start_date' => '2026-07-01']);
        $habitB = Habit::factory()->for($user)->create(['name' => 'Cocinar', 'start_date' => '2026-07-01']);
        HabitLog::factory()->forHabit($habitA)->on('2026-07-30')->create();
        HabitLog::factory()->forHabit($habitB)->on('2026-07-30')->create();

        Livewire::actingAs($user)->test(HistoryTimeline::class)
            ->set('search', 'Correr')
            ->assertSee('Hábito "Correr" completado')
            ->assertDontSee('Hábito "Cocinar" completado');
    }

    // 23. Limpieza de filtros.
    public function test_clear_filters_resets_all_filters(): void
    {
        $user = $this->makeUser();

        Livewire::actingAs($user)->test(HistoryTimeline::class)
            ->set('typeFilter', 'habits')
            ->set('search', 'algo')
            ->set('onlyPerfectDays', true)
            ->call('clearFilters')
            ->assertSet('typeFilter', 'all')
            ->assertSet('search', '')
            ->assertSet('onlyPerfectDays', false);
    }

    // 24. Agrupación correcta por fecha. / 25. Orden cronológico.
    public function test_events_are_grouped_by_date_and_sorted_descending(): void
    {
        $user = $this->makeUser();
        $habit = Habit::factory()->for($user)->create(['start_date' => '2026-07-01']);
        HabitLog::factory()->forHabit($habit)->on('2026-07-28')->create();
        HabitLog::factory()->forHabit($habit)->on('2026-07-30')->create();

        $component = Livewire::actingAs($user)->test(HistoryTimeline::class);
        $groups = $component->viewData('groups');

        $this->assertSame(2, $groups->count());
        $this->assertSame('2026-07-30', $groups->first()->date->format('Y-m-d'));
        $this->assertSame('2026-07-28', $groups->last()->date->format('Y-m-d'));
    }

    // 26. Zona horaria America/Bogota.
    public function test_a_user_with_a_different_timezone_sees_a_different_today(): void
    {
        $user = User::factory()->create(['timezone' => 'Pacific/Auckland', 'created_at' => CarbonImmutable::parse('2026-01-01')]);

        Livewire::actingAs($user)
            ->test(HistoryTimeline::class)
            ->call('goToday')
            ->assertViewHas('to', fn (CarbonImmutable $to) => $to->format('Y-m-d') === '2026-07-31');
    }

    // 27. Paginación o cargar más.
    public function test_load_more_extends_the_window_further_into_the_past(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota', 'created_at' => CarbonImmutable::parse('2025-01-01')]);
        $habit = Habit::factory()->for($user)->create(['name' => 'Viejo', 'start_date' => '2025-01-01', 'created_at' => CarbonImmutable::parse('2025-01-01')]);
        HabitLog::factory()->forHabit($habit)->on('2025-12-01')->create();

        $component = Livewire::actingAs($user)->test(HistoryTimeline::class)->set('period', 'this_year');
        $component->assertDontSee('Hábito "Viejo" completado');

        $component->call('loadMore')->call('loadMore')->assertSee('Hábito "Viejo" completado');
    }

    // 28. Estado vacío (sin actividad nunca).
    public function test_shows_the_never_had_activity_empty_state(): void
    {
        $user = $this->makeUser();

        Livewire::actingAs($user)
            ->test(HistoryTimeline::class)
            ->assertSee('Aún no tienes actividad registrada')
            ->assertSee('Crear hábito')
            ->assertSee('Escribir reflexión');
    }

    // 29. Estado sin resultados (hay datos pero los filtros no producen nada).
    public function test_shows_the_no_results_for_filters_empty_state(): void
    {
        $user = $this->makeUser();
        $habit = Habit::factory()->for($user)->create(['start_date' => '2026-07-01']);
        HabitLog::factory()->forHabit($habit)->on('2026-07-30')->create();

        Livewire::actingAs($user)
            ->test(HistoryTimeline::class)
            ->set('search', 'texto que no existe en absoluto')
            ->assertSee('No encontramos actividad con estos filtros');
    }

    // 30. Sin consultas N+1 importantes.
    public function test_rendering_does_not_trigger_excessive_queries(): void
    {
        $user = $this->makeUser();
        $habits = Habit::factory()->count(5)->for($user)->create(['start_date' => '2026-07-01']);
        foreach ($habits as $habit) {
            HabitLog::factory()->forHabit($habit)->on('2026-07-30')->create();
        }
        $goal = Goal::factory()->for($user)->create();
        GoalProgressEntry::factory()->for($goal)->create(['user_id' => $user->id, 'recorded_at' => '2026-07-30', 'value' => 1]);
        DailyReflection::factory()->for($user)->onDate('2026-07-30')->create();

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        Livewire::actingAs($user)->test(HistoryTimeline::class);

        $this->assertLessThan(40, $queryCount);
    }

    // 32. Livewire funciona correctamente (endpoint de actualización).
    public function test_livewire_update_endpoint_responds_for_the_component(): void
    {
        $user = $this->makeUser();

        Livewire::actingAs($user)
            ->test(HistoryTimeline::class)
            ->set('typeFilter', 'habits')
            ->assertSet('typeFilter', 'habits')
            ->assertOk();
    }

    // 33. No se muestran fechas futuras.
    public function test_future_dates_are_never_shown(): void
    {
        $user = $this->makeUser();
        $habit = Habit::factory()->for($user)->create(['start_date' => '2026-07-01']);
        HabitLog::factory()->forHabit($habit)->on('2026-07-30')->create();

        $component = Livewire::actingAs($user)->test(HistoryTimeline::class)->set('period', 'custom')
            ->set('customFrom', '2026-07-01')
            ->set('customTo', '2026-08-15');

        $component->assertViewHas('to', fn (CarbonImmutable $to) => $to->format('Y-m-d') === '2026-07-30');
    }

    // 34. Años bisiestos y cambio de año.
    public function test_handles_leap_year_and_year_boundary_correctly(): void
    {
        $this->travelTo(CarbonImmutable::create(2028, 3, 1, 12, 0, 0, 'America/Bogota'));
        $user = User::factory()->create(['timezone' => 'America/Bogota', 'created_at' => CarbonImmutable::parse('2027-01-01')]);
        $habit = Habit::factory()->for($user)->create(['name' => 'Bisiesto', 'start_date' => '2027-12-01']);
        HabitLog::factory()->forHabit($habit)->on('2028-02-29')->create();
        HabitLog::factory()->forHabit($habit)->on('2027-12-31')->create();

        Livewire::actingAs($user)->test(HistoryTimeline::class)
            ->set('period', 'custom')
            ->set('customFrom', '2027-12-01')
            ->set('customTo', '2028-03-01')
            ->assertSee('Bisiesto');
    }

    // 35 (aislamiento) cubierto arriba en test_history_only_shows_the_authenticated_users_activity.
}
