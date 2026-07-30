<?php

namespace App\Models;

use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id', 'habit_id', 'name', 'description', 'notes',
    'type', 'status', 'priority', 'icon', 'color',
    'start_date', 'due_date',
    'initial_value', 'target_value', 'unit', 'settings',
    'is_private', 'reminder_enabled', 'reminder_date',
    'completed_at', 'archived_at', 'cancelled_at',
])]
class Goal extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => GoalType::class,
            'status' => GoalStatus::class,
            'priority' => GoalPriority::class,
            'start_date' => 'date',
            'due_date' => 'date',
            'initial_value' => 'decimal:2',
            'target_value' => 'decimal:2',
            'settings' => 'array',
            'is_private' => 'boolean',
            'reminder_enabled' => 'boolean',
            'reminder_date' => 'date',
            'completed_at' => 'datetime',
            'archived_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function habit(): BelongsTo
    {
        return $this->belongsTo(Habit::class);
    }

    public function progressEntries(): HasMany
    {
        return $this->hasMany(GoalProgressEntry::class);
    }

    public function scopeStatus($query, GoalStatus $status)
    {
        return $query->where('status', $status);
    }
}
