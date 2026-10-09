<?php

// Field names as they appear inside validation messages (field => Hindi); see AppServiceProvider::configureAdminTranslations().
// Names every area shares (name, email, phone, title, status, is_active, address, logo, short_name ...) are defined
// once in lang/admin/data/attributes.php with the same Hindi, so they are not repeated here.
return [
    // sign-in, account, users, roles
    'password' => 'पासवर्ड',
    'password_confirmation' => 'पासवर्ड की पुष्टि',
    'current_password' => 'अभी का पासवर्ड',
    'remember' => 'साइन इन रखना',
    'role_id' => 'भूमिका',
    'permissions' => 'अनुमतियाँ',
    'permissions.*' => 'अनुमति',

    // settings: general and theme
    'application_name' => 'ऐप का नाम',
    'tagline' => 'टैगलाइन',
    'favicon' => 'फ़ेविकॉन',
    'remove_logo' => 'लोगो हटाना',
    'remove_favicon' => 'फ़ेविकॉन हटाना',
    'primary_color' => 'मुख्य रंग',
    'secondary_color' => 'दूसरा रंग',
    'header_color' => 'हेडर का रंग',
    'button_color' => 'बटन का रंग',
    'button_hover_color' => 'बटन के होवर का रंग',
    'button_text_color' => 'बटन के टेक्स्ट का रंग',
    'button_shape' => 'बटन का आकार',
    'link_hover_color' => 'लिंक के होवर का रंग',
    'hover_color' => 'होवर का हाइलाइट',
    'announcement_background_color' => 'घोषणा पट्टी की पृष्ठभूमि',
    'announcement_text_color' => 'घोषणा पट्टी का टेक्स्ट',

    // settings: contact, system, payments, public website
    'whatsapp' => 'व्हाट्सऐप',
    'maintenance_mode' => 'मेंटेनेंस मोड',
    'maintenance_message' => 'मेंटेनेंस का संदेश',
    'currency' => 'मुद्रा कोड',
    'currency_symbol' => 'मुद्रा का चिह्न',
    'display_timezone' => 'दिखाने का टाइमज़ोन',
    'committee_minimum_contribution' => 'कमेटी का न्यूनतम योगदान',
    'tournament_day_reminder_enabled' => 'सुबह की याद',
    'tournament_day_reminder_time' => 'याद भेजने का समय',
    'failed_jobs_auto_cleanup_enabled' => 'फ़ेल जॉब की अपने आप सफ़ाई',
    'failed_jobs_retention_days' => 'रखने की अवधि (दिन)',
    'upi_id' => 'UPI ID',
    'upi_qr' => 'UPI QR कोड',
    'remove_upi_qr' => 'QR कोड हटाना',
    'razorpay_enabled' => 'Razorpay',
    'razorpay_mode' => 'मोड',
    'razorpay_key_id' => 'की ID',
    'razorpay_key_secret' => 'की सीक्रेट',
    'razorpay_webhook_secret' => 'वेबहुक सीक्रेट',
    'footer_text' => 'फ़ुटर का टेक्स्ट',

    // notifications, announcements
    'message' => 'संदेश',
    'action_url' => 'एक्शन URL',
    'starts_at' => 'शुरू होने की तारीख़ और समय',
    'ends_at' => 'खत्म होने की तारीख़ और समय',
    'notification_choice' => 'पुश नोटिफ़िकेशन का विकल्प',
    'notification_scheduled_at' => 'भेजने का समय',

    // advertisements
    'tier' => 'प्रायोजक का स्तर',
    'format' => 'सामान्य प्रायोजक की जगह',
    'media_type' => 'प्रकार',
    'media' => 'फ़ोटो या वीडियो',
    'poster' => 'प्रीव्यू फ़ोटो',
    'weight' => 'कितनी बार',
    'starts_on' => 'दिखाना शुरू',
    'ends_on' => 'दिखाना खत्म',

    // analytics filters
    'range' => 'तारीख की सीमा',
    'from_date' => 'शुरू की तारीख',
    'to_date' => 'आखिरी तारीख',
];
