<?php

namespace App\Services\Reflections;

use App\Enums\ReflectionStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Observaciones deterministas sobre las reflexiones del usuario (sin IA
 * externa): comparaciones aritméticas simples sobre el historial reciente.
 * Cada observación exige un mínimo de datos propio antes de mostrarse, para
 * no presentar conclusiones con muestras insuficientes.
 */
class ReflectionInsightService
{
    private const MIN_TOTAL_REFLECTIONS = 3;

    /**
     * @return array{insights: array<int, string>, has_enough_data: bool}
     */
    public function forUser(User $user, CarbonImmutable $referenceDate): array
    {
        $reflections = $user->dailyReflections()
            ->whereDate('reflection_date', '<=', $referenceDate->toDateString())
            ->orderByDesc('reflection_date')
            ->limit(90)
            ->get()
            ->sortBy('reflection_date')
            ->values();

        if ($reflections->count() < self::MIN_TOTAL_REFLECTIONS) {
            return ['insights' => [], 'has_enough_data' => false];
        }

        $insights = array_filter([
            $this->moodTrend($reflections),
            $this->exerciseEnergyCorrelation($reflections),
            $this->weeklyProductivityAverage($reflections, $referenceDate),
            $this->writingStreak($reflections, $referenceDate),
            $this->weekdayPattern($reflections),
        ]);

        return ['insights' => array_values($insights), 'has_enough_data' => true];
    }

    private function moodTrend(Collection $reflections): ?string
    {
        $withMood = $reflections->filter(fn ($r) => $r->mood !== null)->values();
        if ($withMood->count() < 3) {
            return null;
        }

        $lastThree = $withMood->slice(-3)->values();
        $improving = $lastThree[0]->mood < $lastThree[1]->mood && $lastThree[1]->mood < $lastThree[2]->mood;

        return $improving ? 'Tu estado de ánimo ha mejorado durante los últimos tres días registrados.' : null;
    }

    private function exerciseEnergyCorrelation(Collection $reflections): ?string
    {
        $withEnergy = $reflections->filter(fn ($r) => $r->energy_level !== null);
        $withTag = $withEnergy->filter(fn ($r) => in_array('Ejercicio', $r->tags ?? [], true));
        $withoutTag = $withEnergy->reject(fn ($r) => in_array('Ejercicio', $r->tags ?? [], true));

        if ($withTag->count() < 3 || $withoutTag->count() < 3) {
            return null;
        }

        $avgWith = $withTag->avg('energy_level');
        $avgWithout = $withoutTag->avg('energy_level');

        return $avgWith - $avgWithout >= 0.5
            ? 'Los días que etiquetas como "Ejercicio" sueles reportar mayor energía.'
            : null;
    }

    private function weeklyProductivityAverage(Collection $reflections, CarbonImmutable $referenceDate): ?string
    {
        $weekStart = $referenceDate->subDays(6);
        $thisWeek = $reflections->filter(
            fn ($r) => $r->productivity_level !== null && $r->reflection_date->betweenIncluded($weekStart, $referenceDate)
        );

        if ($thisWeek->count() < 3) {
            return null;
        }

        $average = round($thisWeek->avg('productivity_level'), 1);

        return sprintf('Tu productividad percibida promedio esta semana es %s de 5.', rtrim(rtrim((string) $average, '0'), '.'));
    }

    private function writingStreak(Collection $reflections, CarbonImmutable $referenceDate): ?string
    {
        $byDate = $reflections->keyBy(fn ($r) => $r->reflection_date->format('Y-m-d'));

        $streak = 0;
        $cursor = $referenceDate;
        while ($byDate->has($cursor->format('Y-m-d'))) {
            $streak++;
            $cursor = $cursor->subDay();
        }

        if ($streak < 3) {
            return null;
        }

        return sprintf('Has escrito reflexiones durante %d días consecutivos.', $streak);
    }

    private function weekdayPattern(Collection $reflections): ?string
    {
        $withMood = $reflections->filter(fn ($r) => $r->mood !== null);
        if ($withMood->count() < 7) {
            return null;
        }

        $byWeekday = $withMood->groupBy(fn ($r) => $r->reflection_date->dayOfWeekIso)
            ->filter(fn (Collection $group) => $group->count() >= 2);

        if ($byWeekday->count() < 3) {
            return null;
        }

        $best = $byWeekday->sortByDesc(fn (Collection $group) => $group->avg('mood'))->keys()->first();

        $names = [1 => 'lunes', 2 => 'martes', 3 => 'miércoles', 4 => 'jueves', 5 => 'viernes', 6 => 'sábado', 7 => 'domingo'];

        return sprintf('Tu mejor estado de ánimo suele darse los %s.', $names[$best]);
    }
}
