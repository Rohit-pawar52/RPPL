<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A short admin-uploaded RPPL clip (promo/highlight/announcement/etc.)
 * shown on the public homepage's Featured Videos section. video_path/
 * thumbnail_path are storage paths on the 'public' disk (see
 * VideoService), never binary data. Ordering is priority ASC, id DESC
 * as the deterministic tiebreak — see scopeActive()/the videos
 * migration's own comment on why priority is a plain, non-unique int.
 */
class Video extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'inactive'];

    protected $fillable = [
        'title',
        'description',
        'video_path',
        'thumbnail_path',
        'status',
        'priority',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * priority ASC (lower = shown first) then newest first among ties —
     * the one ordering rule every public/admin listing shares.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderByDesc('id');
    }
}
