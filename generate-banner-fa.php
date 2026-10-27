<?php
/**
 * Masterpiece Persian Rastchin Marketplace Banner (2100x1040)
 * Ultra-crisp typography, SVG vectors, glassmorphism, perfectly balanced composition.
 */

$root = __DIR__;
$bg_image_path = $root . '/docs/marketplace-banner-rastchin.jpg';
$bg_base64 = '';
if ( file_exists( $bg_image_path ) ) {
    $bg_base64 = 'data:image/jpeg;base64,' . base64_encode( file_get_contents( $bg_image_path ) );
}

$font_bold_path = $root . '/woo-smart-quote/lib/mpdf/fonts/Vazirmatn-Bold.ttf';
$font_reg_path  = $root . '/woo-smart-quote/lib/mpdf/fonts/Vazirmatn-Regular.ttf';

$font_bold_b64 = file_exists( $font_bold_path ) ? 'data:font/truetype;charset=utf-8;base64,' . base64_encode( file_get_contents( $font_bold_path ) ) : '';
$font_reg_b64  = file_exists( $font_reg_path ) ? 'data:font/truetype;charset=utf-8;base64,' . base64_encode( file_get_contents( $font_reg_path ) ) : '';

$html = <<<HTML
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<style>
  @font-face {
    font-family: 'Vazirmatn';
    src: url('$font_bold_b64') format('truetype');
    font-weight: 700 900;
  }
  @font-face {
    font-family: 'Vazirmatn';
    src: url('$font_reg_b64') format('truetype');
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
    font-family: 'Vazirmatn', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background-color: #030c1b;
  }

  .banner-canvas {
    position: relative;
    width: 2100px;
    height: 1040px;
    background-image: url('$bg_base64');
    background-size: cover;
    background-position: center;
    overflow: hidden;
  }

  /* Seamless Left Gradient Overlay: text side is high-contrast, 3D artwork side is 100% untouched */
  .backdrop-shadow {
    position: absolute;
    top: 0;
    bottom: 0;
    left: 0;
    width: 1180px;
    background: linear-gradient(
      90deg,
      rgba(3, 12, 27, 0.98) 0%,
      rgba(3, 12, 27, 0.94) 62%,
      rgba(3, 12, 27, 0.60) 84%,
      rgba(3, 12, 27, 0.0) 100%
    );
    z-index: 1;
    pointer-events: none;
  }

  /* Soft glowing blue aura */
  .aura-glow {
    position: absolute;
    top: 100px;
    left: 80px;
    width: 600px;
    height: 500px;
    background: radial-gradient(circle, rgba(14, 165, 233, 0.24) 0%, rgba(3, 12, 27, 0) 70%);
    filter: blur(60px);
    z-index: 2;
    pointer-events: none;
  }

  /* Master Left Content Column: Fixed width, right-aligned Persian text, breathing space from 3D invoice */
  .hero-column {
    position: absolute;
    left: 110px;
    top: 50%;
    transform: translateY(-50%);
    width: 870px;
    z-index: 10;
    direction: rtl;
    text-align: right;
    display: flex;
    flex-direction: column;
    gap: 22px;
  }

  /* Top Category Pill */
  .category-pill {
    display: inline-flex;
    align-items: center;
    gap: 12px;
    background: rgba(14, 165, 233, 0.12);
    border: 1px solid rgba(56, 189, 248, 0.35);
    backdrop-filter: blur(16px);
    padding: 8px 24px;
    border-radius: 50px;
    width: fit-content;
    box-shadow: 0 4px 20px rgba(14, 165, 233, 0.18);
  }

  .pill-pulse {
    width: 10px;
    height: 10px;
    background: #38bdf8;
    border-radius: 50%;
    box-shadow: 0 0 14px #38bdf8;
  }

  .pill-label {
    color: #bae6fd;
    font-size: 19px;
    font-weight: 700;
  }

  /* Brand Title & Mobile Badge */
  .brand-row {
    display: flex;
    align-items: center;
    gap: 24px;
    margin-top: 2px;
  }

  .brand-name {
    font-size: 114px;
    font-weight: 900;
    line-height: 1;
    background: linear-gradient(135deg, #ffffff 40%, #bae6fd 75%, #38bdf8 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    filter: drop-shadow(0 10px 32px rgba(14, 165, 233, 0.45));
    letter-spacing: -1.5px;
  }

  .badge-app-pill {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    font-size: 21px;
    font-weight: 800;
    color: #fef08a;
    background: linear-gradient(135deg, rgba(234, 179, 8, 0.22), rgba(202, 138, 4, 0.1));
    border: 1.5px solid rgba(250, 204, 21, 0.55);
    padding: 10px 22px;
    border-radius: 14px;
    box-shadow: 0 4px 20px rgba(234, 179, 8, 0.22);
  }

  .badge-app-pill svg {
    width: 24px;
    height: 24px;
  }

  /* Main Headline & Subtitle */
  .headline {
    font-size: 32px;
    font-weight: 800;
    line-height: 1.45;
    color: #ffffff;
    text-shadow: 0 2px 14px rgba(0, 0, 0, 0.85);
  }

  .headline-cyan {
    color: #38bdf8;
  }

  .lead-text {
    font-size: 19px;
    font-weight: 400;
    line-height: 1.6;
    color: #94a3b8;
    margin-top: -6px;
    width: 870px;
  }

  /* 4 Premium Glass Cards */
  .cards-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
    margin-top: 6px;
  }

  .glass-card {
    display: flex;
    align-items: center;
    gap: 16px;
    background: linear-gradient(135deg, rgba(13, 27, 54, 0.82) 0%, rgba(20, 36, 68, 0.55) 100%);
    border: 1px solid rgba(56, 189, 248, 0.26);
    backdrop-filter: blur(20px);
    border-radius: 18px;
    padding: 16px 20px;
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.4);
  }

  .card-icon-box {
    width: 54px;
    height: 54px;
    border-radius: 14px;
    background: linear-gradient(135deg, rgba(14, 165, 233, 0.32), rgba(56, 189, 248, 0.1));
    border: 1px solid rgba(56, 189, 248, 0.5);
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 0 18px rgba(14, 165, 233, 0.3);
  }

  .card-icon-box svg {
    width: 26px;
    height: 26px;
  }

  .card-body {
    display: flex;
    flex-direction: column;
    gap: 4px;
  }

  .card-title {
    font-size: 20px;
    font-weight: 800;
    color: #ffffff;
  }

  .card-desc {
    font-size: 14px;
    font-weight: 400;
    color: #94a3b8;
    line-height: 1.45;
  }

  /* Bottom Trust Strip */
  .trust-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-top: 10px;
    padding-top: 18px;
    border-top: 1px solid rgba(148, 163, 184, 0.16);
    width: 870px;
  }

  .trust-badge {
    display: flex;
    align-items: center;
    gap: 8px;
    color: #cbd5e1;
    font-size: 16px;
    font-weight: 700;
  }

  .trust-badge svg {
    width: 19px;
    height: 19px;
    fill: #10b981;
  }

  /* Floating Polish Badge over Right Side */
  .phone-pill-badge {
    position: absolute;
    top: 110px;
    right: 320px;
    z-index: 10;
    display: inline-flex;
    align-items: center;
    gap: 10px;
    background: rgba(15, 23, 42, 0.85);
    border: 1.5px solid rgba(56, 189, 248, 0.5);
    backdrop-filter: blur(16px);
    padding: 10px 24px;
    border-radius: 50px;
    box-shadow: 0 8px 30px rgba(0, 0, 0, 0.5), 0 0 20px rgba(14, 165, 233, 0.35);
    color: #ffffff;
    font-size: 18px;
    font-weight: 800;
    direction: rtl;
  }

  .phone-pill-badge .dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: #10b981;
    box-shadow: 0 0 12px #10b981;
  }
