<?php
/**
 * Generates Real Tax 169 PDF and Real Admin Dashboard Screenshot
 * Mock Data:
 * - Seller: بازرگانی آرین فولاد (Arian Steel Trading)
 * - Items: ورق گالوانیزه، پروفیل قوطی، میلگرد ۱۴ آجدار
 * - Buyer: شرکت مهندسی سازه گستر البرز
 */

$root = __DIR__;
$font_bold_path = $root . '/woo-smart-quote/lib/mpdf/fonts/Vazirmatn-Bold.ttf';
$font_reg_path  = $root . '/woo-smart-quote/lib/mpdf/fonts/Vazirmatn-Regular.ttf';

$font_bold_b64 = 'data:font/truetype;charset=utf-8;base64,' . base64_encode( file_get_contents( $font_bold_path ) );
$font_reg_b64  = 'data:font/truetype;charset=utf-8;base64,' . base64_encode( file_get_contents( $font_reg_path ) );

// --- 1. Generate Proforma PDF HTML ---
$qr_svg_proforma = '<svg width="48" height="48" viewBox="0 0 29 29" xmlns="http://www.w3.org/2000/svg" shape-rendering="crispEdges">
<rect width="29" height="29" fill="#ffffff" />
<path fill="#0f172a" d="M0,0 h7 v7 h-7 z M1,1 v5 h5 v-5 z M2,2 h3 v3 h-3 z M0,22 h7 v7 h-7 z M1,23 v5 h5 v-5 z M2,24 h3 v3 h-3 z M22,0 h7 v7 h-7 z M23,1 v5 h5 v-5 z M24,2 h3 v3 h-3 z M8,2 h1 v3 h-1 z M11,1 h3 v1 h-3 z M16,2 h2 v2 h-2 z M19,1 h2 v2 h-2 z M9,8 h2 v2 h-2 z M13,7 h1 v3 h-1 z M16,8 h3 v1 h-3 z M20,8 h2 v2 h-2 z M2,9 h4 v1 h-4 z M8,12 h3 v1 h-3 z M13,11 h3 v2 h-3 z M18,12 h2 v2 h-2 z M22,10 h1 v4 h-1 z M25,12 h3 v2 h-3 z M0,15 h3 v1 h-3 z M5,15 h2 v2 h-2 z M9,15 h1 v3 h-1 z M12,15 h4 v1 h-4 z M18,15 h2 v2 h-2 z M22,16 h2 v2 h-2 z M26,15 h2 v2 h-2 z M2,18 h2 v3 h-2 z M6,18 h3 v1 h-3 z M11,18 h2 v2 h-2 z M15,18 h3 v2 h-3 z M20,19 h2 v2 h-2 z M24,18 h4 v2 h-4 z M9,22 h2 v2 h-2 z M13,22 h3 v1 h-3 z M18,22 h2 v2 h-2 z M22,23 h4 v2 h-4 z M27,22 h2 v4 h-2 z M9,26 h4 v2 h-4 z M15,25 h2 v3 h-2 z M19,26 h2 v2 h-2 z M23,26 h3 v2 h-3 z" />
</svg>';

$seal_svg_proforma = '<svg width="124" height="124" viewBox="0 0 140 140" xmlns="http://www.w3.org/2000/svg">
  <g transform="rotate(-3 70 70)">
    <!-- Outer Double Rings -->
    <circle cx="70" cy="70" r="66" stroke="#b91c1c" stroke-width="2.5" fill="#fffcfc" fill-opacity="0.97" />
    <circle cx="70" cy="70" r="61" stroke="#b91c1c" stroke-width="0.9" stroke-dasharray="2.5,2" fill="none" />
    <circle cx="70" cy="70" r="58" stroke="#b91c1c" stroke-width="0.8" fill="none" />

    <!-- Corner/Edge Vector Diamond Ornaments (No font glyph dependency) -->
    <polygon points="70,14 72,17 70,20 68,17" fill="#b91c1c" />
    <polygon points="126,70 123,72 120,70 123,68" fill="#b91c1c" />
    <polygon points="14,70 17,72 20,70 17,68" fill="#b91c1c" />

    <!-- Company Name Arc/Top -->
    <text x="70" y="32" font-size="8.8" font-weight="bold" fill="#b91c1c" text-anchor="middle" font-family="Vazirmatn">بازرگانی آرین فولاد</text>

    <!-- Subtitle / Registration No -->
    <text x="70" y="42" font-size="6.8" fill="#991b1b" text-anchor="middle" font-family="Vazirmatn">شماره ثبت: ۴۲۸۱۹۰</text>

    <!-- Center Cartouche Badge -->
    <rect x="16" y="47" width="108" height="21" rx="4" fill="#fef2f2" stroke="#b91c1c" stroke-width="1.2" />
    <text x="70" y="61" font-size="8.4" font-weight="bold" fill="#b91c1c" text-anchor="middle" font-family="Vazirmatn">تأیید شد — پیش‌فاکتور رسمی</text>

    <!-- Legal Identifiers -->
    <text x="70" y="79" font-size="7" font-weight="bold" fill="#b91c1c" text-anchor="middle" font-family="Vazirmatn">شناسه ملی: ۱۰۱۰۳۸۵۹۲۰۱</text>
    <text x="70" y="89" font-size="6.5" fill="#991b1b" text-anchor="middle" font-family="Vazirmatn">واحد امور مالی و بازرگانی</text>
    <text x="70" y="99" font-size="6.8" font-weight="bold" fill="#b91c1c" text-anchor="middle" font-family="Vazirmatn">تاریخ صدور: ۱۴۰۳/۰۸/۰۸</text>

    <!-- Bottom Decorative Accent -->
    <polygon points="70,126 72,123 70,120 68,123" fill="#b91c1c" />
  </g>
