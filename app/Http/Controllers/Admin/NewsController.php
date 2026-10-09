<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\News\StoreNewsRequest;
use App\Http\Requests\Admin\News\UpdateNewsRequest;
use App\Models\News;
use App\Services\News\NewsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin CRUD for news posts. Mirrors PhotoController; all file handling
 * goes through NewsService.
 */
class NewsController extends Controller
{
    public function __construct(private readonly NewsService $news) {}

    public function index(): View
    {
        $this->authorize('viewAny', News::class);

        return view('admin.news.index', [
            'newsItems' => News::query()->with('coverImage')->withCount('images')->ordered()->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', News::class);

        return view('admin.news.create');
    }

    public function store(StoreNewsRequest $request): RedirectResponse
    {
        $this->authorize('create', News::class);

        $this->news->createNews(
            $request->safe()->except(['images']),
            $request->file('images', []),
        );

        return redirect()
            ->route('admin.news.index')
            ->with('success', __('News created successfully.'));
    }

    public function edit(News $news): View
    {
        $this->authorize('update', $news);

        return view('admin.news.edit', [
            'news' => $news->load('images'),
        ]);
    }

    public function update(UpdateNewsRequest $request, News $news): RedirectResponse
    {
        $this->authorize('update', $news);

        $this->news->updateNews(
            $news,
            $request->safe()->except(['images', 'remove_images']),
            $request->file('images', []),
            $request->validated('remove_images', []),
        );

        return redirect()
            ->route('admin.news.index')
            ->with('success', __('News updated successfully.'));
    }

    /**
     * One-click Active/Inactive from the table — flips the current value
     * server-side, so no client-supplied status is ever trusted.
     */
    public function toggleStatus(News $news): RedirectResponse
    {
        $this->authorize('update', $news);

        $this->news->toggleStatus($news);

        return redirect()
            ->back(fallback: route('admin.news.index'))
            ->with('success', $news->status === 'active' ? __('News activated successfully.') : __('News deactivated successfully.'));
    }

    public function destroy(News $news): RedirectResponse
    {
        $this->authorize('delete', $news);

        $this->news->deleteNews($news);

        return redirect()
            ->route('admin.news.index')
            ->with('success', __('News deleted successfully.'));
    }
}
