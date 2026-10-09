<?php

namespace App\Services\Finance;

use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Models\EditionTransaction;
use App\Services\Contributor\ContributorService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Owns the one real piece of domain consistency in this phase: a
 * contribution and its Phase 3.25 finance-ledger income transaction
 * must always be created and destroyed together, never independently —
 * see EditionTransactionController's direct-edit protection for the
 * other half of that guarantee.
 *
 * Phase 3.48: a contribution's identity is always a Contributor — there
 * is no more separate "committee" source. Whether it counts toward a
 * committee member's dues is answered by asking
 * Contributor::isCommitteeMemberOf($edition), never by which identity
 * recorded the row.
 */
class EditionContributionService
{
    public function __construct(
        private readonly ContributorService $contributors,
        private readonly EditionCommitteeMembershipService $committee,
    ) {}

    /**
     * $data is expected to already be validated by
     * StoreEditionContributionRequest (edition exists, contributor
     * exists and is active, amount is genuinely positive). Both rows are
     * written in one transaction so the ledger and the contribution can
     * never diverge (e.g. one succeeding while the other fails).
     *
     * Somebody giving for the first time is described in `new_contributor` instead of `contributor_id`:
     * they are added (and, if asked, put on this edition's committee) INSIDE the same transaction, before the
     * ledger line is made - so one failure leaves nothing behind (no contributor without a contribution), and
     * the ledger category already reads "Committee Contribution" for a new committee member.
     *
     * @param  array{edition_id: int, contributor_id?: int, new_contributor?: array{name: string, village: string, phone?: string|null, address?: string|null}, add_to_committee?: bool, amount: float|string, contributed_at: string, notes?: string|null}  $data
     */
    public function createContribution(array $data, int $createdBy): EditionContribution
    {
        return DB::transaction(function () use ($data, $createdBy) {
            $edition = Edition::findOrFail($data['edition_id']);

            if (isset($data['new_contributor'])) {
                $contributor = $this->contributors->createContributor($data['new_contributor']);

                if (! empty($data['add_to_committee'])) {
                    $this->committee->addMember($edition, $contributor->id);
                }
            } else {
                $contributor = $this->resolveContributor((int) $data['contributor_id']);
            }

            $category = $contributor->isCommitteeMemberOf($edition) ? 'Committee Contribution' : 'General Contribution';

            $transaction = EditionTransaction::create([
                'edition_id' => $data['edition_id'],
                'type' => 'income',
                'category' => $category,
                'amount' => $data['amount'],
                'transaction_date' => $data['contributed_at'],
                'description' => 'Contribution from '.$contributor->name,
                'created_by' => $createdBy,
            ]);

            return EditionContribution::create([
                'edition_id' => $data['edition_id'],
                'contributor_id' => $contributor->id,
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
     * that the selected contributor actually exists and is currently
     * active.
     */
    private function resolveContributor(int $contributorId): Contributor
    {
        $contributor = Contributor::query()->where('is_active', true)->find($contributorId);

        if (! $contributor) {
            throw ValidationException::withMessages([
                'contributor_id' => __('The selected contributor must be active.'),
            ]);
        }

        return $contributor;
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
