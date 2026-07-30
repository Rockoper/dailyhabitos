<?php

namespace App\Services\Reflections;

use App\Models\GoalProgressEntry;
use App\Models\HabitLog;
use App\Models\User;
use App\Services\Habits\CalendarService;
use Carbon\CarbonImmutable;

/**
 * Resumen del día para la reflexión diaria: cuántos hábitos se esperaban,
 * cuántos se completaron y la racha vigente a esa fecha. Reutiliza
 * `CalendarService::buildDays()`/`streaks()` (ya usado por el calendario y
 * estadísticas) en vez de recalcular el cumplimiento diario desde cero.
 */
class ReflectionSummaryService
{
    public function __construct(private readonly CalendarService $calendarService) {}

    /**
     * @return array{expected: int, completed: int, percentage: ?float, level: string, day_label: string, current_streak: int, last_activity_at: ?CarbonImmutable, goal_progress_entries: int}
     */
    public function daySummary(User $user, CarbonImmutable $date): array
    {
        $habits = $user->habits()->active()->with('logs')->get();

        $windowStart = $date->subDays(400);
        if ($habits->isNotEmpty()) {
            $earliestStart = $habits->min(fn ($habit) => CarbonImmutable::parse($habit->start_date)->startOfDay());
            if ($earliestStart->gt($windowStart)) {
                $windowStart = $earliestStart;
            }
        }
        if ($windowStart->gt($date)) {
            $windowStart = $date;
        }

        $days = $this->calendarService->buildDays($habits, $windowStart, $date, $date);
        $day = $days->get($date->format('Y-m-d'));

        [$currentStreak] = $this->calendarService->streaks($days->values(), $date);

        $completed = $day['items']->filter(fn (array $item) => $item['completed'])->count();

        return [
            'expected' => $day['items']->count(),
            'completed' => $completed,
            'percentage' => $day['percentage'],
            'level' => $day['level'],
            'day_label' => $this->dayLabel($day['level']),
            'current_streak' => $currentStreak,
            'last_activity_at' => $this->lastActivityAt($user, $date),
            'goal_progress_entries' => $this->goalProgressCount($user, $date),
        ];
    }

    private function dayLabel(string $level): string
    {
        return match ($level) {
            'completed' => 'Día perfecto',
            'partial' => 'Día parcial',
            'pending' => 'Sin registros todavía',
            default => 'Sin hábitos programados',
        };
    }

    private function lastActivityAt(User $user, CarbonImmutable $date): ?CarbonImmutable
    {
        $updatedAt = HabitLog::where('user_id', $user->id)
            ->whereDate('date', $date->toDateString())
            ->max('updated_at');

        if (! $updatedAt) {
            return null;
        }

        return CarbonImmutable::parse($updatedAt, config('app.timezone'))
            ->setTimezone($user->timezone ?: config('app.timezone'));
    }

    private function goalProgressCount(User $user, CarbonImmutable $date): int
    {
        return GoalProgressEntry::where('user_id', $user->id)
            ->whereDate('recorded_at', $date->toDateString())
            ->count();
    }
}
