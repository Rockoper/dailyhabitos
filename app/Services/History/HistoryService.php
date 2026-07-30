<?php

namespace App\Services\History;

use App\Enums\FrequencyType;
use App\Enums\LogStatus;
use App\Models\Goal;
use App\Models\GoalProgressEntry;
use App\Models\Habit;
use App\Models\HabitLog;
use App\Models\DailyReflection;
use App\Models\User;
use App\Services\Habits\CalendarService;
use App\Services\Habits\ScheduleResolver;
use App\Services\Habits\Support\HabitLogEvaluator;
use App\Support\Number;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Construye la línea de tiempo de Historial a partir de tablas ya
 * existentes (`habit_logs`, `goal_progress_entries`, `goals`, `habits`,
 * `daily_reflections`) — no existe una tabla de eventos propia. Cada
 * "fuente" se consulta como máximo una vez por rango (nunca por día ni por
 * tarjeta), y el resumen diario reutiliza `CalendarService::buildDays()`.
 *
 * Racha/logros: se recorre cada hábito una sola vez (no se llama
 * `StreakCalculator` por evento) replicando el mismo algoritmo de racha por
 * rachas de días consecutivos que usa `StreakCalculator::longestDaily()` —
 * si esa lógica cambia, esta debe mantenerse en sintonía.
 */
class HistoryService
{
    private const STREAK_MILESTONES = [7, 14, 30, 60, 90, 365];

    /** Eventos "automáticos" (calculados por el sistema) frente a los originados por una acción directa del usuario. */
    private const AUTOMATIC_TYPES = ['perfect_day', 'streak_milestone', 'first_habit_completed', 'first_goal_completed'];

    public function __construct(
        private readonly ScheduleResolver $scheduleResolver,
        private readonly CalendarService $calendarService,
    ) {}

    /**
     * @param  array{
     *     type: string, status: string, habitId: ?int, goalId: ?int, categoryId: ?int,
     *     onlyPerfectDays: bool, onlyWithReflection: bool, onlyManual: bool, onlyAutomatic: bool, search: string,
     * }  $filters
     * @return array{groups: Collection<int, DailyHistorySummary>, kpis: array, insights: array, hasAnyActivityEver: bool}
     */
    public function build(User $user, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $today, array $filters): array
    {
        $calendarHabits = $user->habits()->active()->with('logs')->get();
        $days = $this->calendarService->buildDays($calendarHabits, $from, $to, $today);

        $events = collect();

        if ($this->categoryAllowed('habits', $filters)) {
            $events = $events->concat($this->habitLogEvents($user, $from, $to, $filters));
        }
        if ($this->categoryAllowed('goals', $filters)) {
            $events = $events->concat($this->goalProgressEvents($user, $from, $to, $filters));
        }
        if ($this->categoryAllowed('system', $filters)) {
            $events = $events->concat($this->goalLifecycleEvents($user, $from, $to, $filters));
            $events = $events->concat($this->habitLifecycleEvents($user, $from, $to, $filters));
        }
        if ($this->categoryAllowed('reflections', $filters)) {
            $events = $events->concat($this->reflectionEvents($user, $from, $to));
        }
        if ($this->categoryAllowed('achievements', $filters)) {
            $events = $events->concat($this->perfectDayEvents($days));
            $events = $events->concat($this->achievementEvents($user, $from, $to, $today, $filters));
        }

        $events = $this->applyManualAutomaticFilter($events, $filters);
        $events = $this->applySearch($events, $filters['search']);

        $groups = $this->groupByDate($events, $days, $today);

        if ($filters['onlyPerfectDays']) {
            $groups = $groups->filter(fn (DailyHistorySummary $g) => $g->dayLevel === 'completed');
        }
        if ($filters['onlyWithReflection']) {
            $groups = $groups->filter(fn (DailyHistorySummary $g) => $g->events->contains(fn (HistoryEvent $e) => $e->type === 'reflection'));
        }

        $groups = $groups->sortByDesc(fn (DailyHistorySummary $g) => $g->date->format('Y-m-d'))->values();

        return [
            'groups' => $groups,
            'kpis' => $this->buildKpis($groups, $days, $today),
            'insights' => $this->buildInsights($groups, $days),
            'hasAnyActivityEver' => $this->hasAnyActivityEver($user),
        ];
    }

