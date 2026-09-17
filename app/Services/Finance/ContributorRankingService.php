<?php

namespace App\Services\Finance;

use App\Models\Edition;
use App\Models\EditionContribution;

/**
 * Combines CommitteeMember and general Contributor contributions into
 * one edition-scoped public recognition ranking. Read-only: never
 * writes to EditionContribution/EditionTransaction, and never rewrites
 * which identity a contribution row was actually recorded against (see
 * EditionContributionService, Phase 3.38B2) — this only aggregates
 * existing rows for display.
 *
 * Canonical identity: a contribution sourced directly from a
 * CommitteeMember, or from a Contributor explicitly linked to one via
 * contributors.committee_member_id, both count toward that SAME
 * CommitteeMember's total. An unlinked Contributor is its own canonical
 * identity. This link is the only thing ever used to combine two rows
 * into one person — never name/phone matching.
 */
class ContributorRankingService
{
    /**
     * @return list<array{position: int, is_top_ten: bool, name: string, total_amount: float, photo_path: ?string}>
     */
    public function getEditionRanking(Edition $edition): array
    {
        $contributions = EditionContribution::query()
            ->where('edition_id', $edition->id)
            ->with([
                'committeeMember:id,name',
                'committeeMember.contributor:id,committee_member_id,photo_path',
                'contributor:id,name,committee_member_id,photo_path',
                'contributor.committeeMember:id,name',
            ])
            ->get(['id', 'committee_member_id', 'contributor_id', 'amount']);

        $totals = [];

        foreach ($contributions as $contribution) {
            [$key, $name, $photoPath] = $this->canonicalIdentity($contribution);

            // Integer-cents accumulation: repeated float addition across
            // many rows can drift; summing whole paise cannot.
            $cents = (int) round(((float) $contribution->amount) * 100);

            $totals[$key] ??= ['name' => $name, 'cents' => 0, 'photo_path' => $photoPath];
            $totals[$key]['cents'] += $cents;
        }

        $rows = collect($totals)
            ->map(fn (array $row, string $key) => [
                'key' => $key,
                'name' => $row['name'],
                'cents' => $row['cents'],
                'photo_path' => $row['photo_path'],
            ])
            // Descending cents, then ascending name, then ascending key as
            // a final stable tie-break — negating cents lets one single
            // ascending array comparison express "cents DESC, name ASC,
            // key ASC" without confusing swapped-side logic.
            ->sort(fn (array $a, array $b) => [-$a['cents'], $a['name'], $a['key']] <=> [-$b['cents'], $b['name'], $b['key']])
            ->values();

        return $rows
            ->map(function (array $row, int $index) {
                $position = $index + 1;

                return [
                    'position' => $position,
                    'is_top_ten' => $position <= 10,
                    'name' => $row['name'],
                    // PHP's `/` returns an int, not a float, when the
                    // division happens to be exact (e.g. 500000/100) —
                    // cast explicitly so callers always get the
                    // documented float regardless of the cents value.
                    'total_amount' => (float) ($row['cents'] / 100),
                    // Public presentation value only — the RAW storage
                    // path (never a resolved URL/internal canonical
                    // key), exactly like every other photo_path this
                    // project already returns to Blade. total_amount
                    // stays in this array regardless of whether any
                    // caller renders it — see Phase 3.40's public
                    // amount-hiding requirement, which is a Blade-layer
                    // presentation rule, not a service-layer one.
                    'photo_path' => $row['photo_path'],
                ];
            })
            ->all();
    }

    /**
     * Resolves BOTH the canonical identity key/name AND the one
     * deterministic photo source for that identity (Phase 3.40) —
     * never by name/phone matching, only via the existing explicit
     * committee_member_id link:
     *
     * - a direct CommitteeMember contribution uses that member's
     *   explicitly linked Contributor's photo, if any;
     * - a Contributor contribution linked to a CommitteeMember collapses
     *   into that SAME canonical identity, so it uses its own photo
     *   (which, by definition of the link, is that same linked
     *   Contributor);
     * - a standalone, unlinked Contributor uses its own photo.
     *
     * @return array{0: string, 1: string, 2: ?string} [canonical identity key, display name, photo_path]
     */
    private function canonicalIdentity(EditionContribution $contribution): array
    {
        if ($contribution->committee_member_id !== null) {
            $committeeMember = $contribution->committeeMember;

            return [
                'committee:'.$committeeMember->id,
                $committeeMember->name,
                $committeeMember->contributor?->photo_path,
            ];
        }

        $contributor = $contribution->contributor;

        if ($contributor->committee_member_id !== null) {
            return ['committee:'.$contributor->committee_member_id, $contributor->committeeMember->name, $contributor->photo_path];
        }

        return ['contributor:'.$contributor->id, $contributor->name, $contributor->photo_path];
    }
}
