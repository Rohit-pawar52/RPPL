<?php

namespace Tests\Feature\Public;

use App\Models\Contributor;
use App\Models\Edition;
use App\Models\EditionContribution;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The homepage contributors slider (up to 20, then "View more") and the full list page behind it. Recognition
 * only: name, village and picture, never an amount.
 */
class ContributorsSliderTest extends TestCase
{
    use RefreshDatabase;

    private function give(Edition $edition, string $name, ?string $village, int $amount): Contributor
    {
        $contributor = Contributor::factory()->create(['name' => $name, 'village' => $village, 'photo_path' => null]);
        EditionContribution::factory()->create(['edition_id' => $edition->id, 'contributor_id' => $contributor->id, 'amount' => $amount]);

        return $contributor;
    }

    public function test_the_home_page_shows_name_and_village_with_the_default_picture_and_no_amount(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        $this->give($edition, 'Ramesh Patil', 'Shirur', 123456);
        $this->give($edition, 'Old Giver', null, 100);

        $this->get(route('public.home'))
            ->assertOk()
            ->assertSee('Ramesh Patil')
            ->assertSee('Shirur')
            ->assertSee('Old Giver')
            ->assertSee('images/default-user.jpeg', false)
            ->assertDontSee('123456')
            ->assertDontSee('1,23,456')
            ->assertDontSee(route('public.contributors.index', ['edition_id' => $edition->id]), false);
    }

    public function test_the_slider_holds_twenty_and_view_more_appears_only_when_there_are_more(): void
    {
        $edition = Edition::factory()->create(['status' => 'active']);
        foreach (range(1, 20) as $i) {
            $this->give($edition, sprintf('Giver %02d', $i), 'Village', 1000 - $i);
        }

        $this->get(route('public.home'))->assertOk()
            ->assertSee('Giver 20')
            ->assertDontSee('View more');

        $this->give($edition, 'Giver 21', 'Village', 1);

        $this->get(route('public.home'))->assertOk()
            ->assertSee('Giver 20')
            ->assertDontSee('Giver 21')
            ->assertSee('View more')
            ->assertSee('+1 more')
            ->assertSee(route('public.contributors.index', ['edition_id' => $edition->id]), false);

        // The list page behind it has everyone, with the village.
        $this->get(route('public.contributors.index', ['edition_id' => $edition->id]))->assertOk()
            ->assertSee('Giver 21')
            ->assertSee('Village')
            ->assertSee('21 contributors');
    }

    public function test_the_list_page_can_show_another_season_and_has_an_empty_state(): void
    {
        $old = Edition::factory()->create(['name' => 'RPPL 2025', 'year' => 2025, 'status' => 'completed']);
        $current = Edition::factory()->create(['name' => 'RPPL 2026', 'year' => 2026, 'status' => 'active']);
        $this->give($old, 'Last Year Giver', 'Pune', 500);

        $this->get(route('public.contributors.index', ['edition_id' => $old->id]))->assertOk()->assertSee('Last Year Giver');
        $this->get(route('public.contributors.index'))->assertOk()
            ->assertDontSee('Last Year Giver')
            ->assertSee('Contributors will be shown here soon.')
            ->assertSee('RPPL 2025');

        // The home page shows no section at all when the current season has no contributors.
        $this->get(route('public.home'))->assertOk()->assertDontSee('Our contributors');
        $this->assertNotNull($current);
    }
}