    private function categoryAllowed(string $category, array $filters): bool
    {
        return $filters['type'] === 'all' || $filters['type'] === $category;
    }

    /**
     * @return Collection<int, HistoryEvent>
     */
    private function habitLogEvents(User $user, CarbonImmutable $from, CarbonImmutable $to, array $filters): Collection
    {
        $query = HabitLog::where('user_id', $user->id)
            ->whereDate('date', '>=', $from->toDateString())
            ->whereDate('date', '<=', $to->toDateString())
            ->with(['habit' => fn ($q) => $q->withTrashed()]);

        if ($filters['habitId']) {
            $query->where('habit_id', $filters['habitId']);
        }
        if ($filters['status'] !== 'all') {
            $query->where('status', $filters['status']);
        }
        if ($filters['categoryId']) {
            $query->whereHas('habit', fn ($q) => $q->withTrashed()->where('category_id', $filters['categoryId']));
        }

        return $query->get()->map(function (HabitLog $log) {
            $habit = $log->habit;
            $name = $habit && $habit->is_private ? 'Hábito privado' : ($habit?->name ?? 'Hábito eliminado');
            $completed = $log->status === LogStatus::Completed
                || ($habit && $this->isQuantityCompleted($habit, $log));

            [$type, $icon, $color, $verb] = match (true) {
                $completed => ['habit_completed', $habit?->icon ?? 'check', 'primary', 'completado'],
                $log->status === LogStatus::Skipped => ['habit_skipped', 'dot-circle', 'secondary', 'omitido'],
                $log->status === LogStatus::Partial => ['habit_partial', 'dot-circle', 'secondary', 'parcial'],
                default => ['habit_missed', 'x-circle', 'error', 'no completado'],
            };

            $descriptionParts = [];
            if ($log->quantity_value !== null && $habit) {
                $descriptionParts[] = sprintf('%s %s', rtrim(rtrim((string) $log->quantity_value, '0'), '.'), $habit->displayUnit() ?? '');
            }
            if ($log->note) {
                $descriptionParts[] = Str::limit($log->note, 80);
            }

            return new HistoryEvent(
                type: $type,
                date: CarbonImmutable::parse($log->date),
                occurredAt: $log->updated_at ? CarbonImmutable::parse($log->updated_at) : null,
                title: sprintf('Hábito "%s" %s', $name, $verb),
                description: $descriptionParts === [] ? null : implode(' · ', $descriptionParts),
                icon: $icon,
                color: $color,
                sourceType: 'habit',
                sourceId: $habit?->id,
                metadata: ['status' => $log->status->value],
                route: $habit && $habit->trashed() === false ? 'habits.show' : null,
                routeParams: $habit && $habit->trashed() === false ? ['habit' => $habit->id] : [],
                priority: $completed ? 10 : 15,
            );
        });
    }

    private function isQuantityCompleted(Habit $habit, HabitLog $log): bool
    {
        return $habit->type->value === 'quantity'
            && $habit->target_quantity !== null
            && $log->quantity_value !== null
            && (float) $log->quantity_value >= (float) $habit->target_quantity;
    }