</svg>';

$signature_svg_proforma = '<svg width="125" height="48" viewBox="0 0 140 54" xmlns="http://www.w3.org/2000/svg" style="display:block; margin-top:-4px;">
  <path d="M 8 34 C 24 14, 38 46, 56 16 C 66 2, 78 42, 94 20 C 104 6, 118 32, 134 14" stroke="#1d4ed8" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity="0.94" />
  <path d="M 22 40 Q 62 24, 112 32 M 42 44 C 72 36, 98 38, 128 30" stroke="#1e40af" stroke-width="1.6" fill="none" stroke-linecap="round" stroke-linejoin="round" opacity="0.88" />
  <path d="M 14 46 C 50 42, 88 44, 136 36" stroke="#1d4ed8" stroke-width="1.2" fill="none" stroke-linecap="round" opacity="0.75" />
</svg>';

$proforma_html = <<<HTML
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>پیش‌فاکتور رسمی ماده ۱۶۹ — بازرگانی آرین فولاد</title>
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

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    width: 820px;
    margin: 0 auto;
    padding: 24px 28px;
    font-family: 'Vazirmatn', Tahoma, sans-serif;
    color: #1e293b;
    background: #ffffff;
    font-size: 10px;
    line-height: 1.45;
    direction: rtl;
    text-align: right;
  }

  table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
    margin-bottom: 6px;
  }
  td, th {
    vertical-align: middle;
  }

  /* Header Box */
  .header-table {
    border: 1.5px solid #0f172a;
    background: #f8fafc;
    margin-bottom: 7px;
  }
  .header-table td {
    padding: 8px 10px;
  }
  .seller-brand h1 {
    font-size: 15px;
    font-weight: 900;
    color: #0f172a;
    margin: 0 0 2px 0;
  }
  .seller-brand p {
    font-size: 8.5px;
    color: #475569;
    margin: 0;
  }
  .doc-title-box {
    text-align: center;
  }
  .doc-title-box h2 {
    font-size: 14px;
    font-weight: 900;
    color: #0f172a;
    margin: 0 0 3px 0;
    letter-spacing: -0.3px;
  }
  .doc-title-box span {
    font-size: 8px;
    color: #475569;
  }
  .doc-meta-table {
    width: 100%;
    font-size: 8.5px;
  }
  .doc-meta-table td {
    padding: 1.5px 2px;
  }

  /* Parties Tables */
  .parties-table {
    border: 1px solid #0f172a;
    margin-bottom: 6px;
    font-size: 9px;
  }
  .parties-table th {
    background: #0f172a;
    color: #ffffff;
    padding: 4px 8px;
    font-weight: bold;
    text-align: right;
    font-size: 9.5px;
    border: 1px solid #0f172a;
  }
  .parties-table td {
    padding: 4px 6px;
    border: 1px solid #cbd5e1;
    font-size: 8.5px;
  }
  .label-cell {
    background: #f1f5f9;
    font-weight: bold;
    color: #334155;
    width: 15%;
  }
  .val-cell {
    width: 35%;
  }

  /* Items Table */
  .items-table {
    border: 1.5px solid #0f172a;
    margin-bottom: 6px;
    font-size: 8.5px;
  }
  .items-table th {
    background: #0f172a;
    color: #ffffff;
    padding: 5px 3px;
    font-weight: bold;
    text-align: center;
    border: 1px solid #334155;
    font-size: 8.5px;
  }
  .items-table td {
    padding: 4.5px 4px;
    border: 1px solid #cbd5e1;
    text-align: center;
    font-size: 8.5px;
  }
  .items-table tr:nth-child(even) td {
    background: #f8fafc;
  }
  .text-right { text-align: right !important; }
  .text-left { text-align: left !important; direction: ltr; }

  /* Summary & Bank Section */
  .bottom-grid {
    margin-bottom: 6px;
  }
  .bank-box-table {
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    margin-bottom: 4px;
    font-size: 8px;
  }
  .bank-box-table th {
    background: #f1f5f9;
    color: #0f172a;
    padding: 4px 6px;
    font-weight: bold;
    text-align: right;
    border-bottom: 1px solid #cbd5e1;
    font-size: 8.5px;
  }
  .bank-box-table td {
    padding: 3px 6px;
  }
  .terms-table {
    border: 1px solid #cbd5e1;
    background: #ffffff;
    font-size: 8px;
    color: #334155;
    margin-bottom: 0;
  }
  .terms-table th {
    background: #f8fafc;
    color: #0f172a;
    padding: 3px 6px;
    font-weight: bold;
    text-align: right;
    border-bottom: 1px solid #cbd5e1;
    font-size: 8.5px;
  }
  .terms-table td {
    padding: 4px 6px;
    line-height: 1.55;
  }

  .totals-table {
    border: 1px solid #cbd5e1;
    font-size: 8.5px;
    margin-bottom: 0;
  }
  .totals-table td {
    padding: 3.5px 6px;
    border: 1px solid #cbd5e1;
  }
  .totals-table td.label {
    background: #f1f5f9;
    font-weight: bold;
    color: #334155;
    width: 48%;
  }
  .totals-table td.val {
    text-align: left;
    direction: ltr;
    font-weight: bold;
    width: 52%;
  }
  .totals-table tr.grand-row td {
    background: #0f172a;
    color: #ffffff;
    font-weight: bold;
    font-size: 10px;
  }
  .totals-table tr.grand-row td.val {
    color: #38bdf8;
    font-size: 10.5px;
  }
  .words-row td {
    background: #f8fafc;
    font-size: 8px;
    padding: 4px 6px;
    text-align: right;
    color: #1e3a8a;
  }

  /* Signatures Table */
  .sign-table {
    margin-top: 6px;
    margin-bottom: 6px;
  }
  .sign-table td.sign-cell {
    border: 1px dashed #94a3b8;
    padding: 6px 10px;
    height: 110px;
    vertical-align: top;
    background: #fafafa;
  }
  .sign-title {
    font-weight: bold;
    font-size: 9px;
    color: #0f172a;
    border-bottom: 1px solid #e2e8f0;
    padding-bottom: 3px;
    margin-bottom: 4px;
  }
  .sign-sub {
    font-size: 7.5px;
    color: #64748b;
  }

  /* Footer */
  .doc-footer {
    border-top: 1px solid #cbd5e1;
    padding-top: 4px;
    text-align: center;
    font-size: 7.5px;
    color: #64748b;
  }
