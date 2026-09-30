<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\News;
use Illuminate\View\View;

/**
 * Public news listing and detail. Read-only, no authorization — only
 * active news whose published_at has passed (News::scopeVisible()). A
 * hidden item's detail URL is a plain 404, same as an unknown slug.
 */
class NewsController extends Controller
{
    public function index(): View
    {
        return view('public.news.index', [
            'newsItems' => News::query()->visible()->with('coverImage')->ordered()->paginate(9),
        ]);
    }

    public function show(string $slug): View
    {
        $news = News::query()->visible()->with('images')->where('slug', $slug)->firstOrFail();

        return view('public.news.show', ['news' => $news]);
    }
}
