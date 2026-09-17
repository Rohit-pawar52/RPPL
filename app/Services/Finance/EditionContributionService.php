<?php

namespace App\Services\Finance;

use App\Models\CommitteeMember;
use App\Models\Contributor;
use App\Models\EditionContribution;
use App\Models\EditionTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the one real piece of domain consistency in this phase: a
 * contribution and its Phase 3.25 finance-ledger income transaction
 * must always be created and destroyed together, never independently —
 * see EditionTransactionController's direct-edit protection for the
 * other half of that guarantee.
 *
 * Phase 3.38B2: a contribution's source identity is either a
 * CommitteeMember or a general Contributor, never both, never neither.
 * resolveSource() is the single place that resolves the request's
 * source_type/source_id into exactly one populated identity FK, so
 * that invariant holds by construction rather than by convention.
 */
class EditionContributionService
{
    /**
     * $data is expected to already be validated by
     * StoreEditionContributionRequest (edition exists, source exists and
     * is active, amount meets the source's minimum). Both rows are
     * written in one transaction so the ledger and the contribution can
     * never diverge (e.g. one succeeding while the other fails).
     *
     * @param  array{edition_id: int, source_type: string, source_id: int, amount: float|string, contributed_at: string, notes?: string|null}  $data
     */
    public function createContribution(array $data, int $createdBy): EditionContribution
    {
        return DB::transaction(function () use ($data, $createdBy) {
            [$committeeMemberId, $contributorId, $name, $category] = $this->resolveSource(
                $data['source_type'],
                (int) $data['source_id'],
            );

            $this->assertExactlyOneSource($committeeMemberId, $contributorId);

            $transaction = EditionTransaction::create([
                'edition_id' => $data['edition_id'],
                'type' => 'income',
                'category' => $category,
                'amount' => $data['amount'],
                'transaction_date' => $data['contributed_at'],
                'description' => 'Contribution from '.$name,
                'created_by' => $createdBy,
            ]);

            return EditionContribution::create([
                'edition_id' => $data['edition_id'],
                'committee_member_id' => $committeeMemberId,
                'contributor_id' => $contributorId,
                'edition_transaction_id' => $transaction->id,
                'amount' => $data['amount'],
                'contributed_at' => $data['contributed_at'],
                'notes' => $data['notes'] ?? null,
                'created_by' => $createdBy,
            ]);
        });
    }

    /**
     * Re-verifies (defense-in-depth, beyond StoreEditionContributionRequest)
     * that the selected source actually exists and is currently active,
     * and resolves it to exactly one populated identity FK plus the
     * display name/ledger category to use. A Contributor linked to a
     * CommitteeMember is NOT redirected to the committee identity here —
     * the source actually used to record the contribution is always the
     * one preserved on the row; that link is for later public
     * aggregation only, never for rewriting contribution history.
     *
     * @return array{0: int|null, 1: int|null, 2: string, 3: string}
     */
    private function resolveSource(string $sourceType, int $sourceId): array
    {
        if ($sourceType === 'committee') {
            $member = CommitteeMember::query()->where('is_active', true)->find($sourceId);

            if (! $member) {
                throw ValidationException::withMessages([
                    'source_id' => 'The selected committee member must be active.',
                ]);
            }

            return [$member->id, null, $member->name, 'Committee Contribution'];
        }

        if ($sourceType === 'contributor') {
            $contributor = Contributor::query()->where('is_active', true)->find($sourceId);

            if (! $contributor) {
                throw ValidationException::withMessages([
                    'source_id' => 'The selected contributor must be active.',
                ]);
            }

            return [null, $contributor->id, $contributor->name, 'General Contribution'];
        }

        throw ValidationException::withMessages([
            'source_type' => 'Invalid contribution source.',
        ]);
    }

    /**
     * The one real domain invariant this whole phase depends on. Never
     * expected to trip given resolveSource() always returns exactly one
     * populated identity — kept as an explicit, named guard rather than
     * relying on that implicitly.
     */
    private function assertExactlyOneSource(?int $committeeMemberId, ?int $contributorId): void
    {
        if (($committeeMemberId === null) === ($contributorId === null)) {
            throw new \LogicException('An EditionContribution must have exactly one source identity.');
        }
    }

    /**
     * Deletes the contribution first, then its linked transaction — the
     * only safe order given edition_contributions.edition_transaction_id
     * has a restrictOnDelete foreign key (the transaction can't be
     * deleted while the contribution still references it).
     */
    public function deleteContribution(EditionContribution $contribution): void
    {
        DB::transaction(function () use ($contribution) {
            $transactionId = $contribution->edition_transaction_id;

            $contribution->delete();

            EditionTransaction::whereKey($transactionId)->delete();
        });
    }
}