</style>
</head>
<body>

<!-- Header -->
<table class="header-table">
  <tr>
    <td style="width: 33%;">
      <div class="seller-brand">
        <h1>بازرگانی آرین فولاد</h1>
        <p>سهامی خاص — شماره ثبت: ۴۲۸۱۹۰</p>
        <p>توزیع سراسری انواع مقاطع فولادی، ورق و میلگرد</p>
      </div>
    </td>
    <td class="doc-title-box" style="width: 37%;">
      <h2>پیش‌فاکتور رسمی فروش کالا و خدمات</h2>
      <span>منطبق با مقررات ماده ۱۶۹ و ۱۶۹ مکرر قانون مالیات‌های مستقیم</span>
    </td>
    <td style="width: 30%;">
      <table style="width: 100%; border-collapse: collapse; margin: 0;">
        <tr>
          <td style="width: 65%; vertical-align: middle; padding: 0;">
            <table class="doc-meta-table" style="margin: 0;">
              <tr>
                <td style="font-weight:bold; color:#475569;">شماره سند:</td>
                <td style="font-weight:bold; direction:ltr; text-align:left;">۱۴۰۳/۰۸/۲۴۰۵</td>
              </tr>
              <tr>
                <td style="font-weight:bold; color:#475569;">تاریخ صدور:</td>
                <td style="font-weight:bold; direction:ltr; text-align:left;">۱۴۰۳/۰۸/۰۸</td>
              </tr>
              <tr>
                <td style="font-weight:bold; color:#475569;">مهلت اعتبار:</td>
                <td style="font-weight:bold; color:#0369a1; direction:ltr; text-align:left;">۱۴۰۳/۰۸/۱۵</td>
              </tr>
            </table>
          </td>
          <td style="width: 35%; text-align: left; vertical-align: middle; padding: 0;">
            {$qr_svg_proforma}
            <div style="font-size:6.5px; color:#64748b; text-align:center;">کد اصالت سند</div>
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<!-- Seller Info -->
<table class="parties-table">
  <tr>
    <th colspan="4">مشخصات فروشنده</th>
  </tr>
  <tr>
    <td class="label-cell">نام شخص حقیقی/حقوقی:</td>
    <td class="val-cell"><strong>بازرگانی آرین فولاد (سهامی خاص)</strong></td>
    <td class="label-cell">شناسه ملی:</td>
    <td class="val-cell"><strong>۱۰۱۰۳۸۵۹۲۰۱</strong></td>
  </tr>
  <tr>
    <td class="label-cell">شماره اقتصادی:</td>
    <td class="val-cell"><strong>۴۱۱۵۸۹۳۲۷۱۴۵</strong></td>
    <td class="label-cell">شماره ثبت:</td>
    <td class="val-cell"><strong>۴۲۸۱۹۰</strong></td>
  </tr>
  <tr>
    <td class="label-cell">کد پستی ۱۰ رقمی:</td>
    <td class="val-cell"><strong>۱۳۷۸۹۴۵۶۱۲</strong></td>
    <td class="label-cell">تلفن و نمابر:</td>
    <td class="val-cell" dir="ltr" style="text-align:right;">۰۲۱-۶۶۳۱۴۵۸۰</td>
  </tr>
  <tr>
    <td class="label-cell">نشانی ثبتی اقامتگاه:</td>
    <td class="val-cell" colspan="3">تهران، بازار آهن شادآباد، بهاران ۲، بلوک ۱۲، پلاک ۴ — انبار مرکزی آهن‌آلات غرب تهران</td>
  </tr>
</table>

<!-- Buyer Info -->
<table class="parties-table">
  <tr>
    <th colspan="4">مشخصات خریدار (متقاضی)</th>
  </tr>
  <tr>
    <td class="label-cell">نام شخص حقیقی/حقوقی:</td>
    <td class="val-cell"><strong>شرکت مهندسی سازه گستر البرز</strong></td>
    <td class="label-cell">شناسه ملی / کد ملی:</td>
    <td class="val-cell"><strong>۱۰۳۸۰۴۵۱۲۳۴</strong></td>
  </tr>
  <tr>
    <td class="label-cell">شماره اقتصادی:</td>
    <td class="val-cell"><strong>۴۱۱۳۵۶۸۹۲۱۴۷</strong></td>
    <td class="label-cell">شماره ثبت / پرونده:</td>
    <td class="val-cell"><strong>۳۲۹۴۰</strong></td>
  </tr>
  <tr>
    <td class="label-cell">کد پستی ۱۰ رقمی:</td>
    <td class="val-cell"><strong>۳۱۶۵۸۹۷۴۱۲</strong></td>
    <td class="label-cell">تلفن همراه / تماس:</td>
    <td class="val-cell" dir="ltr" style="text-align:right;">۰۹۱۲۳۴۵۶۷۸۹</td>
  </tr>
  <tr>
    <td class="label-cell">نشانی کارگاه / پروژه:</td>
    <td class="val-cell" colspan="3">کرج، شهرک صنعتی سیمین دشت، خیابان امید، پلاک ۲۸ — پروژه ساخت سوله صنعتی فاز ۳</td>
  </tr>
