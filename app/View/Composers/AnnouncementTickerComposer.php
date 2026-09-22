<?php

namespace App\View\Composers;

use App\Models\Announcement;
use Illuminate\View\View;

/**
 * The one retrieval path for the public ticker's currently-active
 * announcements (Phase 3.45) — bound only to
 * layouts.partials.announcement-ticker (the ticker's own view, included
 * once from layouts.public), so this query never runs on admin/guest/
 * maintenance pages, which never include that partial at all.
 *
 * No cache: the announcements table is small and a stale cache could
 * keep a future announcement hidden past starts_at or an expired one
 * visible past ends_at — a bug worse than the query cost it would
 * avoid (see Announcement::scopeActive()).
 */
class AnnouncementTickerComposer
{
    public function compose(View $view): void
    {
        $view->with(
            'activeAnnouncements',
            Announcement::active()->orderBy('sort_order')->orderBy('id')->get(['message'])
        );
    }
}
