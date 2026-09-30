<?php

namespace App\Services\Video;

use App\Models\Video;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * File-upload/replace/delete safety mirrors TeamService::createTeam()/
 * updateTeam()/deleteTeam() exactly: a DB write and a filesystem write
 * are never part of the same transaction, so files are always stored
 * before the DB write and only cleaned up afterward — new files on
 * failure, old files only once the DB write that stops referencing
 * them has actually succeeded.
 */
class VideoService
{
    private const VIDEO_DIRECTORY = 'videos';

    private const THUMBNAIL_DIRECTORY = 'videos/thumbnails';

    public function createVideo(array $data, UploadedFile $video, ?UploadedFile $thumbnail = null): Video
    {
        $videoPath = $this->storeVideo($video);
        $thumbnailPath = $thumbnail ? $this->storeThumbnail($thumbnail) : null;

        try {
            return Video::create([
                ...$data,
                'video_path' => $videoPath,
                'thumbnail_path' => $thumbnailPath,
            ]);
        } catch (Throwable $e) {
            $this->deleteFile($videoPath);
            $this->deleteFile($thumbnailPath);

            throw $e;
        }
    }

    public function updateVideo(Video $video, array $data, ?UploadedFile $newVideo = null, ?UploadedFile $newThumbnail = null): Video
    {
        $oldVideoPath = $video->video_path;
        $oldThumbnailPath = $video->thumbnail_path;

        if ($newVideo) {
            $data['video_path'] = $this->storeVideo($newVideo);
        }

        if ($newThumbnail) {
            $data['thumbnail_path'] = $this->storeThumbnail($newThumbnail);
        }

        try {
            $video->update($data);
        } catch (Throwable $e) {
            if ($newVideo) {
                $this->deleteFile($data['video_path']);
            }
            if ($newThumbnail) {
                $this->deleteFile($data['thumbnail_path']);
            }

            throw $e;
        }

        if ($newVideo) {
            $this->deleteFile($oldVideoPath);
        }
        if ($newThumbnail) {
            $this->deleteFile($oldThumbnailPath);
        }

        return $video;
    }

    public function toggleStatus(Video $video): Video
    {
        $video->update(['status' => $video->status === 'active' ? 'inactive' : 'active']);

        return $video;
    }

    public function deleteVideo(Video $video): bool
    {
        $videoPath = $video->video_path;
        $thumbnailPath = $video->thumbnail_path;

        if (! $video->delete()) {
            return false;
        }

        $this->deleteFile($videoPath);
        $this->deleteFile($thumbnailPath);

        return true;
    }

    /**
     * UploadedFile::store() already generates a random, non-guessable
     * filename (never derived from the original filename or any
     * user-supplied path), so no extra sanitization is needed here.
     */
    private function storeVideo(UploadedFile $file): string
    {
        return $file->store(self::VIDEO_DIRECTORY, 'public');
    }

    private function storeThumbnail(UploadedFile $file): string
    {
        return $file->store(self::THUMBNAIL_DIRECTORY, 'public');
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
