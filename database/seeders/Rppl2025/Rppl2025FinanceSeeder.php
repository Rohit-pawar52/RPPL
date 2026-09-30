<?php

namespace Database\Seeders\Rppl2025;

use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionCommitteeMember;
use App\Models\EditionTransaction;
use App\Models\User;
use App\Services\Finance\EditionContributionService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Finance for the completed RPPL 2025 season: committee dues fully paid
 * (the season is over), a couple of general contributions, a sponsorship
 * and the usual running costs, all on absolute Feb-Apr 2025 dates
 * (Asia/Kolkata). Contributions go through EditionContributionService so
 * the linked income transaction is created the real way. Income is
 * clearly above expenses. Mirrors Rppl2026FinanceSeeder's contributors.
 */
class Rppl2025FinanceSeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const COMMITTEE_CONTRIBUTORS = ['Ashwini Deshpande', 'Baban Kale', 'Chandrakant Salunkhe'];

    /**
     * @var list<string>
     */
    private const GENERAL_CONTRIBUTORS = ['Meera Kulkarni', 'Nandini Rane'];

    public function __construct(private readonly EditionContributionService $contributions) {}

    public function run(): void
    {
        $edition = Edition::where('year', Rppl2025EditionSeeder::YEAR)->firstOrFail();

        if (EditionTransaction::where('edition_id', $edition->id)->exists()) {
            return;
        }

        $admin = User::where('email', 'admin@rppl.test')->firstOrFail();

        $committee = collect(self::COMMITTEE_CONTRIBUTORS)
            ->map(fn (string $name, int $i) => Contributor::firstOrCreate(
                ['name' => $name],
                ['phone' => sprintf('96%08d', 10000000 + $i), 'is_active' => true]
            ))
            ->all();

        $general = collect(self::GENERAL_CONTRIBUTORS)
            ->map(fn (string $name, int $i) => Contributor::firstOrCreate(
                ['name' => $name],
                ['phone' => sprintf('95%08d', 10000000 + $i), 'is_active' => true]
            ))
            ->all();

        foreach ($committee as $contributor) {
            EditionCommitteeMember::firstOrCreate(['edition_id' => $edition->id, 'contributor_id' => $contributor->id]);
        }

        // Dues are the same 1000 each as in 2026; here every member has
        // settled in full (two instalments for one of them).
        $this->contribute($edition, $committee[0], 1000, '2025-02-08', $admin);
        $this->contribute($edition, $committee[1], 600, '2025-02-12', $admin);
        $this->contribute($edition, $committee[1], 400, '2025-03-05', $admin);
        $this->contribute($edition, $committee[2], 1000, '2025-02-15', $admin);

        $this->contribute($edition, $general[0], 500, '2025-03-02', $admin);
        $this->contribute($edition, $general[1], 300, '2025-03-20', $admin);

        $this->manual($edition, 'income', 'Sponsorship', 12000, '2025-03-10', 'Local business sponsorship for the season', $admin);

        $this->manual($edition, 'expense', 'Ground Preparation', 3500, '2025-03-28', 'Pitch rolling and boundary marking', $admin);
        $this->manual($edition, 'expense', 'Trophies', 2800, '2025-04-01', 'Winner and runner-up trophies', $admin);
        $this->manual($edition, 'expense', 'Balls', 1800, '2025-03-30', 'Match balls for the season', $admin);
        $this->manual($edition, 'expense', 'Umpires', 2400, '2025-04-27', 'Umpire honorarium for all matches', $admin);
        $this->manual($edition, 'expense', 'Refreshments', 1500, '2025-04-27', 'Water and snacks for players and officials', $admin);
        $this->manual($edition, 'expense', 'Prize Money', 3000, '2025-04-28', 'Prize money for the winning team', $admin);
    }

    private function contribute(Edition $edition, Contributor $contributor, float $amount, string $date, User $admin): void
    {
        $this->contributions->createContribution([
            'edition_id' => $edition->id,
            'contributor_id' => $contributor->id,
            'amount' => $amount,
            'contributed_at' => $date,
        ], $admin->id);
    }

    private function manual(Edition $edition, string $type, string $category, float $amount, string $date, string $description, User $admin): void
    {
        EditionTransaction::create([
            'edition_id' => $edition->id,
            'type' => $type,
            'category' => $category,
            'amount' => $amount,
            'transaction_date' => Carbon::createFromFormat('Y-m-d', $date, 'Asia/Kolkata')->startOfDay()->format('Y-m-d'),
            'description' => $description,
            'created_by' => $admin->id,
        ]);
    }
}
