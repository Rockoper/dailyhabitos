<?php

namespace App\Livewire\Reflections;

use App\Enums\ReflectionStatus;
use App\Models\DailyReflection;
use App\Services\Reflections\DailyReflectionService;
use App\Services\Reflections\ReflectionInsightService;
use App\Services\Reflections\ReflectionSummaryService;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;

class ReflectionForm extends Component
{
    /** Campos que disparan autoguardado cuando cambian (ver `updated()`). */
    private const AUTOSAVE_FIELDS = [
        'mood', 'energy_level', 'productivity_level',
        'went_well', 'challenges', 'learned', 'gratitude', 'improve_tomorrow', 'tomorrow_priority',
        'free_notes', 'tags',
    ];

    #[Url(as: 'fecha')]
    public string $date = '';

    public ?DailyReflection $reflection = null;

    public ?int $mood = null;

    public ?int $energy_level = null;

    public ?int $productivity_level = null;

    public ?string $went_well = null;

    public ?string $challenges = null;

    public ?string $learned = null;

    public ?string $gratitude = null;

    public ?string $improve_tomorrow = null;

    public ?string $tomorrow_priority = null;

    public ?string $free_notes = null;

    public array $tags = [];

    public string $customTag = '';

    public ?string $lastSavedAt = null;

    public bool $isDirty = false;

    public function mount(): void
    {
        $requested = preg_match('/^\d{4}-\d{2}-\d{2}$/', $this->date) === 1
            ? CarbonImmutable::parse($this->date, $this->timezone())->startOfDay()
            : $this->today();

        $this->navigateTo($requested);
    }

