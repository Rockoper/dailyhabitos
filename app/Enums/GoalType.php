<?php

namespace App\Enums;

enum GoalType: string
{
    case Habit = 'habit';
    case Streak = 'streak';
    case Numeric = 'numeric';
    case Percentage = 'percentage';
    case Deadline = 'deadline';
    case Manual = 'manual';

    public function label(): string
    {
        return match ($this) {
            self::Habit => 'Basado en hábito',
            self::Streak => 'Racha',
            self::Numeric => 'Numérico',
            self::Percentage => 'Porcentual',
            self::Deadline => 'Con fecha límite',
            self::Manual => 'Manual',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Habit => 'Cumplir un hábito una cantidad de veces determinada. Ej. completar Gym 20 veces.',
            self::Streak => 'Alcanzar una racha de días consecutivos en un hábito. Ej. mantener una racha de 30 días.',
            self::Numeric => 'Alcanzar un valor numérico con una unidad propia. Ej. ahorrar 5.000.000 COP, leer 12 libros.',
            self::Percentage => 'Alcanzar un porcentaje de cumplimiento en un periodo. Ej. 90% de cumplimiento mensual.',
            self::Deadline => 'Completar una meta manual antes de una fecha límite. Ej. finalizar un curso.',
            self::Manual => 'Se marca como completado manualmente, sin seguimiento numérico.',
        };
    }

    /**
     * Tipos cuyo progreso se deriva automáticamente (hábitos/rachas/porcentaje/
     * numérico con registros propios) frente a los que solo se completan a mano.
     */
    public function isAutoTrackable(): bool
    {
        return in_array($this, [self::Habit, self::Streak, self::Numeric, self::Percentage], true);
    }

    public function requiresHabit(): bool
    {
        return in_array($this, [self::Habit, self::Streak], true);
    }
}
