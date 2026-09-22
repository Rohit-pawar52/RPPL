<?php

namespace Database\Seeders\Demo;

use App\Models\ContentPage;
use Illuminate\Database\Seeder;

/**
 * Creates the three canonical content_pages rows deterministically
 * (Phase 3.46) — ContentPageController's admin/public paths assume all
 * three always exist, so this seeder is what makes that true, not a
 * runtime "create if missing" fallback anywhere else.
 *
 * The starter wording is deliberately generic, non-legally-reviewed
 * demo content, fully editable by an admin afterward — but does not
 * itself announce "placeholder" in the visible copy, since a real
 * site visitor would otherwise see that sentence on every page linked
 * from the footer.
 */
class DemoContentPageSeeder extends Seeder
{
    public function run(): void
    {
        ContentPage::firstOrCreate(
            ['type' => ContentPage::TYPE_PRIVACY_POLICY],
            [
                'title' => ContentPage::DEFAULT_TITLES[ContentPage::TYPE_PRIVACY_POLICY],
                'content' => <<<'MARKDOWN'
                ## Information We Collect

                When you register a player, subscribe to notifications, or otherwise use this site, we collect the information you provide directly, such as your name, phone number, and email address.

                ## How We Use Information

                We use this information to manage tournament registrations, communicate updates about matches, and operate the RajaBhoj Pawar Premier League website.

                ## Contact Us

                Questions about this policy can be directed to the tournament committee using the contact details on this site.
                MARKDOWN,
                'is_active' => true,
                'sort_order' => 0,
            ]
        );

        ContentPage::firstOrCreate(
            ['type' => ContentPage::TYPE_TERMS_CONDITIONS],
            [
                'title' => ContentPage::DEFAULT_TITLES[ContentPage::TYPE_TERMS_CONDITIONS],
                'content' => <<<'MARKDOWN'
                ## 1. Tournament Rules

                All participants agree to follow the rules and schedule set by the RPPL tournament committee.

                ## 2. Registration

                Player registration is subject to review and approval. Registration fees, where applicable, are non-refundable once a player has been confirmed for an edition.

                ## 3. Code of Conduct

                Players, teams, and spectators are expected to conduct themselves respectfully at all matches and venues.
                MARKDOWN,
                'is_active' => true,
                'sort_order' => 1,
            ]
        );

        ContentPage::firstOrCreate(
            ['type' => ContentPage::TYPE_FAQS],
            [
                'title' => ContentPage::DEFAULT_TITLES[ContentPage::TYPE_FAQS],
                'content' => <<<'MARKDOWN'
                ## What is RPPL?

                RajaBhoj Pawar Premier League is a local cricket tournament featuring teams, player registrations, live scoring, and standings.

                ## How can I register as a player?

                Use the "Register" link on the site to submit your player registration for the current edition.

                ## How can I check my registration status?

                Use the "Check Registration Status" page with your registration number.
                MARKDOWN,
                'is_active' => true,
                'sort_order' => 2,
            ]
        );
    }
}