    /**
     * @return Collection<int, HistoryEvent>
     */
    private function goalProgressEvents(User $user, CarbonImmutable $from, CarbonImmutable $to, array $filters): Collection
    {
        $priorSums = GoalProgressEntry::where('user_id', $user->id)
            ->whereDate('recorded_at', '<', $from->toDateString())
            ->groupBy('goal_id')
            ->selectRaw('goal_id, SUM(value) as total')
            ->pluck('total', 'goal_id');

        $query = GoalProgressEntry::where('user_id', $user->id)
            ->whereDate('recorded_at', '>=', $from->toDateString())
            ->whereDate('recorded_at', '<=', $to->toDateString())
            ->with(['goal' => fn ($q) => $q->withTrashed()])
            ->orderBy('goal_id')->orderBy('recorded_at')->orderBy('id');

        if ($filters['goalId']) {
            $query->where('goal_id', $filters['goalId']);
        }
        if ($filters['categoryId']) {
            $query->whereHas('goal', fn ($q) => $q->withTrashed()->where('category_id', $filters['categoryId']));
        }

        $entries = $query->get();
        $events = collect();
        $running = [];

        foreach ($entries->groupBy('goal_id') as $goalId => $goalEntries) {
            /** @var Goal|null $goal */
            $goal = $goalEntries->first()->goal;
            $initial = $goal && $goal->initial_value !== null ? (float) $goal->initial_value : 0.0;
            $running[$goalId] = $initial + (float) ($priorSums[$goalId] ?? 0);
            $target = $goal && $goal->target_value !== null ? (float) $goal->target_value : null;
            $name = $goal && $goal->is_private ? 'Objetivo privado' : ($goal?->name ?? 'Objetivo eliminado');

            foreach ($goalEntries as $entry) {
                $beforeValue = $running[$goalId];
                $running[$goalId] += (float) $entry->value;
                $afterValue = $running[$goalId];
                $percentage = $target && $target > 0 ? round(min(100, max(0, $afterValue / $target * 100)), 1) : null;

                $description = $target !== null
                    ? sprintf('%s → %s de %s (%s%%)', Number::trim($beforeValue), Number::trim($afterValue), Number::trim($target), $percentage)
                    : sprintf('%s → %s', Number::trim($beforeValue), Number::trim($afterValue));

                if ($entry->note) {
                    $description .= ' · '.Str::limit($entry->note, 60);
                }

                $events->push(new HistoryEvent(
                    type: 'goal_progress',
                    date: CarbonImmutable::parse($entry->recorded_at),
                    occurredAt: $entry->created_at ? CarbonImmutable::parse($entry->created_at) : null,
                    title: sprintf('Progreso del objetivo "%s"', $name),
                    description: $description,
                    icon: 'target',
                    color: 'tertiary',
                    sourceType: 'goal',
                    sourceId: $goal?->id,
                    route: $goal && ! $goal->trashed() ? 'goals.show' : null,
                    routeParams: $goal && ! $goal->trashed() ? ['goal' => $goal->id] : [],
                    priority: 20,
                ));
            }
        }

        return $events;
    }

    /**
     * @return Collection<int, HistoryEvent>
     */
    private function goalLifecycleEvents(User $user, CarbonImmutable $from, CarbonImmutable $to, array $filters): Collection
    {
        $query = Goal::withTrashed()->where('user_id', $user->id)
            ->where(function ($q) use ($from, $to) {
                foreach (['created_at', 'completed_at', 'archived_at', 'cancelled_at'] as $column) {
                    $q->orWhere(fn ($qq) => $qq->whereDate($column, '>=', $from->toDateString())->whereDate($column, '<=', $to->toDateString()));
                }
            });

        if ($filters['goalId']) {
            $query->where('id', $filters['goalId']);
        }
        if ($filters['categoryId']) {
            $query->where('category_id', $filters['categoryId']);
        }

        $events = collect();

        foreach ($query->get() as $goal) {
            $name = $goal->is_private ? 'Objetivo privado' : $goal->name;
            $route = $goal->trashed() ? null : 'goals.show';
            $routeParams = $goal->trashed() ? [] : ['goal' => $goal->id];

            $this->pushIfInRange($events, $goal->created_at, $from, $to, fn (CarbonImmutable $at) => new HistoryEvent(
                type: 'goal_created', date: $at->startOfDay(), occurredAt: $at,
                title: sprintf('Se creó el objetivo "%s"', $name), description: null,
                icon: 'plus', color: 'secondary', sourceType: 'goal', sourceId: $goal->id,
                route: $route, routeParams: $routeParams, priority: 40,
            ));

            if ($goal->completed_at) {
                $this->pushIfInRange($events, $goal->completed_at, $from, $to, fn (CarbonImmutable $at) => new HistoryEvent(
                    type: 'goal_completed', date: $at->startOfDay(), occurredAt: $at,
                    title: sprintf('Objetivo "%s" completado', $name),
                    description: $goal->target_value !== null ? sprintf('Meta alcanzada: %s %s', Number::trim((float) $goal->target_value), $goal->unit ?? '') : null,
                    icon: 'trophy', color: 'gold', sourceType: 'goal', sourceId: $goal->id,
                    route: $route, routeParams: $routeParams, priority: 5,
                ));
            }

            if ($goal->archived_at) {
                $this->pushIfInRange($events, $goal->archived_at, $from, $to, fn (CarbonImmutable $at) => new HistoryEvent(
                    type: 'goal_archived', date: $at->startOfDay(), occurredAt: $at,
                    title: sprintf('Objetivo "%s" archivado', $name), description: null,
                    icon: 'archive', color: 'secondary', sourceType: 'goal', sourceId: $goal->id,
                    route: $route, routeParams: $routeParams, priority: 45,
                ));
            }

            if ($goal->cancelled_at) {
                $this->pushIfInRange($events, $goal->cancelled_at, $from, $to, fn (CarbonImmutable $at) => new HistoryEvent(
                    type: 'goal_cancelled', date: $at->startOfDay(), occurredAt: $at,
                    title: sprintf('Objetivo "%s" cancelado', $name), description: null,
                    icon: 'x-circle', color: 'secondary', sourceType: 'goal', sourceId: $goal->id,
                    route: $route, routeParams: $routeParams, priority: 45,
                ));
            }
        }

        return $events;
    }

