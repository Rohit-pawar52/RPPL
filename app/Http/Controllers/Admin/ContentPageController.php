<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ContentPage\UpdateContentPageRequest;
use App\Models\ContentPage;
use App\Services\ContentPage\ContentPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * A single admin screen managing all three fixed content pages (Phase
 * 3.46) — no create/delete: the canonical rows always exist via
 * DemoContentPageSeeder, and this controller only ever reads/updates
 * them. Gated behind "manage-tournament", the same broad admin-only
 * Gate ReportsController/DataCleanupController/SettingsController use
 * for a non-Policy, non-resource admin page.
 */
class ContentPageController extends Controller
{
    public function __construct(private readonly ContentPageService $contentPages) {}

    public function index(Request $request): View
    {
        $this->authorize('manage-tournament');

        $tab = $request->query('tab', ContentPage::TYPE_PRIVACY_POLICY);

        if (! in_array($tab, ContentPage::TYPES, true)) {
            $tab = ContentPage::TYPE_PRIVACY_POLICY;
        }

        return view('admin.content-pages.index', [
            'activeTab' => $tab,
            'pages' => ContentPage::query()->get()->keyBy('type'),
        ]);
    }

    /**
     * `type` is never read from the request here — the record being
     * updated is entirely determined by the {content_page} route-model
     * binding, and the write payload only ever contains title/content/
     * is_active (see UpdateContentPageRequest's own docblock).
     */
    public function update(UpdateContentPageRequest $request, ContentPage $contentPage): RedirectResponse
    {
        $this->authorize('manage-tournament');

        $data = $request->validated();

        $contentPage->update([
            'title' => $data['title'],
            'content' => $data['content'] ?? null,
            'is_active' => $request->boolean('is_active'),
        ]);

        $this->contentPages->flush();

        return redirect()
            ->route('admin.content-pages.index', ['tab' => $contentPage->type])
            ->with('success', $contentPage->title.' updated successfully.');
    }
}
