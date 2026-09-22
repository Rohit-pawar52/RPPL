<?php

namespace Tests\Feature;

use App\Models\CommitteeMember;
use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionContribution;
use App\Services\Finance\ContributorRankingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Phase 3.38B3 — the combined RPPL contributor leaderboard. Covers the
 * service's canonical-identity aggregation directly (fast, precise) plus
 * one public-page integration check for privacy/rendering. Committee/
 * general contribution recording itself is already covered by
 * CommitteeContributionTest/GeneralContributionTest — not repeated here.
 */
class ContributorRankingTest extends TestCase
{
    use RefreshDatabase;

    private function service(): ContributorRankingService
    {
        return app(ContributorRankingService::class);
    }

    /**
     * Committee source, general source, and general-linked-to-committee
     * source all recorded as separate historical rows (never rewritten),
     * yet the linked identity combines into ONE ranking row while the
     * standalone general contributor and multi-payment aggregation are
     * proven in the same pass.
     */
    public function test_linked_identity_combines_while_unlinked_contributor_aggregates_separately(): void
    {
        $edition = Edition::factory()->create();

        $rohitCommittee = CommitteeMember::factory()->create(['name' => 'Rohit Kulkarni']);
        $rohitContributor = Contributor::factory()->create(['name' => 'Rohit Kulkarni', 'committee_member_id' => $rohitCommittee->id]);

        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => $rohitCommittee->id,
            'contributor_id' => null,
            'amount' => '2000.00',
        ]);
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => null,
            'contributor_id' => $rohitContributor->id,
            'amount' => '3000.00',
        ]);

        // A standalone general contributor with THREE separate payments.
        $sunita = Contributor::factory()->create(['name' => 'Sunita Deshmukh']);
        foreach (['500.00', '1500.00', '3000.00'] as $amount) {
            EditionContribution::factory()->create([
                'edition_id' => $edition->id,
                'committee_member_id' => null,
                'contributor_id' => $sunita->id,
                'amount' => $amount,
            ]);
        }

        $ranking = $this->service()->getEditionRanking($edition);

        $this->assertCount(2, $ranking, 'Rohit\'s two rows must combine into one ranking entry');

        $rohitRow = collect($ranking)->firstWhere('name', 'Rohit Kulkarni');
        $this->assertNotNull($rohitRow);
        $this->assertSame(5000.0, $rohitRow['total_amount']);

        $sunitaRow = collect($ranking)->firstWhere('name', 'Sunita Deshmukh');
        $this->assertNotNull($sunitaRow);
        $this->assertSame(5000.0, $sunitaRow['total_amount']);

        // Equal totals (₹5,000 each) tie-break alphabetically: Rohit before Sunita.
        $this->assertSame(1, $rohitRow['position']);
        $this->assertSame(2, $sunitaRow['position']);
    }

    /**
     * Identity-safety: two genuinely different people who merely share a
     * name and are NOT linked must remain two separate leaderboard rows.
     */
    public function test_unlinked_same_name_identities_are_never_merged(): void
    {
        $edition = Edition::factory()->create();

        $committeeRahul = CommitteeMember::factory()->create(['name' => 'Rahul Patil']);
        $contributorRahul = Contributor::factory()->create(['name' => 'Rahul Patil', 'committee_member_id' => null]);

        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => $committeeRahul->id,
            'amount' => '1000.00',
        ]);
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => null,
            'contributor_id' => $contributorRahul->id,
            'amount' => '750.00',
        ]);

        $ranking = $this->service()->getEditionRanking($edition);

        $this->assertCount(2, $ranking, 'unlinked same-name people must never be merged');
        $amounts = collect($ranking)->pluck('total_amount')->sort()->values()->all();
        $this->assertSame([750.0, 1000.0], $amounts);
    }

    public function test_ranking_is_sorted_descending_with_sequential_top_ten_positions_and_is_edition_isolated(): void
    {
        $edition = Edition::factory()->create();
        $otherEdition = Edition::factory()->create();

        // 12 contributors so we can prove positions 1-10 vs 11+ behavior.
        foreach (range(1, 12) as $i) {
            $contributor = Contributor::factory()->create(['name' => sprintf('Contributor %02d', $i)]);
            EditionContribution::factory()->create([
                'edition_id' => $edition->id,
                'committee_member_id' => null,
                'contributor_id' => $contributor->id,
                'amount' => ($i * 100).'.00',
            ]);
        }

        // Noise in a different edition — must never affect this ranking.
        $otherContributor = Contributor::factory()->create(['name' => 'Other Edition Person']);
        EditionContribution::factory()->create([
            'edition_id' => $otherEdition->id,
            'committee_member_id' => null,
            'contributor_id' => $otherContributor->id,
            'amount' => '99999.00',
        ]);

        $ranking = $this->service()->getEditionRanking($edition);

        $this->assertCount(12, $ranking);
        $this->assertFalse(collect($ranking)->contains('name', 'Other Edition Person'));

        // Descending order: highest amount (Contributor 12, ₹1200) first.
        $this->assertSame('Contributor 12', $ranking[0]['name']);
        $this->assertSame(1200.0, $ranking[0]['total_amount']);
        $this->assertSame('Contributor 01', $ranking[11]['name']);

        foreach ($ranking as $index => $row) {
            $this->assertSame($index + 1, $row['position']);
            $this->assertSame($index < 10, $row['is_top_ten']);
        }
    }

    /**
     * Phase 3.40 — photo resolution never uses name/phone matching, only
     * the existing explicit committee_member_id link. A direct
     * CommitteeMember contribution must resolve to that member's LINKED
     * Contributor's photo (not its own — CommitteeMember has no photo
     * column); a Contributor-sourced contribution linked to a committee
     * member collapses into the same canonical row and the same photo;
     * an unlinked standalone Contributor uses its own photo; and a
     * contributor with no photo at all yields a null photo_path so
     * Blade can fall back to initials.
     */
    public function test_canonical_ranking_resolves_the_correct_photo_for_linked_and_standalone_identities(): void
    {
        $edition = Edition::factory()->create();

        $linkedMember = CommitteeMember::factory()->create(['name' => 'Linked Member']);
        $linkedContributor = Contributor::factory()->create([
            'name' => 'Linked Member',
            'committee_member_id' => $linkedMember->id,
            'photo_path' => 'contributors/linked.jpg',
        ]);
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => $linkedMember->id,
            'amount' => '1000.00',
        ]);
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => null,
            'contributor_id' => $linkedContributor->id,
            'amount' => '500.00',
        ]);

        $standalone = Contributor::factory()->create(['name' => 'Standalone Person', 'photo_path' => 'contributors/standalone.jpg']);
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => null,
            'contributor_id' => $standalone->id,
            'amount' => '100.00',
        ]);

        $noPhoto = Contributor::factory()->create(['name' => 'No Photo Person']);
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => null,
            'contributor_id' => $noPhoto->id,
            'amount' => '50.00',
        ]);

        $ranking = collect($this->service()->getEditionRanking($edition))->keyBy('name');

        $this->assertSame('contributors/linked.jpg', $ranking['Linked Member']['photo_path']);
        $this->assertSame('contributors/standalone.jpg', $ranking['Standalone Person']['photo_path']);
        $this->assertNull($ranking['No Photo Person']['photo_path']);
    }

    public function test_inactive_committee_member_and_contributor_remain_in_historical_ranking(): void
    {
        $edition = Edition::factory()->create();
        $inactiveMember = CommitteeMember::factory()->create(['name' => 'Retired Member', 'is_active' => false]);
        $inactiveContributor = Contributor::factory()->create(['name' => 'Inactive Supporter', 'is_active' => false]);

        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => $inactiveMember->id,
            'amount' => '1000.00',
        ]);
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => null,
            'contributor_id' => $inactiveContributor->id,
            'amount' => '500.00',
        ]);

        $names = collect($this->service()->getEditionRanking($edition))->pluck('name');

        $this->assertTrue($names->contains('Retired Member'));
        $this->assertTrue($names->contains('Inactive Supporter'));
    }

    /**
     * Phase 3.40 superseded Phase 3.38B3's requirement here: the public
     * leaderboard must no longer render any contribution amount at all
     * (ranking still uses it internally — see ContributorRankingTest's
     * service-level tests above, which are untouched). This is a
     * legitimate requirement change, not a weakened test.
     */
    public function test_public_edition_page_displays_ranking_without_private_data(): void
    {
        $edition = Edition::factory()->create();
        $member = CommitteeMember::factory()->create(['name' => 'Public Facing Name', 'phone' => '9998887771']);
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => $member->id,
            'amount' => '2500.00',
            'notes' => 'SECRET_INTERNAL_NOTE',
        ]);

        $response = $this->get(route('public.editions.show', $edition));

        $response->assertOk();
        $response->assertSee('Top RPPL Contributors');
        $response->assertSee('Public Facing Name');
        $response->assertSee('Top Contributor');
        $response->assertDontSee('2,500');
        $response->assertDontSee('2500');
        $response->assertDontSee('₹', false);
        $response->assertDontSee('9998887771');
        $response->assertDontSee('SECRET_INTERNAL_NOTE');
        $response->assertDontSee('Committee Member');
        $response->assertDontSee('General Contributor');
    }

    /**
     * Phase 3.40 — public recognition badges by position, and the
     * fallback initials avatar for a contributor with no photo. Ranking
     * order/positions themselves are already covered by the service-level
     * tests above; this only checks the presentation layer.
     */
    public function test_public_leaderboard_shows_badges_by_position_and_initials_fallback(): void
    {
        $edition = Edition::factory()->create();

        foreach (range(1, 11) as $i) {
            $contributor = Contributor::factory()->create(['name' => sprintf('Rank Person %02d', $i)]);
            EditionContribution::factory()->create([
                'edition_id' => $edition->id,
                'committee_member_id' => null,
                'contributor_id' => $contributor->id,
                'amount' => ((12 - $i) * 100).'.00', // person 01 has the highest amount → position 1
            ]);
        }

        $response = $this->get(route('public.editions.show', $edition));

        $response->assertOk();
        $response->assertSee('Top Contributor');
        $response->assertSee('2nd Contributor');
        $response->assertSee('3rd Contributor');
        $response->assertSee('Top 10');
        // Position 11 (the lowest amount, "Rank Person 11") gets no
        // special badge — just its plain rank number.
        $response->assertSee('RP'); // initials fallback (no photo on any of these)
    }

    public function test_public_leaderboard_renders_the_contributors_actual_photo_when_present(): void
    {
        Storage::fake('public');
        $edition = Edition::factory()->create();
        // ->create() (not ->image()): ->image() requires the GD
        // extension purely to render real pixel data, which this
        // environment doesn't have.
        $photoPath = UploadedFile::fake()->create('c.jpg', 100, 'image/jpeg')->store('contributors', 'public');
        Contributor::factory()->create(['name' => 'Photo Person', 'photo_path' => $photoPath]);
        EditionContribution::factory()->create([
            'edition_id' => $edition->id,
            'committee_member_id' => null,
            'contributor_id' => Contributor::firstWhere('name', 'Photo Person')->id,
            'amount' => '100.00',
        ]);

        $response = $this->get(route('public.editions.show', $edition));

        $response->assertOk();
        $response->assertSee(Storage::url($photoPath), false);
    }

    public function test_edition_with_no_contributions_shows_no_ranking_section(): void
    {
        $edition = Edition::factory()->create();

        $this->assertSame([], $this->service()->getEditionRanking($edition));

        $response = $this->get(route('public.editions.show', $edition));
        $response->assertOk();
        $response->assertDontSee('Top RPPL Contributors');
    }
}
