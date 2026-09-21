<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\ContentPage;
use App\Support\MarkdownRenderer;
use Illuminate\View\View;

/**
 * One action serving all three fixed public pages (Phase 3.46) —
 * routes/web.php gives each of /privacy-policy, /terms-and-conditions,
 * /faqs its own explicit route, each binding its own fixed `type` via
 * Route::defaults(), rather than a generic /pages/{anything} that would
 * accept an arbitrary type from the URL. An inactive (or nonexistent —
 * never possible for a canonical type, but defensively handled the
 * same way) page 404s outright; there is no "this page is disabled"
 * placeholder.
 */
class ContentPageController extends Controller
{
    public function show(string $type): View
    {
        $page = ContentPage::query()->active()->where('type', $type)->first();

        abort_unless($page, 404);

        return view('public.content-page', [
            'title' => $page->title,
            'content' => MarkdownRenderer::toSafeHtml($page->content),
        ]);
    }
}
