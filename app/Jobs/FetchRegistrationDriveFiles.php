<?php

namespace App\Jobs;

use App\Models\PlayerRegistration;
use App\Services\Registration\RegistrationDriveFileService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Copies an imported registration's Google Drive photo / payment screenshot
 * into private storage after the import has committed, off the request, so a
 * sheet with hundreds of links does not hold the import page open. A file
 * that cannot be copied is not a job failure: the registration keeps its
 * link and the admin can retry from the registration page.
 */
class FetchRegistrationDriveFiles implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $timeout = 90;

    public function __construct(public PlayerRegistration $registration) {}

    public function handle(RegistrationDriveFileService $files): void
    {
        $result = $files->fetchFor($this->registration);

        if ($result['failed'] !== []) {
            Log::info('Registration Drive files not copied', ['registration' => $this->registration->registration_number, 'failed' => $result['failed']]);
        }
    }
}
