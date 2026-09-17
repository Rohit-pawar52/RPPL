<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A single contribution to an edition — from either a CommitteeMember
 * OR a general Contributor (Phase 3.38B2), never both, never neither
 * (see EditionContributionService::assertExactlyOneSource()) — always
 * paired 1:1 with the EditionTransaction it automatically creates in
 * the Phase 3.25 finance ledger. Never created/deleted independently of
 * that transaction.
 */
class EditionContribution extends Model
{
    use HasFactory;

    /**
     * The original RPPL proposal's minimum committee contribution.
     * Applies per contribution record, not per member/edition — the
     * same member may legitimately contribute multiple times. Does NOT
     * apply to general Contributor rows (see MINIMUM_GENERAL_AMOUNT).
     */
    public const MINIMUM_AMOUNT = 1000;

    /**
     * General/"Chanda" contributions have no fixed minimum beyond being
     * a genuinely positive amount.
     */
    public const MINIMUM_GENERAL_AMOUNT = 0.01;

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

    public function committeeMember(): BelongsTo
    {
        return $this->belongsTo(CommitteeMember::class);
    }

    public function contributor(): BelongsTo
    {
        return $this->belongsTo(Contributor::class);
    }

    /**
     * Resolves the name of whichever identity actually recorded this
     * contribution — never a name-based guess, purely whichever of the
     * two source FKs is populated on this specific row (exactly one,
     * enforced at creation by EditionContributionService).
     */
    public function contributorName(): string
    {
        return $this->committeeMember?->name ?? $this->contributor?->name ?? '';
    }

    /**
     * A short, source-agnostic label for admin display — never exposes
     * which FK column is used, just the human-facing category.
     */
    public function sourceLabel(): string
    {
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
