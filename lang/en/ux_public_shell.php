<?php

/**
 * The public site's frame (header, menu, footer, notification prompt), the
 * home page's own words, the maintenance page, the content-page helpers and
 * the friendly error pages. Hindi twin: lang/hi/ux_public_shell.php.
 */
return [

    'skip_to_content' => 'Skip to content',

    'nav' => [
        'main' => 'Main navigation',
        'menu' => 'Menu',
        'register' => 'Register',
        'register_long' => 'Register as a player',
        'check_status' => 'Check my registration',
        'search_players' => 'Search players',
        'explore' => 'Explore',
        'language' => 'Language',
    ],

    'footer' => [
        'tournament' => 'Tournament',
        'explore' => 'Explore',
        'contact' => 'Contact',
    ],

    'push' => [
        'title' => 'Never miss an update from :name',
        'message' => 'Get important match timings, postponements and tournament updates.',
        'enable' => 'Enable notifications',
        'dismiss' => 'Not now',
    ],

    'home' => [
        'registration_open' => 'Registration open',
        'closes' => 'closes :date',
        'opens' => 'Registration opens :date',
        'register_now' => 'Register now',
        'cta_matches' => 'Matches',
        'stat_teams' => 'Teams',
        'stat_played' => 'Matches played',
        'stat_leader' => 'Table leader',
        'match_centre' => 'Match centre',
        'see_all_matches' => 'See all matches',
        'watch_live' => 'Watch live',
        'no_matches' => 'No matches scheduled yet',
        'no_matches_hint' => 'Fixtures show up here as soon as they are announced.',
        'latest' => 'Latest from the tournament',
    ],

    'maintenance' => [
        'title' => 'Under maintenance',
        'heading' => 'We will be back soon',
        'default_message' => 'The website is currently under maintenance. Please check back shortly.',
        'check_again' => 'Check again',
        'auto_refresh' => 'This page checks again by itself every minute.',
    ],

    'content' => [
        'back_home' => 'Back to home',
        'toc' => 'On this page',
        'help_title' => 'Still have a question?',
        'help_text' => 'Write to the organisers and we will get back to you.',
        'email' => 'Email us',
        'call' => 'Call',
    ],

    'errors' => [
        'go_home' => 'Go to home page',
        'see_matches' => 'See matches',
        'try_again' => 'Try again',
        'admin_back' => 'Back to the admin panel',
        'search_label' => 'Looking for a player?',
        'search_placeholder' => 'Search players by name',
        'search_button' => 'Search',
        'popular' => 'Popular pages',

        '404' => [
            'title' => 'Page not found',
            'message' => 'The page you are looking for does not exist or has moved. Check the address, or head to one of the pages below.',
        ],
        '403' => [
            'title' => 'Access denied',
            'message' => 'You do not have permission to open this page. If you think this is a mistake, sign in with the right account or contact the organisers.',
        ],
        '419' => [
            'title' => 'Page expired',
            'message' => 'Your session ran out before the form was sent. Go back, refresh the page and try again.',
        ],
        '429' => [
            'title' => 'Too many requests',
            'message' => 'You are going a little too fast. Wait a minute and try again.',
        ],
        '500' => [
            'title' => 'Something went wrong',
            'message' => 'This is a problem on our side, not yours. Please try again in a moment.',
        ],
        '503' => [
            'title' => 'We will be back soon',
            'message' => 'The website is down for a short break. Please check back shortly.',
        ],
    ],

];
