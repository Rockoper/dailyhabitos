<?php

namespace Database\Factories;

use App\Models\Goal;
use App\Models\GoalProgressEntry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoalProgressEntry>
 */
class GoalProgressEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'goal_id' => Goal::factory(),
            'user_id' => fn (array $attributes) => Goal::find($attributes['goal_id'])?->user_id ?? User::factory(),
            'value' => fake()->randomFloat(2, 1, 10),
            'recorded_at' => now()->toDateString(),
            'note' => null,
        ];
    }

    public function forGoal(Goal $goal): static
    {
        return $this->state(fn () => [
            'goal_id' => $goal->id,
            'user_id' => $goal->user_id,
        ]);
    }

    public function on(string $date): static
    {
        return $this->state(fn () => ['recorded_at' => $date]);
    }
}
