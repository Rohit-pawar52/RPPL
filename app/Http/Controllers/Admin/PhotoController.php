<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Photo\StorePhotoRequest;
use App\Http\Requests\Admin\Photo\UpdatePhotoRequest;
use App\Models\Photo;
use App\Services\Photo\PhotoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Admin CRUD for the public photo gallery. Mirrors VideoController; all
 * file handling goes through PhotoService.
 */
class PhotoController extends Controller
{
    public function __construct(private readonly PhotoService $photos) {}

    public function index(): View
    {
        $this->authorize('viewAny', Photo::class);

        return view('admin.photos.index', [
            'photos' => Photo::query()->ordered()->paginate(20),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Photo::class);

        return view('admin.photos.create');
    }

    public function store(StorePhotoRequest $request): RedirectResponse
    {
        $this->authorize('create', Photo::class);

        $this->photos->createPhoto($request->safe()->except(['photo']), $request->file('photo'));

        return redirect()
            ->route('admin.photos.index')
            ->with('success', __('Photo uploaded successfully.'));
    }

    public function edit(Photo $photo): View
    {
        $this->authorize('update', $photo);

        return view('admin.photos.edit', ['photo' => $photo]);
    }

    public function update(UpdatePhotoRequest $request, Photo $photo): RedirectResponse
    {
        $this->authorize('update', $photo);

        $this->photos->updatePhoto($photo, $request->safe()->except(['photo']), $request->file('photo'));

        return redirect()
            ->route('admin.photos.index')
            ->with('success', __('Photo updated successfully.'));
    }

    /**
     * One-click Active/Inactive from the table — flips the current
     * value server-side, so no client-supplied status is ever trusted.
     */
    public function toggleStatus(Photo $photo): RedirectResponse
    {
        $this->authorize('update', $photo);

        $this->photos->toggleStatus($photo);

        return redirect()
            ->back(fallback: route('admin.photos.index'))
            ->with('success', $photo->status === 'active' ? __('Photo activated successfully.') : __('Photo deactivated successfully.'));
    }

    public function destroy(Photo $photo): RedirectResponse
    {
        $this->authorize('delete', $photo);

        $this->photos->deletePhoto($photo);

        return redirect()
            ->route('admin.photos.index')
            ->with('success', __('Photo deleted successfully.'));
    }
}
