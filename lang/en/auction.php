<?php

/**
 * The public player-auction page: the live view (also drawn by
 * resources/js/public-auction.js from these same strings), the results, the
 * home-page card and the header link. Amounts are always "points".
 * Placeholders look like :name and are filled in by Laravel here and by the
 * page's script there.
 */
return [

    'nav' => 'Auction',
    'title' => 'Player Auction',
    'pts' => 'pts',

    // Live view
    'live' => 'LIVE',
    'paused' => 'Paused',
    'paused_hint' => 'The auction is paused for a moment. It will carry on shortly.',
    'round' => 'Round :number',
    'on_the_block' => 'On the block',
    'current_bid' => 'Current bid',
    'base_price' => 'Base price',
    'no_bid_yet' => 'No bid yet',
    'bidding' => 'Bidding in progress…',
    'bids' => 'Bids',
    'no_bids' => 'No bids yet.',
    'sold' => 'SOLD',
    'sold_to' => 'Sold to :team',
    'for_points' => 'for :points points',
    'waiting_next' => 'Waiting for the next player…',
    'to_come' => ':count players still to come',
    'to_come_one' => '1 player still to come',
    'teams' => 'Teams',
    'left' => 'left',
    'squad' => 'Squad',
    'players_count' => ':count of :max players',
    'needs_more' => 'needs :count more',
    'recent_sales' => 'Recent sales',
    'no_sales' => 'Nobody is sold yet.',
    'on_hold' => 'On hold',
    'first_time' => 'First time in RPPL',
    'before' => 'Before: :matches matches · :runs runs · :wickets wickets',
    'best_score' => 'best :value',
    'role_line' => ':role',
    'batting_hand' => ':hand bat',
    'bowling_arm' => ':arm bowler',
    'updating' => 'Updating…',
    'connection_lost' => 'Connection lost — trying again…',
    'needs_js' => 'Turn on JavaScript to follow the auction live.',
    'big_screen' => 'Big screen',
    'back_to_page' => 'Back',

    // The numbers, the lists and the bidding detail
    'sold_players' => 'Sold',
    'upcoming' => 'Upcoming',
    'highest_sale' => 'Highest sale',
    'average_price' => 'Average price',
    'progress' => ':done of :total players done',
    'no_upcoming' => 'No players left to come.',
    'no_hold' => 'Nobody is on hold.',
    'no_unsold_yet' => 'Nobody is unsold so far.',
    'bids_count' => ':count bids',
    'bids_one' => '1 bid',
    'bid_history' => 'How the bidding went',
    'loading' => 'Loading…',
    'top_buy' => 'Top buy',
    'round_short' => 'Round :number',

    // Results (after the auction)
    'results' => 'Auction results',
    'players_sold' => 'Players sold',
    'points_spent' => 'Points spent',
    'most_expensive' => 'Most expensive',
    'top_buys' => 'Top buys',
    'spent' => 'Spent',
    'unsold' => 'Unsold',
    'no_unsold' => 'Every player found a team.',
    'no_squad' => 'No players yet.',
    'completed' => 'The auction is completed.',

    // Nothing to show
    'none' => 'There is no auction to show right now.',
    'none_hint' => 'When the player auction starts, you can follow it live right here.',

    // Home page card
    'home_live' => 'The player auction is LIVE',
    'home_paused' => 'The player auction is paused',
    'home_now' => 'Now on the block: :name',
    'home_waiting' => 'Waiting for the next player',
    'home_watch' => 'Watch live',
    'home_results' => 'The player auction is over — see who bought whom',
    'home_results_link' => 'See the results',
];
