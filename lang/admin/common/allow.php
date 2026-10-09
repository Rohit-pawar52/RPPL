<?php

/**
 * What the English-only scan (tests/Support/AdminI18n.php) may leave untranslated: acronyms, brand names,
 * units and the like. A text made only of these words is fine. 'files' lists files (or folders) skipped
 * entirely, with the reason.
 */
return [
    'strings' => [
        'RPPL', 'UTR', 'PDF', 'CSV', 'XLSX', 'URL', 'UPI', 'QR', 'ID', 'OTP', 'SMS', 'FCM', 'IST', 'UTC', 'AM', 'PM', 'GST', 'PAN',
        'WhatsApp', 'YouTube', 'Facebook', 'Instagram', 'Google', 'Firebase', 'Reverb', 'Ctrl', 'Cmd', 'Esc', 'Enter', 'KB', 'MB', 'px',
        'Ctrl K', 'No', 'NB', 'WD', 'LB', 'Wd', 'Nb', 'Lb', 'Lbw', 'OK', 'ok', 'A', 'B', 'C', 'D', 'MI', 'CSK', 'RCB', 'KKR',
    ],
    'patterns' => [],
    'files' => [
        // The season report is a printable PDF, English by design (a Hindi PDF needs a Devanagari font).
        'admin/editions/report-pdf.blade.php',
    ],
];
