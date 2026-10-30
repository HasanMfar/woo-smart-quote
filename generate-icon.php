<?php
/**
 * Generates the official 320x320 Icon and Brand Emblem for Rastchin submission
 * Standards:
 * - Exact 320 x 320 px (1:1 ratio) full bleed
 * - Persian name «نرخنگار» prominently centered in heavy bold typography
 * - Safe margin >= 10% from all edges (>= 32px safe margin)
 * - Modern, clean B2B/fintech aesthetic with clean contrasting background
 * - Distinctive designer-crafted graphic mark (twin chevrons + tax verification node)
 * - Crystal clear and instantly legible at 80px thumbnail scale
 */

// Auto-load GD extension via local ini if running default CLI PHP
if ( ! extension_loaded( 'gd' ) ) {
    $ini = file_exists( __DIR__ . '/php-test.ini' ) ? __DIR__ . '/php-test.ini' : ( file_exists( __DIR__ . '/wp-cli.ini' ) ? __DIR__ . '/wp-cli.ini' : '' );
    if ( $ini ) {
        passthru( 'php -c ' . escapeshellarg( $ini ) . ' ' . escapeshellarg( __FILE__ ), $ret );
        exit( $ret );
    }
}

$root = __DIR__;
$font_path = $root . '/woo-smart-quote/lib/mpdf/fonts/Vazirmatn-Bold.ttf';
$font_b64  = base64_encode( file_get_contents( $font_path ) );

