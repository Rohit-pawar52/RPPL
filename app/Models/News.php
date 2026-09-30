<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

/**
 * A tournament news post: a title, plain-text content (always escaped on
 * output) and zero or more images. The first image by sort_order acts as
 * the cover. Public only while status is active AND published_at has
 * passed (see scopeVisible()).
 */
class News extends Model
{
    use HasFactory;

    public const STATUSES = ['active', 'inactive'];

    public const MAX_IMAGES = 10;

    protected $table = 'news';

    protected $fillable = [
        'title',
        'slug',
        'content',
        'status',
        'priority',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    public function images(): HasMany
    {
        return $this->hasMany(NewsImage::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * The first image in display order — used as the listing thumbnail.
     */
    public function coverImage(): HasOne
    {
        return $this->hasOne(NewsImage::class)->ofMany(['sort_order' => 'min', 'id' => 'min']);
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', 'active')->where('published_at', '<=', now());
    }

    /**
     * priority ASC (lower first, like videos/photos), then newest
     * published first, then newest id as the deterministic tiebreak.
     */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('priority')->orderByDesc('published_at')->orderByDesc('id');
    }

    public function excerpt(int $limit = 180): string
    {
        return Str::limit(trim(preg_replace('/\s+/u', ' ', $this->content)), $limit);
    }
}
