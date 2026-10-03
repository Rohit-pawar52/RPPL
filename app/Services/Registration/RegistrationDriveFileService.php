<?php

namespace App\Services\Registration;

use App\Jobs\ProcessPaymentProofOcr;
use App\Models\PlayerRegistration;
use App\Support\DriveLink;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Copies the photo and payment screenshot of an imported registration from
 * their Google Drive links into the app's private storage, so they open
 * from here like a file the player uploaded on the public form.
 *
 * It only works for files Drive serves without a Google login (shared as
 * "Anyone with the link"): a private file answers with a login page, which
 * is detected and reported, never stored. Nothing here throws — every
 * failure is returned as a short reason and the registration keeps its
 * link, so a file that cannot be copied costs nothing.
 *
 * The URL fetched is always built from the file id, on drive.google.com;
 * the address in the sheet is never requested as given.
 */
class RegistrationDriveFileService
{
    private const DISK = 'local';

    /**
     * Largest file taken, in bytes (the public form allows 10 MB too).
     */
    private const MAX_BYTES = 10 * 1024 * 1024;

    /**
     * label => [link column, stored-path column, directory, image types accepted]
     */
    private const FILES = [
        'photo' => ['photo_url', 'photo_path', 'player-registrations/photos', [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP]],
        'payment screenshot' => ['payment_proof_url', 'payment_proof_path', 'player-registrations/payment-proofs', [IMAGETYPE_JPEG, IMAGETYPE_PNG]],
    ];

    public function hasFilesToFetch(PlayerRegistration $registration): bool
    {
        foreach (self::FILES as [$urlColumn, $pathColumn]) {
            if ($registration->{$urlColumn} && ! $registration->{$pathColumn}) {
                return true;
            }
        }

        return false;
    }

    /**
     * Copies every file that has a link but no stored copy yet.
     *
     * @return array{fetched: list<string>, failed: array<string, string>} what was copied, and why each other file was not
     */
    public function fetchFor(PlayerRegistration $registration): array
    {
        $result = ['fetched' => [], 'failed' => []];

        foreach (self::FILES as $label => [$urlColumn, $pathColumn, $directory, $types]) {
            $url = $registration->{$urlColumn};

            if (! $url || $registration->{$pathColumn}) {
                continue;
            }

            try {
                [$bytes, $extension] = $this->download($url, $types);
                $path = $directory.'/'.Str::random(40).'.'.$extension;
                Storage::disk(self::DISK)->put($path, $bytes);
                $registration->update([$pathColumn => $path]);
                $result['fetched'][] = $label;
            } catch (Throwable $e) {
                $result['failed'][$label] = $e instanceof DriveFetchException ? $e->getMessage() : 'could not be downloaded';

                if (! $e instanceof DriveFetchException) {
                    report($e);
                }

                continue;
            }

            if ($pathColumn === 'payment_proof_path') {
                try {
                    ProcessPaymentProofOcr::dispatch($registration);
                } catch (Throwable $e) {
                    report($e);
                }
            }
        }

        return $result;
    }

    /**
     * @param  list<int>  $types  accepted IMAGETYPE_* values
     * @return array{0: string, 1: string} the file's bytes and its extension
     *
     * @throws DriveFetchException
     */
    private function download(string $url, array $types): array
    {
        $id = DriveLink::fileId($url);

        if ($id === null) {
            throw new DriveFetchException('the link has no Google Drive file id');
        }

        $response = Http::timeout(20)
            ->withHeaders(['User-Agent' => 'Mozilla/5.0 (RPPL registration import)'])
            ->get('https://drive.google.com/uc', ['export' => 'download', 'id' => $id]);

        if (! $response->successful()) {
            throw new DriveFetchException('Google Drive answered with status '.$response->status().' — the file may be private or removed');
        }

        $bytes = $response->body();

        if (strlen($bytes) > self::MAX_BYTES) {
            throw new DriveFetchException('the file is larger than 10 MB');
        }

        $info = @getimagesizefromstring($bytes);

        if ($info === false || ! in_array($info[2], $types, true)) {
            throw new DriveFetchException("it is not a usable image — the file is probably private (share it as 'Anyone with the link') or is another file type");
        }

        return [$bytes, match ($info[2]) {
            IMAGETYPE_PNG => 'png',
            IMAGETYPE_WEBP => 'webp',
            default => 'jpg',
        }];
    }
}
