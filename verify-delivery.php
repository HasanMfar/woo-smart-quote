<?php
/**
 * Automated Verification Script for Nerkhnegar Deliverables
 */

// Auto-load php extensions via local ini if running default CLI PHP
if ( ! extension_loaded( 'mbstring' ) || ! class_exists( 'ZipArchive' ) ) {
    $ini = file_exists( __DIR__ . '/php-test.ini' ) ? __DIR__ . '/php-test.ini' : ( file_exists( __DIR__ . '/wp-cli.ini' ) ? __DIR__ . '/wp-cli.ini' : '' );
    if ( $ini && empty( getenv( 'WSQ_VERIFY_REEXEC' ) ) ) {
        putenv( 'WSQ_VERIFY_REEXEC=1' );
        passthru( 'php -c ' . escapeshellarg( $ini ) . ' ' . escapeshellarg( __FILE__ ), $ret );
        exit( $ret );
    }
}

$root = __DIR__;
$errors = [];
$successes = [];

echo "=== Nerkhnegar Deliverables Verification ===\n\n";

// 1. Check Product Name occurrences (normalized against tashkeel, tatweel, and zwnj)
function normalize_persian_text( $text ) {
    $clean = preg_replace( '/[\x{064B}-\x{065F}\x{0640}\x{200C}\s]/u', '', (string) $text );
    return null !== $clean ? $clean : '';
}

$forbidden_normalized = ['مظنهچند', 'مظنهنگار', 'مظنکار'];
$forbidden_latin      = ['mazankar', 'mazannehchand', 'mazannehnegar'];
$scanned_files = 0;
$clean_name = true;

$scan_dirs = [ $root . '/woo-smart-quote', $root . '/docs', $root . '/wsq_companion' ];
foreach ( $scan_dirs as $sdir ) {
    if ( ! is_dir( $sdir ) ) continue;
    $it = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $sdir, FilesystemIterator::SKIP_DOTS ) );
    foreach ( $it as $f ) {
        if ( $f->isFile() && in_array( $f->getExtension(), ['php', 'txt', 'po', 'pot', 'json', 'css', 'js', 'html', 'md', 'dart', 'yaml', 'xml'] ) ) {
            // Skip legacy banner-rastchin-persian.html
            if ( strpos( $f->getPathname(), 'banner-rastchin-persian.html' ) !== false ) {
                continue;
            }
            $content = file_get_contents( $f->getPathname() );
            $scanned_files++;
            $norm = normalize_persian_text( $content );
            foreach ( $forbidden_normalized as $bad ) {
                if ( mb_strpos( $norm, $bad ) !== false ) {
                    $errors[] = "Forbidden name '{$bad}' found in: " . $f->getPathname();
                    $clean_name = false;
                }
            }
            $lower = strtolower( $content );
            foreach ( $forbidden_latin as $bad_lat ) {
                if ( strpos( $lower, $bad_lat ) !== false ) {
                    $errors[] = "Forbidden latin name '{$bad_lat}' found in: " . $f->getPathname();
                    $clean_name = false;
                }
            }
        }
    }
}
if ( $clean_name ) {
    $successes[] = "Name check passed: Zero occurrences of rejected names in woo-smart-quote, docs, and wsq_companion ({$scanned_files} files scanned).";
}

// 2. Icon 320x320 check (dimensions + valid JPEG magic bytes)
$icon_png = $root . '/docs/icon-320x320.png';
$icon_jpg = $root . '/docs/icon-320x320.jpg';
$asset_icon_jpg = $root . '/woo-smart-quote/assets/images/icon-320x320.jpg';

if ( file_exists( $icon_png ) && file_exists( $icon_jpg ) ) {
    $info_png = getimagesize( $icon_png );
    $info_jpg = getimagesize( $icon_jpg );
    $jpg_header = bin2hex( substr( file_get_contents( $icon_jpg ), 0, 3 ) );
    $asset_jpg_header = file_exists( $asset_icon_jpg ) ? bin2hex( substr( file_get_contents( $asset_icon_jpg ), 0, 3 ) ) : '';

    if ( $info_png[0] === 320 && $info_png[1] === 320 && $info_jpg[0] === 320 && $info_jpg[1] === 320 && 'ffd8ff' === $jpg_header && 'ffd8ff' === $asset_jpg_header ) {
        $successes[] = "Icon check passed: Exactly 320x320 px (Valid PNG & valid JPEG magic bytes present, size: " . filesize( $icon_jpg ) . " bytes).";
    } else {
        $errors[] = "Icon check failed: PNG is {$info_png[0]}x{$info_png[1]}, JPG is {$info_jpg[0]}x{$info_jpg[1]}, header: {$jpg_header}, asset_header: {$asset_jpg_header}";
    }
} else {
    $errors[] = "Icon files missing in docs/";
}

// 3. Cover 2100x1040 check
$cover_jpg = $root . '/docs/marketplace-cover-2100x1040.jpg';
if ( file_exists( $cover_jpg ) ) {
    $info_cover = getimagesize( $cover_jpg );
    if ( $info_cover[0] === 2100 && $info_cover[1] === 1040 ) {
        $successes[] = "Cover check passed: Exactly 2100x1040 px (JPG present, size: " . filesize( $cover_jpg ) . " bytes).";
    } else {
        $errors[] = "Cover dimensions invalid: {$info_cover[0]}x{$info_cover[1]}";
    }
} else {
    $errors[] = "Cover file missing: $cover_jpg";
}