    /**
     * @return Collection<int, HistoryEvent>
     */
    private function habitLifecycleEvents(User $user, CarbonImmutable $from, CarbonImmutable $to, array $filters): Collection
    {
        $query = Habit::withTrashed()->where('user_id', $user->id)
            ->where(function ($q) use ($from, $to) {
                foreach (['created_at', 'archived_at', 'deleted_at'] as $column) {
                    $q->orWhere(fn ($qq) => $qq->whereDate($column, '>=', $from->toDateString())->whereDate($column, '<=', $to->toDateString()));
                }
            });

        if ($filters['habitId']) {
            $query->where('id', $filters['habitId']);
        }
        if ($filters['categoryId']) {
            $query->where('category_id', $filters['categoryId']);
        }

        $events = collect();

        foreach ($query->get() as $habit) {
            $name = $habit->is_private ? 'Hábito privado' : $habit->name;
            $route = $habit->trashed() ? null : 'habits.show';
            $routeParams = $habit->trashed() ? [] : ['habit' => $habit->id];

            $this->pushIfInRange($events, $habit->created_at, $from, $to, fn (CarbonImmutable $at) => new HistoryEvent(
                type: 'habit_created', date: $at->startOfDay(), occurredAt: $at,
                title: sprintf('Se creó el hábito "%s"', $name), description: null,
                icon: $habit->icon ?: 'plus', color: 'secondary', sourceType: 'habit', sourceId: $habit->id,
                route: $route, routeParams: $routeParams, priority: 40,
            ));

            if ($habit->archived_at) {
                $this->pushIfInRange($events, $habit->archived_at, $from, $to, fn (CarbonImmutable $at) => new HistoryEvent(
                    type: 'habit_archived', date: $at->startOfDay(), occurredAt: $at,
                    title: sprintf('Hábito "%s" archivado', $name), description: null,
                    icon: 'archive', color: 'secondary', sourceType: 'habit', sourceId: $habit->id,
                    route: $route, routeParams: $routeParams, priority: 45,
                ));
            }

            if ($habit->deleted_at) {
                $this->pushIfInRange($events, $habit->deleted_at, $from, $to, fn (CarbonImmutable $at) => new HistoryEvent(
                    type: 'habit_deleted', date: $at->startOfDay(), occurredAt: $at,
                    title: sprintf('Hábito "%s" eliminado', $name), description: null,
                    icon: 'trash', color: 'secondary', sourceType: 'habit', sourceId: $habit->id,
                    route: null, routeParams: [], priority: 45,
                ));
            }
        }

        return $events;
    }

    private function pushIfInRange(Collection $events, mixed $timestamp, CarbonImmutable $from, CarbonImmutable $to, \Closure $make): void
    {
        if (! $timestamp) {
            return;
        }

        $at = CarbonImmutable::parse($timestamp);
        if ($at->startOfDay()->betweenIncluded($from->startOfDay(), $to->startOfDay())) {
            $events->push($make($at));
        }
    }

