<?php

namespace Tests\Feature\Statistics;

use App\Livewire\Statistics\StatisticsDashboard;
use App\Models\Category;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class StatisticsDashboardTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Extrae, en orden, los valores grandes de las 8 tarjetas KPI tal como
     * las renderiza la vista (mismo orden que $kpiCards en el Blade).
     *
     * @return array<int, string>
     */
    private function kpiValues(string $html): array
    {
        preg_match_all('/font-heading text-2xl font-semibold text-on-surface">([^<]*)</', $html, $matches);

        return $matches[1];
    }

    public function test_guests_are_redirected_to_login_for_the_statistics_route(): void
    {
        $this->get(route('stats.index'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_the_statistics_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('stats.index'))
            ->assertOk()
            ->assertSee('Estadísticas');
    }

    public function test_a_user_only_sees_their_own_habits_in_statistics(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();

        Habit::factory()->for($user)->create(['name' => 'Mío', 'start_date' => now()->subDays(10)]);
        Habit::factory()->for($otherUser)->create(['name' => 'De otro usuario', 'start_date' => now()->subDays(10)]);

        Livewire::actingAs($user)
            ->test(StatisticsDashboard::class)
            ->assertSee('Mío')
            ->assertDontSee('De otro usuario');
    }

    public function test_filtering_by_a_specific_habit_narrows_the_performance_list(): void
    {
        $user = User::factory()->create();
        Habit::factory()->for($user)->create(['name' => 'Meditar', 'start_date' => now()->subDays(10)]);
        $leer = Habit::factory()->for($user)->create(['name' => 'Leer', 'start_date' => now()->subDays(10)]);

        $html = Livewire::actingAs($user)
            ->test(StatisticsDashboard::class)
            ->set('habitId', $leer->id)
            ->html();

        $this->assertSame(1, substr_count($html, 'wire:key="perf-'));
    }

    public function test_filtering_by_category_narrows_the_performance_list(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();
        Habit::factory()->for($user)->create(['category_id' => $category->id, 'start_date' => now()->subDays(10)]);
        Habit::factory()->for($user)->create(['category_id' => null, 'start_date' => now()->subDays(10)]);

        $html = Livewire::actingAs($user)
            ->test(StatisticsDashboard::class)
            ->set('categoryId', $category->id)
            ->html();

        $this->assertSame(1, substr_count($html, 'wire:key="perf-'));
    }

    public function test_last_7_days_period_only_counts_logs_within_that_window(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create(['start_date' => now()->subDays(30)]);
        HabitLog::factory()->forHabit($habit)->on(now()->subDays(2)->toDateString())->create();
        HabitLog::factory()->forHabit($habit)->on(now()->subDays(20)->toDateString())->create();

        $component = Livewire::actingAs($user)->test(StatisticsDashboard::class)->set('period', '7d');

        $this->assertSame('1', trim($this->kpiValues($component->html())[6]));
    }

    public function test_last_30_days_is_the_default_period(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create(['start_date' => now()->subDays(30)]);
        HabitLog::factory()->forHabit($habit)->on(now()->subDays(2)->toDateString())->create();
        HabitLog::factory()->forHabit($habit)->on(now()->subDays(20)->toDateString())->create();

        $component = Livewire::actingAs($user)->test(StatisticsDashboard::class);
        $component->assertSet('period', '30d');

        $this->assertSame('2', trim($this->kpiValues($component->html())[6]));
    }

    public function test_custom_range_filters_logs_to_the_selected_dates(): void
    {
        $user = User::factory()->create();
        $habit = Habit::factory()->for($user)->create(['start_date' => now()->subDays(60)]);
        HabitLog::factory()->forHabit($habit)->on(now()->subDays(45)->toDateString())->create();
        HabitLog::factory()->forHabit($habit)->on(now()->subDays(5)->toDateString())->create();

        $component = Livewire::actingAs($user)->test(StatisticsDashboard::class)
            ->set('period', 'custom')
            ->set('customFrom', now()->subDays(50)->toDateString())
            ->set('customTo', now()->subDays(40)->toDateString());

        $this->assertSame('1', trim($this->kpiValues($component->html())[6]));
    }

    public function test_archived_habits_are_hidden_by_default_and_shown_with_the_archived_filter(): void
    {
        // La lista de hábitos activos ("Correr") y la de archivados ("Pintar")
        // se comparan por su fila en la tabla de rendimiento (wire:key), no por
        // texto plano: el selector de filtro "Hábito" siempre lista todos los
        // hábitos del usuario sin importar el filtro de estado.
        $user = User::factory()->create();
        $active = Habit::factory()->for($user)->create(['name' => 'Correr', 'start_date' => now()->subDays(10)]);
        $archived = Habit::factory()->for($user)->create(['name' => 'Pintar', 'start_date' => now()->subDays(10), 'is_archived' => true]);

        $component = Livewire::actingAs($user)->test(StatisticsDashboard::class);
        $html = $component->html();
        $this->assertStringContainsString('wire:key="perf-'.$active->id.'"', $html);
        $this->assertStringNotContainsString('wire:key="perf-'.$archived->id.'"', $html);

        $component->set('statusFilter', 'archived');
        $html = $component->html();
        $this->assertStringNotContainsString('wire:key="perf-'.$active->id.'"', $html);
        $this->assertStringContainsString('wire:key="perf-'.$archived->id.'"', $html);
    }

    public function test_empty_state_is_shown_when_there_is_no_data(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(StatisticsDashboard::class)
            ->assertSee('Aún no hay suficientes datos para generar estadísticas');
    }

    public function test_period_boundaries_respect_the_users_timezone(): void
    {
        // UTC+14: casi siempre "mañana" respecto al reloj del servidor (UTC/America-Bogota).
        $user = User::factory()->create(['timezone' => 'Pacific/Kiritimati']);

        $expectedToday = CarbonImmutable::now('Pacific/Kiritimati')->translatedFormat('j \d\e M \d\e Y');

        Livewire::actingAs($user)
            ->test(StatisticsDashboard::class)
            ->assertSee($expectedToday);
    }

    public function test_rendering_the_dashboard_does_not_produce_n_plus_1_queries(): void
    {
        $user = User::factory()->create();
        $category = Category::factory()->for($user)->create();

        foreach (range(1, 15) as $i) {
            $habit = Habit::factory()->for($user)->create([
                'category_id' => $category->id,
                'name' => "Hábito {$i}",
                'start_date' => now()->subDays(40),
            ]);
            foreach (range(1, 10) as $d) {
                HabitLog::factory()->forHabit($habit)->on(now()->subDays($d)->toDateString())->create();
            }
        }

        $queryCount = 0;
        DB::listen(function () use (&$queryCount) {
            $queryCount++;
        });

        Livewire::actingAs($user)->test(StatisticsDashboard::class);

        $this->assertLessThan(
            20,
            $queryCount,
            "Se ejecutaron {$queryCount} consultas para 15 hábitos; el número de consultas no debe crecer con la cantidad de hábitos."
        );
    }
}
