<?php

namespace Tests\Unit\Services\Reflections;

use App\Models\DailyReflection;
use App\Models\User;
use App\Services\Reflections\ReflectionInsightService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReflectionInsightServiceTest extends TestCase
{
    use RefreshDatabase;

    private ReflectionInsightService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ReflectionInsightService;
    }

    public function test_reports_insufficient_data_with_few_reflections(): void
    {
        $user = User::factory()->create();
        DailyReflection::factory()->for($user)->onDate('2026-07-30')->create();

        $result = $this->service->forUser($user, CarbonImmutable::parse('2026-07-30'));

        $this->assertFalse($result['has_enough_data']);
        $this->assertSame([], $result['insights']);
    }

    public function test_detects_an_improving_mood_trend(): void
    {
        $user = User::factory()->create();
        DailyReflection::factory()->for($user)->onDate('2026-07-28')->create(['mood' => 2]);
        DailyReflection::factory()->for($user)->onDate('2026-07-29')->create(['mood' => 3]);
        DailyReflection::factory()->for($user)->onDate('2026-07-30')->create(['mood' => 4]);

        $result = $this->service->forUser($user, CarbonImmutable::parse('2026-07-30'));

        $this->assertTrue($result['has_enough_data']);
        $this->assertContains('Tu estado de ánimo ha mejorado durante los últimos tres días registrados.', $result['insights']);
    }

    public function test_detects_a_writing_streak(): void
    {
        $user = User::factory()->create();
        DailyReflection::factory()->for($user)->onDate('2026-07-28')->create();
        DailyReflection::factory()->for($user)->onDate('2026-07-29')->create();
        DailyReflection::factory()->for($user)->onDate('2026-07-30')->create();

        $result = $this->service->forUser($user, CarbonImmutable::parse('2026-07-30'));

        $this->assertContains('Has escrito reflexiones durante 3 días consecutivos.', $result['insights']);
    }

    public function test_does_not_report_correlation_without_enough_tagged_samples(): void
    {
        $user = User::factory()->create();
        DailyReflection::factory()->for($user)->onDate('2026-07-28')->create(['energy_level' => 5, 'tags' => ['Ejercicio']]);
        DailyReflection::factory()->for($user)->onDate('2026-07-29')->create(['energy_level' => 2, 'tags' => []]);
        DailyReflection::factory()->for($user)->onDate('2026-07-30')->create(['energy_level' => 2, 'tags' => []]);

        $result = $this->service->forUser($user, CarbonImmutable::parse('2026-07-30'));

        foreach ($result['insights'] as $insight) {
            $this->assertStringNotContainsString('Ejercicio', $insight);
        }
    }
}
