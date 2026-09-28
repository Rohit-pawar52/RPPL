<?php

namespace Database\Seeders\Demo;

use App\Models\Rule;
use App\Models\RuleType;
use Illuminate\Database\Seeder;

/**
 * The two initial Rule Types plus a small set of demo Rules — enough to
 * exercise the module end to end without writing a full rulebook here.
 * The Cricket Rules examples below are not invented: each one mirrors a
 * rule already frozen and enforced by this app's own scoring engine
 * (DeliveryService/Delivery/MatchPlayerService), so the demo content is
 * guaranteed accurate rather than a guess at RPPL's official rules. The
 * RPPL Specific Rules examples are deliberately minimal and generic —
 * the Admin populates the real tournament-specific rulebook later. The
 * Committee final-decision principle is a page-level notice (see the
 * public Rules view), not duplicated here as a normal rule row.
 */
class DemoRuleSeeder extends Seeder
{
    public function run(): void
    {
        $cricketRules = RuleType::firstOrCreate(
            ['slug' => 'cricket-rules'],
            ['name' => 'Cricket Rules', 'description' => 'General laws of cricket as applied in RPPL matches.', 'sort_order' => 1, 'is_active' => true]
        );

        $rpplRules = RuleType::firstOrCreate(
            ['slug' => 'rppl-specific-rules'],
            ['name' => 'RPPL Specific Rules', 'description' => 'Tournament-specific rules unique to RajaBhoj Pawar Premier League.', 'sort_order' => 2, 'is_active' => true]
        );

        $this->seedRules($cricketRules, [
            [
                'title' => 'Overs',
                'content' => "Each over consists of 6 legal deliveries bowled from one end.\n\nA wide or a no-ball does not count as one of the 6 legal deliveries — the over continues until 6 legal deliveries have been bowled.",
                'sort_order' => 1,
            ],
            [
                'title' => 'No Ball & Free Hit',
                'content' => "A no-ball results in one penalty run to the batting side, and the delivery does not count toward the over.\n\nThe next delivery is a Free Hit — the batter cannot be dismissed off it except by a run out.",
                'sort_order' => 2,
                'is_important' => true,
            ],
            [
                'title' => 'Playing XI',
                'content' => 'Each team fields exactly 11 players for a match. The Playing XI must be finalised and submitted before the match can begin.',
                'sort_order' => 3,
            ],
            [
                'title' => 'Bowling Restriction',
                'content' => 'The same bowler cannot bowl two overs in a row. A different bowler must be used for the next over.',
                'sort_order' => 4,
            ],
        ]);

        $this->seedRules($rpplRules, [
            [
                'title' => 'Playing XI Submission',
                'content' => "Both teams must submit their Playing XI before the toss.\n\nOnce submitted and the match has started, the Playing XI cannot be changed for that match.",
                'sort_order' => 1,
            ],
        ]);
    }

    /**
     * @param  list<array{title: string, content: string, sort_order: int, is_important?: bool}>  $rules
     */
    private function seedRules(RuleType $ruleType, array $rules): void
    {
        foreach ($rules as $rule) {
            Rule::firstOrCreate(
                ['rule_type_id' => $ruleType->id, 'title' => $rule['title']],
                [
                    'content' => $rule['content'],
                    'sort_order' => $rule['sort_order'],
                    'status' => 'active',
                    'is_important' => $rule['is_important'] ?? false,
                ]
            );
        }
    }
}
