<?php

namespace Database\Factories;

use App\Enums\LogStatus;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HabitLog>
 */
class HabitLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'habit_id' => Habit::factory(),
            'user_id' => fn (array $attributes) => Habit::find($attributes['habit_id'])?->user_id ?? User::factory(),
            'date' => now()->toDateString(),
            'status' => LogStatus::Completed,
            'quantity_value' => null,
            'note' => null,
        ];
    }

    public function failed(): static
    {
        return $this->state(fn () => ['status' => LogStatus::Failed]);
    }

    public function on(string $date): static
    {
        return $this->state(fn () => ['date' => $date]);
    }

    public function forHabit(Habit $habit): static
    {
        return $this->state(fn () => [
            'habit_id' => $habit->id,
            'user_id' => $habit->user_id,
        ]);
    }
}
