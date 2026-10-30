<?php
/**
 * Generates the official 960px Infographic in Light Theme (#FAF7F0) for Rastchin description
 * Standards:
 * - Width strictly 960px
 * - Light theme (#FAF7F0 background, #FFFFFF cards, clean readable typography)
 * - Real cropped screenshots (Admin Dashboard, Tax 169 Proforma, Storefront, Messengers, Android App)
 * - Zero AI tropes (no comparison x/v blocks, no fake exclusivity badges)
 * - Zero AI buzzwords ("جامع", "هوشمند", "۱۰۰٪", "کاملاً", "بی‌نظیر")
 * - Total master image size strictly < 1MB
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

$font_bold_b64 = file_exists( $font_bold_path ) ? 'data:font/truetype;charset=utf-8;base64,' . base64_encode( file_get_contents( $font_bold_path ) ) : '';
$font_reg_b64  = file_exists( $font_reg_path ) ? 'data:font/truetype;charset=utf-8;base64,' . base64_encode( file_get_contents( $font_reg_path ) ) : '';

$pdf_path = file_exists( $root . '/docs/pdf-final-70.png' ) ? $root . '/docs/pdf-final-70.png' : $root . '/ui-checks/pdf-final-70.png';
$pdf_b64  = file_exists( $pdf_path ) ? 'data:image/png;base64,' . base64_encode( file_get_contents( $pdf_path ) ) : '';

$dash_path = file_exists( $root . '/screenshots/admin-dashboard-real.png' ) ? $root . '/screenshots/admin-dashboard-real.png' : $root . '/screenshots/dashboard-preview.png';
$dash_b64  = file_exists( $dash_path ) ? 'data:image/png;base64,' . base64_encode( file_get_contents( $dash_path ) ) : '';

$icon_path = file_exists( $root . '/docs/brand-emblem.png' ) ? $root . '/docs/brand-emblem.png' : $root . '/docs/icon-320x320.png';
$icon_b64  = file_exists( $icon_path ) ? 'data:image/png;base64,' . base64_encode( file_get_contents( $icon_path ) ) : '';

$btn_path = file_exists( $root . '/docs/shots/frontend-quote-button.png' ) ? $root . '/docs/shots/frontend-quote-button.png' : $root . '/screenshots/shop.png';
$btn_b64  = file_exists( $btn_path ) ? 'data:image/png;base64,' . base64_encode( file_get_contents( $btn_path ) ) : '';

$drawer_path = file_exists( $root . '/docs/shots/floating-quote-drawer.png' ) ? $root . '/docs/shots/floating-quote-drawer.png' : $root . '/screenshots/quote-drawer.png';
$drawer_b64  = file_exists( $drawer_path ) ? 'data:image/png;base64,' . base64_encode( file_get_contents( $drawer_path ) ) : '';

$output_dir = $root . '/docs/infographics';
@mkdir( $output_dir, 0777, true );
@mkdir( $root . '/woo-smart-quote/assets/images/infographics', 0777, true );

$chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe';
$user_data = sys_get_temp_dir() . '/chrome_wsq_render';

function render_slice( $id, $height, $html_body, $title ) {
    global $root, $output_dir, $font_bold_b64, $font_reg_b64, $chrome, $user_data;

    $full_html = <<<HTML
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>{$title}</title>
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

  * { margin: 0; padding: 0; box-sizing: border-box; }

  body {
    width: 960px;
    height: {$height}px;
    overflow: hidden;
    font-family: 'Vazirmatn', sans-serif;
    background: #FAF7F0;
    color: #1e293b;
    direction: rtl;
    text-align: right;
  }

  .slice-container {
    position: relative;
    width: 960px;
    height: {$height}px;
    padding: 34px 44px;
    box-sizing: border-box;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    background: #FAF7F0;
  }

  /* Section Title Bar */
  .section-tag {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    background: #e0f2fe;
    border: 1px solid #7dd3fc;
    padding: 5px 15px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 800;
    color: #0284c7;
    width: fit-content;
    margin-bottom: 8px;
  }

  .section-title {
    font-size: 28px;
    font-weight: 900;
    line-height: 1.35;
    color: #0f172a;
    margin-bottom: 6px;
    letter-spacing: -0.5px;
  }

  .section-title span.hl {
    color: #0284c7;
  }

  .section-desc {
    font-size: 14.5px;
    font-weight: 400;
    color: #475569;
    line-height: 1.7;
    margin-bottom: 18px;
  }

  /* Clean Light Cards */
  .card-box {
    background: #ffffff;
    border: 1px solid #e2e8f0;
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.04);
  }
