<?php

namespace App\Services\Rule;

use App\Models\Rule;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Image-upload/replace/delete safety mirrors VideoService/TeamService
 * exactly: the image is stored before the DB write and only cleaned up
 * afterward — a newly-stored file is removed if the DB write fails; an
 * old file is removed only once the DB write that stops referencing it
 * has actually succeeded. At most one image per rule, no gallery.
 */
class RuleService
{
    private const IMAGE_DIRECTORY = 'rules';

    public function createRule(array $data, ?UploadedFile $image = null): Rule
    {
        $imagePath = $image ? $this->storeImage($image) : null;

        try {
            return Rule::create([
                ...$data,
                'image_path' => $imagePath,
            ]);
        } catch (Throwable $e) {
            $this->deleteImage($imagePath);

            throw $e;
        }
    }

    /**
     * $removeImage lets the admin explicitly clear an existing image
     * without uploading a replacement — independent of $newImage, which
     * is a genuine replacement upload.
     */
    public function updateRule(Rule $rule, array $data, ?UploadedFile $newImage = null, bool $removeImage = false): Rule
    {
        $oldImagePath = $rule->image_path;

        if ($newImage) {
            $data['image_path'] = $this->storeImage($newImage);
        } elseif ($removeImage) {
            $data['image_path'] = null;
        }

        try {
            $rule->update($data);
        } catch (Throwable $e) {
            if ($newImage) {
                $this->deleteImage($data['image_path']);
            }

            throw $e;
        }

        if (($newImage || $removeImage) && $oldImagePath) {
            $this->deleteImage($oldImagePath);
        }

        return $rule;
    }

    public function deleteRule(Rule $rule): bool
    {
        $imagePath = $rule->image_path;

        if (! $rule->delete()) {
            return false;
        }

        $this->deleteImage($imagePath);

        return true;
    }

    /**
     * UploadedFile::store() already generates a random, non-guessable
     * filename (never derived from the original filename or any
     * user-supplied path), so no extra sanitization is needed here.
     */
    private function storeImage(UploadedFile $file): string
    {
        return $file->store(self::IMAGE_DIRECTORY, 'public');
    }

    private function deleteImage(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
