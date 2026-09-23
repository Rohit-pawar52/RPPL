<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single contribution to an edition, always from a Contributor (Phase
 * 3.48 — one person identity; a contribution is never sourced from a
 * separate "committee" identity any more) — always paired 1:1 with the
 * EditionTransaction it automatically creates in the Phase 3.25 finance
 * ledger. Never created/deleted independently of that transaction.
 *
 * committee_member_id is LEGACY ONLY, pre-Phase-3.48 — see Contributor's
 * own docblock. Rows created before Phase 3.48 may still carry it
 * alongside their (now backfilled) contributor_id; new rows never set
 * it. Whether a contribution came from a "committee member" is answered
 * by asking $contribution->contributor->isCommitteeMemberOf($contribution->edition),
 * never by which FK is populated on this row.
 */
class EditionContribution extends Model
{
    use HasFactory;

    /**
     * Any genuinely positive amount is accepted (Phase 3.48) — there is
     * no longer a fixed per-payment floor for a committee member, since
     * finance.committee_minimum_contribution (see SettingsRegistry) is a
     * per-edition TARGET a member may reach across several installment
     * payments, not a minimum enforced on any single payment.
     */
    public const MINIMUM_AMOUNT = 0.01;

    protected $fillable = [
        'edition_id',
        'committee_member_id',
        'contributor_id',
        'edition_transaction_id',
        'amount',
        'contributed_at',
        'notes',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'contributed_at' => 'date',
        ];
    }

    public function edition(): BelongsTo
    {
        return $this->belongsTo(Edition::class);
    }

    /**
     * @deprecated Legacy identity link only (pre-Phase-3.48) — see
     * Contributor's class docblock. contributor() is the real identity
     * relation for every row now.
     */
    public function committeeMember(): BelongsTo
    {
        return $this->belongsTo(CommitteeMember::class);
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Contributor::class);
    }

    /**
     * The contributor's name — falls back to the legacy committeeMember
     * relation only for the theoretical case of a pre-migration row that
     * somehow still has no contributor_id (should not exist after the
     * Phase 3.48 data migration; kept as a defensive fallback only).
     */
    public function contributorName(): string
    {
        return $this->contributor?->name ?? $this->committeeMember?->name ?? '';
    }

    /**
     * A short label for admin display — Phase 3.48: derived from whether
     * this contributor is a committee member of THIS contribution's
     * edition (an edition-specific fact), never from which FK happens to
     * be populated on the row.
     */
    public function sourceLabel(): string
    {
        if ($this->contributor) {
            return $this->contributor->isCommitteeMemberOf($this->edition) ? 'Committee Member' : 'General Contributor';
        }

        // Legacy fallback for a pre-migration row with no contributor_id
        // at all (see contributorName()'s docblock).
        return $this->committee_member_id !== null ? 'Committee Member' : 'General Contributor';
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(EditionTransaction::class, 'edition_transaction_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * A deterministic receipt reference derived from the contribution's
     * own immutable id — never persisted, never a separate numbering
     * table. Centralized here so the receipt view/PDF filename and any
     * future lookup always agree on the exact same format.
     */
    public function receiptReference(): string
    {
        return sprintf('RPPL-CON-%06d', $this->id);
    }
}