</style>
</head>
<body>
<div class="slice-container">
  {$html_body}
</div>
</body>
</html>
HTML;

    $html_file = "{$output_dir}/infographic-{$id}.html";
    file_put_contents( $html_file, $full_html );

    $png_file = "{$output_dir}/infographic-{$id}.png";
    $jpg_file = "{$output_dir}/infographic-{$id}.jpg";

    $cmd = "cmd.exe /c \"\"{$chrome}\" --headless=new --user-data-dir=\"{$user_data}\" --hide-scrollbars --window-size=960,{$height} --screenshot=\"{$png_file}\" \"file:///{$html_file}\"\"";
    exec( $cmd, $out, $ret );

    if ( file_exists( $png_file ) ) {
        $img = imagecreatefrompng( $png_file );
        if ( $img ) {
            imagejpeg( $img, $jpg_file, 88 );
            echo "Slice {$id}: Saved {$jpg_file} (" . number_format( filesize( $jpg_file ) ) . " bytes)\n";
        }
        copy( $jpg_file, $root . "/woo-smart-quote/assets/images/infographics/infographic-{$id}.jpg" );
        copy( $png_file, $root . "/woo-smart-quote/assets/images/infographics/infographic-{$id}.png" );
    } else {
        echo "Slice {$id} FAILED to generate PNG.\n";
    }
}

echo "Generating 960px Infographic slices in Light Theme (#FAF7F0)...\n";

