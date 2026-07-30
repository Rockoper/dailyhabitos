<?php

namespace App\Enums;

enum LogStatus: string
{
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Partial = 'partial';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Cumplido',
            self::Failed => 'Fallado',
            self::Skipped => 'Omitido',
            self::Partial => 'Parcial',
        };
    }
}
