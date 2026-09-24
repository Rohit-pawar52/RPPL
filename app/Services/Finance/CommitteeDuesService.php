<?php

namespace App\Services\Finance;

use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionCommitteeMember;
use App\Models\EditionContribution;
use App\Services\Settings\SettingsService;

/**
 * Phase 3.48 — committee dues (target/paid/remaining/status) per
 * contributor per edition. The target is a single GLOBAL Settings value
 * (finance.committee_minimum_contribution, an explicit product decision
 * overriding the original per-edition roadmap proposal) applied against
 * however many editions a contributor has been a committee member of —
 * there is no per-edition historical snapshot of the target, so
 * changing the setting changes every edition's displayed dues the next
 * time they're viewed. Contribution AMOUNTS already recorded are never
 * affected by a later target change — only the target itself, and
 * therefore "remaining"/"status", are derived fresh on every read.
 */
class CommitteeDuesService
{
    public function __construct(private readonly SettingsService $settings) {}

    public function target(): float
    {
        return (float) $this->settings->get('finance.committee_minimum_contribution');
    }

    /**
     * One contributor's dues for one edition — used by the contribution
     * form's "Committee Member" badge (target/paid/remaining preview)
     * and anywhere a single row is needed rather than the whole table.
     *
     * @return array{target: float, paid: float, remaining: float, status: string}
     */
    public function duesFor(Contributor $contributor, Edition $edition): array
    {
        $target = $this->target();
        $paid = $this->paidTotal($contributor->id, $edition->id);

        return $this->duesRow($target, $paid);
    }

    /**
     * Every committee member of $edition with their dues — the Finance
     * "Committee" tab table and the Overview tab's dues summary both
     * build on this one query set, so the two screens can never
     * disagree.
     *
     * @return list<array{contributor: Contributor, membership: EditionCommitteeMember, target: float, paid: float, remaining: float, status: string}>
     */
    public function duesForEdition(Edition $edition): array
    {
        $target = $this->target();

        $memberships = $edition->committeeMemberships()->with('contributor')->get();

        $paidTotals = EditionContribution::query()
            ->where('edition_id', $edition->id)
            ->whereIn('contributor_id', $memberships->pluck('contributor_id'))
            ->selectRaw('contributor_id, SUM(amount) as total')
            ->groupBy('contributor_id')
            ->pluck('total', 'contributor_id');

        return $memberships
            ->map(function ($membership) use ($target, $paidTotals) {
                $paid = (float) ($paidTotals[$membership->contributor_id] ?? 0);

                return array_merge(
                    ['contributor' => $membership->contributor, 'membership' => $membership],
                    $this->duesRow($target, $paid)
                );
            })
            ->sortBy(fn (array $row) => $row['contributor']->name)
            ->values()
            ->all();
    }

    /**
     * Aggregate dues figures for the Finance Overview tab — counts by
     * status plus the total target/contributed/remaining across every
     * committee member of the edition.
     *
     * @return array{total_members: int, paid_in_full: int, partially_paid: int, not_paid: int, total_target: float, total_paid: float, total_remaining: float}
     */
    public function summaryForEdition(Edition $edition): array
    {
        $dues = $this->duesForEdition($edition);

        return [
            'total_members' => count($dues),
            'paid_in_full' => count(array_filter($dues, fn (array $row) => $row['status'] === 'paid in full')),
            'partially_paid' => count(array_filter($dues, fn (array $row) => $row['status'] === 'partially paid')),
            'not_paid' => count(array_filter($dues, fn (array $row) => $row['status'] === 'not paid')),
            'total_target' => array_sum(array_column($dues, 'target')),
            'total_paid' => array_sum(array_column($dues, 'paid')),
            'total_remaining' => array_sum(array_column($dues, 'remaining')),
        ];
    }

    private function paidTotal(int $contributorId, int $editionId): float
    {
        return (float) EditionContribution::query()
            ->where('edition_id', $editionId)
            ->where('contributor_id', $contributorId)
            ->sum('amount');
    }

    /**
     * @return array{target: float, paid: float, remaining: float, status: string}
     */
    private function duesRow(float $target, float $paid): array
    {
        return [
            'target' => $target,
            'paid' => $paid,
            'remaining' => max($target - $paid, 0.0),
            'status' => $this->statusFor($paid, $target),
        ];
    }

    /**
     * paid <= 0 => "not paid"; 0 < paid < target => "partially paid";
     * paid >= target => "paid in full". Overpayment is never an error —
     * it simply reports as "paid in full" with remaining already
     * floored to 0 by duesRow(). Space-separated (not snake_case): this
     * is the exact string <x-status-badge> renders and keys its color
     * on, so there is only one representation to keep in sync, not two.
     */
    private function statusFor(float $paid, float $target): string
    {
        if ($paid <= 0) {
            return 'not paid';
        }

        if ($paid < $target) {
            return 'partially paid';
        }

        return 'paid in full';
    }
}