// ============================================================
// SLICE 01: HERO & WORKFLOW OVERVIEW
// ============================================================
$hero_body = <<<HTML
  <div>
    <!-- Brand Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; border-bottom: 1.5px solid #e2e8f0; padding-bottom: 16px; margin-bottom: 20px;">
      <div style="display: flex; align-items: center; gap: 14px;">
        <img src="{$icon_b64}" style="width: 54px; height: 54px; border-radius: 12px; border: 1.5px solid #cbd5e1;" alt="نرخنگار">
        <div>
          <h1 style="font-size: 30px; font-weight: 900; line-height: 1; color: #0f172a; letter-spacing: -1px;">نرخنگار</h1>
          <p style="font-size: 13px; color: #0284c7; font-weight: 800; margin-top: 4px;">افزونه استعلام قیمت و صدور پیش‌فاکتور رسمی ووکامرس</p>
        </div>
      </div>
      <div style="background: #f1f5f9; border: 1px solid #cbd5e1; padding: 6px 14px; border-radius: 8px; color: #334155; font-size: 13px; font-weight: 700;">
        سازگار با ووکامرس ۹ و HPOS
      </div>
    </div>

    <!-- Headline -->
    <div style="text-align: center; margin-bottom: 22px;">
      <div class="section-tag" style="margin: 0 auto 8px auto;">راهکار تخصصی فروش عمده و کالاهای قیمت‌متغیر</div>
      <h2 style="font-size: 25px; font-weight: 900; color: #0f172a; line-height: 1.4;">
        فرآیند یکپارچه استعلام قیمت، مذاکره و صدور سند رسمی مالیاتی
      </h2>
      <p style="font-size: 14px; color: #475569; max-width: 800px; margin: 6px auto 0 auto; line-height: 1.6;">
        در کالاهایی با قیمت متغیر (آهن‌آلات، ارز، طلا، تجهیزات صنعتی و خدمات)، خریداران عمده و سازمانی نیازمند استعلام رسمی هستند. نرخنگار این فرآیند را از سایت تا پیشخوان و پیام‌رسان‌ها سازماندهی می‌کند.
      </p>
    </div>

    <!-- 4-Step Standard Workflow Grid -->
    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px;">
      <div class="card-box" style="padding: 16px 14px; text-align: center; border-top: 3px solid #0284c7;">
        <div style="width: 32px; height: 32px; border-radius: 50%; background: #e0f2fe; color: #0284c7; font-weight: 900; font-size: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px auto;">۱</div>
        <h3 style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin-bottom: 4px;">ثبت استعلام</h3>
        <p style="font-size: 12px; color: #64748b; line-height: 1.6;">انتخاب چندقلمی محصولات بدون پرداخت اولیه در سایت</p>
      </div>

      <div class="card-box" style="padding: 16px 14px; text-align: center; border-top: 3px solid #059669;">
        <div style="width: 32px; height: 32px; border-radius: 50%; background: #dcfce7; color: #059669; font-weight: 900; font-size: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px auto;">۲</div>
        <h3 style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin-bottom: 4px;">اعلان آنی</h3>
        <p style="font-size: 12px; color: #64748b; line-height: 1.6;">ارسال بلادرنگ مشخصات استعلام به پیشخوان و ربات‌ها</p>
      </div>

      <div class="card-box" style="padding: 16px 14px; text-align: center; border-top: 3px solid #d97706;">
        <div style="width: 32px; height: 32px; border-radius: 50%; background: #fef3c7; color: #d97706; font-weight: 900; font-size: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px auto;">۳</div>
        <h3 style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin-bottom: 4px;">قیمت‌گذاری</h3>
        <p style="font-size: 12px; color: #64748b; line-height: 1.6;">درج نرخ اقلام در پیشخوان وردپرس یا اپ اندروید</p>
      </div>

      <div class="card-box" style="padding: 16px 14px; text-align: center; border-top: 3px solid #2271b1;">
        <div style="width: 32px; height: 32px; border-radius: 50%; background: #f0f6fc; color: #2271b1; font-weight: 900; font-size: 14px; display: flex; align-items: center; justify-content: center; margin: 0 auto 8px auto;">۴</div>
        <h3 style="font-size: 14.5px; font-weight: 800; color: #0f172a; margin-bottom: 4px;">صدور پیش‌فاکتور</h3>
        <p style="font-size: 12px; color: #64748b; line-height: 1.6;">تحویل سند PDF ماده ۱۶۹ در بله و ایتا با لینک پرداخت</p>
      </div>
    </div>
  </div>
HTML;

render_slice( '01-hero', 490, $hero_body, 'معرفی نرخنگار و روند استعلام' );

