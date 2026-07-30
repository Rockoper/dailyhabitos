<?php

namespace App\Enums;

/**
 * Estados persistidos de un objetivo. "Vencido" NO es un valor de este enum:
 * se deriva en tiempo real (fecha límite pasada + meta no alcanzada mientras
 * status = Active) — ver `App\Services\Goals\GoalProgressCalculator`. Esto
 * evita un job programado que recorra todos los objetivos para marcarlos.
 */
enum GoalStatus: string
{
    case Active = 'active';
    case Paused = 'paused';
    case Completed = 'completed';
    case Archived = 'archived';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Activo',
            self::Paused => 'Pausado',
            self::Completed => 'Completado',
            self::Archived => 'Archivado',
            self::Cancelled => 'Cancelado',
        };
    }
}