</table>

<!-- Items Table -->
<table class="items-table">
  <thead>
    <tr>
      <th style="width: 4%;">ردیف</th>
      <th style="width: 9%;">کد کالا</th>
      <th style="width: 29%;">شرح کالا یا خدمات درخواستی</th>
      <th style="width: 6%;">مقدار</th>
      <th style="width: 6%;">واحد</th>
      <th style="width: 11%;">مبلغ واحد (تومان)</th>
      <th style="width: 11%;">مبلغ کل (تومان)</th>
      <th style="width: 7%;">تخفیف</th>
      <th style="width: 7%;">مالیات ۱۰٪</th>
      <th style="width: 10%;">مبلغ نهایی (تومان)</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td>۱</td>
      <td style="color:#64748b;">ST-GALV-20</td>
      <td class="text-right"><strong>ورق گالوانیزه ۲ میل</strong> (ابعاد ۱۲۵۰×۲۵۰۰ کارخانه تاراز)</td>
      <td><strong>۲۰</strong></td>
      <td>برگ</td>
      <td class="text-left">۱,۴۵۰,۰۰۰</td>
      <td class="text-left">۲۹,۰۰۰,۰۰۰</td>
      <td class="text-left" style="color:#dc2626;">۱,۰۰۰,۰۰۰</td>
      <td class="text-left">۲,۸۰۰,۰۰۰</td>
      <td class="text-left"><strong>۳۰,۸۰۰,۰۰۰</strong></td>
    </tr>
    <tr>
      <td>۲</td>
      <td style="color:#64748b;">ST-PROF-40</td>
      <td class="text-right"><strong>پروفیل قوطی ۴۰×۴۰</strong> (ضخامت ۲ میل - شاخه ۶ متری استاندارد)</td>
      <td><strong>۵۰</strong></td>
      <td>شاخه</td>
      <td class="text-left">۸۲۰,۰۰۰</td>
      <td class="text-left">۴۱,۰۰۰,۰۰۰</td>
      <td class="text-left" style="color:#dc2626;">۱,۵۰۰,۰۰۰</td>
      <td class="text-left">۳,۹۵۰,۰۰۰</td>
      <td class="text-left"><strong>۴۳,۴۵۰,۰۰۰</strong></td>
    </tr>
    <tr>
      <td>۳</td>
      <td style="color:#64748b;">ST-REBAR-14</td>
      <td class="text-right"><strong>میلگرد ۱۴ آجدار A3</strong> (طول ۱۲ متری ذوب‌آهن اصفهان)</td>
      <td><strong>۱۰۰</strong></td>
      <td>شاخه</td>
      <td class="text-left">۵۸۰,۰۰۰</td>
      <td class="text-left">۵۸,۰۰۰,۰۰۰</td>
      <td class="text-left" style="color:#dc2626;">۲,۰۰۰,۰۰۰</td>
      <td class="text-left">۵,۶۰۰,۰۰۰</td>
      <td class="text-left"><strong>۶۱,۶۰۰,۰۰۰</strong></td>
    </tr>
  </tbody>
</table>

<!-- Bottom Grid: Bank & Notes on Right (52%), Totals on Left (48%) -->
<table class="bottom-grid">
  <tr>
    <!-- Right Cell: Bank Info & Terms -->
    <td style="width: 52%; vertical-align: top; padding-left: 8px;">
      <table class="bank-box-table">
        <tr>
          <th colspan="2">مشخصات حساب بانکی فروشنده جهت واریز وجه:</th>
        </tr>
        <tr>
          <td style="width: 25%; font-weight: bold; color: #475569;">بانک و شعبه:</td>
          <td style="width: 75%;">بانک سامان — شعبه بازار آهن تهران</td>
        </tr>
        <tr>
          <td style="font-weight: bold; color: #475569;">شماره شبا (IBAN):</td>
          <td dir="ltr" style="text-align: right; font-weight: bold; color: #0369a1;">IR45 0560 0842 8103 2596 0000 01</td>
        </tr>
        <tr>
          <td style="font-weight: bold; color: #475569;">شماره حساب / کارت:</td>
          <td dir="ltr" style="text-align: right;">6219-8610-3344-5566 (حساب: ۸۴۲-۸۱۰-۳۲۵۹۶۰-۱)</td>
        </tr>
        <tr>
          <td style="font-weight: bold; color: #475569;">به نام:</td>
          <td><strong>شرکت بازرگانی آرین فولاد</strong></td>
        </tr>
      </table>

      <table class="terms-table">
        <tr>
          <th>شرایط و ضوابط پیش‌فاکتور:</th>
        </tr>
        <tr>
          <td>
            • مهلت اعتبار: معتبر تا تاریخ ۱۴۰۳/۰۸/۱۵ به مدت ۷ روز کاری از زمان صدور.<br>
            • شرایط تحویل: تسویه کامل پیش از بارگیری الزامی بوده؛ کرایه حمل بر عهده خریدار است.<br>
            • توضیحات خریدار: هماهنگی ورود تریلی با سرپرست کارگاه سیمین دشت.
          </td>
        </tr>
      </table>
    </td>

    <!-- Left Cell: Financial Totals -->
    <td style="width: 48%; vertical-align: top;">
      <table class="totals-table">
        <tr>
          <td class="label">جمع کل اقلام (ناخالص):</td>
          <td class="val">۱۲۸,۰۰۰,۰۰۰ تومان</td>
        </tr>
        <tr>
          <td class="label">تخفیف ویژه تجاری:</td>
          <td class="val" style="color: #dc2626;">- ۴,۵۰۰,۰۰۰ تومان</td>
        </tr>
        <tr>
          <td class="label">جمع پس از کسر تخفیف:</td>
          <td class="val">۱۲۳,۵۰۰,۰۰۰ تومان</td>
        </tr>
        <tr>
          <td class="label">مالیات بر ارزش افزوده (۱۰٪):</td>
          <td class="val">+ ۱۲,۳۵۰,۰۰۰ تومان</td>
        </tr>
        <tr class="grand-row">
          <td>مبلغ نهایی قابل پرداخت:</td>
          <td class="val">۱۳۵,۸۵۰,۰۰۰ تومان</td>
        </tr>
        <tr class="words-row">
          <td colspan="2">
            <strong>مبلغ به حروف:</strong> یکصد و سی و پنج میلیون و هشتصد و پنجاه هزار تومان تمام
          </td>
        </tr>
      </table>
    </td>
  </tr>
