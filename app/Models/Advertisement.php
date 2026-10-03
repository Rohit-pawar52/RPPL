<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * A sponsor shown on the public website: an image or a short video that is
 * only displayed (no link, no click). The tier decides the slot:
 *
 *  - main   — one premium sponsor, a fixed banner at the top of the page;
 *  - normal — banner in the middle of the page, rotating between all
 *             live Normal ads (a higher weight is picked more often);
 *  - mini   — small logo in the "Our sponsors" strip at the bottom
 *             (image only, all shown together).
 *
 * media_path/poster_path are paths on the 'public' disk (see
 * AdvertisementService). starts_on / ends_on are calendar dates in the
 * display timezone and both optional.
 */
class Advertisement extends Model
{
    use HasFactory;

    public const TIER_MAIN = 'main';

    public const TIER_NORMAL = 'normal';

    public const TIER_MINI = 'mini';

    public const TIERS = [
        self::TIER_MAIN => 'Main sponsor',
        self::TIER_NORMAL => 'Normal sponsor',
        self::TIER_MINI => 'Mini sponsor',
    ];

    public const MEDIA_IMAGE = 'image';

    public const MEDIA_VIDEO = 'video';

    public const MEDIA_TYPES = [self::MEDIA_IMAGE, self::MEDIA_VIDEO];

    public const STATUSES = ['active', 'inactive'];

    public const MAX_WEIGHT = 10;

    protected $fillable = [
        'title',
        'tier',
        'media_type',
        'media_path',
        'poster_path',
        'status',
        'weight',
        'starts_on',
        'ends_on',
    ];

    protected function casts(): array
    {
        return [
            'weight' => 'integer',
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    /**
     * Active and inside its date window on the given calendar day (a
     * 'Y-m-d' string in the display timezone). A missing start/end date
     * means no limit on that side.
     */
    public function scopeLiveOn(Builder $query, string $date): Builder
    {
        return $query->active()
            ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $date))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date));
    }

    public function isVideo(): bool
    {
        return $this->media_type === self::MEDIA_VIDEO;
    }

    public function mediaUrl(): string
    {
        return Storage::disk('public')->url($this->media_path);
    }

    public function posterUrl(): ?string
    {
        return $this->poster_path ? Storage::disk('public')->url($this->poster_path) : null;
    }

    public function tierLabel(): string
    {
        return self::TIERS[$this->tier] ?? ucfirst($this->tier);
    }

    /**
     * Human text for the admin list: "Always", "From 05 Oct 2026",
     * "Until 20 Oct 2026" or "05 Oct 2026 – 20 Oct 2026".
     */
    public function scheduleLabel(): string
    {
        $start = $this->starts_on?->format('d M Y');
        $end = $this->ends_on?->format('d M Y');

        return match (true) {
            $start && $end => $start.' – '.$end,
            (bool) $start => 'From '.$start,
            (bool) $end => 'Until '.$end,
            default => 'Always',
        };
    }
}
