<?php
/**
 * Generates the official 2100x1040 Marketplace Cover for Rastchin
 * Standards:
 * - Exact 2100 x 1040 px
 * - Authentic real product screenshots on left (Admin Dashboard + Tax 169 Proforma)
 * - Clean RTL layout on right
 * - Brand name: «نرخنگار»
 * - Single punchy subtitle: <= 5 words («استعلام قیمت و پیش‌فاکتور رسمی»)
 * - 4 short single-line features (<= 3 words each) in an amber high-contrast box:
 *     1. پیش‌فاکتور رسمی ۱۶۹
 *     2. اتصال بله، ایتا
 *     3. اپلیکیشن اختصاصی اندروید
 *     4. سازگاری کامل HPOS
 * - High contrast, legible at 350px thumbnail scale
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
$font_bold_path = $root . '/woo-smart-quote/lib/mpdf/fonts/Vazirmatn-Bold.ttf';
$font_reg_path  = $root . '/woo-smart-quote/lib/mpdf/fonts/Vazirmatn-Regular.ttf';

$font_bold_b64 = 'data:font/truetype;charset=utf-8;base64,' . base64_encode( file_get_contents( $font_bold_path ) );
$font_reg_b64  = 'data:font/truetype;charset=utf-8;base64,' . base64_encode( file_get_contents( $font_reg_path ) );

// Screenshots
$dash_img_path = $root . '/screenshots/admin-dashboard-real.png';
$dash_b64 = 'data:image/png;base64,' . base64_encode( file_get_contents( $dash_img_path ) );

$pdf_img_path = $root . '/docs/pdf-final-70.png';
$pdf_b64 = 'data:image/png;base64,' . base64_encode( file_get_contents( $pdf_img_path ) );

$emblem_img_path = $root . '/docs/brand-emblem.png';
$emblem_b64 = 'data:image/png;base64,' . base64_encode( file_get_contents( $emblem_img_path ) );

$html = <<<HTML
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<style>
  @font-face {
    font-family: 'Vazirmatn';
    src: url('{$font_bold_b64}') format('truetype');
    font-weight: 700 900;
  }
  @font-face {
    font-family: 'Vazirmatn';
    src: url('{$font_reg_b64}') format('truetype');
    font-weight: 400 500;
  }

  * {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
  }

  body {
    width: 2100px;
    height: 1040px;
    overflow: hidden;
    font-family: 'Vazirmatn', -apple-system, BlinkMacSystemFont, Tahoma, sans-serif;
    background: #07152b;
    color: #ffffff;
  }

  .canvas {
    position: relative;
    width: 2100px;
    height: 1040px;
    background: linear-gradient(135deg, #050f1d 0%, #0a2140 50%, #061324 100%);
    overflow: hidden;
    display: flex;
    direction: ltr; /* Left is screenshots, Right is typography */
  }

  /* Right column (Brand Typography & Features) */
  .right-col {
    position: relative;
    z-index: 10;
    width: 1060px;
    height: 1040px;
    padding: 70px 80px 70px 50px;
    display: flex;
    flex-direction: column;
    justify-content: center;
    gap: 22px;
    direction: rtl;
    text-align: right;
  }

  .tag-pill {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(56, 189, 248, 0.12);
    border: 1.5px solid rgba(56, 189, 248, 0.45);
    padding: 8px 22px;
    border-radius: 100px;
    width: fit-content;
  }
  .tag-pill .pill-text {
    font-size: 19px;
    font-weight: 800;
    color: #38bdf8;
    letter-spacing: -0.2px;
  }

  .brand-row {
    display: flex;
    align-items: center;
    gap: 22px;
  }
  .brand-logo-img {
    width: 100px;
    height: 100px;
    border-radius: 24px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 16px 36px rgba(0, 0, 0, 0.5);
  }
  .brand-name {
    font-size: 88px;
    font-weight: 900;
    color: #ffffff;
    line-height: 1;
    letter-spacing: -2px;
    text-shadow: 0 6px 24px rgba(0, 0, 0, 0.7);
  }

  /* Single punchy subtitle: <= 5 words */
  .punchy-subtitle {
    font-size: 32px;
    font-weight: 800;
    color: #e2e8f0;
    line-height: 1.3;
    letter-spacing: -0.5px;
    text-shadow: 0 2px 10px rgba(0,0,0,0.5);
  }

  /* High-Contrast Feature Box: Solid white card clearly differing from the dark canvas, crystal readable at 350px thumbnail */
  .amber-box {
    background: #ffffff;
    border: 3.5px solid #f59e0b;
    border-radius: 24px;
    padding: 28px 36px;
    box-shadow: 0 24px 60px rgba(0, 0, 0, 0.6), inset 0 1px 2px rgba(255, 255, 255, 0.8);
    display: flex;
    flex-direction: column;
    gap: 20px;
  }

  .amber-box-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding-bottom: 12px;
    border-bottom: 2px solid #fef3c7;
  }
  .amber-box-title {
    font-size: 26px;
    font-weight: 900;
    color: #b45309;
    display: flex;
    align-items: center;
    gap: 12px;
    letter-spacing: -0.3px;
  }

  /* Single clean column of 4 features with large font (>= 50px bold) */
  .features-column {
    display: flex;
    flex-direction: column;
    gap: 16px;
  }
  .feature-item {
    display: flex;
    align-items: center;
    gap: 16px;
  }
  .feature-check {
    width: 48px;
    height: 48px;
    border-radius: 12px;
    background: #f59e0b;
    color: #ffffff;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    font-weight: 900;
    flex-shrink: 0;
    box-shadow: 0 4px 10px rgba(245, 158, 11, 0.35);
  }
  .feature-text {
    font-size: 52px;
    font-weight: 900;
    color: #0f172a;
    white-space: nowrap;
    line-height: 1.15;
    letter-spacing: -0.5px;
  }

  /* Left column: Real Screenshots Showcase */
  .left-col {
    position: relative;
    width: 1040px;
    height: 1040px;
    overflow: hidden;
  }

  /* Dashboard screenshot in background (angled) */
  .dash-frame {
    position: absolute;
    top: 75px;
    left: 40px;
    width: 820px;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 30px 70px rgba(0, 0, 0, 0.7), 0 0 0 1.5px rgba(255, 255, 255, 0.15);
    transform: rotate(-3deg);
    transition: transform 0.3s;
  }
  .dash-frame img {
    width: 100%;
    display: block;
  }

  /* PDF Proforma in foreground */
  .pdf-frame {
    position: absolute;
    top: 130px;
    left: 450px;
    width: 530px;
    border-radius: 14px;
    overflow: hidden;
    box-shadow: 0 40px 90px rgba(0, 0, 0, 0.85), 0 0 0 2px rgba(56, 189, 248, 0.4);
    transform: rotate(2deg);
    background: #ffffff;
  }
  .pdf-frame img {
    width: 100%;
    display: block;
  }

