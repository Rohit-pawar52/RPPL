<?php

// What the English-only scan may leave untranslated in this area (acronyms, brand names, units); see lang/admin/common/allow.php.
return [
    'strings' => [
        // Short badge for a Free Hit ball in the scoring tables; the same two letters are used in Hindi.
        'FH',
        // The column names of the simple import format (a CSV header).
        'name,phone,email,registration_fee,payment_status,registered_at',
    ],
    'patterns' => [],
    'files' => [
        // The printable result is a PDF, English like the season report (a Hindi PDF needs a Devanagari font).
        'admin/auctions/results-pdf.blade.php',
    ],
];
