<?php

/**
 * New public-site words for the redesigned directory and content pages
 * (teams, players, news, photos, videos, rules), the photo viewer and the
 * auction pages. The older words of those pages stay in directory.php,
 * registration.php and auction.php. Hindi: lang/hi/ux_public_content.php.
 */
return [

    // The search box of the teams / players directories.
    'search' => [
        'clear' => 'Clear search',
        'none_title' => 'Nothing found for ":term"',
        'none_hint' => 'Check the spelling or try another name.',
        'no_match_title' => 'No match on this page',
        'no_match_hint' => 'It may be on another page. Search everyone to be sure.',
        'search_all' => 'Search everyone',
    ],

    'count' => [
        'teams' => ':count team|:count teams',
        'players' => ':count player|:count players',
        'photos' => ':count photo|:count photos',
        'videos' => ':count video|:count videos',
    ],

    'team' => [
        'points' => 'Points',
    ],

    'player' => [
        'bats' => 'Bats: :style',
        'bowls' => 'Bowls: :style',
        'season' => 'Choose a season',
        'best_figures' => 'Best bowling',
        'runs_total' => ':count runs in all',
        'wickets_total' => ':count wickets in all',
        'recent_form' => 'Recent form',
        'by_season' => 'Season by season',
        'season_col' => 'Season',
        'team_col' => 'Team',
        'bat_short' => 'Bat',
        'bowl_short' => 'Bowl',
    ],

    'news' => [
        'empty_hint' => 'Fresh stories will show up here soon.',
        'go_home' => 'Go to the home page',
        'latest' => 'Latest',
        'share' => 'Share',
        'link_copied' => 'Link copied',
        'gallery' => 'Pictures',
        'more' => 'More news',
        'all' => 'All news',
    ],

    'photos' => [
        'empty_hint' => 'Pictures from the matches will show up here.',
    ],

    'videos' => [
        'empty_hint' => 'Match highlights will show up here.',
    ],

    // The shared photo viewer (lightbox).
    'viewer' => [
        'previous' => 'Previous picture',
        'next' => 'Next picture',
    ],

    'rules' => [
        'empty_hint' => 'The rules will appear here as soon as they are published.',
        'in_this_category' => 'In this category',
        'filter' => 'Find a rule',
        'link_copied' => 'Link copied',
        'copy_link' => 'Copy the link to this rule',
        'no_match' => 'No rule matches',
    ],

    'auction' => [
        'see_matches' => 'See the matches',
        'see_teams' => 'See the teams',
    ],

];