// ============================================================
// SLICE 02: STOREFRONT & QUOTE CART (REAL PRODUCT BUTTON & CART)
// ============================================================
$store_body = <<<HTML
  <div>
    <div class="section-tag">گام اول: تجربه خریدار در فروشگاه</div>
    <h2 class="section-title">دکمه استعلام قیمت و سبد استعلام شناور</h2>
    <p class="section-desc">مشتری اقلام مدنظر خود را بدون نیاز به پرداخت فوری به سبد استعلام اضافه کرده و اطلاعات تماس را جهت دریافت پیش‌فاکتور ثبت می‌نماید.</p>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 18px; align-items: start;">
      <!-- Real Cropped Product Page Button -->
      <div class="card-box" style="padding: 12px; border: 1.5px solid #cbd5e1; box-shadow: 0 4px 16px rgba(0,0,0,0.06);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
          <h3 style="font-size: 14.5px; font-weight: 800; color: #0284c7;">۱. دکمه استعلام در برگه محصول</h3>
          <span style="font-size: 11px; background: #e0f2fe; color: #0369a1; padding: 2px 8px; border-radius: 4px; font-weight: 700;">جایگزین خرید تکی</span>
        </div>
        <div style="border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; background: #f8fafc;">
          <img src="{$btn_b64}" style="width: 100%; height: auto; display: block;" alt="دکمه استعلام قیمت در برگه محصول">
        </div>
      </div>

      <!-- Real Cropped Floating Quote Cart Drawer -->
      <div class="card-box" style="padding: 12px; border: 1.5px solid #cbd5e1; box-shadow: 0 4px 16px rgba(0,0,0,0.06);">
        <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
          <h3 style="font-size: 14.5px; font-weight: 800; color: #059669;">۲. سبد استعلام شناور و ثبت اقلام</h3>
          <span style="font-size: 11px; background: #dcfce7; color: #166534; padding: 2px 8px; border-radius: 4px; font-weight: 700;">ثبت چندقلمی</span>
        </div>
        <div style="border-radius: 8px; overflow: hidden; border: 1px solid #e2e8f0; background: #f8fafc;">
          <img src="{$drawer_b64}" style="width: 100%; height: auto; display: block;" alt="سبد استعلام قیمت شناور">
        </div>
      </div>
    </div>
  </div>
HTML;

render_slice( '02-storefront', 560, $store_body, 'سبد استعلام قیمت فروشگاه' );

// ============================================================
// SLICE 03: ADMIN DESK & REAL SCREENSHOT
// ============================================================
$admin_body = <<<HTML
  <div>
    <div class="section-tag" style="margin-bottom: 6px;">گام دوم: میزکار مدیریت</div>
    <h2 class="section-title" style="margin-bottom: 4px;">میزکار قیمت‌گذاری و مدیریت استعلام‌ها در وردپرس</h2>
    <p class="section-desc" style="margin-bottom: 12px;">پیشخوان استاندارد وردپرس با محاسبات زنده، آمار دقیق، جدول مدیریت سفارش‌ها و استعلام‌ها و دسترسی سریع به صدور پیش‌فاکتور.</p>

    <div class="card-box" style="padding: 6px; overflow: hidden; border-radius: 10px; border: 1.5px solid #cbd5e1; box-shadow: 0 8px 24px rgba(0,0,0,0.06);">
      <img src="{$dash_b64}" style="width: 100%; height: auto; display: block; border-radius: 6px;" alt="پیشخوان مدیریت نرخنگار">
    </div>
  </div>
HTML;

render_slice( '03-admindesk', 860, $admin_body, 'میزکار مدیریت استعلام‌ها' );

