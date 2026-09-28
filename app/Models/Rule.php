<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single rule/regulation entry under a RuleType. image_path is a
 * storage path on the 'public' disk (see RuleService), never binary
 * data — at most one image per rule, no gallery. Publicly visible only
 * when BOTH this row's status is 'active' AND its parent RuleType is
 * active (scopeActive() only covers this row's own status; callers
 * building the public page filter by an already-active RuleType, so
 * the two checks are never duplicated in one query).
 */
class Rule extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'rule_type_id',
        'title',
        'content',
        'image_path',
        'sort_order',
        'status',
        'is_important',
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_important' => 'boolean',
        ];
    }

    public function ruleType(): BelongsTo
    {
        return $this->belongsTo(RuleType::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * sort_order ASC then id ASC — ordering is only meaningful within a
     * single rule_type_id, never compared across types.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('id');
    }
}
