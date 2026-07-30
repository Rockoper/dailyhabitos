<?php

namespace App\Livewire\Goals;

use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\Goal;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Component;

class GoalForm extends Component
{
    public ?Goal $goal = null;

    public string $name = '';

    public ?string $description = null;

    public ?string $notes = null;

    public ?int $category_id = null;

    public ?int $habit_id = null;

    public string $icon = 'target';

    public string $color = '#5b5a8b';

    public string $type = 'manual';

    public string $priority = 'medium';

    public string $start_date;

    public ?string $due_date = null;

    public ?float $initial_value = null;

    public ?float $target_value = null;

    public ?string $unit = null;

    public string $settings_period = 'month';

    public bool $is_private = false;

    public bool $reminder_enabled = false;

    public ?string $reminder_date = null;

    public function mount(?Goal $goal = null): void
    {
        $this->start_date = $this->today();

        if ($goal) {
            $this->authorize('update', $goal);
            $this->goal = $goal;

            $this->name = $goal->name;
            $this->description = $goal->description;
            $this->notes = $goal->notes;
            $this->category_id = $goal->category_id;
            $this->habit_id = $goal->habit_id;
            $this->icon = $goal->icon;
            $this->color = $goal->color;
            $this->type = $goal->type->value;
            $this->priority = $goal->priority->value;
            $this->start_date = $goal->start_date->toDateString();
            $this->due_date = $goal->due_date?->toDateString();
            $this->initial_value = $goal->initial_value !== null ? (float) $goal->initial_value : null;
            $this->target_value = $goal->target_value !== null ? (float) $goal->target_value : null;
            $this->unit = $goal->unit;
            $this->settings_period = $goal->settings['period'] ?? 'month';
            $this->is_private = $goal->is_private;
            $this->reminder_enabled = $goal->reminder_enabled;
            $this->reminder_date = $goal->reminder_date?->toDateString();
        } else {
            $this->authorize('create', Goal::class);
        }
    }

    private function today(): string
    {
        return CarbonImmutable::now(Auth::user()->timezone ?: config('app.timezone'))->toDateString();
    }

    #[Computed]
    public function categories()
    {
        return Auth::user()->categories()->orderBy('position')->get();
    }

    #[Computed]
    public function habits()
    {
        return Auth::user()->habits()->active()->orderBy('name')->get();
    }

    public function updatedType(): void
    {
        if (! GoalType::from($this->type)->requiresHabit()) {
            // Un objetivo porcentual puede opcionalmente seguir vinculado a un
            // hábito; los demás tipos sin hábito lo limpian al cambiar de tipo.
            if ($this->type !== GoalType::Percentage->value) {
                $this->habit_id = null;
            }
        }
    }

    protected function rules(): array
    {
        $userId = Auth::id();

        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'category_id' => ['nullable', Rule::exists('categories', 'id')->where('user_id', $userId)],
            'icon' => ['required', 'string', 'max:40'],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'type' => ['required', Rule::enum(GoalType::class)],
            'priority' => ['required', Rule::enum(GoalPriority::class)],
            'start_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'is_private' => ['boolean'],
            'reminder_enabled' => ['boolean'],
            'reminder_date' => ['nullable', 'date'],
        ];

        $ownedHabitRule = Rule::exists('habits', 'id')->where('user_id', $userId)->whereNull('deleted_at');

        if ($this->type === GoalType::Habit->value) {
            $rules['habit_id'] = ['required', $ownedHabitRule];
            $rules['target_value'] = ['required', 'numeric', 'min:1'];
        } elseif ($this->type === GoalType::Streak->value) {
            $rules['habit_id'] = ['required', $ownedHabitRule];
            $rules['target_value'] = ['required', 'integer', 'min:1'];
        } elseif ($this->type === GoalType::Numeric->value) {
            $rules['target_value'] = ['required', 'numeric', 'min:0.01'];
            $rules['unit'] = ['required', 'string', 'max:40'];
            $rules['initial_value'] = ['nullable', 'numeric', 'min:0'];
        } elseif ($this->type === GoalType::Percentage->value) {
            $rules['target_value'] = ['required', 'numeric', 'min:1', 'max:100'];
            $rules['habit_id'] = ['nullable', $ownedHabitRule];
            $rules['settings_period'] = ['required', Rule::in(['week', 'month', 'year'])];
        } elseif ($this->type === GoalType::Deadline->value) {
            $rules['due_date'] = ['required', 'date', 'after_or_equal:start_date'];
        }

        return $rules;
    }

    public function save(): void
    {
        $validated = $this->validate();

        $settings = $this->type === GoalType::Percentage->value
            ? ['period' => $validated['settings_period'] ?? 'month']
            : null;

        $isNumericLike = in_array($this->type, [GoalType::Habit->value, GoalType::Streak->value, GoalType::Numeric->value, GoalType::Percentage->value], true);
        $usesHabit = in_array($this->type, [GoalType::Habit->value, GoalType::Streak->value], true) || ($this->type === GoalType::Percentage->value);

        $data = [
            'category_id' => $validated['category_id'] ?? null,
            'habit_id' => $usesHabit ? ($validated['habit_id'] ?? null) : null,
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'type' => $this->type,
            'priority' => $validated['priority'],
            'icon' => $validated['icon'],
            'color' => $validated['color'],
            'start_date' => $validated['start_date'],
            'due_date' => $validated['due_date'] ?? null,
            'initial_value' => $this->type === GoalType::Numeric->value ? ($validated['initial_value'] ?? 0) : null,
            'target_value' => $isNumericLike ? ($validated['target_value'] ?? null) : null,
            'unit' => $this->type === GoalType::Numeric->value ? $validated['unit'] : null,
            'settings' => $settings,
            'is_private' => $this->is_private,
            'reminder_enabled' => $this->reminder_enabled,
            'reminder_date' => $this->reminder_enabled ? ($validated['reminder_date'] ?? null) : null,
        ];

        if ($this->goal) {
            $this->goal->update($data);
            $goal = $this->goal;
        } else {
            $data['status'] = GoalStatus::Active->value;
            $goal = Auth::user()->goals()->create($data);
        }

        session()->flash('status', $this->goal ? 'Objetivo actualizado correctamente.' : 'Objetivo creado correctamente.');

        $this->redirectRoute('goals.show', $goal, navigate: true);
    }

    public function render()
    {
        return view('livewire.goals.goal-form');
    }
}
