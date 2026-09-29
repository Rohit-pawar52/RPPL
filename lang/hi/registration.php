<?php

/**
 * हिन्दी — see lang/en/registration.php for the key structure/comment.
 * Written for real village players filling this form on a phone: plain,
 * everyday Hindi (मोबाइल नंबर, स्क्रीनशॉट, अपलोड) over formal
 * bureaucratic wording. "पंजीकरण" matches public.nav.player_registration.
 */
return [

    'titles' => [
        'registration' => 'खिलाड़ी पंजीकरण',
        'success' => 'पंजीकरण सफल',
        'status' => 'पंजीकरण की स्थिति देखें',
    ],

    'form' => [
        'heading' => 'खिलाड़ी पंजीकरण',
        'fee_label' => 'पंजीकरण शुल्क:',
        'payment_instructions' => 'पंजीकरण शुल्क UPI या बैंक ट्रांसफर से भरें और नीचे भुगतान का सबूत (स्क्रीनशॉट) अपलोड करें। फ़ॉर्म जमा होने के बाद :league प्रबंधन आपके भुगतान और दस्तावेज़ों की जाँच करेगा।',
        'already_registered' => 'पहले से पंजीकरण कर चुके हैं?',
        'check_status_link' => 'पंजीकरण की स्थिति देखें',
        'submit' => 'पंजीकरण जमा करें',
    ],

    'closed' => [
        'message' => 'खिलाड़ी पंजीकरण अभी बंद है।',
        'contact' => 'कृपया बाद में फिर देखें या :league प्रबंधन से संपर्क करें।',
    ],

    'fields' => [
        'name' => 'पूरा नाम',
        'phone' => 'मोबाइल नंबर',
        'phone_placeholder' => '10 अंकों का मोबाइल नंबर',
        'email' => 'ईमेल (ज़रूरी नहीं)',
        'date_of_birth' => 'जन्म तिथि',
        'player_type' => 'खिलाड़ी का प्रकार',
        'player_type_placeholder' => 'खिलाड़ी का प्रकार चुनें',
        'aadhaar_document' => 'आधार कार्ड',
        'aadhaar_hint' => 'JPEG, PNG या PDF — ज़्यादा से ज़्यादा 4 MB।',
        'payment_proof' => 'भुगतान का सबूत',
        'payment_proof_hint' => 'UPI या बैंक भुगतान का स्क्रीनशॉट — JPEG या PNG, ज़्यादा से ज़्यादा 2 MB।',
        'registration_number' => 'पंजीकरण नंबर',
    ],

    'roles' => [
        'batter' => 'बल्लेबाज़',
        'bowler' => 'गेंदबाज़',
        'all_rounder' => 'ऑलराउंडर',
        'wicket_keeper' => 'विकेटकीपर',
    ],

    'success' => [
        'heading' => 'पंजीकरण सफल',
        'registration_number' => 'पंजीकरण नंबर',
        'save_notice' => 'कृपया यह पंजीकरण नंबर संभाल कर रखें — बाद में अपनी स्थिति देखने के लिए इसकी ज़रूरत पड़ेगी।',
        'edition' => 'संस्करण',
        'player_name' => 'खिलाड़ी का नाम',
        'payment_status' => 'भुगतान की स्थिति',
        'pending' => 'जाँच बाकी',
        'registration_fee' => 'पंजीकरण शुल्क',
        'verification_notice' => ':league प्रबंधन आपके भुगतान और दस्तावेज़ों की जाँच करेगा। इसमें कुछ दिन लग सकते हैं।',
        'return_notice' => 'आप कभी भी अपने पंजीकरण नंबर से अपनी स्थिति देख सकते हैं:',
        'back_home' => ':league होम पर वापस जाएँ',
    ],

    'status' => [
        'heading' => 'पंजीकरण की स्थिति देखें',
        'intro' => 'अपना पंजीकरण नंबर और वह मोबाइल नंबर डालें जिससे आपने पंजीकरण किया था।',
        'submit' => 'स्थिति देखें',
        'registration_number' => 'पंजीकरण नंबर',
        'player_name' => 'खिलाड़ी का नाम',
        'edition' => 'संस्करण',
        'registration_fee' => 'पंजीकरण शुल्क',
        'payment_status' => 'भुगतान की स्थिति',
        'registered_at' => 'पंजीकरण की तारीख',
        'not_found' => 'कोई पंजीकरण नहीं मिला। कृपया अपना पंजीकरण नंबर और मोबाइल नंबर जाँच लें।',
        'back_to_registration' => 'खिलाड़ी पंजीकरण पर वापस जाएँ',
        'payment' => [
            'pending' => 'जाँच बाकी है',
            'pending_message' => 'आपके भुगतान के सबूत की जाँच अभी बाकी है।',
            'paid' => 'भुगतान हो गया',
            'paid_message' => 'आपके भुगतान की पुष्टि हो गई है।',
            'failed' => 'भुगतान की पुष्टि नहीं हो सकी',
            'failed_message' => 'आपके भुगतान की पुष्टि नहीं हो सकी। कृपया :league प्रबंधन से संपर्क करें।',
            'refunded' => 'पैसा वापस किया गया',
            'refunded_message' => 'आपका भुगतान वापस कर दिया गया है।',
        ],
    ],

    'errors' => [
        'generic_rejection' => 'दी गई जानकारी से पंजीकरण नहीं हो सका। कृपया RPPL प्रबंधन से संपर्क करें।',
        'duplicate' => 'इस जानकारी से पंजीकरण पहले ही हो चुका है। कृपया पंजीकरण की स्थिति देखें या RPPL प्रबंधन से संपर्क करें।',
        'closed' => 'खिलाड़ी पंजीकरण अभी बंद है।',
    ],

    'validation' => [
        'phone_regex' => 'कृपया सही 10 अंकों का भारतीय मोबाइल नंबर डालें।',
        'registration_number_regex' => 'पंजीकरण नंबर ठीक वैसे ही डालें जैसा दिया गया था, जैसे RPPL-2026-000125।',
    ],

];
