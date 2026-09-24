<?php

namespace App\Services\DataCleanup;

use App\Models\Edition;
use App\Models\PlayerRegistration;
use Illuminate\Support\Facades\Storage;

/**
 * Phase 3.49 — purges the private Aadhaar/payment-proof FILES a guest
 * registration uploaded, without ever touching the registration record
 * itself (player, payment status, registration number, fee, edition,
 * financial history all survive unchanged) — only the file and its own
 * path column are cleared. Edition-scoped and type-scoped on purpose:
 * there is no "delete every Aadhaar file in the system" action.
 *
 * Every path deleted here is read directly from the PlayerRegistration
 * row's own column (never accepted from a request), on the same
 * 'local' private disk the guest-upload flow itself uses (see
 * PlayerRegistrationService::deleteRegistration() for the identical
 * convention) — this service cannot be pointed at an arbitrary path.
 */
class RegistrationDocumentCleanupService
{
    public const TYPES = ['aadhaar', 'payment_proof', 'both'];

    /**
     * @return array{aadhaar: int, payment_proof: int}
     */
    public function previewCounts(Edition $edition, string $documentType): array
    {
        return [
            'aadhaar' => $this->includesAadhaar($documentType)
                ? PlayerRegistration::where('edition_id', $edition->id)->whereNotNull('aadhaar_document_path')->count()
                : 0,
            'payment_proof' => $this->includesPaymentProof($documentType)
                ? PlayerRegistration::where('edition_id', $edition->id)->whereNotNull('payment_proof_path')->count()
                : 0,
        ];
    }

    /**
     * Deletes the matching private file(s) for every registration under
     * $edition and clears the corresponding path column — one
     * registration at a time (not a single bulk file-delete call), so a
     * missing file on disk for one row never prevents clearing/counting
     * the rest. A DB update and a filesystem delete cannot share one
     * transaction; the path column is only cleared for a registration
     * after this method has already attempted its file delete, so a
     * mid-loop failure can only ever leave a row's path pointing at an
     * already-deleted (or already-missing) file — never the reverse
     * (a cleared path with the real file still sitting on disk).
     *
     * @return array{registrations_updated: int, files_deleted: int, files_missing: int}
     */
    public function deleteDocuments(Edition $edition, string $documentType): array
    {
        $columns = array_filter([
            $this->includesAadhaar($documentType) ? 'aadhaar_document_path' : null,
            $this->includesPaymentProof($documentType) ? 'payment_proof_path' : null,
        ]);

        if ($columns === []) {
            return ['registrations_updated' => 0, 'files_deleted' => 0, 'files_missing' => 0];
        }

        $query = PlayerRegistration::where('edition_id', $edition->id)
            ->where(function ($query) use ($columns) {
                foreach ($columns as $column) {
                    $query->orWhereNotNull($column);
                }
            });

        $registrationsUpdated = 0;
        $filesDeleted = 0;
        $filesMissing = 0;

        $query->select(array_merge(['id'], $columns))
            ->chunkById(200, function ($registrations) use ($columns, &$registrationsUpdated, &$filesDeleted, &$filesMissing) {
                foreach ($registrations as $registration) {
                    $updates = [];

                    foreach ($columns as $column) {
                        $path = $registration->{$column};

                        if ($path === null) {
                            continue;
                        }

                        if (Storage::disk('local')->exists($path)) {
                            Storage::disk('local')->delete($path);
                            $filesDeleted++;
                        } else {
                            $filesMissing++;
                        }

                        $updates[$column] = null;
                    }

                    if ($updates !== []) {
                        $registration->update($updates);
                        $registrationsUpdated++;
                    }
                }
            });

        return [
            'registrations_updated' => $registrationsUpdated,
            'files_deleted' => $filesDeleted,
            'files_missing' => $filesMissing,
        ];
    }

    private function includesAadhaar(string $documentType): bool
    {
        return in_array($documentType, ['aadhaar', 'both'], true);
    }

    private function includesPaymentProof(string $documentType): bool
    {
        return in_array($documentType, ['payment_proof', 'both'], true);
    }
}