</table>

<!-- Signatures Table -->
<table class="sign-table">
  <tr>
    <!-- Seller Box (Right) -->
    <td class="sign-cell" style="width: 50%;">
      <div class="sign-title">مهر و امضای مجاز فروشنده:</div>
      <div class="sign-sub">تأییدیه مشخصات، نرخ و شرایط پیش‌فاکتور رسمی</div>
      <table style="width: 100%; border: none; margin: 0;">
        <tr>
          <td style="border: none; padding: 0; width: 48%; vertical-align: top;">
            <div style="font-size: 8px; color: #334155; line-height: 1.5;">
              مدیر امور مالی و فروش:<br>
              <strong style="color: #0f172a;">بازرگانی آرین فولاد</strong>
            </div>
            {$signature_svg_proforma}
          </td>
          <td style="border: none; padding: 0; width: 52%; text-align: left; vertical-align: middle;">
            {$seal_svg_proforma}
          </td>
        </tr>
      </table>
    </td>

    <!-- Spacer -->
    <td style="width: 2%; border: none; background: transparent;">&nbsp;</td>

    <!-- Buyer Box (Left) -->
    <td class="sign-cell" style="width: 48%;">
      <div class="sign-title">تأیید و امضای خریدار (متقاضی):</div>
      <div class="sign-sub">مشخصات اقلام، بهای پیشنهادی و شرایط فوق مورد تأیید می‌باشد.</div>
      <div style="margin-top: 36px; font-size: 8px; color: #334155; line-height: 1.8;">
        نام و سمت نماینده تام‌الاختیار: ....................................................<br>
        تاریخ و امضا: .................... / .................... / ....................
      </div>
    </td>
  </tr>
</table>

<!-- Footer -->
<div class="doc-footer" style="display: flex; justify-content: space-between; align-items: center;">
  <span>این سند رسمی الکترونیکی مطابق با دستورالعمل اجرایی ماده ۱۶۹ قانون مالیات‌های مستقیم توسط افزونه «نرخنگار» صادر گردیده است.</span>
  <span style="direction: rtl; font-weight: bold;">صفحه ۱ از ۱</span>
</div>

</body>
</html>
HTML;

$proforma_file = $root . '/docs/pdf-proforma-real.html';
file_put_contents( $proforma_file, $proforma_html );
echo "Wrote $proforma_file\n";

$chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe';
$user_data = sys_get_temp_dir() . '/chrome_wsq_render';

// Render PDF Screenshot (docs/pdf-final-70.png)
$pdf_png = $root . '/docs/pdf-final-70.png';
$cmd_png = "cmd.exe /c \"\"$chrome\" --headless=new --user-data-dir=\"$user_data\" --hide-scrollbars --window-size=820,1080 --screenshot=\"$pdf_png\" \"file:///$proforma_file\"\"";
exec( $cmd_png, $out, $ret );
echo "Rendered $pdf_png (code $ret, size: " . filesize( $pdf_png ) . " bytes)\n";

// Copy to docs/shots/proforma-pdf.png
@mkdir( $root . '/docs/shots', 0777, true );
copy( $pdf_png, $root . '/docs/shots/proforma-pdf.png' );
echo "Copied to docs/shots/proforma-pdf.png\n";

// Also render quote-preview.pdf directly using Chrome
$pdf_doc = $root . '/quote-preview.pdf';
$cmd_pdf = "cmd.exe /c \"\"$chrome\" --headless=new --user-data-dir=\"$user_data\" --print-to-pdf=\"$pdf_doc\" \"file:///$proforma_file\"\"";
exec( $cmd_pdf, $out2, $ret2 );
echo "Printed $pdf_doc (code $ret2, size: " . filesize( $pdf_doc ) . " bytes)\n";

// Copy to docs/quote-preview.pdf
copy( $pdf_doc, $root . '/docs/quote-preview.pdf' );
echo "Copied to docs/quote-preview.pdf\n";

// --- 2. Generate Real WordPress Admin Dashboard Screenshot with Same Mock Data ---
$admin_css_content = file_get_contents( $root . '/woo-smart-quote/assets/css/admin.css' );

$emblem_path = $root . '/docs/brand-emblem.png';
$emblem_b64  = file_exists( $emblem_path ) ? 'data:image/png;base64,' . base64_encode( file_get_contents( $emblem_path ) ) : '';

