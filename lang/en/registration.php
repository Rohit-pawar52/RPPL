<?php

/**
 * Public player-registration UI strings: the registration form, its
 * success page, the registration-status lookup page, the service-layer
 * rejection messages shown back on the form, and the form-specific
 * validation messages (phone / UTR / file size / registration-number
 * format).
 *
 * Only static app text lives here — never a player's name, phone,
 * registration number, edition name, or any other stored value; those
 * always render exactly as stored regardless of locale. `:league` is
 * replaced with the branding short name (e.g. "RPPL"); `:size` is the
 * largest upload the server accepts, in MB.
 *
 * Generic validation-rule messages (required/max/mimes/...) for the
 * Hindi locale live in lang/hi/validation.php instead — that's where
 * Laravel's validator looks for them.
 */
return [

    'titles' => [
        'registration' => 'Player Registration',
        'success' => 'Registration Successful',
        'status' => 'Check Registration Status',
    ],

    'form' => [
        'heading' => 'Player Registration',
        'fee_label' => 'Registration fee:',
        'payment_instructions' => 'Pay the registration fee by UPI/bank transfer, then enter the UTR / Transaction ID and upload a screenshot of the payment below. Payment and details are verified manually by :league administration after submission.',
        'payment_instructions_upi' => 'Pay the registration fee using the QR code or UPI ID below, then enter the UTR / Transaction ID and upload a screenshot of the payment. Payment and details are verified manually by :league administration after submission.',
        'already_registered' => 'Already registered?',
        'check_status_link' => 'Check Registration Status',
        'required_note' => 'Fields marked * are required.',
        'submit' => 'Submit Registration',
        'submitting' => 'Submitting…',
        'sections' => [
            'you' => 'About you',
            'playing' => 'Playing details',
            'address' => 'Where you live',
            'photo' => 'Your photo',
            'payment' => 'Payment',
        ],
    ],

    'closed' => [
        'message' => 'Player registration is currently closed.',
        'contact' => 'Please check back later or contact :league administration.',
    ],

    'window' => [
        'not_yet_open' => 'Registration has not opened yet.',
        'opens_on' => 'Registration opens on :date.',
        'closes_on' => 'Registration closes on :date.',
    ],

    'fields' => [
        'name' => 'Full Name',
        'age' => 'Age',
        'age_placeholder' => 'Age in years',
        'phone' => 'Phone Number',
        'phone_placeholder' => '10-digit mobile number',
        'email' => 'Email (optional)',
        'player_type' => 'Player Type',
        'batting_style' => 'Batting hand',
        'bowling_style' => 'Bowling arm',
        'village' => 'Village (Gram)',
        'tehsil' => 'Tehsil',
        'district' => 'District',
        'photo' => 'Your Photo',
        'photo_hint' => 'A clear photo of your face — JPEG, PNG or WebP, up to :size MB.',
        'submitted_utr' => 'UTR / Transaction ID',
        'submitted_utr_hint' => 'Shown in your payment app after you pay — for UPI it is usually 12 digits.',
        'payment_proof' => 'Payment Screenshot',
        'payment_proof_hint' => 'Screenshot of your UPI/bank payment — JPEG or PNG, up to :size MB.',
        'registration_number' => 'Registration Number',
    ],

    // Display labels for Player::PRIMARY_ROLES — the submitted option
    // VALUE (batter/bowler/...) never changes with the locale.
    'roles' => [
        'batter' => 'Batter',
        'bowler' => 'Bowler',
        'all_rounder' => 'All-rounder',
        'wicket_keeper' => 'Wicket Keeper',
    ],

    // Labels for Player::BATTING_STYLES / BOWLING_STYLES (values never
    // translated).
    'batting_styles' => [
        'right_hand' => 'Right hand',
        'left_hand' => 'Left hand',
    ],

    'bowling_styles' => [
        'right_arm' => 'Right arm',
        'left_arm' => 'Left arm',
        'none' => "Doesn't bowl",
    ],

    'payment' => [
        'amount' => 'Amount to pay',
        'scan' => 'Scan this QR code with any UPI app',
        'upi_id' => 'UPI ID',
        'copy' => 'Copy',
        'copied' => 'Copied',
        'pay_with_app' => 'Pay with a UPI app',
        'pay_with_app_hint' => 'On a phone you cannot scan a QR code shown on the same screen — tap this to open your UPI app instead.',
    ],

    // Browser-side messages (the file-size check before uploading).
    'js' => [
        'too_large' => 'This file is larger than :size MB. Please choose a smaller one.',
    ],

    'success' => [
        'heading' => 'Registration Successful',
        'registration_number' => 'Registration Number',
        'save_notice' => 'Please save this Registration Number — you will need it to check your status later.',
        'edition' => 'Edition',
        'player_name' => 'Player Name',
        'payment_status' => 'Payment Status',
        'pending' => 'Pending',
        'registration_fee' => 'Registration Fee',
        'verification_notice' => 'Your payment and details will be manually verified by :league administration. This may take a few days.',
        'return_notice' => 'You can come back anytime and check your status using your Registration Number:',
        'back_home' => 'Back to :league home',
    ],

    'status' => [
        'heading' => 'Check Registration Status',
        'intro' => 'Enter your Registration Number and the phone number you registered with.',
        'submit' => 'Check Status',
        'registration_number' => 'Registration Number',
        'player_name' => 'Player Name',
        'edition' => 'Edition',
        'registration_fee' => 'Registration Fee',
        'payment_status' => 'Payment Status',
        'registered_at' => 'Registered At',
        'not_found' => 'No matching registration was found. Please check your Registration Number and Phone Number.',
        'back_to_registration' => 'Back to Player Registration',
        'payment' => [
            'pending' => 'Pending Verification',
            'pending_message' => 'Your payment proof is awaiting manual verification.',
            'paid' => 'Paid',
            'paid_message' => 'Your payment has been verified.',
            'failed' => 'Payment Verification Failed',
            'failed_message' => 'Your payment could not be verified. Please contact :league administration.',
            'refunded' => 'Refunded',
            'refunded_message' => 'Your payment is marked as refunded.',
        ],
    ],

    // Thrown by GuestPlayerRegistrationService (field: phone) or flashed
    // by the controller when no edition is accepting registrations.
    // "RPPL" stays literal — the acronym never changes.
    'errors' => [
        'generic_rejection' => "We couldn't process the registration with the details provided. Please contact RPPL administration.",
        'duplicate' => 'A registration already exists for these details. Please use the registration status option or contact RPPL administration.',
        'closed' => 'Player registration is currently closed.',
    ],

    // Plain-language names for the validation messages of the newer
    // fields ("The village field is required."). The older fields use the
    // names in lang/{locale}/validation.php.
    'attributes' => [
        'age' => 'age',
        'batting_style' => 'batting hand',
        'bowling_style' => 'bowling arm',
        'village' => 'village',
        'tehsil' => 'tehsil',
        'district' => 'district',
        'photo' => 'photo',
        'submitted_utr' => 'UTR / transaction ID',
    ],

    // Form-specific overrides returned from the FormRequests' messages().
    'validation' => [
        'phone_regex' => 'Enter a valid 10-digit Indian mobile number.',
        'age_between' => 'Enter an age between :min and :max.',
        'utr_regex' => 'Enter the UTR / Transaction ID from your payment app — letters and numbers only, 8 to 30 characters.',
        'file_max' => 'The file is too large. Please choose one up to :size MB.',
        'registration_number_regex' => 'Enter your Registration Number exactly as given, e.g. RPPL-2026-000125.',
    ],

];
