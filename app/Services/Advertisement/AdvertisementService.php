<?php

namespace App\Services\Advertisement;

use App\Models\Advertisement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Admin-side writes for sponsor ads. File handling follows VideoService: a
 * DB write and a filesystem write are never one transaction, so new files
 * are stored first and only cleaned up on failure, and old files are
 * removed only once the row no longer references them.
 */
class AdvertisementService
{
    private const MEDIA_DIRECTORY = 'ads';

    private const POSTER_DIRECTORY = 'ads/posters';

    public function createAdvertisement(array $data, UploadedFile $media, ?UploadedFile $poster = null): Advertisement
    {
        $data = $this->withFormat($data);
        $mediaPath = $this->storeFile($media, self::MEDIA_DIRECTORY);
        // Only a video has a poster frame; an image is its own preview.
        $posterPath = $poster && $data['media_type'] === Advertisement::MEDIA_VIDEO
            ? $this->storeFile($poster, self::POSTER_DIRECTORY)
            : null;

        try {
            return Advertisement::create([
                ...$data,
                'media_path' => $mediaPath,
                'poster_path' => $posterPath,
            ]);
        } catch (Throwable $e) {
            $this->deleteFile($mediaPath);
            $this->deleteFile($posterPath);

            throw $e;
        }
    }

    public function updateAdvertisement(Advertisement $advertisement, array $data, ?UploadedFile $newMedia = null, ?UploadedFile $newPoster = null): Advertisement
    {
        $data = $this->withFormat($data, $advertisement);

        $oldMediaPath = $advertisement->media_path;
        $oldPosterPath = $advertisement->poster_path;

        if ($newMedia) {
            $data['media_path'] = $this->storeFile($newMedia, self::MEDIA_DIRECTORY);
        }

        $becomesImage = ($data['media_type'] ?? $advertisement->media_type) === Advertisement::MEDIA_IMAGE;

        if ($becomesImage) {
            $data['poster_path'] = null;
        } elseif ($newPoster) {
            $data['poster_path'] = $this->storeFile($newPoster, self::POSTER_DIRECTORY);
        }

        try {
            $advertisement->update($data);
        } catch (Throwable $e) {
            if ($newMedia) {
                $this->deleteFile($data['media_path']);
            }
            if (! $becomesImage && $newPoster) {
                $this->deleteFile($data['poster_path']);
            }

            throw $e;
        }

        if ($newMedia) {
            $this->deleteFile($oldMediaPath);
        }
        if ($oldPosterPath && $oldPosterPath !== $advertisement->poster_path) {
            $this->deleteFile($oldPosterPath);
        }

        return $advertisement;
    }

    /**
     * Only a Normal sponsor has a choice of spot (banner or card); every
     * other level is stored without one, and a Normal ad with none is a
     * banner.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withFormat(array $data, ?Advertisement $current = null): array
    {
        $tier = $data['tier'] ?? $current?->tier;

        $data['format'] = $tier === Advertisement::TIER_NORMAL
            ? ($data['format'] ?? $current?->format ?? Advertisement::FORMAT_BANNER)
            : null;

        return $data;
    }

    /**
     * Flips the status server-side. Activating a Main or Auction sponsor is
     * refused while another one of the same level is active in overlapping
     * dates.
     *
     * @throws ValidationException
     */
    public function toggleStatus(Advertisement $advertisement): Advertisement
    {
        $activating = $advertisement->status !== 'active';

        if ($activating) {
            $clash = $this->singleSlotClash(
                $advertisement->tier,
                'active',
                $advertisement->starts_on?->format('Y-m-d'),
                $advertisement->ends_on?->format('Y-m-d'),
                $advertisement->id,
            );

            if ($clash) {
                throw ValidationException::withMessages(['status' => $this->clashMessage($clash)]);
            }
        }

        $advertisement->update(['status' => $activating ? 'active' : 'inactive']);

        return $advertisement;
    }

    public function deleteAdvertisement(Advertisement $advertisement): bool
    {
        $mediaPath = $advertisement->media_path;
        $posterPath = $advertisement->poster_path;

        if (! $advertisement->delete()) {
            return false;
        }

        $this->deleteFile($mediaPath);
        $this->deleteFile($posterPath);

        return true;
    }

    /**
     * The Main and the Auction sponsor each have one slot, so two active ads
     * of one of those levels may not be shown on the same day. Returns the
     * other ad of the same level that would clash (date windows overlap; an
     * empty date means open-ended), or null.
     */
    public function singleSlotClash(string $tier, string $status, ?string $startsOn, ?string $endsOn, ?int $ignoreId = null): ?Advertisement
    {
        if (! in_array($tier, Advertisement::SINGLE_SLOT_TIERS, true) || $status !== 'active') {
            return null;
        }

        return Advertisement::query()
            ->active()
            ->where('tier', $tier)
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->get()
            ->first(fn (Advertisement $other) => $this->datesOverlap(
                $startsOn,
                $endsOn,
                $other->starts_on?->format('Y-m-d'),
                $other->ends_on?->format('Y-m-d'),
            ));
    }

    public function clashMessage(Advertisement $clash): string
    {
        return 'There is only one '.$clash->tierLabel().' slot, and "'.$clash->title.'" already holds it ('
            .$clash->scheduleLabel().'). Deactivate it first or choose dates that do not overlap.';
    }

    private function datesOverlap(?string $startA, ?string $endA, ?string $startB, ?string $endB): bool
    {
        $aEndsBeforeBStarts = $endA !== null && $startB !== null && $endA < $startB;
        $bEndsBeforeAStarts = $endB !== null && $startA !== null && $endB < $startA;

        return ! $aEndsBeforeBStarts && ! $bEndsBeforeAStarts;
    }

    /**
     * UploadedFile::store() generates a random, non-guessable filename
     * (never derived from the original name), so no sanitising is needed.
     */
    private function storeFile(UploadedFile $file, string $directory): string
    {
        return $file->store($directory, 'public');
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('public')->delete($path);
        }
    }
}
