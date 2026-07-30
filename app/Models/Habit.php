<?php

namespace App\Models;

use App\Enums\FrequencyType;
use App\Enums\HabitType;
use App\Enums\HabitUnit;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable([
    'category_id', 'name', 'description', 'icon', 'color',
    'type', 'frequency_type', 'frequency_config',
    'target_quantity', 'unit', 'unit_custom_label',
    'start_date', 'end_date',
    'never_fail_twice', 'is_private',
    'remind_at_enabled', 'remind_at',
    'is_archived', 'archived_at', 'position',
])]
class Habit extends Model
{
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'type' => HabitType::class,
            'frequency_type' => FrequencyType::class,
            'frequency_config' => 'array',
            'unit' => HabitUnit::class,
            'target_quantity' => 'decimal:2',
            'start_date' => 'date',
            'end_date' => 'date',
            'never_fail_twice' => 'boolean',
            'is_private' => 'boolean',
            'remind_at_enabled' => 'boolean',
            'is_archived' => 'boolean',
            'archived_at' => 'datetime',
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

    public function logs(): HasMany
    {
        return $this->hasMany(HabitLog::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_archived', false);
    }

    public function scopeArchived($query)
    {
        return $query->where('is_archived', true);
    }

    public function displayUnit(): ?string
    {
        if ($this->unit === null) {
            return null;
        }

        return $this->unit === HabitUnit::Other
            ? ($this->unit_custom_label ?: HabitUnit::Other->label())
            : $this->unit->label();
    }
}
