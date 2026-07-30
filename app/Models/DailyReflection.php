<?php

namespace App\Models;

use App\Enums\ReflectionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'reflection_date', 'status',
    'mood', 'energy_level', 'productivity_level',
    'went_well', 'challenges', 'learned', 'gratitude', 'improve_tomorrow', 'tomorrow_priority',
    'free_notes', 'tags', 'completed_at',
])]
class DailyReflection extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'reflection_date' => 'date',
            'status' => ReflectionStatus::class,
            'mood' => 'integer',
            'energy_level' => 'integer',
            'productivity_level' => 'integer',
            'tags' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
