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
     * registration_number is deliberately NEVER listed here — it must
     * only ever be server-generated via assignRegistrationNumber(),
     * never settable through mass-assignment from any request (admin
     * form, CSV import, or a future guest submission). Direct attribute
     * assignment (as assignRegistrationNumber() does) still works
     * regardless of $fillable; only fill()/create()/update() are
     * restricted by it.
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
}
