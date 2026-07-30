<?php

namespace App\Services\Habits;

use App\Enums\FrequencyType;
use App\Models\Habit;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Calcula, para un hábito y un rango de fechas, el conjunto de "fechas esperadas"
 * (o "semanas esperadas" para hábitos weekly_count) según su frecuencia.
 * Ver docs/ARCHITECTURE.md §5.1.
 */
class ScheduleResolver
{
    /**
     * @return Collection<int, CarbonImmutable> fechas esperadas, orden ascendente
     */
    public function expectedDates(Habit $habit, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        [$from, $to] = $this->clampRange($habit, $from, $to);

        if ($from === null) {
            return collect();
        }

        $dates = collect();
        $cursor = $from;

        while ($cursor->lte($to)) {
            if ($this->isExpected($habit, $cursor)) {
                $dates->push($cursor);
            }
            $cursor = $cursor->addDay();
        }

        return $dates;
    }

    public function isExpected(Habit $habit, CarbonImmutable $date): bool
    {
        $startDate = CarbonImmutable::parse($habit->start_date)->startOfDay();
        if ($date->lt($startDate)) {
            return false;
        }

        if ($habit->end_date && $date->gt(CarbonImmutable::parse($habit->end_date)->startOfDay())) {
            return false;
        }

        return match ($habit->frequency_type) {
            FrequencyType::Daily, FrequencyType::WeeklyCount => true,
            FrequencyType::SpecificDays => in_array(
                $date->isoWeekday(),
                $habit->frequency_config['days'] ?? [],
                true
            ),
            FrequencyType::Interval => $this->matchesInterval($habit, $date, $startDate),
        };
    }

    /**
     * @return Collection<int, array{start: CarbonImmutable, end: CarbonImmutable}> semanas ascendentes
     */
    public function expectedWeeks(Habit $habit, CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        [$from, $to] = $this->clampRange($habit, $from, $to);

        if ($from === null) {
            return collect();
        }

        $weeks = collect();
        $cursor = $from->startOfWeek(CarbonImmutable::MONDAY);
        $lastWeekStart = $to->startOfWeek(CarbonImmutable::MONDAY);

        while ($cursor->lte($lastWeekStart)) {
            $weeks->push([
                'start' => $cursor,
                'end' => $cursor->endOfWeek(CarbonImmutable::SUNDAY)->startOfDay(),
            ]);
            $cursor = $cursor->addWeek();
        }

        return $weeks;
    }

    /**
     * @return array{0: ?CarbonImmutable, 1: CarbonImmutable}
     */
    private function clampRange(Habit $habit, CarbonImmutable $from, CarbonImmutable $to): array
    {
        $from = $from->startOfDay();
        $to = $to->startOfDay();

        $startDate = CarbonImmutable::parse($habit->start_date)->startOfDay();
        if ($from->lt($startDate)) {
            $from = $startDate;
        }

        if ($habit->end_date) {
            $endDate = CarbonImmutable::parse($habit->end_date)->startOfDay();
            if ($to->gt($endDate)) {
                $to = $endDate;
            }
        }

        if ($from->gt($to)) {
            return [null, $to];
        }

        return [$from, $to];
    }

    private function matchesInterval(Habit $habit, CarbonImmutable $date, CarbonImmutable $startDate): bool
    {
        $everyN = max(1, (int) ($habit->frequency_config['every_n_days'] ?? 1));
        $diff = $startDate->diffInDays($date);

        return $diff % $everyN === 0;
    }
}