$dash_html = <<<HTML
<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
<meta charset="UTF-8">
<title>پیشخوان مدیریت نرخنگار — استعلام‌های ووکامرس</title>
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

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    width: 1200px;
    margin: 0;
    padding: 24px;
    background: #f0f0f1;
    font-family: 'Vazirmatn', -apple-system, BlinkMacSystemFont, Tahoma, sans-serif;
    color: #1d2327;
    font-size: 13px;
    line-height: 1.6;
    direction: rtl;
  }

  {$admin_css_content}

  /* WordPress Core Native Admin Table & Button Baseline */
  .wp-list-table {
    width: 100%;
    border-collapse: collapse;
    border: 1px solid var(--wsq-border);
    background: #ffffff;
    border-radius: 4px;
  }
  .wp-list-table th {
    background: #f6f7f7;
    padding: 10px 14px;
    font-size: 12.5px;
    font-weight: 700;
    color: var(--wsq-ink);
    border-bottom: 1px solid var(--wsq-border);
    text-align: right;
  }
  .wp-list-table td {
    padding: 12px 14px;
    border-bottom: 1px solid #f0f0f1;
    font-size: 12.5px;
    vertical-align: middle;
  }
  .wp-list-table.striped tbody tr:nth-child(odd) {
    background: #fbfbfb;
  }
  .wp-list-table tbody tr:hover {
    background: #f6f7f7;
  }
  .button {
    display: inline-block;
    padding: 4px 14px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    border: 1px solid var(--wsq-border);
    background: #f6f7f7;
    color: #2c3338;
  }
  .button.wsq-pending-action {
    border-color: var(--wsq-primary);
    background: #f6f7f7;
    color: var(--wsq-primary);
    font-weight: 700;
  }
  .button.wsq-pending-action:hover {
    background: var(--wsq-primary);
    color: #ffffff;
  }
</style>
</head>
<body class="wsq-admin-wrap" style="max-width: 1200px; margin: 0 auto;">

<div class="wsq-admin-header">
  <div class="wsq-brand-box">
    <div class="wsq-brand-id">
      <span class="wsq-brand-badge">
        <img src="{$emblem_b64}" width="36" height="36" alt="نرخنگار" style="display:block; border-radius:4px;">
      </span>
      <div>
        <h1 class="wsq-brand-title">نرخنگار</h1>
        <span class="wsq-brand-sub">استعلام قیمت، پیش‌فاکتور رسمی ماده ۱۶۹ و ربات فروشندگان</span>
      </div>
    </div>
    <div class="wsq-brand-meta">
      <span class="wsq-version-badge">نسخه 1.0.0</span>
    </div>
  </div>
  <div class="wsq-status-row" role="status">
    <span class="wsq-chip is-ok"><span class="wsq-dot"></span>بله: متصل</span>
    <span class="wsq-chip is-ok"><span class="wsq-dot"></span>ایتا: متصل</span>
    <span class="wsq-chip is-ok"><span class="wsq-dot"></span>ارسال خودکار به خریدار: فعال</span>
  </div>
  <div class="wsq-setup-progress">
    <div class="wsq-setup-progress-label"><span>پیشرفت راه‌اندازی</span><span>آماده بهره‌برداری</span></div>
    <div class="wsq-progress"><span style="width:100%;"></span></div>
  </div>
</div>

<!-- Tabs -->
<nav class="nav-tab-wrapper" style="margin-bottom: 20px;">
  <a href="#" class="nav-tab nav-tab-active">
    <span class="wsq-tab-icon"><svg class="wsq-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4.2 5.2h15.6l1.5 8v4.3a2.2 2.2 0 0 1-2.2 2.2H4.9a2.2 2.2 0 0 1-2.2-2.2V13.2l1.5-8Z"/><path d="M2.7 13.4h5.1l1.5 2.9h5.4l1.5-2.9h5.1"/></svg></span>
    داشبورد استعلام‌ها
  </a>
  <a href="#" class="nav-tab">
    <span class="wsq-tab-icon"><svg class="wsq-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M13.8 3.4H7.4a2 2 0 0 0-2 2v13.2a2 2 0 0 0 2 2h9.2a2 2 0 0 0 2-2V8.2l-4.8-4.8Z"/><path d="M13.8 3.4v4.8h4.8"/><path d="M8.6 13.2h6.8M8.6 16.4h4.4"/></svg></span>
    تنظیمات و مشخصات رسمی PDF
  </a>
  <a href="#" class="nav-tab">
    <span class="wsq-tab-icon"><svg class="wsq-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M18 15.8V11a6 6 0 1 0-12 0v4.8L4.2 18.4h15.6L18 15.8Z"/><path d="M10 21h4"/></svg></span>
    پیام‌رسان‌ها (اعلان کارشناسان)
  </a>
  <a href="#" class="nav-tab">
    <span class="wsq-tab-icon"><svg class="wsq-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M20.8 3.4 10.6 13.6"/><path d="M20.8 3.4 14.6 21l-4-7-7-4 17.2-6.6Z"/></svg></span>
    ارسال به خریدار (بله، ایتا، پیامک)
  </a>
  <a href="#" class="nav-tab">
    <span class="wsq-tab-icon"><svg class="wsq-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="6.6" y="2.6" width="10.8" height="18.8" rx="2.6"/><path d="M8.4 6.6h7.2"/><path d="M10.6 18.6h2.8"/></svg></span>
    اپلیکیشن همراه (Android)
  </a>
</nav>

