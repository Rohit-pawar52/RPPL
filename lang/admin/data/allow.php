<?php

// What the English-only scan may leave untranslated in this area (acronyms, brand names, units); see lang/admin/common/allow.php.
return [
    'strings' => [],
    'patterns' => [],
    'files' => [
        // The contribution receipt is a printable PDF handed to the contributor, English by design (its font,
        // DejaVu Sans, has no Devanagari, and the receipt is the same document whatever language the admin uses).
        'admin/edition-contributions/_receipt.blade.php',
        'admin/edition-contributions/receipt.blade.php',
        'admin/edition-contributions/receipts-batch.blade.php',
    ],
];
