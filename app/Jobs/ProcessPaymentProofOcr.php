<?php

namespace App\Jobs;

use App\Models\PlayerRegistration;
use App\Services\Registration\PaymentProofOcrService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Runs OCR on a guest registration's payment proof off the request
 * cycle (Phase B) — Tesseract can take a second or more per image, and
 * a guest shouldn't sit waiting on that just to see "your registration
 * was submitted." OCR is an enhancement to the registration, never a
 * precondition for it existing, and never authoritative for payment
 * verification (see PlayerRegistration::hasDuplicateOcrTransactionId()
 * and PaymentProofOcrService's own docblocks).
 *
 * PaymentProofOcrService is designed to never throw — a 'failed'
 * ocr_status from a clean run is a normal, SUCCESSFUL outcome for this
 * job, not a retryable failure. The only things that can actually make
 * this job fail are genuine infrastructure problems (e.g. a dropped DB
 * connection on the final update() call), which is exactly what
 * $tries/$backoff exist to retry.
 *
 * SerializesModels means $registration is re-fetched fresh from the
 * database when the job is deserialized off the queue — never the
 * stale in-memory instance captured at dispatch time.
 */
class ProcessPaymentProofOcr implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 30, 60];

    public function __construct(public PlayerRegistration $registration) {}

    public function handle(PaymentProofOcrService $ocrService): void
    {
        // Defensive, not expected via the public guest flow (a payment
        // proof is required at submission there) — but admin-created/
        // CSV-imported registrations legitimately have no payment proof
        // at all, so if this job is ever dispatched for one it must
        // still resolve to a conclusive state, never sit at "pending"
        // forever.
        if ($this->registration->payment_proof_path === null) {
            $this->registration->update([
                'ocr_status' => PlayerRegistration::OCR_FAILED,
                'ocr_transaction_id' => null,
            ]);

            return;
        }

        // Only ever resolves the path already stored on this
        // registration's own column — never a path supplied by a
        // request. Payment proof stays on the private 'local' disk
        // throughout; OCR never touches the public disk.
        $absolutePath = Storage::disk('local')->path($this->registration->payment_proof_path);
        $result = $ocrService->process($absolutePath);

        $this->registration->update([
            'ocr_status' => $result['status'],
            'ocr_transaction_id' => $result['transaction_id'],
        ]);
    }

    /**
     * Reached only if handle() itself threw on every attempt (OCR
     * failures are never exceptions here — see PaymentProofOcrService's
     * own docblock). Sets ocr_status to 'failed' explicitly so a future
     * admin screen shows a conclusive result instead of "pending"
     * forever. Never touches payment_reference/payment_status — those
     * remain entirely the admin's domain, untouched by this job in
     * every branch.
     */
    public function failed(Throwable $exception): void
    {
        $this->registration->update([
            'ocr_status' => PlayerRegistration::OCR_FAILED,
            'ocr_transaction_id' => null,
        ]);
    }
}
