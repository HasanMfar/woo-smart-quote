<?php
$lines = file('docs/demo-preview.html');
foreach ($lines as $i => $l) {
    if (stripos($l, '.pdf') !== false || stripos($l, 'پیش‌فاکتور') !== false) {
        if (stripos($l, 'href') !== false || stripos($l, 'button') !== false || stripos($l, 'download') !== false) {
            echo ($i + 1) . ': ' . trim($l) . "\n";
        }
    }
}
