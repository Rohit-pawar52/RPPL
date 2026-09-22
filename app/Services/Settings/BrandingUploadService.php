<?php

namespace App\Services\Settings;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Logo/favicon upload lifecycle for the Settings module (Phase 3.44B2) —
 * mirrors TeamService's exact store-then-persist-then-delete-old sequence
 * (a DB write and a filesystem write are never the same transaction, so
 * the new file is always stored first and explicitly cleaned up if the
 * settings write that follows fails).
 */
class BrandingUploadService
{
    private const DIRECTORY = 'branding';

    public function __construct(private readonly SettingsService $settings) {}

    public function replaceLogo(UploadedFile $logo): void
    {
        $this->replace('general.logo_path', $logo);
    }

    public function replaceFavicon(UploadedFile $favicon): void
    {
        $this->replace('general.favicon_path', $favicon);
    }

    public function removeLogo(): void
    {
        $this->remove('general.logo_path');
    }

    public function removeFavicon(): void
    {
        $this->remove('general.favicon_path');
    }

    /**
     * Stores the new file, persists the setting, and only then removes
     * the old file. If the settings write fails, the just-uploaded file
     * is removed and the old file (still referenced by the unchanged
     * setting) is left alone.
     */
    private function replace(string $key, UploadedFile $file): void
    {
        $oldPath = $this->settings->get($key);
        $newPath = $file->store(self::DIRECTORY, 'public');

        try {
            $this->settings->set($key, $newPath);
        } catch (Throwable $e) {
            Storage::disk('public')->delete($newPath);

            throw $e;
        }

        if ($oldPath) {
            Storage::disk('public')->delete($oldPath);
        }
    }

    private function remove(string $key): void
    {
        $oldPath = $this->settings->get($key);

        if (! $oldPath) {
            return;
        }

        $this->settings->set($key, null);

        Storage::disk('public')->delete($oldPath);
    }
}
