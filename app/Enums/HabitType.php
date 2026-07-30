<?php

namespace App\Enums;

enum HabitType: string
{
    case Binary = 'binary';
    case Quantity = 'quantity';
    case Weekly = 'weekly';
    case Abstinence = 'abstinence';

    public function label(): string
    {
        return match ($this) {
            self::Binary => 'Binario (cumplido o no)',
            self::Quantity => 'Cantidad',
            self::Weekly => 'Semanal (N veces por semana)',
            self::Abstinence => 'Abstinencia',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Binary => 'Se marca como cumplido o no cumplido cada día esperado.',
            self::Quantity => 'Se registra un valor numérico contra una meta diaria.',
            self::Weekly => 'Se debe cumplir una cantidad de veces determinada dentro de la semana.',
            self::Abstinence => 'Se registra una racha sin realizar una conducta.',
        };
    }
}
