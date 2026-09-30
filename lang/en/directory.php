<?php

/**
 * Public directory/information pages: Teams, Players, Venues, Editions
 * (incl. the shared points table / leaderboard / records partials),
 * Videos, Rules & Regulations, the fixed content pages and the
 * announcement ticker's surrounding chrome.
 *
 * Only static UI text lives here — team/player/venue/edition names,
 * rule titles/content, video titles, content-page bodies and
 * announcement messages are Admin-entered and always render as-is.
 *
 * The English values are deliberately identical to the strings the
 * views hard-coded before localization, so English output (and the
 * Admin Edition page, which shares the standings/statistics partials
 * and never runs in Hindi) is unchanged.
 */
return [

    'common' => [
        'search' => 'Search',
        'search_by_name' => 'Search by name',
        'vs' => 'vs',
    ],

    // Player::PRIMARY_ROLES / team_players.role enum values.
    'roles' => [
        'batter' => 'Batter',
        'bowler' => 'Bowler',
        'all_rounder' => 'All rounder',
        'wicket_keeper' => 'Wicket keeper',
    ],

    'teams' => [
        'title' => 'Teams',
        'empty' => 'No teams available yet.',
        'back' => 'Back to teams',
        'no_short_name' => 'No short name',
        'no_history' => 'No tournament history available yet.',
        'played' => 'Played',
        'won' => 'Won',
        'lost' => 'Lost',
        'tied' => 'Tied',
        'squad' => 'Squad',
        'squad_empty' => 'No squad available for this edition yet.',
        'recent_matches' => 'Recent Matches',
        'recent_matches_empty' => 'No matches available for this edition yet.',
    ],

    'players' => [
        'title' => 'Players',
        'empty' => 'No players available yet.',
        'no_team' => 'No team',
        'back' => 'Back to players',
        'no_current_team' => 'No current team',
        'career' => 'Career / All Editions',
        'batting' => 'Batting',
        'bowling' => 'Bowling',
        'matches' => 'Matches',
        'innings' => 'Innings',
        'runs' => 'Runs',
        'highest' => 'Highest',
        'average' => 'Average',
        'strike_rate' => 'Strike Rate',
        'fours' => '4s',
        'sixes' => '6s',
        'overs' => 'Overs',
        'wickets' => 'Wickets',
        'best' => 'Best',
        'economy' => 'Economy',
        'match_history' => 'Match History',
        'date' => 'Date',
        'match' => 'Match',
        'result' => 'Result',
        'match_history_empty' => 'No match history available yet.',
    ],

    'venues' => [
        'title' => 'Venues',
        'search_placeholder' => 'Search by name or city',
        'empty' => 'No venues available yet.',
        'back' => 'Back to venues',
        'location_unavailable' => 'Location unavailable',
        'match_count' => ':count match|:count matches',
        'upcoming_live' => 'Upcoming & Live',
        'upcoming_empty' => 'No upcoming matches at this venue.',
        'recent_completed' => 'Recent Completed',
        'completed_empty' => 'No completed matches at this venue yet.',
    ],

    'editions' => [
        'title' => 'Editions',
        'heading' => 'Tournament Editions',
        'edition' => 'Edition',
        'status' => 'Status',
        'teams' => 'Teams',
        'matches' => 'Matches',
        'view' => 'View',
        'empty' => 'No tournament editions available yet.',
        'all_editions' => 'All editions',
        'participating_teams' => 'Participating Teams',
        'participating_teams_empty' => 'No teams participating yet.',
        'top_contributors' => 'Top :name Contributors',
        'badge_top' => 'Top Contributor',
        'badge_second' => '2nd Contributor',
        'badge_third' => '3rd Contributor',
        'badge_top_ten' => 'Top 10',
        'matches_empty' => 'No matches scheduled yet.',
    ],

    // shared/standings/_table.blade.php — column headers stay short.
    'standings' => [
        'points_table' => 'Points Table',
        'team' => 'Team',
        'played' => 'P',
        'won' => 'W',
        'lost' => 'L',
        'tied' => 'T',
        'no_result' => 'NR',
        'points' => 'Pts',
        'empty' => 'No teams in this edition yet.',
    ],

    // shared/statistics/_leaderboard.blade.php
    'leaderboard' => [
        'top_run_scorers' => 'Top Run Scorers',
        'top_wicket_takers' => 'Top Wicket Takers',
        'player' => 'Player',
        'runs' => 'Runs',
        'innings' => 'Inn',
        'average' => 'Avg',
        'strike_rate' => 'SR',
        'wickets' => 'Wkts',
        'overs' => 'Overs',
        'economy' => 'Econ',
        'batting_empty' => 'No batting data yet.',
        'bowling_empty' => 'No bowling data yet.',
    ],

    // shared/statistics/_records.blade.php
    'records' => [
        'title' => 'Records',
        'highest_score' => 'Highest individual score',
        'best_bowling' => 'Best bowling figures',
        'most_sixes' => 'Most sixes',
    ],

    'videos' => [
        'title' => 'Videos',
        'empty' => 'No videos available yet.',
        'featured' => 'Featured Videos',
        'view_all' => 'View All Videos',
        'unsupported' => 'Your browser does not support embedded video.',
    ],

    'rules' => [
        'title' => 'Rules & Regulations',
        'empty' => 'Rules & Regulations will be published here soon.',
        'committee_label' => 'Committee decision:',
        'committee_notice' => 'In any critical, exceptional, disputed, or unforeseen situation not clearly covered by these rules, the decision of the RPPL Committee shall be final.',
        'categories' => 'Rule categories',
        'important' => 'Important',
    ],

    // Privacy Policy / Terms & Conditions / FAQs — title and body are
    // Admin-authored; only the empty-state is static.
    'content' => [
        'empty' => 'This page has no content yet.',
    ],

    'announcements' => [
        'label' => 'Announcements',
    ],

];