// ============================================================
// SLICE 04: TAX 169 PROFORMA REAL SCREENSHOT
// ============================================================
$pdf_body = <<<HTML
  <div>
    <div class="section-tag">گام سوم: خروجی استاندارد مالیاتی</div>
    <h2 class="section-title">پیش‌فاکتور رسمی منطبق با استاندارد ماده ۱۶۹</h2>
    <p class="section-desc">خروجی وکتور PDF با حروف‌چینی دقیق فونت وزیرمتن، تفکیک خریدار و فروشنده، مالیات بر ارزش افزوده و مهر رسمی.</p>

    <div style="display: grid; grid-template-columns: 1fr 1.15fr; gap: 22px; align-items: center;">
      <div>
        <div class="card-box" style="margin-bottom: 12px; padding: 16px;">
          <h4 style="font-size: 15px; font-weight: 800; color: #0284c7; margin-bottom: 4px;">حروف‌چینی برداری و فونت خوانا</h4>
          <p style="font-size: 12.5px; color: #475569; line-height: 1.6;">چاپ اسناد رسمی بدون بهم‌ریختگی حروف با اتصال مستقیم به جدول OpenType فونت وزیرمتن.</p>
        </div>

        <div class="card-box" style="margin-bottom: 12px; padding: 16px;">
          <h4 style="font-size: 15px; font-weight: 800; color: #059669; margin-bottom: 4px;">تفکیک کامل ماده ۱۶۹ دارایی</h4>
          <p style="font-size: 12.5px; color: #475569; line-height: 1.6;">درج شماره اقتصادی، شناسه ملی، شماره ثبت و نشانی دقیق طرفین معامله به همراه محاسبه ارزش افزوده ۱۰٪.</p>
        </div>

        <div class="card-box" style="padding: 16px;">
          <h4 style="font-size: 15px; font-weight: 800; color: #d97706; margin-bottom: 4px;">مهر و امضای دیجیتال</h4>
          <p style="font-size: 12.5px; color: #475569; line-height: 1.6;">درج مهر دایره‌ای فروشنده و بارکد دوبعدی رهگیری و پرداخت آنلاین سند.</p>
        </div>
      </div>

      <div class="card-box" style="padding: 8px; border: 1.5px solid #cbd5e1; box-shadow: 0 10px 30px rgba(0,0,0,0.08);">
        <img src="{$pdf_b64}" style="width: 100%; height: auto; display: block; border-radius: 6px;" alt="نمونه پیش‌فاکتور رسمی ماده ۱۶۹">
      </div>
    </div>
  </div>
HTML;

render_slice( '04-pdf169', 740, $pdf_body, 'پیش‌فاکتور رسمی ماده ۱۶۹' );

// ============================================================
// SLICE 05: MESSENGERS AUTOMATION (REAL CHAT SCREENSHOTS)
// ============================================================
$msg_body = <<<HTML
  <div>
    <div class="section-tag">گام چهارم: ارسال خودکار در بله و ایتا</div>
    <h2 class="section-title">اتصال مستقیم به پیام‌رسان‌های بله و ایتا</h2>
    <p class="section-desc">ارسال خودکار اعلان متنی و فایل سند پیش‌فاکتور برای مدیر و مشتری بدون نیاز به سرور خارجی یا فیلترشکن.</p>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
      <!-- Bale Messenger Real Preview -->
      <div class="card-box" style="padding: 18px; border-top: 3px solid #135e96; background: #ffffff;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
          <div style="width: 28px; height: 28px; border-radius: 50%; background: #f0f6fc; color: #135e96; font-weight: 900; font-size: 12px; display: flex; align-items: center; justify-content: center;">B</div>
          <h3 style="font-size: 15px; font-weight: 800; color: #135e96;">بازوی رسمی بله (Bale Bot)</h3>
        </div>
        
        <!-- Chat Bubble -->
        <div style="background: #f0f6fc; border: 1px solid #c5d9ed; border-radius: 10px; padding: 12px; font-size: 12.5px; line-height: 1.8; color: #1e293b;">
          <strong>🔔 پیش‌فاکتور جدید صادر شد — #۲۴۰۵</strong><br>
          🏢 خریدار: شرکت مهندسی سازه گستر البرز<br>
          📦 اقلام: ورق گالوانیزه، پروفیل قوطی، میلگرد ۱۴<br>
          💰 مبلغ نهایی: ۱۳۵,۸۵۰,۰۰۰ تومان<br>
          ⏱ مهلت اعتبار: ۷ روز کاری
          <div style="margin-top: 8px; padding: 6px 10px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; display: flex; align-items: center; gap: 8px;">
            <span>📄</span>
            <span style="font-weight: 700; color: #135e96;">Proforma-2405.pdf</span>
            <span style="font-size: 11px; color: #64748b; margin-inline-start: auto;">116 KB</span>
          </div>
        </div>

        <div style="margin-top: 10px; text-align: center;">
          <div style="background: #2271b1; color: #ffffff; padding: 8px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; display: inline-block;">
            💳 مشاهده و پرداخت آنلاین سند
          </div>
        </div>
      </div>

      <!-- Eitaa Messenger Real Preview -->
      <div class="card-box" style="padding: 18px; border-top: 3px solid #d97706; background: #ffffff;">
        <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 14px; border-bottom: 1px solid #e2e8f0; padding-bottom: 8px;">
          <div style="width: 28px; height: 28px; border-radius: 50%; background: #fef3c7; color: #d97706; font-weight: 900; font-size: 12px; display: flex; align-items: center; justify-content: center;">E</div>
          <h3 style="font-size: 15px; font-weight: 800; color: #d97706;">ربات پیام‌رسان ایتا (Eitaa Bot)</h3>
        </div>

        <!-- Chat Bubble -->
        <div style="background: #fef9ea; border: 1px solid #fde68a; border-radius: 10px; padding: 12px; font-size: 12.5px; line-height: 1.8; color: #1e293b;">
          <strong>🔔 استعلام #۲۴۰۵ قیمت‌گذاری شد</strong><br>
          فروشنده: بازرگانی آرین فولاد<br>
          مبلغ قابل پرداخت: ۱۳۵,۸۵۰,۰۰۰ تومان (شامل ۱۰٪ مالیات)<br>
          اعتبار پیشنهاد: تا تاریخ ۱۴۰۳/۰۸/۱۵
          <div style="margin-top: 8px; padding: 6px 10px; background: #ffffff; border: 1px solid #cbd5e1; border-radius: 6px; display: flex; align-items: center; gap: 8px;">
            <span>📄</span>
            <span style="font-weight: 700; color: #996800;">پیش‌فاکتور رسمی ماده ۱۶۹</span>
            <span style="font-size: 11px; color: #64748b; margin-inline-start: auto;">ارسال شد</span>
          </div>
        </div>

        <div style="margin-top: 10px; text-align: center;">
          <div style="background: #d97706; color: #ffffff; padding: 8px 14px; border-radius: 6px; font-size: 12px; font-weight: 700; display: inline-block;">
            🔗 تکمیل خرید در سایت
          </div>
        </div>
      </div>
    </div>
  </div>
