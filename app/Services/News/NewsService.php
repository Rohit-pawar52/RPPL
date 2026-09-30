<?php

namespace App\Services\News;

use App\Models\News;
use App\Models\NewsImage;
use App\Services\Settings\DisplayTimezoneFormatter;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Same store-before-DB / clean-up-after pattern as PhotoService, for a
 * News item and its images: new files are stored first and removed again
 * if the DB write fails; files that stop being referenced are only
 * removed once the DB change has committed.
 */
class NewsService
{
    private const IMAGE_DIRECTORY = 'news';

    public function __construct(private readonly DisplayTimezoneFormatter $displayTimezone) {}

    /**
     * @param  array<string, mixed>  $data  title, content, status, priority, published_at (display-timezone string or null)
     * @param  array<int, UploadedFile>  $images
     */
    public function createNews(array $data, array $images = []): News
    {
        $paths = $this->storeImages($images);

        try {
            return DB::transaction(function () use ($data, $paths) {
                $news = News::create([
                    'title' => $data['title'],
                    'slug' => $this->uniqueSlug($data['title']),
                    'content' => $data['content'],
                    'status' => $data['status'],
                    'priority' => $data['priority'],
                    // Blank means "publish now".
                    'published_at' => $this->displayTimezone->parseFromDisplayTimezone($data['published_at'] ?? null) ?? now(),
                ]);

                $this->attachImages($news, $paths, 0);

                return $news;
            });
        } catch (Throwable $e) {
            $this->deleteFiles($paths);

            throw $e;
        }
    }

    /**
     * The slug is deliberately NOT regenerated when the title changes, so
     * links that were already shared keep working.
     *
     * @param  array<int, UploadedFile>  $newImages
     * @param  array<int, int|string>  $removeImageIds
     */
    public function updateNews(News $news, array $data, array $newImages = [], array $removeImageIds = []): News
    {
        $newPaths = $this->storeImages($newImages);
        $removedPaths = [];

        try {
            DB::transaction(function () use ($news, $data, $newPaths, $removeImageIds, &$removedPaths) {
                $attributes = [
                    'title' => $data['title'],
                    'content' => $data['content'],
                    'status' => $data['status'],
                    'priority' => $data['priority'],
                ];

                // Blank keeps the existing publication time.
                if ($published = $this->displayTimezone->parseFromDisplayTimezone($data['published_at'] ?? null)) {
                    $attributes['published_at'] = $published;
                }

                $news->update($attributes);

                // Scoped to this news item, so an id belonging to another
                // item can never be removed through this one.
                $removed = NewsImage::where('news_id', $news->id)->whereIn('id', $removeImageIds)->get();
                foreach ($removed as $image) {
                    $removedPaths[] = $image->image_path;
                    $image->delete();
                }

                $next = (int) (NewsImage::where('news_id', $news->id)->max('sort_order') ?? -1) + 1;
                $this->attachImages($news, $newPaths, $next);
            });
        } catch (Throwable $e) {
            $this->deleteFiles($newPaths);

            throw $e;
        }

        $this->deleteFiles($removedPaths);

        return $news;
    }

    public function deleteNews(News $news): bool
    {
        $paths = NewsImage::where('news_id', $news->id)->pluck('image_path')->all();

        // Image rows go with it (cascade); files only after the row is gone.
        if (! $news->delete()) {
            return false;
        }

        $this->deleteFiles($paths);

        return true;
    }

    public function toggleStatus(News $news): News
    {
        $news->update(['status' => $news->status === 'active' ? 'inactive' : 'active']);

        return $news;
    }

    /**
     * Unique across all news, e.g. "my-title", "my-title-2", "my-title-3".
     * A title with no usable characters falls back to "news".
     */
    public function uniqueSlug(string $title): string
    {
        $base = Str::limit(Str::slug($title), 200, '') ?: 'news';
        $slug = $base;
        $suffix = 2;

        while (News::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$suffix++;
        }

        return $slug;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function attachImages(News $news, array $paths, int $firstSortOrder): void
    {
        foreach ($paths as $offset => $path) {
            $news->images()->create([
                'image_path' => $path,
                'sort_order' => $firstSortOrder + $offset,
            ]);
        }
    }

    /**
     * UploadedFile::store() generates a random filename, never derived
     * from the original name or any user-supplied path. If any file
     * fails to store, the ones already stored are removed again.
     *
     * @param  array<int, UploadedFile>  $images
     * @return array<int, string>
     */
    private function storeImages(array $images): array
    {
        $paths = [];

        try {
            foreach ($images as $image) {
                $path = $image->store(self::IMAGE_DIRECTORY, 'public');

                if ($path === false) {
                    throw new RuntimeException('Could not store the uploaded news image.');
                }

                $paths[] = $path;
            }
        } catch (Throwable $e) {
            $this->deleteFiles($paths);

            throw $e;
        }

        return $paths;
    }

    /**
     * Deleting a file that is already gone is a harmless no-op.
     *
     * @param  array<int, string>  $paths
     */
    private function deleteFiles(array $paths): void
    {
        foreach ($paths as $path) {
            if ($path) {
                Storage::disk('public')->delete($path);
            }
        }
    }
}
