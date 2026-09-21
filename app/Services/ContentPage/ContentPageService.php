<?php

namespace App\Services\ContentPage;

use App\Models\ContentPage;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * The one retrieval path for "which content pages should the public
 * footer link to" (Phase 3.46) — the footer is included on every
 * public page, so this is the one query worth a small cache. Only
 * title/type are ever selected (the footer needs nothing else).
 *
 * These records change only through an explicit admin save
 * (ContentPageController::update()), which always calls flush()
 * immediately after — unlike Announcements, there's no time-based
 * visibility window to worry about going stale, so a bounded TTL here
 * is purely a defensive backstop, the same pattern SettingsService
 * already established.
 */
class ContentPageService
{
    private const CACHE_KEY = 'content-pages:footer';

    /**
     * @return Collection<int, ContentPage>
     */
    public function activeFooterPages(): Collection
    {
        return Cache::remember(self::CACHE_KEY, now()->addDay(), function () {
            return ContentPage::query()
                ->active()
                ->orderBy('sort_order')
                ->orderBy('id')
                ->get(['type', 'title']);
        });
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