</style>
</head>
<body>

<div class="canvas">

  <!-- Left: Real Product Screenshots -->
  <div class="left-col">
    <!-- Admin Dashboard (Real Mock Data: Aryan Steel) -->
    <div class="dash-frame">
      <img src="{$dash_b64}" alt="پیشخوان مدیریت استعلام">
    </div>

    <!-- Official Tax 169 Proforma (Real PDF: Galvanized Sheet, Profiles, Rebar) -->
    <div class="pdf-frame">
      <img src="{$pdf_b64}" alt="پیش‌فاکتور رسمی ماده ۱۶۹">
    </div>
  </div>

  <!-- Right: Brand Typography & High-Contrast Features -->
  <div class="right-col">
    <div class="tag-pill">
      <span class="pill-text">افزونه اختصاصی استعلام قیمت ووکامرس</span>
    </div>

    <div class="brand-row">
      <img src="{$emblem_b64}" class="brand-logo-img" alt="نرخنگار">
      <h1 class="brand-name">نرخنگار</h1>
    </div>

    <!-- Single punchy subtitle: <= 5 words -->
    <div class="punchy-subtitle">استعلام قیمت و پیش‌فاکتور رسمی</div>

    <!-- Amber High-Contrast Box: 4 key features in a single clean column (>= 50px bold) -->
    <div class="amber-box">
      <div class="amber-box-header">
        <div class="amber-box-title">
          <span>ویژگی‌های کلیدی نرخنگار</span>
        </div>
      </div>

      <div class="features-column">
        <div class="feature-item">
          <div class="feature-check">✓</div>
          <span class="feature-text">پیش‌فاکتور رسمی ۱۶۹</span>
        </div>
        <div class="feature-item">
          <div class="feature-check">✓</div>
          <span class="feature-text">اتصال بله و ایتا</span>
        </div>
        <div class="feature-item">
          <div class="feature-check">✓</div>
          <span class="feature-text">اپلیکیشن اختصاصی اندروید</span>
        </div>
        <div class="feature-item">
          <div class="feature-check">✓</div>
          <span class="feature-text">سازگاری کامل HPOS</span>
        </div>
      </div>
    </div>
  </div>

</div>

</body>
</html>
HTML;

$html_file = $root . '/docs/marketplace-cover-2100x1040.html';
file_put_contents( $html_file, $html );
echo "Generated $html_file\n";

$png_file = $root . '/docs/marketplace-cover-2100x1040.png';
$jpg_file = $root . '/docs/marketplace-cover-2100x1040.jpg';

$chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe';
$user_data = sys_get_temp_dir() . '/chrome_wsq_render';
$cmd = "cmd.exe /c \"\"$chrome\" --headless=new --user-data-dir=\"$user_data\" --hide-scrollbars --window-size=2100,1040 --screenshot=\"$png_file\" \"file:///$html_file\"\"";
exec( $cmd, $output, $return_var );
echo "Chrome screenshot exit code: $return_var\n";

if ( file_exists( $png_file ) ) {
    $img = imagecreatefrompng( $png_file );
    if ( $img ) {
        imagejpeg( $img, $jpg_file, 92 );
        echo "Saved JPG: $jpg_file (" . filesize( $jpg_file ) . " bytes)\n";

        // Generate 350px preview for Rastchin catalog thumbnail legibility check
        $thumb_w = 350;
        $thumb_h = (int) round( 1040 * ( 350 / 2100 ) ); // 173 px
        $thumb = imagecreatetruecolor( $thumb_w, $thumb_h );
        imagecopyresampled( $thumb, $img, 0, 0, 0, 0, $thumb_w, $thumb_h, 2100, 1040 );
        $preview_jpg = $root . '/docs/marketplace-cover-350px-preview.jpg';
        imagejpeg( $thumb, $preview_jpg, 88 );
        echo "Saved 350px preview: $preview_jpg (" . filesize( $preview_jpg ) . " bytes)\n";
    }

    // Copy to marketplace-banner files
    copy( $jpg_file, $root . '/docs/marketplace-banner-rastchin-fa.jpg' );
    copy( $png_file, $root . '/docs/marketplace-banner-rastchin-fa.png' );
    @mkdir( $root . '/woo-smart-quote/assets/images', 0777, true );
    copy( $jpg_file, $root . '/woo-smart-quote/assets/images/marketplace-banner-rastchin-fa.jpg' );
    copy( $png_file, $root . '/woo-smart-quote/assets/images/marketplace-banner-rastchin-fa.png' );
    echo "Copied cover banner to woo-smart-quote/assets/images/\n";
}