    /**
     * @return Collection<int, HistoryEvent>
     */
    private function reflectionEvents(User $user, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        $query = DailyReflection::where('user_id', $user->id)
            ->where('status', 'completed')
            ->whereDate('reflection_date', '>=', $from->toDateString())
            ->whereDate('reflection_date', '<=', $to->toDateString());

        return $query->get()->map(function (DailyReflection $reflection) {
            $moodLabels = [1 => 'Muy mal', 2 => 'Mal', 3 => 'Neutral', 4 => 'Bien', 5 => 'Muy bien'];
            $scaleLabels = [1 => 'Muy baja', 2 => 'Baja', 3 => 'Media', 4 => 'Alta', 5 => 'Muy alta'];

            $bits = [];
            if ($reflection->mood) {
                $bits[] = 'Ánimo: '.$moodLabels[$reflection->mood];
            }
            if ($reflection->energy_level) {
                $bits[] = 'Energía: '.$scaleLabels[$reflection->energy_level];
            }

            $snippet = Str::limit((string) ($reflection->went_well ?: $reflection->free_notes ?: ''), 70);

            return new HistoryEvent(
                type: 'reflection',
                date: CarbonImmutable::parse($reflection->reflection_date),
                occurredAt: $reflection->updated_at ? CarbonImmutable::parse($reflection->updated_at) : null,
                title: 'Reflexión diaria registrada',
                description: trim(implode(' · ', $bits).($snippet !== '' ? ' — "'.$snippet.'"' : '')) ?: null,
                icon: 'note',
                color: 'secondary',
                sourceType: 'reflection',
                sourceId: $reflection->id,
                route: 'reflections.index',
                routeParams: ['fecha' => $reflection->reflection_date->toDateString()],
                priority: 30,
            );
        });
    }

    /**
     * @return Collection<int, HistoryEvent>
     */
    private function perfectDayEvents(Collection $days): Collection
    {
        return $days->filter(fn (array $day) => $day['level'] === 'completed' && $day['items']->isNotEmpty())
            ->map(fn (array $day) => new HistoryEvent(
                type: 'perfect_day',
                date: $day['date'],
                occurredAt: null,
                title: 'Día perfecto',
                description: sprintf('%d de %d hábitos completados', $day['items']->count(), $day['items']->count()),
                icon: 'star',
                color: 'gold',
                sourceType: 'day',
                sourceId: null,
                priority: 60,
            ))
            ->values();
    }

    /**
     * @return Collection<int, HistoryEvent>
     */
    private function achievementEvents(User $user, CarbonImmutable $from, CarbonImmutable $to, CarbonImmutable $today, array $filters): Collection
    {
        $events = collect();

        $habitsQuery = $user->habits()->withTrashed()->with('logs');
        if ($filters['habitId']) {
            $habitsQuery->where('id', $filters['habitId']);
        }
        $habits = $habitsQuery->get();

        foreach ($habits as $habit) {
            if ($habit->frequency_type === FrequencyType::WeeklyCount) {
                continue;
            }

            $windowStart = $to->subDays(400);
            $habitStart = CarbonImmutable::parse($habit->start_date)->startOfDay();
            if ($habitStart->gt($windowStart)) {
                $windowStart = $habitStart;
            }

            $expected = $this->scheduleResolver->expectedDates($habit, $windowStart, $to);
            $evaluator = new HabitLogEvaluator($habit);
            $failureThreshold = $habit->never_fail_twice ? 2 : 1;
            $run = 0;
            $consecutiveFailures = 0;
            $name = $habit->is_private ? 'Hábito privado' : $habit->name;
            $route = $habit->trashed() ? null : 'habits.show';
            $routeParams = $habit->trashed() ? [] : ['habit' => $habit->id];

            foreach ($expected as $date) {
                if ($date->isSameDay($today) && $evaluator->logOn($date) === null) {
                    continue;
                }

                if ($evaluator->isCompleted($date)) {
                    $run++;
                    $consecutiveFailures = 0;

                    if (in_array($run, self::STREAK_MILESTONES, true) && $date->betweenIncluded($from, $to)) {
                        $events->push(new HistoryEvent(
                            type: 'streak_milestone',
                            date: $date,
                            occurredAt: null,
                            title: sprintf('Racha de "%s" alcanzó %d días', $name, $run),
                            description: null,
                            icon: 'flame',
                            color: 'gold',
                            sourceType: 'habit',
                            sourceId: $habit->id,
                            metadata: ['streak' => $run],
                            route: $route,
                            routeParams: $routeParams,
                            priority: 55,
                        ));
                    }

                    continue;
                }

                $consecutiveFailures++;
                if ($consecutiveFailures >= $failureThreshold) {
                    $run = 0;
                    $consecutiveFailures = 0;
                }
            }
        }

        $events = $events->concat($this->firstEverEvents($user, $from, $to, $filters));

        return $events;
    }

