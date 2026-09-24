<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 3.48 data migration — collapses the CommitteeMember identity into
 * Contributor (one person = one Contributor identity) and reconstructs
 * edition-specific committee membership from contribution evidence.
 *
 * Purely additive and non-destructive: no row in committee_members,
 * contributors, or edition_contributions is deleted, and no existing
 * committee_member_id/contributor_id value already present is ever
 * overwritten. Only the following are written:
 *   1. A new `contributors` row for any CommitteeMember that had no
 *      explicit Contributor link yet (contributors.committee_member_id
 *      is set on it, purely as a traceability breadcrumb back to the
 *      legacy row — application code no longer reads that column for
 *      identity purposes after this phase).
 *   2. edition_contributions.contributor_id is backfilled (only where it
 *      was still null) for every row that was recorded against a
 *      committee_member_id, using the same explicit-link-or-new-row
 *      mapping as (1) — never a name/phone match.
 *   3. One edition_committee_members row per (edition_id, contributor_id)
 *      pair for which a committee-sourced contribution exists — "this
 *      person made a committee contribution in this edition" is the
 *      only evidence this phase uses to reconstruct membership, per the
 *      product decision recorded in Phase 3.48's roadmap note. A
 *      CommitteeMember who never actually contributed gets a Contributor
 *      identity preserved here but no edition membership row — there is
 *      no historical evidence of which edition(s) they belonged to, and
 *      this migration deliberately does not guess.
 *
 * committee_members and edition_contributions.committee_member_id are
 * left fully intact (legacy/unused going forward, not physically
 * removed this phase — see the Phase 3.48 report for why).
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        // 1. CommitteeMember -> Contributor identity mapping. Reuse an
        // existing explicit link; otherwise create one new Contributor,
        // never merged/matched by name.
        $committeeMembers = DB::table('committee_members')->get(['id', 'name', 'phone', 'is_active']);
        $existingLinks = DB::table('contributors')
            ->whereNotNull('committee_member_id')
            ->pluck('id', 'committee_member_id');

        $committeeMemberToContributor = [];

        foreach ($committeeMembers as $committeeMember) {
            if (isset($existingLinks[$committeeMember->id])) {
                $committeeMemberToContributor[$committeeMember->id] = $existingLinks[$committeeMember->id];

                continue;
            }

            $committeeMemberToContributor[$committeeMember->id] = DB::table('contributors')->insertGetId([
                'name' => $committeeMember->name,
                'phone' => $committeeMember->phone,
                'committee_member_id' => $committeeMember->id,
                'is_active' => $committeeMember->is_active,
                'photo_path' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if (empty($committeeMemberToContributor)) {
            return;
        }

        // 2. Backfill edition_contributions.contributor_id for
        // committee-sourced rows that don't already have one (a row
        // already carrying both — shouldn't exist per the pre-Phase-3.48
        // invariant, but is left untouched either way).
        $committeeContributions = DB::table('edition_contributions')
            ->whereNotNull('committee_member_id')
            ->whereNull('contributor_id')
            ->get(['id', 'committee_member_id']);

        foreach ($committeeContributions as $contribution) {
            $contributorId = $committeeMemberToContributor[$contribution->committee_member_id] ?? null;

            if ($contributorId === null) {
                continue;
            }

            DB::table('edition_contributions')
                ->where('id', $contribution->id)
                ->update(['contributor_id' => $contributorId]);
        }

        // 3. Reconstruct edition_committee_members from committee-sourced
        // contribution evidence (edition_id + contributor_id pairs),
        // deduplicated, inserted only where the pair doesn't already
        // exist (idempotent under the unique(edition_id, contributor_id)
        // constraint).
        $membershipPairs = DB::table('edition_contributions')
            ->whereNotNull('committee_member_id')
            ->select('edition_id', 'committee_member_id')
            ->distinct()
            ->get();

        $existingMemberships = DB::table('edition_committee_members')
            ->get(['edition_id', 'contributor_id'])
            ->map(fn ($row) => $row->edition_id.':'.$row->contributor_id)
            ->flip();

        foreach ($membershipPairs as $pair) {
            $contributorId = $committeeMemberToContributor[$pair->committee_member_id] ?? null;

            if ($contributorId === null) {
                continue;
            }

            $dedupeKey = $pair->edition_id.':'.$contributorId;

            if (isset($existingMemberships[$dedupeKey])) {
                continue;
            }

            DB::table('edition_committee_members')->insert([
                'edition_id' => $pair->edition_id,
                'contributor_id' => $contributorId,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            $existingMemberships[$dedupeKey] = true;
        }
    }

    /**
     * Deliberately a no-op: this migration only ever creates new
     * contributors/edition_committee_members rows and fills previously-
     * null contributor_id values — there is no reliable way to tell
     * "created by this migration" apart from "created normally
     * afterward" on rollback, so reversing it safely is not possible.
     * Rolling back the schema migration before this one is not
     * supported either for the same reason (see its own down()).
     */
    public function down(): void
    {
        // Intentionally left blank — see class docblock.
    }
};
