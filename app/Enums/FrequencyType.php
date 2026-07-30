<?php

namespace App\Enums;

enum FrequencyType: string
{
    case Daily = 'daily';
    case SpecificDays = 'specific_days';
    case Interval = 'interval';
    case WeeklyCount = 'weekly_count';

    public function label(): string
    {
        return match ($this) {
            self::Daily => 'Todos los días',
            self::SpecificDays => 'Días específicos de la semana',
            self::Interval => 'Cada N días',
            self::WeeklyCount => 'N veces por semana',
        };
    }
}
