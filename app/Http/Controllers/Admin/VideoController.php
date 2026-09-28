<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Video\StoreVideoRequest;
use App\Http\Requests\Admin\Video\UpdateVideoRequest;
use App\Models\Video;
use App\Services\Video\VideoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin CRUD for short RPPL clips shown on the public homepage's
 * Featured Videos section. All file storage/replacement/cleanup goes
 * through VideoService — this controller never touches Storage directly.
 * Activate/deactivate is simply the `status` field on the edit form; there
 * is deliberately no separate toggle route.
 */
class VideoController extends Controller
{
    public function __construct(private readonly VideoService $videos) {}

    public function index(): View
    {
        $this->authorize('viewAny', Video::class);

        $videos = Video::query()
            ->ordered()
            ->paginate(20);

        return view('admin.videos.index', [
            'videos' => $videos,
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Video::class);

        return view('admin.videos.create');
    }

    public function store(StoreVideoRequest $request): RedirectResponse
    {
        $this->authorize('create', Video::class);

        $this->videos->createVideo(
            $request->safe()->except(['video', 'thumbnail']),
            $request->file('video'),
            $request->file('thumbnail'),
        );

        return redirect()
            ->route('admin.videos.index')
            ->with('success', 'Video uploaded successfully.');
    }

    public function edit(Video $video): View
    {
        $this->authorize('update', $video);

        return view('admin.videos.edit', [
            'video' => $video,
        ]);
    }

    /**
     * The video and thumbnail files are both optional here — when not
     * re-uploaded, VideoService leaves the existing stored paths as-is.
     */
    public function update(UpdateVideoRequest $request, Video $video): RedirectResponse
    {
        $this->authorize('update', $video);

        $this->videos->updateVideo(
            $video,
            $request->safe()->except(['video', 'thumbnail']),
            $request->file('video'),
            $request->file('thumbnail'),
        );

        return redirect()
            ->route('admin.videos.index')
            ->with('success', 'Video updated successfully.');
    }

    public function destroy(Video $video): RedirectResponse
    {
        $this->authorize('delete', $video);

        $this->videos->deleteVideo($video);

        return redirect()
            ->route('admin.videos.index')
            ->with('success', 'Video deleted successfully.');
    }
}
