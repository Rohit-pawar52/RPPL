<?php

/**
 * Public match-facing UI strings: homepage sections, Match Centre /
 * Featured Match cards, the matches list, per-match tabs, Match Info,
 * Squads, Live and Scorecard page chrome. Static labels only — team,
 * player and venue names, scores, overs, dates and result text are
 * always passed through untouched (as :placeholders where they sit
 * inside a sentence).
 *
 * Deliberately NOT covered here (see this phase's completion report):
 * shared/scorecard/_innings.blade.php (shared verbatim with the Admin
 * scorecard) and the live-page partials that public-live-match.js
 * re-renders client-side (_live-innings-row, _live-chase,
 * _live-delivery-row, the live "No deliveries" empty state).
 */
return [

    'nav' => [
        'live' => 'Live',
        'scorecard' => 'Scorecard',
        'squads' => 'Squads',
        'match_info' => 'Match Info',
        'match_sections' => 'Match sections',
    ],

    'home' => [
        'tagline_fallback' => 'Local cricket tournament scores, fixtures, and standings.',
        'edition_details' => 'Edition details',
        'no_editions' => 'No tournament editions available yet.',
        'no_editions_hint' => 'Fixtures, scores and standings will appear here once a tournament is set up.',
        'no_matches_hint' => 'The fixture list will appear here once matches are announced.',
        'points_table' => 'Points Table',
        'full_table' => 'Full table',
        'standings_empty' => 'Standings will appear once teams are added.',
        'recent_results' => 'Recent Results',
        'upcoming_matches' => 'Upcoming Matches',
        'view_all_matches' => 'View All Matches',
        'teams' => 'Teams',
        'all_teams' => 'All teams',
        'no_teams' => 'No teams participating yet.',
        'top_performers' => 'Top Performers',
        'more_stats' => 'More stats',
        'most_runs' => 'Most Runs',
        'most_wickets' => 'Most Wickets',
        'stats_empty' => 'Player statistics will appear once scoring begins.',
    ],

    // Home points-table preview column headers.
    'table' => [
        'team' => 'Team',
        'played' => 'P',
        'won' => 'W',
        'lost' => 'L',
        'points' => 'Pts',
    ],

    'centre' => [
        'live' => 'Live',
        'next_match' => 'Next Match',
        'recent_result' => 'Recent Result',
        'view_live' => 'View Live',
        'match_info' => 'Match Info',
        'scorecard' => 'Scorecard',
        'live_scorecard' => 'Live Scorecard',
        'full_scorecard' => 'Full Scorecard',
        'bowler_to_striker' => ':bowler to :striker',
    ],

    'list' => [
        'title' => 'Matches',
        'all_editions' => 'All editions',
        'filter' => 'Filter',
        'live_now' => 'Live Now',
        'upcoming' => 'Upcoming',
        'results' => 'Results',
        'no_other_scheduled' => 'No other matches scheduled yet.',
        'no_scheduled' => 'No matches scheduled yet.',
        'no_completed' => 'No completed matches yet.',
        'follow_live' => 'Follow live',
        'subtitle' => 'Fixtures, live scores and results',
    ],

    'info' => [
        'match_number' => 'Match :number',
        'venue' => 'Venue',
        'scheduled' => 'Scheduled',
        'overs' => 'Overs',
        'tbd' => 'TBD',
        'toss' => 'Toss',
        'toss_result' => ':team won the toss and chose to :decision',
        'toss_decision' => [
            'bat' => 'bat',
            'bowl' => 'bowl',
        ],
        'innings' => 'Innings',
        'details' => 'Match Details',
        'format' => 'Format',
        'date' => 'Date & time',
        'edition' => 'Edition',
        'stage' => 'Stage',
    ],

    'squads' => [
        'playing_xi' => 'Playing XI',
        'not_announced' => 'Playing XI not announced yet.',
        'captain' => '(C)',
        'wicket_keeper' => '(WK)',
        'captain_and_wicket_keeper' => '(C & WK)',
        'role' => [
            'batter' => 'Batter',
            'bowler' => 'Bowler',
            'all_rounder' => 'All-rounder',
            'wicket_keeper' => 'Wicketkeeper',
        ],
    ],

    'scorecard' => [
        'not_available' => 'Scorecard will be available once the match begins.',
    ],

    'live' => [
        'this_over' => 'This Over',
        'commentary' => 'Commentary',
        'auto_updates' => 'Updates automatically',
    ],

    'chase' => [
        'target' => 'Target :target',
        'need_from_balls' => 'Need :runs from :balls balls',
        'rrr' => 'RRR :rate',
        'crr' => 'CRR :rate',
    ],

    'common' => [
        'vs' => 'vs',
        'result_unavailable' => 'Result unavailable',
        'overs_count' => ':overs overs',
        'overs_short' => ':overs ov',
    ],

];
