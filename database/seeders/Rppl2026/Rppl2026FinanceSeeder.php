<?php

namespace Database\Seeders\Rppl2026;

use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionCommitteeMember;
use App\Models\EditionTransaction;
use App\Models\User;
use App\Services\Finance\EditionContributionService;
use Illuminate\Database\Seeder;

/**
 * A SMALL amount of RPPL 2026 finance/committee demo data — enough that
 * the Finance admin screens aren't empty during manual UAT, deliberately
 * not the elaborate multi-status history Demo\DemoFinanceSeeder builds
 * for the old dataset. Every contribution goes through
 * EditionContributionService::createContribution(), the one place that
 * keeps a contribution and its linked EditionTransaction income row in
 * sync — never a direct EditionContribution::create() call.
 */
class Rppl2026FinanceSeeder extends Seeder
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
        $edition = Edition::where('year', Rppl2026EditionSeeder::YEAR)->firstOrFail();

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

        // One paid in full, one partially paid, one not paid — enough
        // dues-status variety for the Committee tab without building a
        // full status matrix.
        $this->contribute($edition, $committee[0], 1000, '-3 weeks', $admin);
        $this->contribute($edition, $committee[1], 400, '-1 week', $admin);

        $this->contribute($edition, $general[0], 500, '-2 weeks', $admin);
        $this->contribute($edition, $general[1], 300, '-4 days', $admin);

        $this->manualTransaction($edition, 'income', 'Sponsorship', 10000, '-1 month', $admin);
        $this->manualTransaction($edition, 'expense', 'Ground Preparation', 3000, '-3 weeks', $admin);
        $this->manualTransaction($edition, 'expense', 'Trophies', 2500, '-1 week', $admin);
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