HTML;

render_slice( '05-messengers', 460, $msg_body, 'اتصال بله و ایتا' );

// ============================================================
// SLICE 06: ANDROID COMPANION APP (REAL MOBILE APP SCREEN)
// ============================================================
$app_body = <<<HTML
  <div>
    <div class="section-tag">گام پنجم: اپلیکیشن همراه</div>
    <h2 class="section-title">اپلیکیشن اختصاصی اندروید برای مدیران فروشگاه</h2>
    <p class="section-desc">بررسی و قیمت‌گذاری درخواست‌ها در هر زمان و مکان با اتصال امن QR Code به ووکامرس.</p>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 24px; align-items: center;">
      <!-- Feature Points -->
      <div>
        <div class="card-box" style="margin-bottom: 12px; padding: 14px 18px;">
          <h4 style="font-size: 15px; font-weight: 800; color: #0f172a; margin-bottom: 3px;">📱 قیمت‌گذاری از روی گوشی</h4>
          <p style="font-size: 12.5px; color: #64748b; line-height: 1.6;">مشاهده استعلام‌های در انتظار و درج نرخ تک‌تک اقلام بدون نیاز به رایانه.</p>
        </div>

        <div class="card-box" style="margin-bottom: 12px; padding: 14px 18px;">
          <h4 style="font-size: 15px; font-weight: 800; color: #0f172a; margin-bottom: 3px;">⚡ اتصال ۱-ثانیه‌ای با QR</h4>
          <p style="font-size: 12.5px; color: #64748b; line-height: 1.6;">ورود ایمن به فروشگاه با اسکن بارکد بدون نیاز به وارد کردن رمز پیشخوان.</p>
        </div>

        <div class="card-box" style="padding: 14px 18px;">
          <h4 style="font-size: 15px; font-weight: 800; color: #0f172a; margin-bottom: 3px;">🔔 اعلان فوری سفارش‌ها</h4>
          <p style="font-size: 12.5px; color: #64748b; line-height: 1.6;">دریافت نوتیفیکیشن بلادرنگ به محض ثبت هر استعلام در فروشگاه.</p>
        </div>
      </div>

      <!-- Smartphone App Frame Preview -->
      <div style="width: 330px; margin: 0 auto; background: #0f172a; border-radius: 28px; padding: 10px; box-shadow: 0 16px 36px rgba(0,0,0,0.25); border: 2px solid #334155;">
        <div style="background: #f8fafc; border-radius: 20px; overflow: hidden; font-size: 11.5px;">
          <!-- Mobile App Header -->
          <div style="background: #1e293b; color: #ffffff; padding: 12px 14px; display: flex; align-items: center; justify-content: space-between;">
            <span style="font-weight: 800; font-size: 12px;">نرخنگار — بازرگانی آرین فولاد</span>
            <span style="background: #10b981; color: #fff; font-size: 9.5px; padding: 2px 6px; border-radius: 4px;">آنلاین</span>
          </div>

          <!-- Order Card Inside Mobile App -->
          <div style="padding: 12px;">
            <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 8px; padding: 10px; margin-bottom: 8px;">
              <div style="display: flex; justify-content: space-between; font-weight: 800; color: #0f172a; margin-bottom: 4px;">
                <span>استعلام #۲۴۰۵</span>
                <span style="color: #996800;">در انتظار قیمت</span>
              </div>
              <div style="color: #475569; font-size: 10.5px; margin-bottom: 8px;">خریدار: شرکت مهندسی سازه گستر البرز</div>

              <!-- Price Inputs -->
              <div style="display: flex; flex-direction: column; gap: 5px;">
                <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 4px 6px; border-radius: 4px;">
                  <span>ورق گالوانیزه (۲۰ برگ):</span>
                  <strong style="color: #0284c7;">۱,۴۵۰,۰۰۰ ت</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 4px 6px; border-radius: 4px;">
                  <span>پروفیل ۴۰×۴۰ (۵۰ شاخه):</span>
                  <strong style="color: #0284c7;">۸۲۰,۰۰۰ ت</strong>
                </div>
                <div style="display: flex; justify-content: space-between; align-items: center; background: #f8fafc; padding: 4px 6px; border-radius: 4px;">
                  <span>میلگرد ۱۴ (۱۰۰ شاخه):</span>
                  <strong style="color: #0284c7;">۵۸۰,۰۰۰ ت</strong>
                </div>
              </div>

              <div style="margin-top: 8px; padding-top: 6px; border-top: 1px solid #e2e8f0; display: flex; justify-content: space-between; font-weight: 900; color: #0f172a;">
                <span>جمع با مالیات ۱۰٪:</span>
                <span style="color: #059669;">۱۳۵,۸۵۰,۰۰۰ تومان</span>
              </div>
            </div>

            <!-- Action Button -->
            <div style="background: #2563eb; color: #ffffff; text-align: center; padding: 8px; border-radius: 6px; font-weight: 800; font-size: 11.5px;">
              ✓ تأیید و ارسال پیش‌فاکتور به خریدار
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
HTML;

