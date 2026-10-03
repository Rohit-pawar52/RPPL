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
        'payment_instructions' => 'पंजीकरण शुल्क UPI या बैंक ट्रांसफर से भरें, फिर नीचे भुगतान का स्क्रीनशॉट अपलोड करें (UTR / ट्रांज़ैक्शन ID डालना ज़रूरी नहीं)। फ़ॉर्म जमा होने के बाद :league प्रबंधन आपके भुगतान और जानकारी की जाँच करेगा।',
        'payment_instructions_upi' => 'पंजीकरण शुल्क नीचे दिए QR कोड या UPI ID से भरें, फिर भुगतान का स्क्रीनशॉट अपलोड करें (UTR / ट्रांज़ैक्शन ID डालना ज़रूरी नहीं)। फ़ॉर्म जमा होने के बाद :league प्रबंधन आपके भुगतान और जानकारी की जाँच करेगा।',
        'already_registered' => 'पहले से पंजीकरण कर चुके हैं?',
        'check_status_link' => 'पंजीकरण की स्थिति देखें',
        'required_note' => '* वाले खाने भरना ज़रूरी है।',
        'submit' => 'पंजीकरण जमा करें',
        'submitting' => 'जमा हो रहा है…',
        'sections' => [
            'you' => 'आपके बारे में',
            'playing' => 'खेल की जानकारी',
            'address' => 'आप कहाँ रहते हैं',
            'photo' => 'आपकी फ़ोटो',
            'payment' => 'भुगतान',
        ],
    ],

    'closed' => [
        'message' => 'खिलाड़ी पंजीकरण अभी बंद है।',
        'contact' => 'कृपया बाद में फिर देखें या :league प्रबंधन से संपर्क करें।',
    ],

    'window' => [
        'not_yet_open' => 'पंजीकरण अभी शुरू नहीं हुआ है।',
        'opens_on' => 'पंजीकरण :date से शुरू होगा।',
        'closes_on' => 'पंजीकरण :date को बंद होगा।',
    ],

    'fields' => [
        'name' => 'पूरा नाम',
        'age' => 'उम्र (ज़रूरी नहीं)',
        'age_placeholder' => 'साल में',
        'phone' => 'मोबाइल नंबर',
        'phone_placeholder' => '10 अंकों का मोबाइल नंबर',
        'email' => 'ईमेल (ज़रूरी नहीं)',
        'player_type' => 'खिलाड़ी का प्रकार',
        'batting_style' => 'बल्लेबाज़ी का हाथ',
        'bowling_style' => 'गेंदबाज़ी का हाथ',
        'village' => 'गाँव (ग्राम)',
        'tehsil' => 'तहसील',
        'district' => 'ज़िला',
        'photo' => 'आपकी फ़ोटो',
        'photo_hint' => 'आपके चेहरे की साफ़ फ़ोटो — JPEG, PNG या WebP, ज़्यादा से ज़्यादा :size MB।',
        'submitted_utr' => 'UTR / ट्रांज़ैक्शन ID (ज़रूरी नहीं)',
        'submitted_utr_hint' => 'भुगतान के बाद आपके पेमेंट ऐप में दिखता है — UPI में यह आमतौर पर 12 अंकों का होता है।',
        'payment_proof' => 'भुगतान का स्क्रीनशॉट',
        'payment_proof_hint' => 'UPI या बैंक भुगतान का स्क्रीनशॉट — JPEG या PNG, ज़्यादा से ज़्यादा :size MB।',
        'registration_number' => 'पंजीकरण नंबर',
        'registration_number_optional' => 'पंजीकरण नंबर (ज़रूरी नहीं)',
    ],

    'roles' => [
        'batter' => 'बल्लेबाज़',
        'bowler' => 'गेंदबाज़',
        'all_rounder' => 'ऑलराउंडर',
        'wicket_keeper' => 'विकेटकीपर',
    ],

    'batting_styles' => [
        'right_hand' => 'दायाँ हाथ',
        'left_hand' => 'बायाँ हाथ',
    ],

    'bowling_styles' => [
        'right_arm' => 'दायाँ हाथ',
        'left_arm' => 'बायाँ हाथ',
        'none' => 'गेंदबाज़ी नहीं करता',
    ],

    'payment' => [
        'amount' => 'भुगतान की राशि',
        'scan' => 'किसी भी UPI ऐप से यह QR कोड स्कैन करें',
        'upi_id' => 'UPI ID',
        'copy' => 'कॉपी करें',
        'copied' => 'कॉपी हो गया',
        'pay_with_app' => 'UPI ऐप से भुगतान करें',
        'pay_with_app_hint' => 'फ़ोन पर अपनी ही स्क्रीन का QR कोड स्कैन नहीं हो सकता — इसके बजाय इस बटन से अपना UPI ऐप खोलें।',
    ],

    'js' => [
        'too_large' => 'यह फ़ाइल :size MB से बड़ी है। कृपया छोटी फ़ाइल चुनें।',
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
        'verification_notice' => ':league प्रबंधन आपके भुगतान और जानकारी की जाँच करेगा। इसमें कुछ दिन लग सकते हैं।',
        'return_notice' => 'आप कभी भी सिर्फ़ अपने मोबाइल नंबर से अपनी स्थिति देख सकते हैं (पंजीकरण नंबर ज़रूरी नहीं):',
        'back_home' => ':league होम पर वापस जाएँ',
    ],

    'status' => [
        'heading' => 'पंजीकरण की स्थिति देखें',
        'intro' => 'वह मोबाइल नंबर डालें जिससे आपने पंजीकरण किया था। पंजीकरण नंबर हो तो वह भी डालें, तब आपका पूरा नाम दिखेगा।',
        'number_optional_hint' => 'नंबर खो गया? इसे खाली छोड़ें और सिर्फ़ मोबाइल नंबर डालें।',
        'submit' => 'स्थिति देखें',
        'registration_number' => 'पंजीकरण नंबर',
        'player_name' => 'खिलाड़ी का नाम',
        'edition' => 'संस्करण',
        'registration_fee' => 'पंजीकरण शुल्क',
        'payment_status' => 'भुगतान की स्थिति',
        'registered_at' => 'पंजीकरण की तारीख',
        'not_found' => 'कोई पंजीकरण नहीं मिला। कृपया अपना मोबाइल नंबर जाँच लें, या पंजीकरण नंबर खाली छोड़ दें।',
        'back_to_registration' => 'खिलाड़ी पंजीकरण पर वापस जाएँ',
        'payment' => [
            'pending' => 'जाँच बाकी है',
            'pending_message' => 'आपके भुगतान के सबूत की जाँच अभी बाकी है।',
            'paid' => 'भुगतान हो गया',
            'paid_message' => 'आपके भुगतान की पुष्टि हो गई है।',
            'failed' => 'भुगतान की पुष्टि नहीं हो सकी',
            'failure_reason' => 'कारण:',
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

    'attributes' => [
        'age' => 'उम्र',
        'batting_style' => 'बल्लेबाज़ी का हाथ',
        'bowling_style' => 'गेंदबाज़ी का हाथ',
        'village' => 'गाँव',
        'tehsil' => 'तहसील',
        'district' => 'ज़िला',
        'photo' => 'फ़ोटो',
        'submitted_utr' => 'UTR / ट्रांज़ैक्शन ID',
    ],

    'validation' => [
        'phone_regex' => 'कृपया सही 10 अंकों का भारतीय मोबाइल नंबर डालें।',
        'age_between' => 'कृपया :min से :max के बीच की उम्र डालें।',
        'utr_regex' => 'अपने पेमेंट ऐप में दिखने वाला UTR / ट्रांज़ैक्शन ID डालें — सिर्फ़ अक्षर और अंक, 8 से 30 वर्ण।',
        'file_max' => 'फ़ाइल बहुत बड़ी है। कृपया :size MB तक की फ़ाइल चुनें।',
        'registration_number_regex' => 'पंजीकरण नंबर ठीक वैसे ही डालें जैसा दिया गया था, जैसे RPPL-2026-000125।',
    ],

];
