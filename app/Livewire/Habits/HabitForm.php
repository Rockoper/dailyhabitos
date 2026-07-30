<?php

namespace App\Livewire\Habits;

use App\Enums\FrequencyType;
use App\Enums\HabitType;
use App\Enums\HabitUnit;
use App\Models\Habit;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class HabitForm extends Component
{
    public ?Habit $habit = null;

    public string $name = '';

    public ?string $description = null;

    public ?int $category_id = null;

    public string $icon = 'sparkle';

    public string $color = '#5b5a8b';

    public string $type = 'binary';

    public string $frequency_type = 'daily';

    /** @var array<int, int> */
    public array $specific_days = [];

    public ?int $interval_days = 2;

    public ?int $times_per_week = 3;

    public ?float $target_quantity = null;

    public ?string $unit = null;

    public ?string $unit_custom_label = null;

    public string $start_date;

    public ?string $end_date = null;

    public bool $never_fail_twice = false;

    public bool $is_private = false;

    public bool $remind_at_enabled = false;

    public ?string $remind_at = null;

    public function mount(?Habit $habit = null): void
    {
        $today = Auth::user()->timezone
            ? now(Auth::user()->timezone)->toDateString()
            : now()->toDateString();

        $this->start_date = $today;

        if ($habit) {
            $this->authorize('update', $habit);
            $this->habit = $habit;

            $this->name = $habit->name;
            $this->description = $habit->description;
            $this->category_id = $habit->category_id;
            $this->icon = $habit->icon;
            $this->color = $habit->color;
            $this->type = $habit->type->value;
            $this->frequency_type = $habit->frequency_type->value;
            $this->specific_days = $habit->frequency_config['days'] ?? [];
            $this->interval_days = $habit->frequency_config['every_n_days'] ?? 2;
            $this->times_per_week = $habit->frequency_config['times_per_week'] ?? 3;
            $this->target_quantity = $habit->target_quantity !== null ? (float) $habit->target_quantity : null;
            $this->unit = $habit->unit?->value;
            $this->unit_custom_label = $habit->unit_custom_label;
            $this->start_date = $habit->start_date->toDateString();
            $this->end_date = $habit->end_date?->toDateString();
            $this->never_fail_twice = $habit->never_fail_twice;
            $this->is_private = $habit->is_private;
            $this->remind_at_enabled = $habit->remind_at_enabled;
            $this->remind_at = $habit->remind_at ? substr((string) $habit->remind_at, 0, 5) : null;
        } else {
            $this->authorize('create', Habit::class);
        }
    }

    #[Computed]
    public function categories()
    {
        return Auth::user()->categories()->orderBy('position')->get();
    }

    public function updatedType(): void
    {
        if ($this->type === HabitType::Weekly->value) {
            $this->frequency_type = FrequencyType::WeeklyCount->value;
        } elseif ($this->frequency_type === FrequencyType::WeeklyCount->value) {
            $this->frequency_type = FrequencyType::Daily->value;
        }
    }

    protected function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('user_id', Auth::id())],
            'icon' => ['required', 'string', 'max:40'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'type' => ['required', Rule::enum(HabitType::class)],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'never_fail_twice' => ['boolean'],
            'is_private' => ['boolean'],
            'remind_at_enabled' => ['boolean'],
            'remind_at' => ['nullable', 'date_format:H:i'],
        ];

        if ($this->type === HabitType::Weekly->value) {
            $rules['times_per_week'] = ['required', 'integer', 'min:1', 'max:7'];
        } else {
            $rules['frequency_type'] = ['required', Rule::in([
                FrequencyType::Daily->value,
                FrequencyType::SpecificDays->value,
                FrequencyType::Interval->value,
            ])];

            if ($this->frequency_type === FrequencyType::SpecificDays->value) {
                $rules['specific_days'] = ['required', 'array', 'min:1'];
                $rules['specific_days.*'] = ['integer', 'between:1,7'];
            }

            if ($this->frequency_type === FrequencyType::Interval->value) {
                $rules['interval_days'] = ['required', 'integer', 'min:1', 'max:365'];
            }
        }

        if ($this->type === HabitType::Quantity->value) {
            $rules['target_quantity'] = ['nullable', 'numeric', 'min:0'];
            $rules['unit'] = ['nullable', Rule::enum(HabitUnit::class)];

            if ($this->unit === HabitUnit::Other->value) {
                $rules['unit_custom_label'] = ['required', 'string', 'max:40'];
            }
        }

        return $rules;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $frequencyConfig = match ($this->type === HabitType::Weekly->value ? FrequencyType::WeeklyCount->value : $this->frequency_type) {
            FrequencyType::SpecificDays->value => ['days' => array_values(array_map('intval', $this->specific_days))],
            FrequencyType::Interval->value => ['every_n_days' => (int) $this->interval_days],
            FrequencyType::WeeklyCount->value => ['times_per_week' => (int) $this->times_per_week],
            default => null,
        };

        $data = [
            'category_id' => $validated['category_id'] ?? null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'],
            'color' => $validated['color'],
            'type' => $this->type,
            'frequency_type' => $this->type === HabitType::Weekly->value ? FrequencyType::WeeklyCount->value : $this->frequency_type,
            'frequency_config' => $frequencyConfig,
            'target_quantity' => $this->type === HabitType::Quantity->value ? ($validated['target_quantity'] ?? null) : null,
            'unit' => $this->type === HabitType::Quantity->value ? $this->unit : null,
            'unit_custom_label' => $this->type === HabitType::Quantity->value ? ($validated['unit_custom_label'] ?? null) : null,
            'start_date' => $validated['start_date'],
            'end_date' => $validated['end_date'] ?? null,
            'never_fail_twice' => $this->never_fail_twice,
            'is_private' => $this->is_private,
            'remind_at_enabled' => $this->remind_at_enabled,
            'remind_at' => $this->remind_at_enabled ? $validated['remind_at'] : null,
        ];

        if ($this->habit) {
            $this->habit->update($data);
            $habit = $this->habit;
        } else {
            $habit = Auth::user()->habits()->create($data);
        }

        session()->flash('status', $this->habit ? 'Hábito actualizado correctamente.' : 'Hábito creado correctamente.');

        $this->redirectRoute('habits.show', $habit, navigate: true);
    }

    public function render()
    {
        return view('livewire.habits.habit-form');
    }
}
