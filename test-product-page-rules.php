<?php
/**
 * Test Rastchin Product Page and Description Rules
 */

// Auto-load mbstring via local ini if running default CLI PHP
if ( ! extension_loaded( 'mbstring' ) ) {
    $ini = file_exists( __DIR__ . '/php-test.ini' ) ? __DIR__ . '/php-test.ini' : ( file_exists( __DIR__ . '/wp-cli.ini' ) ? __DIR__ . '/wp-cli.ini' : '' );
    if ( $ini && empty( getenv( 'WSQ_RULES_REEXEC' ) ) ) {
        putenv( 'WSQ_RULES_REEXEC=1' );
        passthru( 'php -c ' . escapeshellarg( $ini ) . ' ' . escapeshellarg( __FILE__ ), $ret );
        exit( $ret );
    }
}

$files = [
    'docs/rastchin-description.html',
    'docs/rastchin-submission-guide.md',
    'docs/rastchin-product-page.html',
    'docs/rastchin-product-page.md'
];

$buzzwords = [
    'جامع',
    'هوشمند',
    '۱۰۰٪',
    'کاملاً',
    'بی‌نظیر',
    'بینظیر'
];

$errors = [];

foreach ($files as $file) {
    if (!file_exists(__DIR__ . '/' . $file)) {
        $errors[] = "Missing required file: $file";
        continue;
    }
    $content = file_get_contents(__DIR__ . '/' . $file);
    if (!$content) {
        $errors[] = "Could not read $file";
        continue;
    }
    
    // Check forbidden buzzwords
    foreach ($buzzwords as $b) {
        $pos = 0;
        while (($pos = mb_strpos($content, $b, $pos, 'UTF-8')) !== false) {
            $context = mb_substr($content, max(0, $pos - 30), 60, 'UTF-8');
            $errors[] = "Forbidden buzzword '{$b}' found in {$file}: ...{$context}...";
            $pos += mb_strlen($b, 'UTF-8');
        }
    }
}

// Check HTML files and markdown embed code for non-numbered <h2> headings and structure
$targets_to_check = [
    'docs/rastchin-description.html' => file_get_contents(__DIR__ . '/docs/rastchin-description.html'),
    'docs/rastchin-product-page.html' => file_get_contents(__DIR__ . '/docs/rastchin-product-page.html'),
];

// Extract embedded HTML snippet from docs/rastchin-product-page.md
$md_content = file_get_contents(__DIR__ . '/docs/rastchin-product-page.md');
if (preg_match('/```html\s*(<div class="rastchin-product-description".*?<\/div>)\s*```/s', $md_content, $md_m)) {
    $targets_to_check['docs/rastchin-product-page.md (embedded HTML)'] = $md_m[1];
}

foreach ($targets_to_check as $hfile => $html) {
    echo "\nTesting rules for $hfile...\n";

    // 1. Validate that NO <h2> has number or section prefixes (e.g. <h2>۱. or <h2>1. or <h2>بخش ۱:)
    if (preg_match_all('/<h2>\s*([0-9\x{06F0}-\x{06F9}]+[\.\-\s]|(?:بخش|فصل|قسمت)\s*[\x{06F0}-\x{06F9}0-9\x{0621}-\x{064A}]+[\.\-\:\s][^<]*)<\/h2>/u', $html, $bad_h2)) {
        foreach ($bad_h2[1] as $numbered) {
            $errors[] = "Found numbered/prefixed <h2> heading in {$hfile}: '{$numbered}'";
        }
    } else {
        echo "PASS: Zero numbered/prefixed <h2> headings found in $hfile.\n";
    }

    // 2. Validate Section: معرفی محصول (exactly 2 lines, <= 35 words)
    if (preg_match('/<h2>معرفی محصول<\/h2>\s*<p>(.*?)<\/p>/s', $html, $m1)) {
        $p = trim($m1[1]);
        $lines = explode('<br>', $p);
        echo "  - Section 'معرفی محصول' lines count: " . count($lines) . "\n";
        if (count($lines) !== 2) {
            $errors[] = "Section 'معرفی محصول' in {$hfile} does not have exactly 2 lines (separated by <br>). Found: " . count($lines);
        }
        $text_plain = str_replace('<br>', ' ', $p);
        $text_plain = strip_tags($text_plain);
        $words = preg_split('/\s+/u', trim($text_plain));
        echo "  - Section 'معرفی محصول' word count: " . count($words) . "\n";
        if (count($words) > 35) {
            $errors[] = "Section 'معرفی محصول' in {$hfile} exceeds 35 words. Found: " . count($words);
        }
    } else {
        $errors[] = "Could not match <h2>معرفی محصول</h2> in {$hfile}";
    }

    // 3. Validate Section: ویژگی‌های اصلی (exactly 5 items, each <= 10 words)
    if (preg_match('/<h2>ویژگی‌های اصلی<\/h2>\s*<ul>(.*?)<\/ul>/s', $html, $m2)) {
        preg_match_all('/<li>(.*?)<\/li>/s', $m2[1], $items);
        $count = count($items[1]);
        echo "  - Section 'ویژگی‌های اصلی' items count: " . $count . "\n";
        if ($count !== 5) {
            $errors[] = "Section 'ویژگی‌های اصلی' in {$hfile} does not have exactly 5 items. Found: $count";
        }
        foreach ($items[1] as $idx => $it) {
            $it_plain = strip_tags($it);
            $words = preg_split('/\s+/u', trim($it_plain));
            $wcount = count($words);
            echo "    item " . ($idx + 1) . " word count: " . $wcount . " | " . $it_plain . "\n";
            if ($wcount > 10) {
                $errors[] = "Section 'ویژگی‌های اصلی' item " . ($idx + 1) . " in {$hfile} exceeds 10 words. Found: $wcount words";
            }
        }
    } else {
        $errors[] = "Could not match <h2>ویژگی‌های اصلی</h2> in {$hfile}";
    }

    // 4. Validate no artificial CDN links
    if (preg_match('/YOUR-SITE-CDN/i', $html)) {
        $errors[] = "Artificial placeholder CDN link found in {$hfile}";
    } else {
        echo "PASS: Zero artificial CDN links in $hfile.\n";
    }

    // 5. Validate no stale section number references
    if (preg_match('/بخش\s+[0-9\x{06F0}-\x{06F9}]+/u', $html, $bad_sec_ref)) {
        $errors[] = "Stale section number reference found in {$hfile}: '{$bad_sec_ref[0]}'";
    } else {
        echo "PASS: Zero stale section number references in $hfile.\n";
    }
}

if (!empty($errors)) {
    echo "\nERRORS (" . count($errors) . "):\n";
    foreach ($errors as $e) {
        echo " - $e\n";
    }
    exit(1);
}

echo "\nALL PRODUCT PAGE RULES PASSED PERFECTLY!\n";
exit(0);
