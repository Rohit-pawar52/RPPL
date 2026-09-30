<?php

/**
 * हिन्दी — see lang/en/directory.php for the key structure/comment.
 * Reuses lang/hi/public.php's established terminology (टीमें,
 * खिलाड़ी, मैदान, संस्करण, नियम, मैच, अंक तालिका) and common cricket
 * Hindi (रन, विकेट, ओवर, पारी, स्ट्राइक रेट, इकॉनमी).
 */
return [

    'common' => [
        'search' => 'खोजें',
        'search_by_name' => 'नाम से खोजें',
        'vs' => 'बनाम',
    ],

    'roles' => [
        'batter' => 'बल्लेबाज़',
        'bowler' => 'गेंदबाज़',
        'all_rounder' => 'ऑलराउंडर',
        'wicket_keeper' => 'विकेटकीपर',
    ],

    'teams' => [
        'title' => 'टीमें',
        'empty' => 'अभी कोई टीम उपलब्ध नहीं है।',
        'back' => 'टीमों पर वापस जाएँ',
        'no_short_name' => 'कोई संक्षिप्त नाम नहीं',
        'no_history' => 'अभी कोई टूर्नामेंट इतिहास उपलब्ध नहीं है।',
        'played' => 'खेले',
        'won' => 'जीते',
        'lost' => 'हारे',
        'tied' => 'टाई',
        'squad' => 'टीम सूची',
        'squad_empty' => 'इस संस्करण के लिए अभी कोई टीम सूची उपलब्ध नहीं है।',
        'recent_matches' => 'हाल के मैच',
        'recent_matches_empty' => 'इस संस्करण के लिए अभी कोई मैच उपलब्ध नहीं है।',
    ],

    'players' => [
        'title' => 'खिलाड़ी',
        'empty' => 'अभी कोई खिलाड़ी उपलब्ध नहीं है।',
        'no_team' => 'कोई टीम नहीं',
        'back' => 'खिलाड़ियों पर वापस जाएँ',
        'no_current_team' => 'कोई वर्तमान टीम नहीं',
        'career' => 'करियर / सभी संस्करण',
        'batting' => 'बल्लेबाज़ी',
        'bowling' => 'गेंदबाज़ी',
        'matches' => 'मैच',
        'innings' => 'पारियाँ',
        'runs' => 'रन',
        'highest' => 'सर्वोच्च',
        'average' => 'औसत',
        'strike_rate' => 'स्ट्राइक रेट',
        'fours' => 'चौके',
        'sixes' => 'छक्के',
        'overs' => 'ओवर',
        'wickets' => 'विकेट',
        'best' => 'सर्वश्रेष्ठ',
        'economy' => 'इकॉनमी',
        'match_history' => 'मैच इतिहास',
        'date' => 'तारीख',
        'match' => 'मैच',
        'result' => 'परिणाम',
        'match_history_empty' => 'अभी कोई मैच इतिहास उपलब्ध नहीं है।',
    ],

    'venues' => [
        'title' => 'मैदान',
        'search_placeholder' => 'नाम या शहर से खोजें',
        'empty' => 'अभी कोई मैदान उपलब्ध नहीं है।',
        'back' => 'मैदानों पर वापस जाएँ',
        'location_unavailable' => 'स्थान उपलब्ध नहीं',
        'match_count' => ':count मैच|:count मैच',
        'upcoming_live' => 'आगामी और लाइव',
        'upcoming_empty' => 'इस मैदान पर कोई आगामी मैच नहीं है।',
        'recent_completed' => 'हाल ही में पूर्ण',
        'completed_empty' => 'इस मैदान पर अभी कोई मैच पूर्ण नहीं हुआ है।',
    ],

    'editions' => [
        'title' => 'संस्करण',
        'heading' => 'टूर्नामेंट संस्करण',
        'edition' => 'संस्करण',
        'status' => 'स्थिति',
        'teams' => 'टीमें',
        'matches' => 'मैच',
        'view' => 'देखें',
        'empty' => 'अभी कोई टूर्नामेंट संस्करण उपलब्ध नहीं है।',
        'all_editions' => 'सभी संस्करण',
        'participating_teams' => 'भाग लेने वाली टीमें',
        'participating_teams_empty' => 'अभी कोई टीम भाग नहीं ले रही है।',
        'top_contributors' => 'शीर्ष :name योगदानकर्ता',
        'badge_top' => 'शीर्ष योगदानकर्ता',
        'badge_second' => 'दूसरे योगदानकर्ता',
        'badge_third' => 'तीसरे योगदानकर्ता',
        'badge_top_ten' => 'शीर्ष 10',
        'matches_empty' => 'अभी कोई मैच निर्धारित नहीं है।',
    ],

    // Full short Hindi words rather than one-letter abbreviations, which
    // would be unclear in Devanagari.
    'standings' => [
        'points_table' => 'अंक तालिका',
        'team' => 'टीम',
        'played' => 'मैच',
        'won' => 'जीत',
        'lost' => 'हार',
        'tied' => 'टाई',
        'no_result' => 'बेनतीजा',
        'points' => 'अंक',
        'empty' => 'इस संस्करण में अभी कोई टीम नहीं है।',
    ],

    'leaderboard' => [
        'top_run_scorers' => 'सबसे ज़्यादा रन',
        'top_wicket_takers' => 'सबसे ज़्यादा विकेट',
        'player' => 'खिलाड़ी',
        'runs' => 'रन',
        'innings' => 'पारी',
        'average' => 'औसत',
        'strike_rate' => 'स्ट्राइक रेट',
        'wickets' => 'विकेट',
        'overs' => 'ओवर',
        'economy' => 'इकॉनमी',
        'batting_empty' => 'अभी बल्लेबाज़ी का कोई डेटा नहीं है।',
        'bowling_empty' => 'अभी गेंदबाज़ी का कोई डेटा नहीं है।',
    ],

    'records' => [
        'title' => 'रिकॉर्ड',
        'highest_score' => 'सर्वोच्च व्यक्तिगत स्कोर',
        'best_bowling' => 'सर्वश्रेष्ठ गेंदबाज़ी',
        'most_sixes' => 'सबसे ज़्यादा छक्के',
    ],

    'videos' => [
        'title' => 'वीडियो',
        'empty' => 'अभी कोई वीडियो उपलब्ध नहीं है।',
        'featured' => 'चुनिंदा वीडियो',
        'view_all' => 'सभी वीडियो देखें',
        'unsupported' => 'आपका ब्राउज़र वीडियो चलाने का समर्थन नहीं करता।',
    ],

    'rules' => [
        'title' => 'नियम और विनियम',
        'empty' => 'नियम और विनियम जल्द ही यहाँ प्रकाशित किए जाएँगे।',
        'committee_label' => 'समिति का निर्णय:',
        'committee_notice' => 'किसी भी गंभीर, विशेष, विवादित या इन नियमों में स्पष्ट रूप से शामिल न की गई परिस्थिति में RPPL समिति का निर्णय अंतिम होगा।',
        'categories' => 'नियम श्रेणियाँ',
        'important' => 'महत्वपूर्ण',
    ],

    'content' => [
        'empty' => 'इस पेज पर अभी कोई सामग्री नहीं है।',
    ],

    'announcements' => [
        'label' => 'घोषणाएँ',
    ],

];
