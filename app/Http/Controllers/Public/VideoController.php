<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Video;
use Illuminate\View\View;

/**
 * Public video listing. Read-only, no authorization — only active
 * videos, in the shared priority order (Video::scopeOrdered()).
 */
class VideoController extends Controller
{
    public function index(): View
    {
        $videos = Video::query()->active()->ordered()->paginate(12);

        return view('public.videos.index', [
            'videos' => $videos,
        ]);
    }
}
