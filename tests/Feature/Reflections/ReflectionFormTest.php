<?php

namespace Tests\Feature\Reflections;

use App\Livewire\Reflections\ReflectionForm;
use App\Models\DailyReflection;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\TestCase;

class ReflectionFormTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(CarbonImmutable::create(2026, 7, 30, 20, 0, 0, 'America/Bogota'));
    }

    // 1. Usuario no autenticado no puede acceder.
    public function test_guests_are_redirected_to_login(): void
    {
        $this->get(route('reflections.index'))->assertRedirect(route('login'));
    }

    // 2. Usuario autenticado puede acceder. / 27. Ruta /reflexion.
    public function test_authenticated_user_can_access_the_reflection_route(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);

        $this->actingAs($user)
            ->get(route('reflections.index'))
            ->assertOk()
            ->assertSee('Reflexión diaria');
    }

    // 3. La fecha actual se carga con America/Bogota. / 26. Zona horaria.
    public function test_default_date_resolves_using_the_users_timezone(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);

        Livewire::actingAs($user)
            ->test(ReflectionForm::class)
            ->assertSet('date', '2026-07-30');
    }

    public function test_a_user_with_a_different_timezone_resolves_a_different_today(): void
    {
        // 20:00 en Bogotá (UTC-5) es 23:00 UTC, todavía 30 de julio en Auckland (UTC+12) sería 31.
        $user = User::factory()->create(['timezone' => 'Pacific/Auckland']);

        Livewire::actingAs($user)
            ->test(ReflectionForm::class)
            ->assertSet('date', '2026-07-31');
    }

    // 4. No se puede crear reflexión futura. / 20. No navegar al futuro.
    public function test_cannot_navigate_or_save_a_future_date(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);

        Livewire::actingAs($user)
            ->test(ReflectionForm::class)
            ->call('goNext')
            ->assertSet('date', '2026-07-30');

        $this->assertDatabaseMissing('daily_reflections', ['reflection_date' => '2026-07-31']);
    }

    // 5. Se crea una reflexión. / 6. Pertenece al usuario. / 30. Livewire guarda correctamente.
    public function test_saving_creates_a_reflection_owned_by_the_user(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);

        Livewire::actingAs($user)
            ->test(ReflectionForm::class)
            ->set('mood', 4)
            ->set('went_well', 'Terminé mis tareas importantes.')
            ->call('save')
            ->assertHasNoErrors();

        $reflection = DailyReflection::where('user_id', $user->id)->whereDate('reflection_date', '2026-07-30')->firstOrFail();
        $this->assertSame(4, $reflection->mood);
        $this->assertSame('Terminé mis tareas importantes.', $reflection->went_well);
        $this->assertSame('completed', $reflection->status->value);
        $this->assertNotNull($reflection->completed_at);
    }

    // 7. No se duplican reflexiones del mismo usuario y fecha. / 8. Guardar nuevamente actualiza la existente.
    public function test_saving_twice_on_the_same_date_updates_instead_of_duplicating(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);

        $component = Livewire::actingAs($user)->test(ReflectionForm::class);

        $component->set('went_well', 'Primera versión.')->call('save')->assertHasNoErrors();
        $component->set('went_well', 'Segunda versión.')->call('save')->assertHasNoErrors();

        $this->assertSame(1, DailyReflection::where('user_id', $user->id)->count());
        $this->assertSame('Segunda versión.', DailyReflection::first()->went_well);
    }

    // 9. Otro usuario puede tener reflexión en la misma fecha.
    public function test_two_users_can_have_a_reflection_on_the_same_date(): void
    {
        $userA = User::factory()->create(['timezone' => 'America/Bogota']);
        $userB = User::factory()->create(['timezone' => 'America/Bogota']);

        DailyReflection::factory()->for($userA)->onDate('2026-07-30')->create();
        DailyReflection::factory()->for($userB)->onDate('2026-07-30')->create();

        $this->assertSame(2, DailyReflection::whereDate('reflection_date', '2026-07-30')->count());
    }

    // 10. Un usuario no puede ver la reflexión de otro. / 29. Aislamiento completo.
    public function test_a_user_cannot_see_another_users_reflection_content(): void
    {
        $owner = User::factory()->create(['timezone' => 'America/Bogota']);
        $intruder = User::factory()->create(['timezone' => 'America/Bogota']);

        DailyReflection::factory()->for($owner)->onDate('2026-07-30')->create(['went_well' => 'Secreto del dueño']);

        Livewire::actingAs($intruder)
            ->test(ReflectionForm::class)
            ->assertSet('went_well', null)
            ->assertDontSee('Secreto del dueño');
    }

    // 11. Un usuario no puede editar la reflexión de otro.
    public function test_a_user_cannot_edit_another_users_reflection_via_the_policy(): void
    {
        $owner = User::factory()->create(['timezone' => 'America/Bogota']);
        $intruder = User::factory()->create(['timezone' => 'America/Bogota']);

        $reflection = DailyReflection::factory()->for($owner)->onDate('2026-07-30')->create();

        $this->assertTrue(Gate::forUser($owner)->allows('update', $reflection));
        $this->assertFalse(Gate::forUser($intruder)->allows('update', $reflection));
        $this->assertFalse(Gate::forUser($intruder)->allows('view', $reflection));
    }

    // 12. Validación mood.
    public function test_mood_must_be_between_one_and_five(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);

        Livewire::actingAs($user)
            ->test(ReflectionForm::class)
            ->set('mood', 9)
            ->call('save')
            ->assertHasErrors(['mood' => 'between']);
    }

    // 13. Validación energía.
    public function test_energy_level_must_be_between_one_and_five(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);

        Livewire::actingAs($user)
            ->test(ReflectionForm::class)
            ->set('energy_level', 0)
            ->call('save')
            ->assertHasErrors(['energy_level' => 'between']);
    }

    // 14. Validación productividad.
    public function test_productivity_level_must_be_between_one_and_five(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);

        Livewire::actingAs($user)
            ->test(ReflectionForm::class)
            ->set('productivity_level', 6)
            ->call('save')
            ->assertHasErrors(['productivity_level' => 'between']);
    }

    // 15. Validación longitud de textos.
    public function test_guided_question_and_free_notes_have_length_limits(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);

        Livewire::actingAs($user)
            ->test(ReflectionForm::class)
            ->set('went_well', str_repeat('a', 501))
            ->set('free_notes', str_repeat('a', 10001))
            ->call('save')
            ->assertHasErrors(['went_well' => 'max', 'free_notes' => 'max']);
    }

    // 16. Estado draft.
    public function test_autosave_keeps_the_reflection_as_draft(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);

        Livewire::actingAs($user)
            ->test(ReflectionForm::class)
            ->set('went_well', 'Autoguardado en curso')
            ->call('autosave')
            ->assertHasNoErrors();

        $reflection = DailyReflection::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('draft', $reflection->status->value);
        $this->assertNull($reflection->completed_at);
    }

    // 17. Estado completed.
    public function test_save_marks_the_reflection_as_completed_even_if_it_started_as_draft(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);
        $reflection = DailyReflection::factory()->draft()->for($user)->onDate('2026-07-30')->create();

        Livewire::actingAs($user)
            ->test(ReflectionForm::class)
            ->set('went_well', 'Ahora sí termino.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('completed', $reflection->fresh()->status->value);
    }

    // 18. Navegación al día anterior.
    public function test_can_navigate_to_the_previous_day(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);

        Livewire::actingAs($user)
            ->test(ReflectionForm::class)
            ->call('goPrevious')
            ->assertSet('date', '2026-07-29');
    }

    // 19. Navegación al día siguiente (dentro del rango permitido).
    public function test_can_navigate_forward_when_not_on_todays_date(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);

        Livewire::actingAs($user)
            ->test(ReflectionForm::class)
            ->call('goPrevious')
            ->call('goNext')
            ->assertSet('date', '2026-07-30');
    }

    // 21. Resumen de hábitos del día.
    public function test_day_summary_reflects_expected_and_completed_habits(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);
        $habitA = Habit::factory()->for($user)->create(['start_date' => '2026-07-01']);
        $habitB = Habit::factory()->for($user)->create(['start_date' => '2026-07-01']);

        HabitLog::factory()->forHabit($habitA)->on('2026-07-30')->create();

        Livewire::actingAs($user)
            ->test(ReflectionForm::class)
            ->assertSee('1/2')
            ->assertSee('Día parcial');
    }

    // 22. Reflexión sin hábitos. / 24. Estado vacío.
    public function test_reflection_form_works_without_any_habits(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);

        Livewire::actingAs($user)
            ->test(ReflectionForm::class)
            ->assertSee('No tenías hábitos programados')
            ->set('gratitude', 'Un día tranquilo sin hábitos que registrar.')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('daily_reflections', [
            'user_id' => $user->id,
            'gratitude' => 'Un día tranquilo sin hábitos que registrar.',
        ]);
    }

    // 23. Últimas reflexiones.
    public function test_recent_reflections_are_listed(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);
        DailyReflection::factory()->for($user)->onDate('2026-07-28')->create(['went_well' => 'Reflexión de antier']);
        DailyReflection::factory()->for($user)->onDate('2026-07-29')->create(['went_well' => 'Reflexión de ayer']);

        Livewire::actingAs($user)
            ->test(ReflectionForm::class)
            ->assertSee('Reflexión de antier')
            ->assertSee('Reflexión de ayer');
    }

    // 25. Hard delete (no se implementó soft delete para daily_reflections, a diferencia de Habit/Goal).
    public function test_deleting_a_reflection_removes_the_row_permanently(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);
        $reflection = DailyReflection::factory()->for($user)->onDate('2026-07-30')->create();

        $reflection->delete();

        $this->assertDatabaseMissing('daily_reflections', ['id' => $reflection->id]);
    }

    // 28. Ausencia de consultas N+1 importantes en el resumen del día.
    public function test_rendering_the_form_does_not_trigger_excessive_queries(): void
    {
        $user = User::factory()->create(['timezone' => 'America/Bogota']);
        Habit::factory()->count(5)->for($user)->create(['start_date' => '2026-07-01'])
            ->each(fn (Habit $habit) => HabitLog::factory()->forHabit($habit)->on('2026-07-30')->create());

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        Livewire::actingAs($user)->test(ReflectionForm::class);

        $this->assertLessThan(30, $queryCount);
    }

    public function test_a_user_cannot_link_or_view_a_reflection_belonging_to_another_user_by_reusing_the_component(): void
    {
        $owner = User::factory()->create(['timezone' => 'America/Bogota']);
        $intruder = User::factory()->create(['timezone' => 'America/Bogota']);
        DailyReflection::factory()->for($owner)->onDate('2026-07-30')->create(['free_notes' => 'Notas privadas del dueño']);

        Livewire::actingAs($intruder)
            ->test(ReflectionForm::class)
            ->set('free_notes', 'Notas del intruso')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame('Notas privadas del dueño', DailyReflection::where('user_id', $owner->id)->first()->free_notes);
        $this->assertSame('Notas del intruso', DailyReflection::where('user_id', $intruder->id)->first()->free_notes);
    }
}