<!-- KPI Cards -->
<div class="wsq-kpi-grid">
  <div class="wsq-kpi">
    <span class="wsq-kpi-icon"><svg class="wsq-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4.2 5.2h15.6l1.5 8v4.3a2.2 2.2 0 0 1-2.2 2.2H4.9a2.2 2.2 0 0 1-2.2-2.2V13.2l1.5-8Z"/><path d="M2.7 13.4h5.1l1.5 2.9h5.4l1.5-2.9h5.1"/></svg></span>
    <span class="wsq-kpi-label">استعلام‌های امروز</span>
    <span class="wsq-kpi-value">۲۴</span>
    <span class="wsq-kpi-meta">دیروز: ۱۸</span>
    <span class="wsq-kpi-delta is-up">▲ ۳۳٪</span>
  </div>
  <div class="wsq-kpi">
    <span class="wsq-kpi-icon"><svg class="wsq-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><rect x="3.6" y="5.4" width="16.8" height="15" rx="2.4"/><path d="M8.2 3.4v3.6M15.8 3.4v3.6M3.6 10.2h16.8"/></svg></span>
    <span class="wsq-kpi-label">استعلام‌های این هفته</span>
    <span class="wsq-kpi-value">۱۱۸</span>
    <span class="wsq-kpi-meta">هفته قبل: ۹۵</span>
    <span class="wsq-kpi-delta is-up">▲ ۲۴٪</span>
  </div>
  <div class="wsq-kpi">
    <span class="wsq-kpi-icon"><svg class="wsq-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><circle cx="12" cy="12" r="8.4"/><path d="M12 7.4V12l3.2 2"/></svg></span>
    <span class="wsq-kpi-label">در انتظار بررسی (کل صف)</span>
    <span class="wsq-kpi-value">۶</span>
    <span class="wsq-kpi-meta"><span class="wsq-nowrap">قدیمی‌ترین: امروز</span></span>
    <a class="wsq-kpi-link" href="#">مشاهده صف</a>
  </div>
  <div class="wsq-kpi">
    <span class="wsq-kpi-icon"><svg class="wsq-icon" viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="1.7"><path d="M4 19.5V4.5M4 19.5h16"/><path d="M7.6 15.6 11.6 11l3 2.4 5-6"/></svg></span>
    <span class="wsq-kpi-label">نرخ تبدیل به سفارش</span>
    <span class="wsq-kpi-value">۷۲٪<span class="wsq-kpi-unit"></span></span>
    <span class="wsq-kpi-meta">۸۵ سفارش از ۱۱۸ استعلام — ۳۰ روز اخیر</span>
    <span class="wsq-kpi-track"><span style="width:72%;"></span></span>
  </div>
</div>

<!-- Chart Card -->
<div class="wsq-section-card wsq-chart-card">
  <h3>روند استعلام‌های ۱۴ روز اخیر</h3>
  <p class="description">هر ستون تعداد استعلام‌های یک روز است؛ بخش پررنگ آن‌هایی که به سفارش تبدیل شده‌اند.</p>
  <div class="wsq-chart-legend">
    <span class="wsq-legend is-total">کل استعلام</span>
    <span class="wsq-legend is-converted">تبدیل به سفارش</span>
  </div>
  <div class="wsq-chart">
    <div class="wsq-chart-col"><div class="wsq-chart-track"><span class="wsq-chart-value" style="bottom:42%;">۵</span><div class="wsq-chart-bar" style="height:42%;"><span class="wsq-chart-fill" style="height:60%;"></span></div></div><span class="wsq-chart-day">۱۶</span><span class="wsq-chart-weekday">شنبه</span></div>
    <div class="wsq-chart-col"><div class="wsq-chart-track"><span class="wsq-chart-value" style="bottom:58%;">۷</span><div class="wsq-chart-bar" style="height:58%;"><span class="wsq-chart-fill" style="height:71%;"></span></div></div><span class="wsq-chart-day">۱۷</span><span class="wsq-chart-weekday">۱ش</span></div>
    <div class="wsq-chart-col"><div class="wsq-chart-track"><span class="wsq-chart-value" style="bottom:50%;">۶</span><div class="wsq-chart-bar" style="height:50%;"><span class="wsq-chart-fill" style="height:66%;"></span></div></div><span class="wsq-chart-day">۱۸</span><span class="wsq-chart-weekday">۲ش</span></div>
    <div class="wsq-chart-col"><div class="wsq-chart-track"><span class="wsq-chart-value" style="bottom:66%;">۸</span><div class="wsq-chart-bar" style="height:66%;"><span class="wsq-chart-fill" style="height:75%;"></span></div></div><span class="wsq-chart-day">۱۹</span><span class="wsq-chart-weekday">۳ش</span></div>
    <div class="wsq-chart-col"><div class="wsq-chart-track"><span class="wsq-chart-value" style="bottom:75%;">۹</span><div class="wsq-chart-bar" style="height:75%;"><span class="wsq-chart-fill" style="height:78%;"></span></div></div><span class="wsq-chart-day">۲۰</span><span class="wsq-chart-weekday">۴ش</span></div>
    <div class="wsq-chart-col"><div class="wsq-chart-track"><span class="wsq-chart-value" style="bottom:82%;">۱۰</span><div class="wsq-chart-bar" style="height:82%;"><span class="wsq-chart-fill" style="height:70%;"></span></div></div><span class="wsq-chart-day">۲۱</span><span class="wsq-chart-weekday">۵ش</span></div>
    <div class="wsq-chart-col"><div class="wsq-chart-track"><span class="wsq-chart-value" style="bottom:33%;">۴</span><div class="wsq-chart-bar" style="height:33%;"><span class="wsq-chart-fill" style="height:50%;"></span></div></div><span class="wsq-chart-day">۲۲</span><span class="wsq-chart-weekday">جمعه</span></div>
    <div class="wsq-chart-col"><div class="wsq-chart-track"><span class="wsq-chart-value" style="bottom:58%;">۷</span><div class="wsq-chart-bar" style="height:58%;"><span class="wsq-chart-fill" style="height:71%;"></span></div></div><span class="wsq-chart-day">۲۳</span><span class="wsq-chart-weekday">شنبه</span></div>
    <div class="wsq-chart-col"><div class="wsq-chart-track"><span class="wsq-chart-value" style="bottom:66%;">۸</span><div class="wsq-chart-bar" style="height:66%;"><span class="wsq-chart-fill" style="height:75%;"></span></div></div><span class="wsq-chart-day">۲۴</span><span class="wsq-chart-weekday">۱ش</span></div>
    <div class="wsq-chart-col"><div class="wsq-chart-track"><span class="wsq-chart-value" style="bottom:75%;">۹</span><div class="wsq-chart-bar" style="height:75%;"><span class="wsq-chart-fill" style="height:67%;"></span></div></div><span class="wsq-chart-day">۲۵</span><span class="wsq-chart-weekday">۲ش</span></div>
    <div class="wsq-chart-col"><div class="wsq-chart-track"><span class="wsq-chart-value" style="bottom:82%;">۱۰</span><div class="wsq-chart-bar" style="height:82%;"><span class="wsq-chart-fill" style="height:80%;"></span></div></div><span class="wsq-chart-day">۲۶</span><span class="wsq-chart-weekday">۳ش</span></div>
    <div class="wsq-chart-col"><div class="wsq-chart-track"><span class="wsq-chart-value" style="bottom:90%;">۱۱</span><div class="wsq-chart-bar" style="height:90%;"><span class="wsq-chart-fill" style="height:73%;"></span></div></div><span class="wsq-chart-day">۲۷</span><span class="wsq-chart-weekday">۴ش</span></div>
    <div class="wsq-chart-col"><div class="wsq-chart-track"><span class="wsq-chart-value" style="bottom:75%;">۹</span><div class="wsq-chart-bar" style="height:75%;"><span class="wsq-chart-fill" style="height:78%;"></span></div></div><span class="wsq-chart-day">۲۸</span><span class="wsq-chart-weekday">۵ش</span></div>
    <div class="wsq-chart-col is-today"><div class="wsq-chart-track"><span class="wsq-chart-value" style="bottom:98%;">۱۲</span><div class="wsq-chart-bar" style="height:98%;"><span class="wsq-chart-fill" style="height:83%;"></span></div></div><span class="wsq-chart-day">۲۹</span><span class="wsq-chart-weekday">امروز</span></div>
  </div>
