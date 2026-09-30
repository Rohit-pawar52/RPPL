<?php

/**
 * हिन्दी — see lang/en/matches.php for the key structure/comment.
 * Keeps the everyday cricket words people actually use (ओवर, रन,
 * विकेट, टॉस, स्कोरकार्ड, प्लेइंग XI, लाइव) rather than formal
 * textbook translations; follows lang/hi/public.php's terminology
 * (मैदान for venue, संस्करण for edition, अंक तालिका for points table).
 */
return [

    'nav' => [
        'live' => 'लाइव',
        'scorecard' => 'स्कोरकार्ड',
        'squads' => 'स्क्वॉड',
        'match_info' => 'मैच जानकारी',
        'match_sections' => 'मैच के भाग',
    ],

    'home' => [
        'tagline_fallback' => 'लोकल क्रिकेट टूर्नामेंट के स्कोर, मैच और अंक तालिका।',
        'edition_details' => 'संस्करण की जानकारी',
        'no_editions' => 'अभी कोई टूर्नामेंट संस्करण उपलब्ध नहीं है।',
        'no_editions_hint' => 'टूर्नामेंट शुरू होते ही मैच, स्कोर और अंक तालिका यहाँ दिखेंगे।',
        'no_matches_hint' => 'मैचों की घोषणा होते ही मैच सूची यहाँ दिखेगी।',
        'points_table' => 'अंक तालिका',
        'full_table' => 'पूरी तालिका',
        'standings_empty' => 'टीमें जुड़ने के बाद अंक तालिका यहाँ दिखेगी।',
        'recent_results' => 'हाल के नतीजे',
        'upcoming_matches' => 'आने वाले मैच',
        'view_all_matches' => 'सभी मैच देखें',
        'teams' => 'टीमें',
        'all_teams' => 'सभी टीमें',
        'no_teams' => 'अभी कोई टीम शामिल नहीं है।',
        'top_performers' => 'टॉप खिलाड़ी',
        'more_stats' => 'और आँकड़े',
        'most_runs' => 'सबसे ज़्यादा रन',
        'most_wickets' => 'सबसे ज़्यादा विकेट',
        'stats_empty' => 'स्कोरिंग शुरू होने के बाद खिलाड़ियों के आँकड़े यहाँ दिखेंगे।',
    ],

    'table' => [
        'team' => 'टीम',
        'played' => 'मैच',
        'won' => 'जीत',
        'lost' => 'हार',
        'points' => 'अंक',
    ],

    'centre' => [
        'live' => 'लाइव',
        'next_match' => 'अगला मैच',
        'recent_result' => 'ताज़ा नतीजा',
        'view_live' => 'लाइव देखें',
        'match_info' => 'मैच जानकारी',
        'scorecard' => 'स्कोरकार्ड',
        'live_scorecard' => 'लाइव स्कोरकार्ड',
        'full_scorecard' => 'पूरा स्कोरकार्ड',
        'bowler_to_striker' => ':bowler की गेंद पर :striker',
    ],

    'list' => [
        'title' => 'मैच',
        'all_editions' => 'सभी संस्करण',
        'filter' => 'फ़िल्टर',
        'live_now' => 'अभी लाइव',
        'upcoming' => 'आने वाले',
        'results' => 'नतीजे',
        'no_other_scheduled' => 'अभी कोई और मैच तय नहीं है।',
        'no_scheduled' => 'अभी कोई मैच तय नहीं है।',
        'no_completed' => 'अभी तक कोई मैच पूरा नहीं हुआ।',
        'follow_live' => 'लाइव देखें',
    ],

    'info' => [
        'match_number' => 'मैच :number',
        'venue' => 'मैदान',
        'scheduled' => 'तारीख व समय',
        'overs' => 'ओवर',
        'tbd' => 'तय नहीं',
        'toss' => 'टॉस',
        'toss_result' => ':team ने टॉस जीतकर पहले :decision चुनी',
        'toss_decision' => [
            'bat' => 'बल्लेबाज़ी',
            'bowl' => 'गेंदबाज़ी',
        ],
        'innings' => 'पारी',
    ],

    'squads' => [
        'playing_xi' => 'प्लेइंग XI',
        'not_announced' => 'प्लेइंग XI की घोषणा अभी नहीं हुई है।',
        'captain' => '(कप्तान)',
        'wicket_keeper' => '(विकेटकीपर)',
        'captain_and_wicket_keeper' => '(कप्तान व विकेटकीपर)',
        'role' => [
            'batter' => 'बल्लेबाज़',
            'bowler' => 'गेंदबाज़',
            'all_rounder' => 'ऑलराउंडर',
            'wicket_keeper' => 'विकेटकीपर',
        ],
    ],

    'scorecard' => [
        'not_available' => 'मैच शुरू होने के बाद स्कोरकार्ड यहाँ दिखेगा।',
    ],

    'live' => [
        'this_over' => 'यह ओवर',
    ],

    'chase' => [
        'target' => 'लक्ष्य :target',
        'need_from_balls' => ':balls गेंदों में :runs रन चाहिए',
        'rrr' => 'ज़रूरी रन रेट :rate',
        'crr' => 'रन रेट :rate',
    ],

    'common' => [
        'vs' => 'बनाम',
        'result_unavailable' => 'नतीजा उपलब्ध नहीं',
        'overs_count' => ':overs ओवर',
        'overs_short' => ':overs ओवर',
    ],

];