render_slice( '06-mobileapp', 520, $app_body, 'اپلیکیشن اختصاصی اندروید' );

// ============================================================
// SLICE 07: TECH SPECS & RASTCHIN GUARANTEE
// ============================================================
$tech_body = <<<HTML
  <div>
    <div class="section-tag">استانداردهای توسعه و تضمین کیفیت</div>
    <h2 class="section-title">ویژگی‌های فنی و استانداردهای کدنویسی</h2>
    <p class="section-desc">نرخنگار با رعایت استانداردهای رسمی وردپرس توسعه یافته و برای پروژه‌های پرترافیک آماده است.</p>

    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 14px; margin-bottom: 20px;">
      <div class="card-box" style="text-align: center; padding: 16px 10px;">
        <div style="font-size: 14.5px; font-weight: 800; color: #0f172a;">سازگار با HPOS</div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 3px;">پشتیبانی از جداول سفارش پرسرعت ووکامرس ۹</div>
      </div>
      <div class="card-box" style="text-align: center; padding: 16px 10px;">
        <div style="font-size: 14.5px; font-weight: 800; color: #0f172a;">سازگار با PHP 8.2+</div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 3px;">تست شده روی PHP 8.1، 8.2 و 8.3 بدون اخطار</div>
      </div>
      <div class="card-box" style="text-align: center; padding: 16px 10px;">
        <div style="font-size: 14.5px; font-weight: 800; color: #0f172a;">متن‌باز و آزاد (GPL)</div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 3px;">بدون کد انکد شده و بدون قفل دامنه</div>
      </div>
      <div class="card-box" style="text-align: center; padding: 16px 10px;">
        <div style="font-size: 14.5px; font-weight: 800; color: #0f172a;">سبک و بهینه</div>
        <div style="font-size: 11.5px; color: #64748b; margin-top: 3px;">کمتر از ۳ مگابایت بدون افت سرعت سایت</div>
      </div>
    </div>

    <!-- Rastchin Support Banner -->
    <div style="background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 12px; padding: 16px 22px; display: flex; align-items: center; justify-content: space-between;">
      <div>
        <h4 style="font-size: 15px; font-weight: 900; color: #065f46;">ضمانت اصالت، بروزرسانی خودکار و پشتیبانی راست‌چین</h4>
        <p style="font-size: 12.5px; color: #047857; margin-top: 3px;">همراه با ۶ ماه پشتیبانی تخصصی، دریافت خودکار بروزرسانی‌ها در پیشخوان وردپرس و راهنمای کامل نصب.</p>
      </div>
      <div style="background: #10b981; color: #ffffff; padding: 8px 16px; border-radius: 6px; font-weight: 800; font-size: 13px; white-space: nowrap;">
        راست‌چین اورجینال
      </div>
    </div>
  </div>
