<?php

namespace App\Services\Finance;

use App\Models\Edition;
use App\Models\EditionContribution;

/**
 * The public edition-scoped contributor recognition ranking. Read-only:
 * never writes to EditionContribution/EditionTransaction.
 *
 * Phase 3.48: one Contributor identity per person means every
 * contribution row already carries its real, final identity
 * (contributor_id) — no canonical-identity merge logic is needed any
 * more (there used to be a separate CommitteeMember identity that could
 * be explicitly linked to a Contributor; both have collapsed into one).
 * This service now simply groups by contributor_id.
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
            ->with('contributor:id,name,photo_path')
            ->get(['id', 'contributor_id', 'amount']);

        $totals = [];

        foreach ($contributions as $contribution) {
            $contributor = $contribution->contributor;

            if (! $contributor) {
                continue;
            }

            // Integer-cents accumulation: repeated float addition across
            // many rows can drift; summing whole paise cannot.
            $cents = (int) round(((float) $contribution->amount) * 100);

            $totals[$contributor->id] ??= ['name' => $contributor->name, 'cents' => 0, 'photo_path' => $contributor->photo_path];
            $totals[$contributor->id]['cents'] += $cents;
        }

        $rows = collect($totals)
            ->map(fn (array $row, int $contributorId) => [
                'key' => $contributorId,
                'name' => $row['name'],
                'cents' => $row['cents'],
                'photo_path' => $row['photo_path'],
            ])
            // Descending cents, then ascending name, then ascending id as
            // a final stable tie-break — negating cents lets one single
            // ascending array comparison express "cents DESC, name ASC,
            // id ASC" without confusing swapped-side logic.
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
}
