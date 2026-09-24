<?php

namespace Database\Seeders\Demo;

use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionCommitteeMember;
use App\Models\EditionTransaction;
use App\Models\User;
use App\Services\Finance\EditionContributionService;
use Illuminate\Database\Seeder;

/**
 * Phase 3.48 — general "Chanda" contributors, committee contributors
 * (an edition-specific EditionCommitteeMember membership on the SAME
 * Contributor identity, not a separate person type), their
 * contributions, and the additional manual finance-ledger entries
 * beyond what contributions already create. All contributions go
 * through EditionContributionService::createContribution() — the one
 * place that keeps a contribution and its linked EditionTransaction
 * income row in sync — never a direct EditionContribution::create()
 * call. Registration fees are deliberately never linked into this
 * ledger (see Edition Transactions' existing design note); only
 * contributions and these standalone manual entries ever create an
 * EditionTransaction here.
 *
 * The active edition's committee is deliberately seeded with every dues
 * status the Finance "Committee" tab can show (not paid / partially
 * paid / paid in full / over target) against the default
 * finance.committee_minimum_contribution (₹1000) — see
 * seedActiveFinance()'s per-member comments for exactly which is which.
 */
class DemoFinanceSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const COMMITTEE_CONTRIBUTORS = [
        'Ashwini Deshpande', 'Baban Kale', 'Chandrakant Salunkhe', 'Dattatray Bhoir', 'Eknath Wagh',
        'Fakir Shaikh', 'Govind Thorat', 'Hemant Raut', 'Ishwar Nikam', 'Jayram Bhagat',
    ];

    /**
     * General/"Chanda" contributors — never on any committee.
     *
     * @var list<string>
     */
    private const GENERAL_CONTRIBUTORS = [
        'Meera Kulkarni', 'Nandini Rane', 'Om Prakash Sharma', 'Pallavi Joshi', 'Qasim Sheikh',
        'Rukmini Bhosale', 'Shalini Patil', 'Tanaji Gaikwad', 'Uttam Kadam', 'Vaishali Mane',
        'Waman Pisal', 'Yamini Chavan', 'Zaheer Pathan', 'Anjali Kamble',
    ];

    public function __construct(private readonly EditionContributionService $contributions) {}

    public function run(): void
    {
        $admin = User::where('email', 'admin@rppl.test')->firstOrFail();

        $committeeContributors = $this->seedContributors(self::COMMITTEE_CONTRIBUTORS, 98);
        $generalContributors = $this->seedContributors(self::GENERAL_CONTRIBUTORS, 97);

        $historical = Edition::where('year', DemoEditionSeeder::HISTORICAL_YEAR)->firstOrFail();
        $active = Edition::where('year', DemoEditionSeeder::ACTIVE_YEAR)->firstOrFail();

        if (EditionTransaction::where('edition_id', $active->id)->exists()) {
            return;
        }

        $this->seedHistoricalFinance($historical, $committeeContributors, $generalContributors, $admin);
        $this->seedActiveFinance($active, $committeeContributors, $generalContributors, $admin);
    }

    /**
     * @param  list<string>  $names
     * @return list<Contributor>
     */
    private function seedContributors(array $names, int $phonePrefix): array
    {
        return collect($names)
            ->map(fn (string $name, int $index) => Contributor::firstOrCreate(
                ['name' => $name],
                ['phone' => sprintf('%d%08d', $phonePrefix, 10000000 + $index), 'is_active' => true]
            ))
            ->all();
    }

    /**
     * @param  list<Contributor>  $committeeContributors
     * @param  list<Contributor>  $generalContributors
     */
    private function seedHistoricalFinance(Edition $edition, array $committeeContributors, array $generalContributors, User $admin): void
    {
        // Only the two who actually contributed become committee members
        // of this edition — membership is reconstructed from
        // contribution evidence, same rule the Phase 3.48 data migration
        // uses for real historical data.
        $this->addToCommittee($edition, $committeeContributors[0]);
        $this->addToCommittee($edition, $committeeContributors[1]);

        $this->contribute($edition, $committeeContributors[0], 1500, '-13 months', $admin);
        $this->contribute($edition, $committeeContributors[1], 2000, '-13 months', $admin);
        $this->contribute($edition, $generalContributors[5], 500, '-12 months', $admin);

        $this->manualTransaction($edition, 'income', 'Sponsorship', 8000, '-14 months', $admin);
        $this->manualTransaction($edition, 'expense', 'Ground Preparation', 3000, '-13 months', $admin);
        $this->manualTransaction($edition, 'expense', 'Trophies', 2500, '-12 months', $admin);
    }

    /**
     * @param  list<Contributor>  $committeeContributors
     * @param  list<Contributor>  $generalContributors
     */
    private function seedActiveFinance(Edition $edition, array $committeeContributors, array $generalContributors, User $admin): void
    {
        // Every committee contributor is a member of THIS edition's
        // committee (added before any contribution, exactly like the
        // admin would do from the Finance "Committee" tab) — deliberately
        // covering every dues status against the default ₹1000 target:
        foreach ($committeeContributors as $contributor) {
            $this->addToCommittee($edition, $contributor);
        }

        // index 0: NOT PAID — no contribution at all this edition.

        // index 1: PARTIALLY PAID — two installments summing to less than the target.
        $this->contribute($edition, $committeeContributors[1], 300, '-9 weeks', $admin);
        $this->contribute($edition, $committeeContributors[1], 200, '-2 weeks', $admin);

        // index 2: PAID IN FULL — one payment exactly at the target.
        $this->contribute($edition, $committeeContributors[2], 1000, '-8 weeks', $admin);

        // index 3: PAID IN FULL via installments summing exactly to the target.
        $this->contribute($edition, $committeeContributors[3], 600, '-7 weeks', $admin);
        $this->contribute($edition, $committeeContributors[3], 400, '-1 week', $admin);

        // index 4: OVER TARGET — a single payment above the target;
        // remaining floors at 0, never treated as an error.
        $this->contribute($edition, $committeeContributors[4], 1500, '-6 weeks', $admin);

        // index 5-8: PAID IN FULL with a mix of amounts above the target,
        // some contributing twice — exercises multi-payment aggregation
        // for the public leaderboard/ranking too.
        foreach (array_slice($committeeContributors, 5, 4) as $offset => $contributor) {
            $this->contribute($edition, $contributor, 1000 + ($offset * 250), sprintf('-%d weeks', 5 - $offset), $admin);
        }
        $this->contribute($edition, $committeeContributors[5], 200, '-1 week', $admin);

        // index 9: NOT PAID — a second not-paid member, so the Committee
        // tab's "Not Paid" count is never just a single-row coincidence.

        // General (non-committee) contributors — standalone identities,
        // enough distinct people for every public leaderboard badge tier
        // (Top Contributor / 2nd / 3rd / Top 10 / outside Top 10).
        foreach ($generalContributors as $index => $contributor) {
            $this->contribute($edition, $contributor, 200 + ($index * 75), sprintf('-%d days', 60 - ($index * 3)), $admin);
        }

        // The last general contributor also gives a second, larger
        // payment — multi-payment aggregation for a non-committee
        // contributor too, not just committee members.
        $this->contribute($edition, $generalContributors[array_key_last($generalContributors)], 900, '-5 days', $admin);

        $this->manualTransaction($edition, 'income', 'Sponsorship', 15000, '-2 months', $admin);
        $this->manualTransaction($edition, 'income', 'Registration Support Grant', 5000, '-6 weeks', $admin);
        $this->manualTransaction($edition, 'income', 'Ground Support', 3000, '-1 month', $admin);
        $this->manualTransaction($edition, 'expense', 'Ground Preparation', 4500, '-5 weeks', $admin);
        $this->manualTransaction($edition, 'expense', 'Equipment', 6200, '-4 weeks', $admin);
        $this->manualTransaction($edition, 'expense', 'Trophies', 3800, '-3 weeks', $admin);
        $this->manualTransaction($edition, 'expense', 'Refreshments', 2100, '-2 weeks', $admin);
        $this->manualTransaction($edition, 'expense', 'Printing', 900, '-10 days', $admin);
    }

    private function addToCommittee(Edition $edition, Contributor $contributor): void
    {
        EditionCommitteeMember::firstOrCreate(['edition_id' => $edition->id, 'contributor_id' => $contributor->id]);
    }

    private function contribute(Edition $edition, Contributor $contributor, float $amount, string $contributedAt, User $admin): void
    {
        $this->contributions->createContribution([
            'edition_id' => $edition->id,
            'contributor_id' => $contributor->id,
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
