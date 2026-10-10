<?php

/**
 * खिलाड़ी नीलामी का सार्वजनिक पेज: लाइव दृश्य (जिसे resources/js/public-auction.js
 * इन्हीं शब्दों से बनाता है), नतीजे, होम पेज का कार्ड और हेडर का लिंक। राशि हमेशा
 * "पॉइंट" में है।
 */
return [

    'nav' => 'नीलामी',
    'title' => 'खिलाड़ी नीलामी',
    'pts' => 'पॉइंट',

    // Live view
    'live' => 'लाइव',
    'paused' => 'रुकी हुई',
    'paused_hint' => 'नीलामी कुछ देर के लिए रुकी है। जल्दी ही फिर शुरू होगी।',
    'round' => 'राउंड :number',
    'on_the_block' => 'बोली में',
    'current_bid' => 'मौजूदा बोली',
    'base_price' => 'आधार कीमत',
    'no_bid_yet' => 'अभी कोई बोली नहीं',
    'bidding' => 'बोली चल रही है…',
    'bids' => 'बोलियाँ',
    'no_bids' => 'अभी कोई बोली नहीं।',
    'sold' => 'बिका',
    'sold_to' => ':team ने खरीदा',
    'for_points' => ':points पॉइंट में',
    'waiting_next' => 'अगले खिलाड़ी का इंतज़ार…',
    'to_come' => 'अभी :count खिलाड़ी बाकी हैं',
    'to_come_one' => 'अभी 1 खिलाड़ी बाकी है',
    'teams' => 'टीमें',
    'left' => 'बचे',
    'squad' => 'टीम',
    'players_count' => ':max में से :count खिलाड़ी',
    'needs_more' => 'अभी :count और चाहिए',
    'recent_sales' => 'हाल की बिक्री',
    'no_sales' => 'अभी कोई खिलाड़ी नहीं बिका।',
    'on_hold' => 'होल्ड पर',
    'first_time' => 'RPPL में पहली बार',
    'before' => 'पहले: :matches मैच · :runs रन · :wickets विकेट',
    'best_score' => 'सर्वश्रेष्ठ :value',
    'role_line' => ':role',
    'batting_hand' => ':hand बल्लेबाज़ी',
    'bowling_arm' => ':arm गेंदबाज़',
    'updating' => 'अपडेट हो रहा है…',
    'connection_lost' => 'कनेक्शन टूटा — फिर कोशिश हो रही है…',
    'needs_js' => 'नीलामी लाइव देखने के लिए JavaScript चालू करें।',
    'big_screen' => 'बड़ी स्क्रीन',
    'share' => 'शेयर करें',
    'link_copied' => 'लिंक कॉपी हो गया',
    'roles_title' => 'स्क्वॉड में बल्लेबाज़ · गेंदबाज़ · ऑलराउंडर · विकेटकीपर',
    'roles_short' => [
        'batter' => 'बैट',
        'bowler' => 'बॉल',
        'all_rounder' => 'ऑल',
        'wicket_keeper' => 'WK',
    ],
    'back_to_page' => 'वापस',

    // The numbers, the lists and the bidding detail
    'sold_players' => 'बिके',
    'upcoming' => 'आने वाले',
    'highest_sale' => 'सबसे महँगी बिक्री',
    'average_price' => 'औसत कीमत',
    'progress' => ':total में से :done खिलाड़ी पूरे',
    'no_upcoming' => 'अब कोई खिलाड़ी बाकी नहीं।',
    'no_hold' => 'कोई खिलाड़ी होल्ड पर नहीं है।',
    'no_unsold_yet' => 'अभी तक कोई खिलाड़ी अनबिका नहीं है।',
    'bids_count' => ':count बोलियाँ',
    'bids_one' => '1 बोली',
    'bid_history' => 'बोली कैसे चली',
    'loading' => 'लोड हो रहा है…',
    'top_buy' => 'सबसे महँगा',
    'round_short' => 'राउंड :number',

    // Results (after the auction)
    'results' => 'नीलामी के नतीजे',
    'players_sold' => 'बिके खिलाड़ी',
    'points_spent' => 'खर्च हुए पॉइंट',
    'most_expensive' => 'सबसे महँगा',
    'top_buys' => 'सबसे महँगी खरीद',
    'spent' => 'खर्च',
    'unsold' => 'नहीं बिके',
    'no_unsold' => 'हर खिलाड़ी को टीम मिल गई।',
    'no_squad' => 'अभी कोई खिलाड़ी नहीं।',
    'completed' => 'नीलामी पूरी हो चुकी है।',

    // Nothing to show
    'none' => 'अभी दिखाने के लिए कोई नीलामी नहीं है।',
    'none_hint' => 'खिलाड़ी नीलामी शुरू होते ही आप उसे यहीं लाइव देख सकेंगे।',

    // Home page card
    'home_live' => 'खिलाड़ी नीलामी लाइव है',
    'home_paused' => 'खिलाड़ी नीलामी रुकी हुई है',
    'home_now' => 'अभी बोली में: :name',
    'home_waiting' => 'अगले खिलाड़ी का इंतज़ार',
    'home_watch' => 'लाइव देखें',
    'home_results' => 'खिलाड़ी नीलामी पूरी हुई — देखें किसे किसने खरीदा',
    'home_results_link' => 'नतीजे देखें',
];