</style>
</head>
<body>

<div class="banner-canvas">
  <div class="backdrop-shadow"></div>
  <div class="aura-glow"></div>

  <!-- Floating Polish Badge over Right Side -->
  <div class="phone-pill-badge">
    <span class="dot"></span>
    <span>میز کار هوشمند قیمت‌گذاری و صدور پیش‌فاکتور</span>
  </div>

  <div class="hero-column">
    <!-- Category Pill -->
    <div class="category-pill">
      <span class="pill-pulse"></span>
      <span class="pill-label">افزونه پیشرفته استعلام قیمت و پیش‌فاکتور رسمی ووکامرس</span>
    </div>

    <!-- Main Title -->
    <div class="brand-row">
      <h1 class="brand-name">مَظَنّه چند؟</h1>
      <div class="badge-app-pill">
        <svg viewBox="0 0 24 24" fill="none" stroke="#facc15" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
          <rect x="5" y="2" width="14" height="20" rx="3" ry="3"></rect>
          <line x1="12" y1="18" x2="12.01" y2="18"></line>
        </svg>
        <span>همراه با اپلیکیشن اندروید</span>
      </div>
    </div>

    <!-- Headline -->
    <div class="headline">
      میزکار حرفه‌ای قیمت‌گذاری و <span class="headline-cyan">صدور پیش‌فاکتور رسمی ماده ۱۶۹</span>
    </div>

    <!-- Lead Text -->
    <div class="lead-text">
      پاسخگویی سریع به استعلام مشتریان عمده و خریداران سازمانی<br>
      ارسال پیش‌فاکتور در بله و ایتا • اتصال امن موبایل با اسکن کد QR
    </div>

    <!-- 4 Key Feature Cards with SVGs -->
    <div class="cards-grid">
      <!-- Card 1: Android App -->
      <div class="glass-card">
        <div class="card-icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="5" y="2" width="14" height="20" rx="3" ry="3"></rect>
            <path d="M12 18h.01"></path>
            <path d="M9 7h6"></path>
          </svg>
        </div>
        <div class="card-body">
          <span class="card-title">اپلیکیشن اختصاصی اندروید</span>
          <span class="card-desc">میز کار سیار فروشنده با استعلام لحظه‌ای و اتصال ۱ کلیکه با اسکن QR</span>
        </div>
      </div>

      <!-- Card 2: Official Invoice -->
      <div class="glass-card">
        <div class="card-icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
            <polyline points="14 2 14 8 20 8"></polyline>
            <line x1="16" y1="13" x2="8" y2="13"></line>
            <line x1="16" y1="17" x2="8" y2="17"></line>
            <line x1="10" y1="9" x2="8" y2="9"></line>
          </svg>
        </div>
        <div class="card-body">
          <span class="card-title">پیش‌فاکتور رسمی ماده ۱۶۹</span>
          <span class="card-desc">فونت وزیرمتن، شناسه ملی، کد اقتصادی، مالیات بر ارزش افزوده و مهر دیجیتال</span>
        </div>
      </div>

      <!-- Card 3: Bale & Eitaa Bot -->
      <div class="glass-card">
        <div class="card-icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
          </svg>
        </div>
        <div class="card-body">
          <span class="card-title">ربات هوشمند بله و ایتا</span>
          <span class="card-desc">ارسال خودکار پیش‌فاکتور به خریدار و ارسال اعلان فوری برای مدیر فروشگاه</span>
        </div>
      </div>

      <!-- Card 4: HPOS & Speed -->
      <div class="glass-card">
        <div class="card-icon-box">
          <svg viewBox="0 0 24 24" fill="none" stroke="#38bdf8" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"></polygon>
          </svg>
        </div>
        <div class="card-body">
          <span class="card-title">سازگاری ۱۰۰٪ با HPOS</span>
          <span class="card-desc">کدنویسی استاندارد و سبک (۲.۷ مگابایت) بدون هیچ‌گونه کندی در سرعت سایت</span>
        </div>
      </div>
    </div>

    <!-- Trust Row -->
    <div class="trust-row">
      <div class="trust-badge">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        <span>۶ ماه پشتیبانی رایگان</span>
      </div>
      <div class="trust-badge">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        <span>آپدیت دائمی و خودکار</span>
      </div>
      <div class="trust-badge">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        <span>ضمانت بازگشت وجه راست‌چین</span>
      </div>
      <div class="trust-badge">
        <svg viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z"/></svg>
        <span>سازگار با PHP 8.1 / 8.2 / 8.3</span>
      </div>
    </div>
  </div>
</div>

</body>
</html>
HTML;

file_put_contents( $root . '/docs/banner-rastchin-persian.html', $html );
echo "Generated docs/banner-rastchin-persian.html successfully (" . strlen( $html ) . " bytes).\n";
