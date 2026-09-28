<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A rule category (e.g. "Cricket Rules", "RPPL Specific Rules") — kept
 * as its own normalized table rather than a hardcoded enum specifically
 * so a future category (Code of Conduct, Registration Rules, ...) is a
 * plain Admin-created row, never a migration/deploy. Deactivating a
 * type hides every one of its Rules from the public page regardless of
 * each Rule's own status (see scopeActive() on Rule) — individual Rule
 * rows are never mutated when their type is deactivated.
 */
class RuleType extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * sort_order ASC then id ASC as a deterministic tiebreak — the one
     * ordering rule every public/admin listing shares.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
