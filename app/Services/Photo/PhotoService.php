<?php

namespace App\Services\Photo;

use App\Models\Photo;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Same store-before-DB / clean-up-after pattern as VideoService: a new
 * file is stored first and removed if the DB write fails; an old file is
 * only removed once the write that stops referencing it has succeeded.
 */
class PhotoService
{
    private const PHOTO_DIRECTORY = 'photos';

    public function createPhoto(array $data, UploadedFile $photo): Photo
    {
        $path = $this->storePhoto($photo);

        try {
            return Photo::create([...$data, 'photo_path' => $path]);
        } catch (Throwable $e) {
            $this->deleteFile($path);

            throw $e;
        }
    }

    public function updatePhoto(Photo $photo, array $data, ?UploadedFile $newPhoto = null): Photo
    {
        $oldPath = $photo->photo_path;

        if ($newPhoto) {
            $data['photo_path'] = $this->storePhoto($newPhoto);
        }

        try {
            $photo->update($data);
        } catch (Throwable $e) {
            if ($newPhoto) {
                $this->deleteFile($data['photo_path']);
            }

            throw $e;
        }

        if ($newPhoto) {
            $this->deleteFile($oldPath);
        }

        return $photo;
    }

    public function deletePhoto(Photo $photo): bool
    {
        $path = $photo->photo_path;

        if (! $photo->delete()) {
            return false;
        }

        $this->deleteFile($path);

        return true;
    }

    public function toggleStatus(Photo $photo): Photo
    {
        $photo->update(['status' => $photo->status === 'active' ? 'inactive' : 'active']);

        return $photo;
    }

    /**
     * UploadedFile::store() generates a random filename, never derived
     * from the original name or any user-supplied path.
     */
    private function storePhoto(UploadedFile $file): string
    {
        return $file->store(self::PHOTO_DIRECTORY, 'public');
    }

    /**
     * Deleting a file that is already gone is a harmless no-op.
     */
    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
