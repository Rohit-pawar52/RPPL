<?php

namespace App\Services\Registration;

use App\Models\PlayerRegistration;
use Illuminate\Support\Facades\Log;
use thiagoalessio\TesseractOCR\TesseractOCR;
use Throwable;

/**
 * Runs Tesseract OCR against a payment-proof image and tries to pull
 * out a transaction/reference ID via TransactionIdExtractor (Phase B).
 * OCR is an enhancement, never a requirement: this class is designed so
 * it is structurally impossible for it to throw — every failure mode
 * (missing binary, unreadable image, no match) ends in a result array
 * with a null transaction_id, never an exception bubbling up into
 * registration creation.
 *
 * Never logs raw OCR text or payment-screenshot contents — only a
 * concise warning naming the failure, on the already-private stored
 * path (never anything extracted from the image itself).
 */
class PaymentProofOcrService
{
    /**
     * @param  string  $absolutePath  A real filesystem path (not a
     *                                Storage-relative path) — Tesseract
     *                                shells out to the `tesseract`
     *                                binary and needs an actual file to
     *                                read.
     * @return array{status: string, transaction_id: ?string}
     */
    public function process(string $absolutePath): array
    {
        try {
            $ocr = new TesseractOCR($absolutePath);

            // Blank by default (see config/services.php) — leaves the
            // package's own default behavior (resolve `tesseract` from
            // PATH) untouched, which is all production/Linux needs.
            if ($executable = config('services.tesseract.path')) {
                $ocr->executable($executable);
            }

            $text = $ocr->run();
        } catch (Throwable $exception) {
            // Covers a missing tesseract binary, an unreadable image,
            // or any other failure the underlying process can throw.
            // Logged as a warning (expected/handled), never the raw
            // image path's contents or any OCR output.
            Log::warning('Payment proof OCR failed', [
                'exception' => $exception->getMessage(),
            ]);

            return ['status' => PlayerRegistration::OCR_FAILED, 'transaction_id' => null];
        }

        $transactionId = TransactionIdExtractor::extract($text);

        if ($transactionId === null) {
            return ['status' => PlayerRegistration::OCR_NOT_FOUND, 'transaction_id' => null];
        }

        return ['status' => PlayerRegistration::OCR_EXTRACTED, 'transaction_id' => $transactionId];
    }
}