    private function timezone(): string
    {
        return Auth::user()->timezone ?: config('app.timezone');
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone())->startOfDay();
    }

    private function greeting(): string
    {
        $hour = CarbonImmutable::now($this->timezone())->hour;

        $moment = match (true) {
            $hour < 12 => 'Buenos días',
            $hour < 19 => 'Buenas tardes',
            default => 'Buenas noches',
        };

        return sprintf('%s, %s', $moment, Auth::user()->name);
    }

    public function goPrevious(): void
    {
        $this->navigateTo(CarbonImmutable::parse($this->date, $this->timezone())->startOfDay()->subDay());
    }

    public function goNext(): void
    {
        $this->navigateTo(CarbonImmutable::parse($this->date, $this->timezone())->startOfDay()->addDay());
    }

    public function goToday(): void
    {
        $this->navigateTo($this->today());
    }

    public function openDate(string $date): void
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) !== 1) {
            return;
        }

        $this->navigateTo(CarbonImmutable::parse($date, $this->timezone())->startOfDay());
    }

    private function navigateTo(CarbonImmutable $date): void
    {
        $today = $this->today();
        if ($date->gt($today)) {
            $date = $today;
        }

        $this->date = $date->toDateString();
        $this->loadForDate($date);
    }

    private function loadForDate(CarbonImmutable $date): void
    {
        $reflection = Auth::user()->dailyReflections()
            ->whereDate('reflection_date', $date->toDateString())
            ->first();

        if ($reflection) {
            $this->authorize('view', $reflection);
        }

        $this->reflection = $reflection;
        $this->mood = $reflection?->mood;
        $this->energy_level = $reflection?->energy_level;
        $this->productivity_level = $reflection?->productivity_level;
        $this->went_well = $reflection?->went_well;
        $this->challenges = $reflection?->challenges;
        $this->learned = $reflection?->learned;
        $this->gratitude = $reflection?->gratitude;
        $this->improve_tomorrow = $reflection?->improve_tomorrow;
        $this->tomorrow_priority = $reflection?->tomorrow_priority;
        $this->free_notes = $reflection?->free_notes;
        $this->tags = $reflection?->tags ?? [];
        $this->lastSavedAt = $reflection?->updated_at?->setTimezone($this->timezone())->diffForHumans();
        $this->isDirty = false;

        $this->resetErrorBag();
    }

    public function toggleTag(string $tag): void
    {
        if (in_array($tag, $this->tags, true)) {
            $this->tags = array_values(array_diff($this->tags, [$tag]));
        } elseif (count($this->tags) < 10) {
            $this->tags[] = $tag;
        }

        $this->autosave();
    }

    public function addCustomTag(): void
    {
        $tag = trim($this->customTag);
        $this->customTag = '';

        if ($tag === '' || in_array($tag, $this->tags, true) || count($this->tags) >= 10) {
            return;
        }

        $this->tags[] = mb_substr($tag, 0, 30);
        $this->autosave();
    }

    public function removeTag(string $tag): void
    {
        $this->tags = array_values(array_diff($this->tags, [$tag]));
        $this->autosave();
    }

    public function updated(string $name): void
    {
        if (in_array($name, self::AUTOSAVE_FIELDS, true)) {
            $this->isDirty = true;
            $this->autosave();
        }
    }

    protected function rules(): array
    {
        return [
            'mood' => ['nullable', 'integer', 'between:1,5'],
            'energy_level' => ['nullable', 'integer', 'between:1,5'],
            'productivity_level' => ['nullable', 'integer', 'between:1,5'],
            'went_well' => ['nullable', 'string', 'max:500'],
            'challenges' => ['nullable', 'string', 'max:500'],
            'learned' => ['nullable', 'string', 'max:500'],
            'gratitude' => ['nullable', 'string', 'max:500'],
            'improve_tomorrow' => ['nullable', 'string', 'max:500'],
            'tomorrow_priority' => ['nullable', 'string', 'max:500'],
            'free_notes' => ['nullable', 'string', 'max:10000'],
            'tags' => ['array', 'max:10'],
            'tags.*' => ['string', 'max:30'],
        ];
    }

    public function autosave(): void
    {
        $date = CarbonImmutable::parse($this->date, $this->timezone())->startOfDay();
        if ($date->gt($this->today())) {
            return;
        }

        if ($this->reflection) {
            $this->authorize('update', $this->reflection);
        } else {
            $this->authorize('create', DailyReflection::class);
        }

        if ($this->isFormEmpty()) {
            return;
        }

        $validated = $this->validate($this->rules());

        $this->reflection = app(DailyReflectionService::class)->saveDraft(Auth::user(), $date, $validated);
        $this->lastSavedAt = 'hace un momento';
        $this->isDirty = false;
        $this->dispatch('reflection-saved');
    }

    public function save(): void
    {
        $date = CarbonImmutable::parse($this->date, $this->timezone())->startOfDay();
        if ($date->gt($this->today())) {
            $this->addError('date', 'No puedes guardar una reflexión para una fecha futura.');

            return;
        }

        if ($this->reflection) {
            $this->authorize('update', $this->reflection);
        } else {
            $this->authorize('create', DailyReflection::class);
        }

        $validated = $this->validate($this->rules());

        $this->reflection = app(DailyReflectionService::class)->complete(Auth::user(), $date, $validated);
        $this->lastSavedAt = 'hace un momento';
        $this->isDirty = false;
        $this->dispatch('reflection-saved');
        session()->flash('status', 'Reflexión guardada.');
    }

    private function isFormEmpty(): bool
    {
        return $this->mood === null
            && $this->energy_level === null
            && $this->productivity_level === null
            && blank($this->went_well)
            && blank($this->challenges)
            && blank($this->learned)
            && blank($this->gratitude)
            && blank($this->improve_tomorrow)
            && blank($this->tomorrow_priority)
            && blank($this->free_notes)
            && $this->tags === [];
    }

    public function render(
        ReflectionSummaryService $summaryService,
        ReflectionInsightService $insightService,
    ) {
        $user = Auth::user();
        $date = CarbonImmutable::parse($this->date, $this->timezone())->startOfDay();

        $recent = $user->dailyReflections()
            ->orderByDesc('reflection_date')
            ->limit(7)
            ->get();

        return view('livewire.reflections.reflection-form', [
            'today' => $this->today(),
            'selectedDate' => $date,
            'isToday' => $date->isSameDay($this->today()),
            'canGoNext' => $date->lt($this->today()),
            'summary' => $summaryService->daySummary($user, $date),
            'recent' => $recent,
            'insights' => $insightService->forUser($user, $date),
            'status' => $this->reflection?->status ?? ReflectionStatus::Draft,
            'greeting' => $this->greeting(),
        ]);
    }
}
