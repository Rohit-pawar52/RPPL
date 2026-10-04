<?php

namespace Tests\Feature\Public;

use App\Models\Rule;
use App\Models\RuleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class RulesPageTest extends TestCase
{
    use RefreshDatabase;

    private const COMMITTEE_NOTICE = 'the decision of the RPPL Committee shall be final';

    private function typeWithRules(array $typeAttributes, array $ruleTitles): RuleType
    {
        $type = RuleType::factory()->create($typeAttributes);

        foreach (array_values($ruleTitles) as $index => $title) {
            Rule::factory()->create([
                'rule_type_id' => $type->id,
                'title' => $title,
                'sort_order' => $index + 1,
            ]);
        }

        return $type;
    }

    public function test_guest_sees_first_type_by_sort_order_by_default_with_notice_and_nav_link(): void
    {
        // Created out of order so the default must come from sort_order.
        $this->typeWithRules(['name' => 'Tournament Rules', 'sort_order' => 2], ['Submission Deadline']);
        $this->typeWithRules(['name' => 'Cricket Rules', 'sort_order' => 1], ['Overs Per Innings', 'Free Hit']);

        $response = $this->get(route('public.rules.index'));

        $response->assertOk();
        $response->assertSeeInOrder(['Cricket Rules', 'Tournament Rules']);
        $response->assertSeeInOrder(['Overs Per Innings', 'Free Hit']);
        $response->assertDontSee('Submission Deadline');
        $response->assertSee(self::COMMITTEE_NOTICE);
        $response->assertSee('href="'.route('public.rules.index').'"', false);
    }

    public function test_type_query_selects_category_and_invalid_slug_falls_back_to_default(): void
    {
        $this->typeWithRules(['name' => 'Cricket Rules', 'slug' => 'cricket-rules', 'sort_order' => 1], ['Overs Per Innings']);
        $this->typeWithRules(['name' => 'Tournament Rules', 'slug' => 'tournament-rules', 'sort_order' => 2], ['Submission Deadline']);

        $this->get(route('public.rules.index', ['type' => 'tournament-rules']))
            ->assertOk()
            ->assertSee('Submission Deadline')
            ->assertDontSee('Overs Per Innings');

        foreach (['does-not-exist', '<script>', ''] as $garbage) {
            $this->get(route('public.rules.index', ['type' => $garbage]))
                ->assertOk()
                ->assertSee('Overs Per Innings')
                ->assertDontSee('Submission Deadline');
        }

        // Array-shaped query value must not crash either.
        $this->get('/rules?type[]=tournament-rules')
            ->assertOk()
            ->assertSee('Overs Per Innings');
    }

    public function test_inactive_type_is_hidden_and_its_slug_cannot_be_selected(): void
    {
        $this->typeWithRules(['name' => 'Cricket Rules', 'sort_order' => 1], ['Overs Per Innings']);
        $this->typeWithRules(['name' => 'Retired Rules', 'slug' => 'retired-rules', 'sort_order' => 0, 'is_active' => false], ['Old Hidden Rule']);

        $this->get(route('public.rules.index', ['type' => 'retired-rules']))
            ->assertOk()
            ->assertDontSee('Retired Rules')
            ->assertDontSee('Old Hidden Rule')
            ->assertSee('Overs Per Innings');
    }

    public function test_active_type_with_only_inactive_rules_is_not_shown_as_a_tab(): void
    {
        $this->typeWithRules(['name' => 'Cricket Rules', 'sort_order' => 1], ['Overs Per Innings']);
        $emptyType = RuleType::factory()->create(['name' => 'Draft Rules', 'slug' => 'draft-rules', 'sort_order' => 0]);
        Rule::factory()->inactive()->create(['rule_type_id' => $emptyType->id, 'title' => 'Unpublished Draft']);

        $this->get(route('public.rules.index', ['type' => 'draft-rules']))
            ->assertOk()
            ->assertDontSee('Draft Rules')
            ->assertDontSee('Unpublished Draft')
            ->assertSee('Overs Per Innings');
    }

    public function test_inactive_rule_is_hidden_while_active_siblings_render(): void
    {
        $type = $this->typeWithRules(['name' => 'Cricket Rules'], ['Overs Per Innings']);
        Rule::factory()->inactive()->create(['rule_type_id' => $type->id, 'title' => 'Withdrawn Rule']);

        $this->get(route('public.rules.index'))
            ->assertOk()
            ->assertSee('Overs Per Innings')
            ->assertDontSee('Withdrawn Rule');
    }

    public function test_rule_content_image_and_important_marker_render_safely(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('rules/pitch-diagram.png', 'picture');
        $type = RuleType::factory()->create();
        Rule::factory()->important()->create([
            'rule_type_id' => $type->id,
            'title' => 'Pitch Diagram',
            'content' => "First line of the rule.\nSecond line of the rule.",
            'image_path' => 'rules/pitch-diagram.png',
            'sort_order' => 1,
        ]);
        Rule::factory()->create([
            'rule_type_id' => $type->id,
            'title' => 'Text Only Rule',
            'content' => 'No picture here.',
            'sort_order' => 2,
        ]);

        $response = $this->get(route('public.rules.index'))->assertOk();

        $response->assertSee("First line of the rule.\nSecond line of the rule.", false);
        $response->assertSee('Important');
        $response->assertSee(Storage::disk('public')->url('rules/pitch-diagram.png'), false);
        $response->assertSee('alt="Pitch Diagram"', false);
        // The text-only rule renders with no (broken) <img> at all.
        $response->assertSee('No picture here.');
        $response->assertDontSee('alt="Text Only Rule"', false);
    }

    public function test_empty_state_when_no_type_has_visible_rules(): void
    {
        RuleType::factory()->inactive()->create();
        $emptyActive = RuleType::factory()->create();
        Rule::factory()->inactive()->create(['rule_type_id' => $emptyActive->id]);

        $this->get(route('public.rules.index', ['type' => $emptyActive->slug]))
            ->assertOk()
            ->assertSee('Rules &amp; Regulations will be published here soon.', false);
    }
}
