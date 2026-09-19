<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class PlayerRegistration extends Model
{
    use HasFactory;

    /**
     * Must match the enum values in the player_registrations migration
     * exactly.
     */
    public const PAYMENT_STATUSES = ['pending', 'paid', 'failed', 'refunded'];

    /**
     * Must match the enum values in the add_ocr_fields migration
     * exactly. Named constants (rather than scattering the bare string
     * literals through PaymentProofOcrService/ProcessPaymentProofOcr)
     * so a typo in one of these values fails at compile/static-analysis
     * time instead of silently creating a fifth, unrecognized status.
     */
    public const OCR_PENDING = 'pending';

    public const OCR_EXTRACTED = 'extracted';

    public const OCR_NOT_FOUND = 'not_found';

    public const OCR_FAILED = 'failed';

    /**
     * registration_number is deliberately NEVER listed here — it must
     * only ever be server-generated via assignRegistrationNumber(),
     * never settable through mass-assignment from any request (admin
     * form, CSV import, or a future guest submission). Direct attribute
     * assignment (as assignRegistrationNumber() does) still works
     * regardless of $fillable; only fill()/create()/update() are
     * restricted by it.
     *
     * ocr_transaction_id/ocr_status are listed here purely so
     * ProcessPaymentProofOcr can write them via a plain update() call,
     * matching every other internal write in this model — no public
     * FormRequest (guest submission, admin create/update) ever includes
     * either key in its validated() rules, so this carries no more risk
     * than payment_reference already does.
     */
    protected $fillable = [
        'edition_id',
        'player_id',
        'payment_status',
        'registration_fee',
        'registered_at',
        'aadhaar_document_path',
        'payment_proof_path',
        'payment_reference',
        'ocr_transaction_id',
        'ocr_status',
    ];

    protected function casts(): array
    {
        return [
            'registration_fee' => 'decimal:2',
            'registered_at' => 'datetime',
        ];
    }

    /**
     * The single mechanism every creation path (admin CRUD, CSV import,
     * and the future guest registration in Phase 3.39C) must call right
     * after creating a row, so every PlayerRegistration ends up with the
     * exact same format generated the exact same way — never three
     * separate sprintf() calls drifting apart. Uses only this row's own
     * already-unique, already-assigned primary key plus its edition's
     * year; never created_at/COUNT/row-ordering, so it is trivially
     * concurrency-safe (the DB's own auto-increment already guarantees
     * uniqueness) and needs no lock/sequence table.
     */
    public function assignRegistrationNumber(): static
    {
        $this->registration_number = self::formatRegistrationNumber($this->edition->year, $this->id);
        $this->save();

        return $this;
    }

    /**
     * Pure formatting, kept separate from assignRegistrationNumber() so
     * the exact format is independently testable without needing a
     * persisted model. Zero-padded to 6 digits; ids beyond that width
     * are never truncated (sprintf only pads up to the minimum width).
     */
    public static function formatRegistrationNumber(int $editionYear, int $registrationId): string
    {
        return sprintf('RPPL-%d-%06d', $editionYear, $registrationId);
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /**
     * team_players.player_registration_id is UNIQUE, so a registration
     * maps to at most one edition-team squad slot.
     */
    public function teamPlayer(): HasOne
    {
        return $this->hasOne(TeamPlayer::class);
    }

    /**
     * Whether this registration's OCR-extracted candidate also appears
     * on a different registration. Advisory only — ocr_transaction_id
     * has no unique constraint (see the migration), and this never
     * rejects/blocks anything; it only lets a future admin screen
     * (Phase C) surface a warning to actually investigate. Deliberately
     * compares against ocr_transaction_id only, never payment_reference
     * — the two are intentionally separate values (see
     * GuestPlayerRegistrationService/ProcessPaymentProofOcr).
     */
    public function hasDuplicateOcrTransactionId(): bool
    {
        if ($this->ocr_transaction_id === null || $this->ocr_transaction_id === '') {
            return false;
        }

        return static::where('ocr_transaction_id', $this->ocr_transaction_id)
            ->where('id', '!=', $this->id)
            ->exists();
    }
}
