<?php

namespace App\View\Composers;

use App\Services\ContentPage\ContentPageService;
use Illuminate\View\View;

/**
 * Shares the currently-active content pages with the public footer
 * (Phase 3.46) — bound only to layouts.partials.public-footer, so this
 * is the one query per request, never repeated per page. See
 * ContentPageService for the (small, defensively-bounded) cache this
 * reads through.
 */
class ContentPageFooterComposer
{
    public function __construct(private readonly ContentPageService $contentPages) {}

    public function compose(View $view): void
    {
        $view->with('footerContentPages', $this->contentPages->activeFooterPages());
    }
}
