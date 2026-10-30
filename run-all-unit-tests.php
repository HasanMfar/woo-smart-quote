<?php
$tests = [
    'test-abuse-controls-unit.php',
    'test-bootstrap-unit.php',
    'test-bot-relay-unit.php',
    'test-cron-schedule-unit.php',
    'test-delivery-unit.php',
    'test-pdf-delivery-unit.php',
    'test-pricing-unit.php',
    'test-provider-unit.php',
    'test-rest-pricing-unit.php',
    'test-security-unit.php',
    'test-session-unit.php',
    'test-updates-unit.php',
    'test-admin-apk-download-unit.php'
];

$failed = [];
$passed = [];

foreach ($tests as $t) {
    echo "====================================================\n";
    echo "Running: $t\n";
    echo "====================================================\n";
    $output = [];
    $code = 0;
    exec("php -n " . escapeshellarg(__DIR__ . '/' . $t) . " 2>&1", $output, $code);
    echo implode("\n", $output) . "\n";
    if ($code !== 0) {
        $failed[] = $t;
        echo "--> FAILED (code $code)\n\n";
    } else {
        $passed[] = $t;
        echo "--> PASSED\n\n";
    }
}

echo "====================================================\n";
echo "SUMMARY: " . count($passed) . " PASSED, " . count($failed) . " FAILED\n";
echo "====================================================\n";

if (!empty($failed)) {
    echo "Failed tests:\n - " . implode("\n - ", $failed) . "\n";
    exit(1);
}

echo "ALL 13 TEST SUITES PASSED!\n";
exit(0);
