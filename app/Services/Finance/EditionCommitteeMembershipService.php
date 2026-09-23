<?php

namespace App\Services\Finance;

use App\Models\Edition;
use App\Models\EditionCommitteeMember;
use App\Models\EditionContribution;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3.48 — manages which Contributors are on a given edition's
 * committee. Membership itself carries no financial data (see
 * CommitteeDuesService for target/paid/remaining) — this service only
 * owns the edition_committee_members rows.
 */
class EditionCommitteeMembershipService
{
    /**
     * Idempotent by the table's own unique(edition_id, contributor_id)
     * constraint — adding an already-current member is a silent no-op,
     * never a duplicate row or an error.
     */
    public function addMember(Edition $edition, int $contributorId): EditionCommitteeMember
    {
        return EditionCommitteeMember::query()->firstOrCreate([
            'edition_id' => $edition->id,
            'contributor_id' => $contributorId,
        ]);
    }

    /**
     * Removing a membership never touches the Contributor, their
     * contributions, or their linked transactions — only this one
     * edition-specific membership row. Blocked when the contributor has
     * recorded contribution history for THIS edition: removing
     * membership while that history exists would contradict it (the
     * dues table, the contribution form's badge, and the public
     * "Committee Member" label would all start disagreeing with what
     * actually happened). Deactivating the Contributor entirely, or simply
     * leaving the historical membership in place, are the two safe
     * alternatives — this method does not choose between them.
     */
    public function removeMember(Edition $edition, int $contributorId): bool
    {
        $hasHistory = EditionContribution::query()
            ->where('edition_id', $edition->id)
            ->where('contributor_id', $contributorId)
            ->exists();

        if ($hasHistory) {
            return false;
        }

        EditionCommitteeMember::query()
            ->where('edition_id', $edition->id)
            ->where('contributor_id', $contributorId)
            ->delete();

        return true;
    }

    /**
     * The edition with the largest `year` strictly less than $edition's
     * own year — this project's existing convention for "previous
     * edition" ordering (see Edition::orderByDesc('year') used
     * throughout). Never guesses across a gap in years; whichever
     * edition is chronologically immediately before is used, however far
     * back that is.
     */
    public function previousEdition(Edition $edition): ?Edition
    {
        return Edition::query()
            ->where('year', '<', $edition->year)
            ->orderByDesc('year')
            ->first();
    }

    /**
     * Copies every committee membership from the previous edition (see
     * previousEdition()) onto $edition — membership only, never
     * contributions/payment history. Idempotent: a Contributor already
     * on $edition's committee is silently skipped, never duplicated.
     *
     * @return array{added: int, already_existed: int, previous_edition: ?Edition}
     */
    public function copyFromPreviousEdition(Edition $edition): array
    {
        $previous = $this->previousEdition($edition);

        if (! $previous) {
            return ['added' => 0, 'already_existed' => 0, 'previous_edition' => null];
        }

        return DB::transaction(function () use ($edition, $previous) {
            $previousContributorIds = EditionCommitteeMember::query()
                ->where('edition_id', $previous->id)
                ->pluck('contributor_id');

            $existingContributorIds = EditionCommitteeMember::query()
                ->where('edition_id', $edition->id)
                ->whereIn('contributor_id', $previousContributorIds)
                ->pluck('contributor_id')
                ->all();

            $toAdd = $previousContributorIds->diff($existingContributorIds);

            $now = now();
            $rows = $toAdd->map(fn (int $contributorId) => [
                'edition_id' => $edition->id,
                'contributor_id' => $contributorId,
                'created_at' => $now,
                'updated_at' => $now,
            ])->all();

            if (! empty($rows)) {
                EditionCommitteeMember::query()->insert($rows);
            }

            return [
                'added' => count($rows),
                'already_existed' => count($existingContributorIds),
                'previous_edition' => $previous,
            ];
        });
    }
}
