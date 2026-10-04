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
 *             (image only, all shown together);
 *  - auction — one sponsor for the pop-up on the player auction page (the
 *             Main sponsor is shown there until one is added).
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

    public const TIER_AUCTION = 'auction';

    public const TIERS = [
        self::TIER_MAIN => 'Main sponsor',
        self::TIER_AUCTION => 'Auction sponsor',
        self::TIER_NORMAL => 'Normal sponsor',
        self::TIER_MINI => 'Mini sponsor',
    ];

    /**
     * Levels with a single slot: two active ads of one of these may not be
     * live on the same day.
     */
    public const SINGLE_SLOT_TIERS = [self::TIER_MAIN, self::TIER_AUCTION];

    public const FORMAT_BANNER = 'banner';

    public const FORMAT_CARD = 'card';

    /**
     * Normal sponsors only: the spot they show in.
     */
    public const FORMATS = [
        self::FORMAT_BANNER => 'Banner (above the season summary)',
        self::FORMAT_CARD => 'Card (in the match row)',
    ];

    /**
     * Every spot an ad can show in, with the picture size that fits it
     * (the admin form shows this). The strip is about 88 px tall on a
     * desktop (72 px on a phone) and up to 1100 px wide, so 8:1 suits it;
     * a match-row card's picture area is 272 x 104 px, so 2.6:1.
     *
     * @var array<string, array{label: string, where: string, size: string, ratio: string, note: string}>
     */
    public const SPOTS = [
        'main' => [
            'label' => 'Main sponsor — top banner',
            'where' => 'A slim strip at the very top of the homepage, full width. Shown first, folds away after a few seconds and comes back. It also pops up on the player auction page until an Auction sponsor is added.',
            'size' => '1600 × 200 px',
            'ratio' => '8 : 1',
            'note' => 'Keep the text and logo in the middle; on a phone the strip is narrower and the picture shrinks to fit. A narrower picture still works — the sides are filled with a blurred copy of it.',
        ],
        'auction' => [
            'label' => 'Auction sponsor — pop-up',
            'where' => 'A pop-up on the player auction page (the live page, its big screen and the results). It opens by itself a little after the page loads, stays a few seconds and comes back every couple of minutes. A video plays muted while it is open. With no Auction sponsor live, the Main sponsor is shown there instead.',
            'size' => '1600 × 360 px',
            'ratio' => '4.4 : 1',
            'note' => 'Keep the text and logo in the middle. The pop-up is about as wide as a tablet, so a wider strip such as the 8 : 1 banner also works — the empty space above and below is filled with a blurred copy of the picture.',
        ],
        'normal-banner' => [
            'label' => 'Normal sponsor — banner',
            'where' => 'The same kind of strip, above the season summary on the homepage. Normal banners take turns on every page load.',
            'size' => '1600 × 200 px',
            'ratio' => '8 : 1',
            'note' => 'Same size as the Main banner.',
        ],
        'normal-card' => [
            'label' => 'Normal sponsor — card',
            'where' => 'A card as wide as a match card, between the upcoming matches and the results in the scrolling match row. The ad title is shown under the picture.',
            'size' => '1040 × 400 px',
            'ratio' => '2.6 : 1',
            'note' => 'Only the picture area is this shape; the title and "Sponsored" label sit below it.',
        ],
        'mini' => [
            'label' => 'Mini sponsor — logo',
            'where' => 'A small logo in the "Our sponsors" strip at the bottom of the homepage (images only). It is shown at most 128 px wide and 48 px tall.',
            'size' => '400 × 150 px',
            'ratio' => '8 : 3',
            'note' => 'Use a PNG with a transparent background so the logo sits cleanly on the white strip.',
        ],
    ];

    public const MEDIA_IMAGE = 'image';

    public const MEDIA_VIDEO = 'video';

    public const MEDIA_TYPES = [self::MEDIA_IMAGE, self::MEDIA_VIDEO];

    public const STATUSES = ['active', 'inactive'];

    public const MAX_WEIGHT = 10;

    protected $fillable = [
        'title',
        'tier',
        'format',
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

    /**
     * Which key of SPOTS this ad's picture is for.
     */
    public function spotKey(): string
    {
        return match ($this->tier) {
            self::TIER_MAIN => 'main',
            self::TIER_AUCTION => 'auction',
            self::TIER_MINI => 'mini',
            default => $this->format === self::FORMAT_CARD ? 'normal-card' : 'normal-banner',
        };
    }

    public function spotLabel(): string
    {
        return self::SPOTS[$this->spotKey()]['label'];
    }

    /**
     * Normal sponsors: 'banner' unless the admin chose 'card' (an empty
     * value is a banner — see the migration).
     */
    public function effectiveFormat(): string
    {
        return $this->format === self::FORMAT_CARD ? self::FORMAT_CARD : self::FORMAT_BANNER;
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
