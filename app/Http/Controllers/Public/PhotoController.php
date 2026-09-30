<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Photo;
use Illuminate\View\View;

/**
 * Public photo gallery. Read-only, no authorization — only active photos,
 * in the shared priority order (Photo::scopeOrdered()).
 */
class PhotoController extends Controller
{
    public function index(): View
    {
        return view('public.photos.index', [
            'photos' => Photo::query()->active()->ordered()->paginate(12),
        ]);
    }
}