</div>

<!-- Pending Quotes Table (Exact Plugin WP List Table) -->
<div class="wsq-section-card">
  <h3>قدیمی‌ترین استعلام‌های در انتظار بررسی (فروشنده: بازرگانی آرین فولاد)</h3>
  <div class="wsq-table-scroll">
    <table class="wp-list-table widefat fixed striped wsq-pending-table">
      <thead>
        <tr>
          <th scope="col" style="width: 10%;">استعلام</th>
          <th scope="col" style="width: 25%;">خریدار / شرکت</th>
          <th scope="col" style="width: 14%;">تلفن</th>
          <th scope="col" style="width: 29%;">اقلام درخواستی</th>
          <th scope="col" style="width: 10%;">در انتظار</th>
          <th scope="col" style="width: 12%;">اقدام</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td><strong>#۲۴۰۵</strong></td>
          <td><strong>شرکت مهندسی سازه گستر البرز</strong></td>
          <td dir="ltr">۰۹۱۲۳۴۵۶۷۸۹</td>
          <td>ورق گالوانیزه ۲ میل (۲۰ برگ)، پروفیل ۴۰×۴۰ (۵۰ شاخه)، میلگرد ۱۴ (۱۰۰ شاخه)</td>
          <td class="wsq-days">امروز</td>
          <td><a href="#" class="button wsq-pending-action">قیمت‌گذاری</a></td>
        </tr>
        <tr>
          <td><strong>#۲۴۰۴</strong></td>
          <td><strong>صنایع فلزی کیهان</strong></td>
          <td dir="ltr">۰۹۱۸۴۴۴۵۵۶۶</td>
          <td>پروفیل قوطی ۴۰×۴۰ (۸۰ شاخه)، نبشی ۵ سنگین</td>
          <td class="wsq-days">امروز</td>
          <td><a href="#" class="button wsq-pending-action">قیمت‌گذاری</a></td>
        </tr>
        <tr>
          <td><strong>#۲۴۰۳</strong></td>
          <td><strong>بازرگانی صنعت پارس</strong></td>
          <td dir="ltr">۰۹۳۵۱۱۱۲۲۳۳</td>
          <td>ورق گالوانیزه ۱.۵ میل (۳۰ برگ)</td>
          <td class="wsq-days">۱ روز</td>
          <td><a href="#" class="button wsq-pending-action">قیمت‌گذاری</a></td>
        </tr>
      </tbody>
    </table>
  </div>
</div>

</body>
</html>
HTML;

$dash_file = $root . '/ui-checks/admin-dashboard-real.html';
file_put_contents( $dash_file, $dash_html );
echo "Wrote $dash_file\n";

$dash_png = $root . '/screenshots/admin-dashboard-real.png';
$cmd_dash = "cmd.exe /c \"\"$chrome\" --headless=new --user-data-dir=\"$user_data\" --hide-scrollbars --window-size=1200,840 --screenshot=\"$dash_png\" \"file:///$dash_file\"\"";
exec( $cmd_dash, $out3, $ret3 );
echo "Rendered $dash_png (code $ret3, size: " . filesize( $dash_png ) . " bytes)\n";

// Copy to dashboard-preview.png, docs/admin-dashboard-real.png, and admin-dashboard-real.png
copy( $dash_png, $root . '/screenshots/dashboard-preview.png' );
copy( $dash_png, $root . '/docs/admin-dashboard-real.png' );
copy( $dash_png, $root . '/admin-dashboard-real.png' );
echo "Updated screenshots/dashboard-preview.png, docs/admin-dashboard-real.png, and admin-dashboard-real.png\n";