// 4. Infographic 960px check (< 1MB)
$info_jpg_path = $root . '/docs/infographic-960px.jpg';
if ( file_exists( $info_jpg_path ) ) {
    $info_img = getimagesize( $info_jpg_path );
    $size_bytes = filesize( $info_jpg_path );
    if ( $info_img[0] === 960 && $size_bytes < 1048576 ) {
        $successes[] = "Infographic check passed: Width 960px ({$info_img[0]}x{$info_img[1]}), size " . number_format( $size_bytes ) . " bytes (< 1MB limit).";
    } else {
        $errors[] = "Infographic failed check: Width {$info_img[0]}, size: {$size_bytes} bytes";
    }
} else {
    $errors[] = "Infographic file missing: $info_jpg_path";
}

// 5. CSS cleanliness check
$admin_css = file_get_contents( $root . '/woo-smart-quote/assets/css/admin.css' );
$bad_colors = ['#0f172a', '#64748b', '#2563eb'];
$css_clean = true;
foreach ( $bad_colors as $color ) {
    if ( stripos( $admin_css, $color ) !== false ) {
        $errors[] = "Admin CSS contains legacy color: {$color}";
        $css_clean = false;
    }
}
if ( $css_clean ) {
    $successes[] = "Admin CSS check passed: Zero legacy Tailwind slate/blue colors found.";
}

// 6. Deliverable zip packages check
$plugin_zip = $root . '/woo-smart-quote.zip';
$master_pkg = $root . '/package-nerkhnegar-1.0.0.zip';
$legacy_pkg = $root . '/package-mazankar-1.0.0.zip';

if ( file_exists( $legacy_pkg ) ) {
    $errors[] = "Outdated legacy package 'package-mazankar-1.0.0.zip' must be deleted!";
}

if ( file_exists( $plugin_zip ) && file_exists( $master_pkg ) ) {
    $zip = new ZipArchive();
    if ( $zip->open( $plugin_zip ) === true ) {
        $has_entry = $zip->locateName( 'woo-smart-quote/woo-smart-quote.php' ) !== false;
        $has_icon  = $zip->locateName( 'woo-smart-quote/assets/images/icon-320x320.png' ) !== false;
        $num_files = $zip->numFiles;
        $zip->close();
        if ( $has_entry && $has_icon ) {
            $successes[] = "Plugin archive check passed: {$plugin_zip} contains entrypoint and icon-320x320.png ({$num_files} files, " . filesize( $plugin_zip ) . " bytes).";
        } else {
            $errors[] = "Plugin zip missing entrypoint or icon";
        }
    } else {
        $errors[] = "Cannot open $plugin_zip";
    }

    $zip2 = new ZipArchive();
    if ( $zip2->open( $master_pkg ) === true ) {
        $has_clean_zip = $zip2->locateName( 'woo-smart-quote.zip' ) !== false;
        $has_manual    = $zip2->locateName( 'راهنمای نصب و راه‌اندازی.html' ) !== false;
        $has_pdf       = ( $zip2->locateName( 'راهنمای نصب و راه‌اندازی.pdf' ) !== false || $zip2->locateName( 'manual.pdf' ) !== false );
        $has_notes     = $zip2->locateName( 'مستندات قبل از نصب.txt' ) !== false;
        $zip2->close();
        if ( $has_clean_zip && $has_manual && $has_pdf && $has_notes ) {
            $successes[] = "Master package check passed: {$master_pkg} contains woo-smart-quote.zip, PDF manual, HTML guide, and pre-install notes (" . filesize( $master_pkg ) . " bytes).";
        } else {
            $errors[] = "Master package missing required delivery files (clean_zip: $has_clean_zip, manual_html: $has_manual, manual_pdf: $has_pdf, notes: $has_notes)";
        }
    } else {
        $errors[] = "Cannot open $master_pkg";
    }
} else {
    $errors[] = "Zip archives missing from root (expected $plugin_zip and $master_pkg)";
}

// 7. Demo preview and product description link check
$html_files = [
    'docs/demo-preview.html',
    'index.html',
    'docs/rastchin-product-page.html'
];
foreach ( $html_files as $hf ) {
    $content = file_get_contents( $root . '/' . $hf );
    preg_match_all( '/<img[^>]+src=[\'"]([^\'"]+)[\'"]/i', $content, $m );
    $missing_images = [];
    foreach ( $m[1] as $src ) {
        if ( strpos( $src, 'http' ) === 0 || strpos( $src, 'data:' ) === 0 ) {
            continue;
        }
        $dir = dirname( $root . '/' . $hf );
        $resolved = $dir . '/' . $src;
        if ( ! file_exists( $resolved ) ) {
            $missing_images[] = "{$src} in {$hf}";
        }
    }
    if ( empty( $missing_images ) ) {
        $successes[] = "Zero broken image links in {$hf}.";
    } else {
        foreach ( $missing_images as $mi ) {
            $errors[] = "Broken image link: {$mi}";
        }
    }
}

// Print results
echo "SUCCESSES (" . count( $successes ) . "):\n";
foreach ( $successes as $s ) {
    echo "  [✓] $s\n";
}

if ( ! empty( $errors ) ) {
    echo "\nERRORS (" . count( $errors ) . "):\n";
    foreach ( $errors as $e ) {
        echo "  [✗] $e\n";
    }
    exit( 1 );
}

echo "\nALL DELIVERABLE CHECKS PASSED PERFECTLY!\n";
exit( 0 );