    /**
     * @return Collection<int, HistoryEvent>
     */
    private function firstEverEvents(User $user, CarbonImmutable $from, CarbonImmutable $to, array $filters): Collection
    {
        $events = collect();

        if (! $filters['goalId']) {
            $firstLog = HabitLog::where('user_id', $user->id)
                ->where('status', LogStatus::Completed)
                ->with(['habit' => fn ($q) => $q->withTrashed()])
                ->orderBy('date')->orderBy('id')
                ->first();

            if ($firstLog && CarbonImmutable::parse($firstLog->date)->betweenIncluded($from, $to)) {
                $habit = $firstLog->habit;
                $name = $habit && $habit->is_private ? 'Hábito privado' : ($habit?->name ?? 'un hábito');
                $events->push(new HistoryEvent(
                    type: 'first_habit_completed',
                    date: CarbonImmutable::parse($firstLog->date),
                    occurredAt: null,
                    title: sprintf('Primer hábito completado: "%s"', $name),
                    description: 'El comienzo de tu historial de hábitos.',
                    icon: 'sparkle',
                    color: 'gold',
                    sourceType: 'habit',
                    sourceId: $habit?->id,
                    priority: 3,
                ));
            }
        }

        if (! $filters['habitId']) {
            $firstGoal = Goal::withTrashed()->where('user_id', $user->id)
                ->whereNotNull('completed_at')
                ->orderBy('completed_at')
                ->first();

            if ($firstGoal && CarbonImmutable::parse($firstGoal->completed_at)->startOfDay()->betweenIncluded($from, $to)) {
                $name = $firstGoal->is_private ? 'Objetivo privado' : $firstGoal->name;
                $events->push(new HistoryEvent(
                    type: 'first_goal_completed',
                    date: CarbonImmutable::parse($firstGoal->completed_at)->startOfDay(),
                    occurredAt: null,
                    title: sprintf('Primer objetivo completado: "%s"', $name),
                    description: null,
                    icon: 'sparkle',
                    color: 'gold',
                    sourceType: 'goal',
                    sourceId: $firstGoal->id,
                    priority: 3,
                ));
            }
        }

        return $events;
    }

    /**
     * @param  Collection<int, HistoryEvent>  $events
     * @return Collection<int, HistoryEvent>
     */
    private function applyManualAutomaticFilter(Collection $events, array $filters): Collection
    {
        if ($filters['onlyManual']) {
            return $events->reject(fn (HistoryEvent $e) => in_array($e->type, self::AUTOMATIC_TYPES, true))->values();
        }
        if ($filters['onlyAutomatic']) {
            return $events->filter(fn (HistoryEvent $e) => in_array($e->type, self::AUTOMATIC_TYPES, true))->values();
        }

        return $events;
    }

    /**
     * @param  Collection<int, HistoryEvent>  $events
     * @return Collection<int, HistoryEvent>
     */
    private function applySearch(Collection $events, string $search): Collection
    {
        $search = trim($search);
        if ($search === '') {
            return $events;
        }

        $needle = Str::lower($search);

        return $events->filter(function (HistoryEvent $event) use ($needle) {
            return Str::contains(Str::lower($event->title), $needle)
                || ($event->description && Str::contains(Str::lower($event->description), $needle));
        })->values();
    }

    /**
     * @param  Collection<int, HistoryEvent>  $events
     * @return Collection<int, DailyHistorySummary>
     */
    private function groupByDate(Collection $events, Collection $days, CarbonImmutable $today): Collection
    {
        $byDate = $events->groupBy(fn (HistoryEvent $e) => $e->date->format('Y-m-d'));

        return $byDate->map(function (Collection $dayEvents, string $dateKey) use ($days, $today) {
            $date = CarbonImmutable::parse($dateKey);
            $day = $days->get($dateKey);

            $sorted = $dayEvents->sortBy([
                fn (HistoryEvent $e) => $e->occurredAt ? 0 : 1,
                fn (HistoryEvent $e) => $e->occurredAt?->format('H:i:s') ?? '99:99:99',
                fn (HistoryEvent $e) => $e->priority,
            ])->values();

            return new DailyHistorySummary(
                date: $date,
                isToday: $date->isSameDay($today),
                isYesterday: $date->isSameDay($today->subDay()),
                expectedHabits: $day ? $day['items']->count() : 0,
                completedHabits: $day ? $day['items']->filter(fn (array $i) => $i['completed'])->count() : 0,
                percentage: $day['percentage'] ?? null,
                dayLevel: $day['level'] ?? 'none',
                events: $sorted,
            );
        })->values();
    }

