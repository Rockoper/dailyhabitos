<?php

namespace App\Enums;

enum HabitUnit: string
{
    case Minutes = 'minutes';
    case Hours = 'hours';
    case Pages = 'pages';
    case Kilometers = 'kilometers';
    case Glasses = 'glasses';
    case Repetitions = 'repetitions';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Minutes => 'Minutos',
            self::Hours => 'Horas',
            self::Pages => 'Páginas',
            self::Kilometers => 'Kilómetros',
            self::Glasses => 'Vasos',
            self::Repetitions => 'Repeticiones',
            self::Other => 'Otra',
        };
    }
}