$html = <<<HTML
<!DOCTYPE html>
<html lang="fa">
<head>
<meta charset="UTF-8">
<style>
  @font-face {
    font-family: 'Vazirmatn';
    src: url('data:font/truetype;charset=utf-8;base64,{$font_b64}') format('truetype');
    font-weight: 700;
  }
  * { box-sizing: border-box; margin: 0; padding: 0; }
  html, body {
    width: 320px;
    height: 320px;
    margin: 0;
    padding: 0;
    overflow: hidden;
    background: #030b17;
    font-family: 'Vazirmatn', -apple-system, BlinkMacSystemFont, Tahoma, sans-serif;
  }
  .icon-canvas {
    width: 320px;
    height: 320px;
    position: absolute;
    top: 0;
    left: 0;
    overflow: hidden;
    background: radial-gradient(circle at 50% 50%, #0d274c 0%, #08172e 60%, #040c1a 100%);
    box-shadow: inset 0 0 0 1.5px rgba(255, 255, 255, 0.14);
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 34px 24px;
    box-sizing: border-box;
    text-align: center;
  }
  .brand-mark {
    display: block;
    margin: 0 auto;
    filter: drop-shadow(0 4px 14px rgba(14, 165, 233, 0.45));
  }
  .brand-title {
    font-family: 'Vazirmatn', -apple-system, BlinkMacSystemFont, Tahoma, sans-serif;
    font-size: 76px;
    font-weight: 700;
    line-height: 1.05;
    color: #ffffff;
    text-align: center;
    direction: rtl;
    margin-top: 10px;
    margin-bottom: 8px;
    letter-spacing: -1px;
    text-shadow: 0 4px 20px rgba(0, 0, 0, 0.85), 0 1px 3px rgba(0, 0, 0, 0.9);
  }
  .brand-sub {
    font-family: 'Vazirmatn', -apple-system, BlinkMacSystemFont, Tahoma, sans-serif;
    font-size: 13.5px;
    font-weight: 700;
    color: #7dd3fc;
    letter-spacing: 0.2px;
    text-shadow: 0 2px 8px rgba(0, 0, 0, 0.6);
    direction: rtl;
  }
</style>
</head>
<body>
<div class="icon-canvas">
  <!-- Designer Twin Quotation Mark with Verification Node -->
  <svg class="brand-mark" width="60" height="42" viewBox="0 0 76 54" fill="none" xmlns="http://www.w3.org/2000/svg">
    <defs>
      <linearGradient id="icnWhite" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#FFFFFF"/>
        <stop offset="100%" stop-color="#E2E8F0"/>
      </linearGradient>
      <linearGradient id="icnCyan" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#38BDF8"/>
        <stop offset="100%" stop-color="#0284C7"/>
      </linearGradient>
      <linearGradient id="icnSpark" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#34D399"/>
        <stop offset="100%" stop-color="#059669"/>
      </linearGradient>
    </defs>
    <!-- Chevron 1: White (Inquiry) -->
    <path d="M28 6 C29.5 4.5 32 5.5 32 7.6 L32 17.5 C32 18.6 31.4 19.6 30.4 20.3 L21 26.5 C19.8 27.3 19.8 28.7 21 29.5 L30.4 35.7 C31.4 36.4 32 37.4 32 38.5 L32 48.4 C32 50.5 29.5 51.5 28 50 L11 31 C9.5 29.5 9.5 26.5 11 25 Z" fill="url(#icnWhite)"/>
    <!-- Chevron 2: Cyan (Official Quote) -->
    <path d="M52 6 C53.5 4.5 56 5.5 56 7.6 L56 17.5 C56 18.6 55.4 19.6 54.4 20.3 L45 26.5 C43.8 27.3 43.8 28.7 45 29.5 L54.4 35.7 C55.4 36.4 56 37.4 56 38.5 L56 48.4 C56 50.5 53.5 51.5 52 50 L35 31 C33.5 29.5 33.5 26.5 35 25 Z" fill="url(#icnCyan)"/>
    <!-- Tax 169 Verification Node -->
    <circle cx="62" cy="28" r="4.5" fill="url(#icnSpark)"/>
    <circle cx="62" cy="28" r="2" fill="#FFFFFF"/>
  </svg>

  <!-- Large Centered Persian Product Name -->
  <div class="brand-title">نرخنگار</div>

  <!-- Subtitle Text -->
  <div class="brand-sub">پیش‌فاکتور رسمی • ووکامرس</div>
</div>
</body>
</html>
HTML;

$html_file = $root . '/docs/icon-320x320.html';
file_put_contents( $html_file, $html );
echo "Generated $html_file\n";

$png_file = $root . '/docs/icon-320x320.png';
$jpg_file = $root . '/docs/icon-320x320.jpg';

// Run headless Chrome to capture 320x320 screenshot
$chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe';
$user_data = sys_get_temp_dir() . '/chrome_wsq_render_' . time();
$cmd = "cmd.exe /c \"\"$chrome\" --headless=new --user-data-dir=\"$user_data\" --hide-scrollbars --window-size=320,320 --screenshot=\"$png_file\" \"file:///$html_file\"\"";
exec( $cmd, $output, $return_var );
echo "Chrome screenshot exit code: $return_var\n";

if ( file_exists( $png_file ) ) {
    $img = imagecreatefrompng( $png_file );
    if ( $img ) {
        $w = imagesx( $img );
        $h = imagesy( $img );
        if ( $w !== 320 || $h !== 320 ) {
            $cropped = imagecreatetruecolor( 320, 320 );
            imagecopy( $cropped, $img, 0, 0, 0, 0, 320, 320 );
            imagepng( $cropped, $png_file );
            imagedestroy( $img );
            $img = $cropped;
        }
        imagejpeg( $img, $jpg_file, 96 );
        echo "Saved JPG: $jpg_file (" . filesize( $jpg_file ) . " bytes)\n";

        // Generate 80x80 thumbnail for legibility verification
        $thumb80 = imagecreatetruecolor( 80, 80 );
        imagecopyresampled( $thumb80, $img, 0, 0, 0, 0, 80, 80, 320, 320 );
        $thumb_file = $root . '/docs/icon-80x80-preview.png';
        imagepng( $thumb80, $thumb_file );
        echo "Saved 80x80 preview: $thumb_file\n";
    }

    // Render standalone 140x140 Brand Emblem from docs/brand-emblem.html
    $emblem_html = $root . '/docs/brand-emblem.html';
    $emblem_file = $root . '/docs/brand-emblem.png';
    if ( file_exists( $emblem_html ) ) {
        $emblem_cmd = "cmd.exe /c \"\"$chrome\" --headless=new --user-data-dir=\"$user_data\" --hide-scrollbars --default-background-color=00000000 --window-size=140,140 --screenshot=\"$emblem_file\" \"file:///$emblem_html\"\"";
        exec( $emblem_cmd, $emblem_out, $emblem_ret );
        echo "Rendered standalone brand emblem: $emblem_file ($emblem_ret)\n";
    }

    // Copy to plugin assets
    @mkdir( $root . '/woo-smart-quote/assets/images', 0777, true );
    copy( $png_file, $root . '/woo-smart-quote/assets/images/icon-320x320.png' );
    copy( $jpg_file, $root . '/woo-smart-quote/assets/images/icon-320x320.jpg' );
    copy( $png_file, $root . '/woo-smart-quote/assets/images/icon-320.png' );
    if ( file_exists( $emblem_file ) ) {
        copy( $emblem_file, $root . '/woo-smart-quote/assets/images/brand-emblem.png' );
    }
    echo "Copied icon and emblem to woo-smart-quote/assets/images/\n";
}
