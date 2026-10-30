<?php
$files = [
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

// Check HTML Section 1 and Section 2 specifically
$html = file_get_contents(__DIR__ . '/docs/rastchin-product-page.html');

// Extract Section 1
if (preg_match('/<h2>۱\. معرفی محصول<\/h2>\s*<p>(.*?)<\/p>/s', $html, $m1)) {
    $p = trim($m1[1]);
    $lines = explode('<br>', $p);
    echo "Section 1 lines count: " . count($lines) . "\n";
    if (count($lines) !== 2) {
        $errors[] = "Section 1 does not have exactly 2 lines (separated by <br>). Found: " . count($lines);
    }
    $text_plain = str_replace('<br>', ' ', $p);
    $text_plain = strip_tags($text_plain);
    $words = preg_split('/\s+/u', trim($text_plain));
    echo "Section 1 word count: " . count($words) . "\n";
    if (count($words) > 35) {
        $errors[] = "Section 1 exceeds 35 words. Found: " . count($words);
    }
} else {
    $errors[] = "Could not match Section 1 in HTML";
}

// Extract Section 2
if (preg_match('/<h2>۲\. ویژگی‌های اصلی<\/h2>\s*<ul>(.*?)<\/ul>/s', $html, $m2)) {
    preg_match_all('/<li>(.*?)<\/li>/s', $m2[1], $items);
    $count = count($items[1]);
    echo "Section 2 items count: " . $count . "\n";
    if ($count !== 5) {
        $errors[] = "Section 2 does not have exactly 5 items. Found: $count";
    }
    foreach ($items[1] as $idx => $it) {
        $it_plain = strip_tags($it);
        $words = preg_split('/\s+/u', trim($it_plain));
        $wcount = count($words);
        echo "Section 2 item " . ($idx + 1) . " word count: " . $wcount . " | " . $it_plain . "\n";
        if ($wcount > 10) {
            $errors[] = "Section 2 item " . ($idx + 1) . " exceeds 10 words. Found: $wcount words";
        }
    }
} else {
    $errors[] = "Could not match Section 2 in HTML";
}

if (!empty($errors)) {
    echo "\nERRORS:\n";
    foreach ($errors as $e) {
        echo " - $e\n";
    }
    exit(1);
}

echo "\nALL PRODUCT PAGE RULES PASSED PERFECTLY!\n";
exit(0);
