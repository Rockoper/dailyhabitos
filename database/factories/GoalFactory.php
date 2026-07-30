<?php

namespace Database\Factories;

use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\Goal;
use App\Models\Habit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Goal>
 */
class GoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'habit_id' => null,
            'name' => fake()->unique()->sentence(3),
            'description' => null,
            'notes' => null,
            'type' => GoalType::Manual,
            'status' => GoalStatus::Active,
            'priority' => GoalPriority::Medium,
            'icon' => 'target',
            'color' => '#5b5a8b',
            'start_date' => now()->toDateString(),
            'due_date' => null,
            'initial_value' => null,
            'target_value' => null,
            'unit' => null,
            'settings' => null,
            'is_private' => false,
            'reminder_enabled' => false,
            'reminder_date' => null,
            'completed_at' => null,
            'archived_at' => null,
            'cancelled_at' => null,
        ];
    }

    public function habitBased(Habit $habit, float $targetCount): static
    {
        return $this->state(fn () => [
            'type' => GoalType::Habit,
            'habit_id' => $habit->id,
            'target_value' => $targetCount,
        ]);
    }

    public function streak(Habit $habit, int $days): static
    {
        return $this->state(fn () => [
            'type' => GoalType::Streak,
            'habit_id' => $habit->id,
            'target_value' => $days,
        ]);
    }

    public function numeric(float $target, string $unit, float $initial = 0): static
    {
        return $this->state(fn () => [
            'type' => GoalType::Numeric,
            'target_value' => $target,
            'initial_value' => $initial,
            'unit' => $unit,
        ]);
    }

    public function percentage(float $targetPercentage, ?Habit $habit = null, string $period = 'month'): static
    {
        return $this->state(fn () => [
            'type' => GoalType::Percentage,
            'habit_id' => $habit?->id,
            'target_value' => $targetPercentage,
            'settings' => ['period' => $period],
        ]);
    }

    public function deadline(string $dueDate): static
    {
        return $this->state(fn () => [
            'type' => GoalType::Deadline,
            'due_date' => $dueDate,
        ]);
    }

    public function completed(): static
    {
        return $this->state(fn () => [
            'status' => GoalStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    public function archived(): static
    {
        return $this->state(fn () => [
            'status' => GoalStatus::Archived,
            'archived_at' => now(),
        ]);
    }

    public function paused(): static
    {
        return $this->state(fn () => ['status' => GoalStatus::Paused]);
    }
}
