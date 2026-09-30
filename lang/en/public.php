<?php

/**
 * Shared public-website UI strings: header/nav, "More" menu, mobile
 * drawer, language switcher, and generic buttons/empty-states reused
 * across multiple pages. Page/domain-specific strings live in their own
 * files (matches.php, directory.php, registration.php) so parallel
 * translation work never touches the same file — see this phase's
 * completion report for why.
 */
return [

    'nav' => [
        'home' => 'Home',
        'matches' => 'Matches',
        'points_table' => 'Points Table',
        'teams' => 'Teams',
        'players' => 'Players',
        'more' => 'More',
        'venues' => 'Venues',
        'editions' => 'Editions',
        'player_registration' => 'Player Registration',
        'rules' => 'Rules',
        'faqs' => 'FAQs',
        'admin' => 'Admin',
    ],

    'language' => [
        'english' => 'English',
        'hindi' => 'हिन्दी',
        'switch_language' => 'Switch language',
    ],

    // <x-status-badge> DISPLAY labels only — never changes the
    // underlying DB enum value it's given. Only public-facing statuses
    // are listed; anything Admin-only (income/expense/queued/paid in
    // full/...) is deliberately absent — status-badge.blade.php falls
    // back to the raw value when a key is missing, which is correct for
    // those (Admin never runs in Hindi anyway).
    'status' => [
        'upcoming' => 'Upcoming',
        'active' => 'Active',
        'completed' => 'Completed',
        'scheduled' => 'Scheduled',
        'toss' => 'Toss',
        'live' => 'Live',
        'abandoned' => 'Abandoned',
        'cancelled' => 'Cancelled',
        'pending' => 'Pending',
        'paid' => 'Paid',
        'failed' => 'Failed',
        'refunded' => 'Refunded',
    ],

    'common' => [
        'view_all' => 'View All',
        'view_match' => 'View Match',
        'view_scorecard' => 'View Scorecard',
        'no_matches_available' => 'No matches available',
        'no_data_available' => 'No data available',
        'back' => 'Back',
        'all_matches' => 'All matches',
    ],

];
