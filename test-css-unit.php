<?php
$c = file_get_contents(__DIR__ . '/woo-smart-quote/assets/css/admin.css');
$checks = [
    '.wsq-savebar.is-dirty',
    '.wsq-savebar-dirty-pill',
    'padding-inline-end: 38px',
    '.wsq-routes-table tr.wsq-no-match-row td',
    '.wsq-stamp-preview-card.is-disabled',
    '.wsq-preview-floating-demo.is-disabled'
];
$passed = true;
foreach ($checks as $check) {
    if (strpos($c, $check) === false) {
        echo "FAIL: Missing {$check}\n";
        $passed = false;
    } else {
        echo "PASS: Found {$check}\n";
    }
}
// Also verify no legacy Tailwind colors exist
$legacy_tailwind = ['#0f172a', '#64748b', '#2563eb'];
foreach ($legacy_tailwind as $tw) {
    if (stripos($c, $tw) !== false) {
        echo "FAIL: Contains legacy Tailwind color {$tw}\n";
        $passed = false;
    } else {
        echo "PASS: Clean of {$tw}\n";
    }
}
if (!$passed) {
    exit(1);
}
echo "All CSS assertions passed!\n";
