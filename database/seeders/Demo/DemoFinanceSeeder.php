<?php

namespace Database\Seeders\Demo;

use App\Models\CommitteeMember;
use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionTransaction;
use App\Models\User;
use App\Services\Finance\EditionContributionService;
use Illuminate\Database\Seeder;

/**
 * Committee members, general "Chanda" contributors, their contributions,
 * and the additional manual finance-ledger entries beyond what
 * contributions already create. All contributions go through
 * EditionContributionService::createContribution() — the one place that
 * keeps a contribution and its linked EditionTransaction income row in
 * sync — never a direct EditionContribution::create() call. Registration
 * fees are deliberately never linked into this ledger (see Edition
 * Transactions' existing design note); only contributions and these
 * standalone manual entries ever create an EditionTransaction here.
 */
class DemoFinanceSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const COMMITTEE_MEMBERS = [
        'Ashwini Deshpande', 'Baban Kale', 'Chandrakant Salunkhe', 'Dattatray Bhoir', 'Eknath Wagh',
        'Fakir Shaikh', 'Govind Thorat', 'Hemant Raut', 'Ishwar Nikam', 'Jayram Bhagat',
    ];

    /**
     * A mix of standalone general contributors and a few explicitly
     * linked to a committee member (Phase 3.38B2's identity link) — index
     * positions in LINKED_TO_COMMITTEE_INDEX below reference
     * self::COMMITTEE_MEMBERS by position.
     *
     * @var list<string>
     */
    private const CONTRIBUTORS = [
        'Meera Kulkarni', 'Nandini Rane', 'Om Prakash Sharma', 'Pallavi Joshi', 'Qasim Sheikh',
        'Rukmini Bhosale', 'Shalini Patil', 'Tanaji Gaikwad', 'Uttam Kadam', 'Vaishali Mane',
        'Waman Pisal', 'Yamini Chavan', 'Zaheer Pathan', 'Anjali Kamble',
    ];

    /**
     * Contributor index => CommitteeMember index it is explicitly linked
     * to. These contributions collapse into the committee member's own
     * canonical leaderboard identity (ContributorRankingService) —
     * demonstrating that linkage, not just standalone contributors.
     *
     * @var array<int, int>
     */
    private const LINKED_TO_COMMITTEE_INDEX = [
        0 => 0, // Meera Kulkarni -> Ashwini Deshpande
        1 => 2, // Nandini Rane -> Chandrakant Salunkhe
        2 => 4, // Om Prakash Sharma -> Eknath Wagh
        3 => 6, // Pallavi Joshi -> Govind Thorat
    ];

    public function __construct(private readonly EditionContributionService $contributions) {}

    public function run(): void
    {
        $admin = User::where('email', 'admin@rppl.test')->firstOrFail();

        $committeeMembers = $this->seedCommitteeMembers();
        $contributors = $this->seedContributors($committeeMembers);

        $historical = Edition::where('year', DemoEditionSeeder::HISTORICAL_YEAR)->firstOrFail();
        $active = Edition::where('year', DemoEditionSeeder::ACTIVE_YEAR)->firstOrFail();

        if (EditionTransaction::where('edition_id', $active->id)->exists()) {
            return;
        }

        $this->seedHistoricalFinance($historical, $committeeMembers, $contributors, $admin);
        $this->seedActiveFinance($active, $committeeMembers, $contributors, $admin);
    }

    /**
     * @return list<CommitteeMember>
     */
    private function seedCommitteeMembers(): array
    {
        return collect(self::COMMITTEE_MEMBERS)
            ->map(fn (string $name, int $index) => CommitteeMember::firstOrCreate(
                ['name' => $name],
                ['phone' => sprintf('98%08d', 10000000 + $index), 'is_active' => $index !== 9]
            ))
            ->all();
    }

    /**
     * @param  list<CommitteeMember>  $committeeMembers
     * @return list<Contributor>
     */
    private function seedContributors(array $committeeMembers): array
    {
        return collect(self::CONTRIBUTORS)
            ->map(function (string $name, int $index) use ($committeeMembers) {
                $linkedCommitteeIndex = self::LINKED_TO_COMMITTEE_INDEX[$index] ?? null;

                return Contributor::firstOrCreate(
                    ['name' => $name],
                    [
                        'phone' => sprintf('97%08d', 20000000 + $index),
                        'committee_member_id' => $linkedCommitteeIndex !== null ? $committeeMembers[$linkedCommitteeIndex]->id : null,
                        'is_active' => true,
                    ]
                );
            })
            ->all();
    }

    /**
     * @param  list<CommitteeMember>  $committeeMembers
     * @param  list<Contributor>  $contributors
     */
    private function seedHistoricalFinance(Edition $edition, array $committeeMembers, array $contributors, User $admin): void
    {
        $this->contribute($edition, 'committee', $committeeMembers[0]->id, 1500, '-13 months', $admin);
        $this->contribute($edition, 'committee', $committeeMembers[1]->id, 2000, '-13 months', $admin);
        $this->contribute($edition, 'contributor', $contributors[5]->id, 500, '-12 months', $admin);

        $this->manualTransaction($edition, 'income', 'Sponsorship', 8000, '-14 months', $admin);
        $this->manualTransaction($edition, 'expense', 'Ground Preparation', 3000, '-13 months', $admin);
        $this->manualTransaction($edition, 'expense', 'Trophies', 2500, '-12 months', $admin);
    }

    /**
     * @param  list<CommitteeMember>  $committeeMembers
     * @param  list<Contributor>  $contributors
     */
    private function seedActiveFinance(Edition $edition, array $committeeMembers, array $contributors, User $admin): void
    {
        // Every committee member contributes at least once (>= the
        // committee minimum) — several contribute twice, to exercise
        // ContributorRankingService's multi-payment aggregation.
        foreach ($committeeMembers as $index => $member) {
            if (! $member->is_active) {
                continue;
            }

            $this->contribute($edition, 'committee', $member->id, 1000 + ($index * 250), sprintf('-%d weeks', 10 - $index), $admin);
        }

        $this->contribute($edition, 'committee', $committeeMembers[0]->id, 1200, '-2 weeks', $admin);
        $this->contribute($edition, 'committee', $committeeMembers[1]->id, 1000, '-1 week', $admin);

        // Standalone (unlinked) general contributors and the linked ones
        // both appear — together these give the public leaderboard enough
        // distinct canonical identities (10 committee + 10 standalone) to
        // demonstrate every badge tier (Top Contributor / 2nd / 3rd /
        // Top 10 / outside Top 10).
        foreach ($contributors as $index => $contributor) {
            $this->contribute($edition, 'contributor', $contributor->id, 200 + ($index * 75), sprintf('-%d days', 60 - ($index * 3)), $admin);
        }

        // Anjali Kamble (the last standalone contributor) also gives a
        // second, larger payment — multi-payment aggregation for an
        // unlinked contributor too, not just committee members.
        $this->contribute($edition, 'contributor', $contributors[array_key_last($contributors)]->id, 900, '-5 days', $admin);

        $this->manualTransaction($edition, 'income', 'Sponsorship', 15000, '-2 months', $admin);
        $this->manualTransaction($edition, 'income', 'Registration Support Grant', 5000, '-6 weeks', $admin);
        $this->manualTransaction($edition, 'income', 'Ground Support', 3000, '-1 month', $admin);
        $this->manualTransaction($edition, 'expense', 'Ground Preparation', 4500, '-5 weeks', $admin);
        $this->manualTransaction($edition, 'expense', 'Equipment', 6200, '-4 weeks', $admin);
        $this->manualTransaction($edition, 'expense', 'Trophies', 3800, '-3 weeks', $admin);
        $this->manualTransaction($edition, 'expense', 'Refreshments', 2100, '-2 weeks', $admin);
        $this->manualTransaction($edition, 'expense', 'Printing', 900, '-10 days', $admin);
    }

    private function contribute(Edition $edition, string $sourceType, int $sourceId, float $amount, string $contributedAt, User $admin): void
    {
        $this->contributions->createContribution([
            'edition_id' => $edition->id,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'amount' => $amount,
            'contributed_at' => now()->modify($contributedAt)->format('Y-m-d'),
        ], $admin->id);
    }

    private function manualTransaction(Edition $edition, string $type, string $category, float $amount, string $transactionDate, User $admin): void
    {
        EditionTransaction::create([
            'edition_id' => $edition->id,
            'type' => $type,
            'category' => $category,
            'amount' => $amount,
            'transaction_date' => now()->modify($transactionDate)->format('Y-m-d'),
            'description' => null,
            'created_by' => $admin->id,
        ]);
    }
}