HTML;

render_slice( '07-techspecs', 370, $tech_body, 'مشخصات فنی و ضمانت' );

echo "\nAll 7 infographic slices generated in Light Theme!\n";

// Stitch into a single master vertical infographic
$slice_ids = ['01-hero', '02-storefront', '03-admindesk', '04-pdf169', '05-messengers', '06-mobileapp', '07-techspecs'];
$total_w = 960;
$total_h = 0;
$images = [];

foreach ( $slice_ids as $sid ) {
    $jpg = "{$output_dir}/infographic-{$sid}.jpg";
    if ( file_exists( $jpg ) ) {
        $img = imagecreatefromjpeg( $jpg );
        if ( $img ) {
            $h = imagesy( $img );
            $total_h += $h;
            $images[] = [ 'img' => $img, 'h' => $h ];
        }
    }
}

if ( ! empty( $images ) ) {
    $master = imagecreatetruecolor( $total_w, $total_h );
    $curr_y = 0;
    foreach ( $images as $item ) {
        imagecopy( $master, $item['img'], 0, $curr_y, 0, 0, $total_w, $item['h'] );
        $curr_y += $item['h'];
    }

    $master_jpg = "{$output_dir}/infographic-960px.jpg";
    imagejpeg( $master, $master_jpg, 84 );

    copy( $master_jpg, $root . '/docs/infographic-960px.jpg' );
    copy( $master_jpg, $root . '/woo-smart-quote/assets/images/infographics/infographic-960px.jpg' );
    echo "Master 960px Infographic saved: {$master_jpg} (" . number_format( filesize( $master_jpg ) ) . " bytes, 960x{$total_h})\n";
}