    private function buildKpis(Collection $groups, Collection $days, CarbonImmutable $today): array
    {
        $allEvents = $groups->flatMap(fn (DailyHistorySummary $g) => $g->events);

        return [
            'events' => $allEvents->count(),
            'habits_completed' => $allEvents->filter(fn (HistoryEvent $e) => $e->type === 'habit_completed')->count(),
            'goals_with_progress' => $allEvents->filter(fn (HistoryEvent $e) => $e->type === 'goal_progress')->pluck('sourceId')->unique()->filter()->count(),
            'reflections' => $allEvents->filter(fn (HistoryEvent $e) => $e->type === 'reflection')->count(),
            'perfect_days' => $groups->filter(fn (DailyHistorySummary $g) => $g->dayLevel === 'completed')->count(),
            'activity_streak' => $this->activityStreak($days, $today),
        ];
    }

    private function activityStreak(Collection $days, CarbonImmutable $today): int
    {
        $current = 0;

        foreach ($days->values()->reverse() as $day) {
            if ($day['date']->gt($today)) {
                continue;
            }
            if ($day['level'] === 'none') {
                continue;
            }

            $hasActivity = $day['items']->contains(fn (array $item) => $item['completed']);

            if ($day['date']->isSameDay($today) && ! $hasActivity) {
                continue;
            }
            if ($hasActivity) {
                $current++;

                continue;
            }
            break;
        }

        return $current;
    }

    private function buildInsights(Collection $groups, Collection $days): array
    {
        $insights = [];
        $allEvents = $groups->flatMap(fn (DailyHistorySummary $g) => $g->events);

        $habitsCompleted = $allEvents->filter(fn (HistoryEvent $e) => $e->type === 'habit_completed')->count();
        if ($habitsCompleted > 0) {
            $insights[] = sprintf('Completaste %d %s en este periodo.', $habitsCompleted, $habitsCompleted === 1 ? 'hábito' : 'hábitos');
        }

        $reflections = $allEvents->filter(fn (HistoryEvent $e) => $e->type === 'reflection')->count();
        if ($reflections > 0) {
            $insights[] = sprintf('Escribiste %d %s en este periodo.', $reflections, $reflections === 1 ? 'reflexión' : 'reflexiones');
        }

        $goalsCompleted = $allEvents->filter(fn (HistoryEvent $e) => $e->type === 'goal_completed')->count();
        if ($goalsCompleted > 0) {
            $insights[] = sprintf('Alcanzaste %d %s en este periodo.', $goalsCompleted, $goalsCompleted === 1 ? 'objetivo' : 'objetivos');
        }

        $busiestDay = $groups->sortByDesc(fn (DailyHistorySummary $g) => $g->events->count())->first();
        if ($busiestDay && $busiestDay->events->count() >= 3) {
            $insights[] = sprintf('Tu día con más actividad fue el %s.', Str::ucfirst($busiestDay->date->translatedFormat('l')));
        }

        [, $longestInPeriod] = $this->calendarService->streaks($days->values(), $days->keys()->isNotEmpty() ? $days->last()['date'] : now());
        if ($longestInPeriod >= 2) {
            $insights[] = sprintf('Tu racha más larga del periodo fue de %d días.', $longestInPeriod);
        }

        return array_slice($insights, 0, 4);
    }

    private function hasAnyActivityEver(User $user): bool
    {
        return HabitLog::where('user_id', $user->id)->exists()
            || GoalProgressEntry::where('user_id', $user->id)->exists()
            || DailyReflection::where('user_id', $user->id)->where('status', 'completed')->exists()
            || Goal::withTrashed()->where('user_id', $user->id)->exists()
            || Habit::withTrashed()->where('user_id', $user->id)->exists();
    }
}
