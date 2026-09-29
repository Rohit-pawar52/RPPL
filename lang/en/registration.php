<?php

/**
 * Public player-registration UI strings: the registration form, its
 * success page, the registration-status lookup page, the service-layer
 * rejection messages shown back on the form, and the two form-specific
 * validation messages (phone / registration-number format).
 *
 * Only static app text lives here — never a player's name, phone,
 * registration number, edition name, or any other stored value; those
 * always render exactly as stored regardless of locale. `:league` is
 * replaced with the branding short name (e.g. "RPPL").
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
        'payment_instructions' => 'Pay the registration fee via UPI/bank transfer and upload your payment proof below. Payment and document verification is done manually by :league administration after submission.',
        'already_registered' => 'Already registered?',
        'check_status_link' => 'Check Registration Status',
        'submit' => 'Submit Registration',
    ],

    'closed' => [
        'message' => 'Player registration is currently closed.',
        'contact' => 'Please check back later or contact :league administration.',
    ],

    'fields' => [
        'name' => 'Full Name',
        'phone' => 'Phone Number',
        'phone_placeholder' => '10-digit mobile number',
        'email' => 'Email (optional)',
        'date_of_birth' => 'Date of Birth',
        'player_type' => 'Player Type',
        'player_type_placeholder' => 'Select player type',
        'aadhaar_document' => 'Aadhaar Document',
        'aadhaar_hint' => 'JPEG, PNG, or PDF — up to 4 MB.',
        'payment_proof' => 'Payment Proof',
        'payment_proof_hint' => 'Screenshot of your UPI/bank payment — JPEG or PNG, up to 2 MB.',
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

    'success' => [
        'heading' => 'Registration Successful',
        'registration_number' => 'Registration Number',
        'save_notice' => 'Please save this Registration Number — you will need it to check your status later.',
        'edition' => 'Edition',
        'player_name' => 'Player Name',
        'payment_status' => 'Payment Status',
        'pending' => 'Pending',
        'registration_fee' => 'Registration Fee',
        'verification_notice' => 'Your payment and documents will be manually verified by :league administration. This may take a few days.',
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

    // Form-specific overrides returned from the FormRequests' messages().
    'validation' => [
        'phone_regex' => 'Enter a valid 10-digit Indian mobile number.',
        'registration_number_regex' => 'Enter your Registration Number exactly as given, e.g. RPPL-2026-000125.',
    ],

];
