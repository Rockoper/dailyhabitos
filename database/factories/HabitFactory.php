<?php

namespace Database\Factories;

use App\Enums\FrequencyType;
use App\Enums\HabitType;
use App\Models\Habit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends Factory<Habit>
 */
class HabitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'category_id' => null,
            'name' => fake()->unique()->sentence(3),
            'description' => null,
            'icon' => 'sparkle',
            'color' => '#5b5a8b',
            'type' => HabitType::Binary,
            'frequency_type' => FrequencyType::Daily,
            'frequency_config' => null,
            'target_quantity' => null,
            'unit' => null,
            'unit_custom_label' => null,
            'start_date' => Carbon::parse('2026-01-01'),
            'end_date' => null,
            'never_fail_twice' => false,
            'is_private' => false,
            'remind_at_enabled' => false,
            'remind_at' => null,
            'is_archived' => false,
            'archived_at' => null,
            'position' => 0,
        ];
    }

    public function quantity(float $target = 30): static
    {
        return $this->state(fn () => [
            'type' => HabitType::Quantity,
            'target_quantity' => $target,
            'unit' => 'minutes',
        ]);
    }

    public function specificDays(array $days): static
    {
        return $this->state(fn () => [
            'frequency_type' => FrequencyType::SpecificDays,
            'frequency_config' => ['days' => $days],
        ]);
    }

    public function interval(int $everyNDays): static
    {
        return $this->state(fn () => [
            'frequency_type' => FrequencyType::Interval,
            'frequency_config' => ['every_n_days' => $everyNDays],
        ]);
    }

    public function weeklyCount(int $timesPerWeek): static
    {
        return $this->state(fn () => [
            'type' => HabitType::Weekly,
            'frequency_type' => FrequencyType::WeeklyCount,
            'frequency_config' => ['times_per_week' => $timesPerWeek],
        ]);
    }

    public function neverFailTwice(): static
    {
        return $this->state(fn () => ['never_fail_twice' => true]);
    }
}
