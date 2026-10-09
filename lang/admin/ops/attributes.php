<?php

// Field names as they appear inside validation messages (field => Hindi); see AppServiceProvider::configureAdminTranslations().
// (name, village, tehsil, district, status, edition_id and edition_team_id are named in lang/admin/data/attributes.php.)
return [
    // player registrations
    'player_id' => 'खिलाड़ी',
    'payment_status' => 'भुगतान की स्थिति',
    'registration_fee' => 'पंजीकरण शुल्क',
    'registered_at' => 'पंजीकरण का समय',
    'payment_failure_reason' => 'नाकाम होने का कारण',
    'payment_reference' => 'भुगतान रेफ़रेंस',
    'submitted_utr' => 'खिलाड़ी का लिखा UTR',
    'age' => 'उम्र',
    'photo_url' => 'फ़ोटो का लिंक',
    'payment_proof_url' => 'भुगतान के स्क्रीनशॉट का लिंक',
    'csv_file' => 'Excel या CSV फ़ाइल',
    'dry_run' => 'सिर्फ़ जाँच',
    'reason' => 'कारण',

    // editions
    'year' => 'साल',
    'registration_open' => 'पब्लिक पंजीकरण',
    'registration_opens_at' => 'पंजीकरण खुलने का समय',
    'registration_closes_at' => 'पंजीकरण बंद होने का समय',
    'registration_reminder_enabled' => 'बंद होने की याद',
    'registration_reminder_minutes_before' => 'इतने मिनट पहले याद',

    // matches
    'edition_team_a_id' => 'टीम A',
    'edition_team_b_id' => 'टीम B',
    'scheduled_at' => 'तय समय',
    'venue_id' => 'मैदान',
    'match_number' => 'मैच नंबर',
    'overs_per_innings' => 'प्रति पारी ओवर',
    'match_stage' => 'मैच का चरण',
    'reminder_enabled' => 'मैच की याद',
    'reminder_minutes_before' => 'इतने मिनट पहले याद',
    'toss_winner_team_id' => 'टॉस जीतने वाला',
    'toss_decision' => 'टॉस का फ़ैसला',
    'winner_team_id' => 'सुपर ओवर का विजेता',

    // Playing XI
    'team_player_ids' => 'खिलाड़ी',
    'team_player_ids.*' => 'खिलाड़ी',
    'team_player_id' => 'खिलाड़ी',
    'match_player_id' => 'खिलाड़ी',
    'designation' => 'भूमिका',

    // scoring
    'striker_match_player_id' => 'स्ट्राइकर',
    'non_striker_match_player_id' => 'नॉन-स्ट्राइकर',
    'bowler_match_player_id' => 'गेंदबाज़',
    'fielder_match_player_id' => 'फ़ील्डर',
    'dismissed_match_player_id' => 'आउट खिलाड़ी',
    'wicket_type' => 'आउट होने का तरीका',
    'runs_off_bat' => 'बल्ले से रन',
    'wide_running_runs' => 'वाइड पर दौड़कर बने रन',
    'bye_runs' => 'बाई',
    'leg_bye_runs' => 'लेग बाई',
    'no_ball_reason' => 'नो बॉल का कारण',
    'runs_physically_run' => 'दौड़कर पूरे हुए रन',
    'confirmed_survivor_end' => 'बचे बल्लेबाज़ का छोर',
    'commentary' => 'कमेंट्री',
    'is_wide' => 'वाइड',
    'is_no_ball' => 'नो बॉल',
    'is_wicket' => 'विकेट',
    'is_short_run' => 'शॉर्ट रन',
    'awarded_team_id' => 'किसे मिले',
    'type' => 'प्रकार',

    // auction
    'team_purse' => 'हर टीम का पर्स',
    'team_purses' => 'टीम पर्स',
    'team_purses.*' => 'टीम का अपना पर्स',
    'min_bid' => 'सबसे कम बोली',
    'bid_step' => 'बोली का स्टेप',
    'min_squad' => 'कम से कम स्क्वॉड',
    'max_squad' => 'ज़्यादा से ज़्यादा स्क्वॉड',
    'show_live_bids' => 'लाइव बोलियाँ दिखाना',
    'notify_start' => 'शुरू/खत्म की सूचना',
    'notify_sale_min' => 'बिक्री की सूचना की कम से कम रकम',
];
