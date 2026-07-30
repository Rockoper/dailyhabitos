<?php

namespace App\Services\History;

use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * Agregado de un día para la línea de tiempo: sus eventos ya ordenados más
 * el resumen de cumplimiento de ese día, reutilizado de `CalendarService`
 * (no recalculado por tarjeta).
 */
final readonly class DailyHistorySummary
{
    /**
     * @param  Collection<int, HistoryEvent>  $events
     */
    public function __construct(
        public CarbonImmutable $date,
        public bool $isToday,
        public bool $isYesterday,
        public int $expectedHabits,
        public int $completedHabits,
        public ?float $percentage,
        public string $dayLevel,
        public Collection $events,
    ) {}

    public function dayLabel(): string
    {
        return match ($this->dayLevel) {
            'completed' => 'Día perfecto',
            'partial' => sprintf('Parcial · %d/%d', $this->completedHabits, $this->expectedHabits),
            'pending' => 'Sin hábitos completados',
            default => 'Sin hábitos programados',
        };
    }
}
