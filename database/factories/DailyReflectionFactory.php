<?php

namespace Database\Factories;

use App\Enums\ReflectionStatus;
use App\Models\DailyReflection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DailyReflection>
 */
class DailyReflectionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'reflection_date' => now()->toDateString(),
            'status' => ReflectionStatus::Completed,
            'mood' => fake()->numberBetween(1, 5),
            'energy_level' => fake()->numberBetween(1, 5),
            'productivity_level' => fake()->numberBetween(1, 5),
            'went_well' => fake()->sentence(),
            'challenges' => fake()->sentence(),
            'learned' => fake()->sentence(),
            'gratitude' => fake()->sentence(),
            'improve_tomorrow' => fake()->sentence(),
            'tomorrow_priority' => fake()->sentence(),
            'free_notes' => fake()->paragraph(),
            'tags' => [],
            'completed_at' => now(),
        ];
    }

    public function draft(): static
    {
        return $this->state(fn () => [
            'status' => ReflectionStatus::Draft,
            'completed_at' => null,
        ]);
    }

    public function onDate(string $date): static
    {
        return $this->state(fn () => ['reflection_date' => $date]);
    }
}
