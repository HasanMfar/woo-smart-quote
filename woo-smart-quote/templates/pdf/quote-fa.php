<?php
/**
 * Formal RTL Persian Tax 169 Proforma Invoice Template (Optimized for mPDF)
 *
 * Variables available:
 * $order, $order_id, $customer_name, $date, $date_gregorian, $date_expiry, $items, $total, $logo_url,
 * $company_name, $economic_code, $national_id, $registration_no, $company_postal_code, $company_phone,
 * $company_address, $company_bank_name, $company_bank_account, $company_bank_shaba, $company_bank_owner,
 * $seal_image_url, $show_stamp, $stamp_text, $signature_image_url, $show_signature, $header_note, $footer_text, $validity_days,
 * $buyer_national_id, $buyer_economic_code, $buyer_registration_no
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Seller details & fallbacks
$seller_name         = ! empty( $company_name ) ? $company_name : get_bloginfo( 'name' );
$seller_economic     = ! empty( $economic_code ) ? $economic_code : '—';
$seller_national_id  = ! empty( $national_id ) ? $national_id : '—';
$seller_reg_no       = ! empty( $registration_no ) ? $registration_no : '—';
$seller_postcode     = ! empty( $company_postal_code ) ? $company_postal_code : '—';
$seller_phone        = ! empty( $company_phone ) ? $company_phone : '—';
$seller_address      = ! empty( $company_address ) ? $company_address : '—';
$seller_bank_name    = ! empty( $company_bank_name ) ? $company_bank_name : '';
$seller_bank_shaba   = ! empty( $company_bank_shaba ) ? $company_bank_shaba : '';
$seller_bank_account = ! empty( $company_bank_account ) ? $company_bank_account : '';
$seller_bank_owner   = ! empty( $company_bank_owner ) ? $company_bank_owner : $seller_name;

$order_number        = method_exists( $order, 'get_order_number' ) ? $order->get_order_number() : $order_id;
$currency_symbol     = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $order->get_currency() ) : __( 'تومان', 'woo-smart-quote' );
$nbsp                = "\xC2\xA0";

// Buyer details
$billing_company     = $order->get_billing_company();
$billing_phone       = $order->get_billing_phone() ? $order->get_billing_phone() : '—';
$billing_email       = $order->get_billing_email();
$billing_postcode    = $order->get_billing_postcode() ? $order->get_billing_postcode() : '—';
$billing_state       = $order->get_billing_state();
$billing_city        = $order->get_billing_city();
$billing_address_str = trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() );

$full_buyer_address  = array_filter( array( $billing_state, $billing_city, $billing_address_str ) );
$full_buyer_address  = ! empty( $full_buyer_address ) ? implode( '، ', $full_buyer_address ) : '—';

$buyer_display_name  = $customer_name;
if ( ! empty( $billing_company ) && $billing_company !== $customer_name ) {
	$buyer_display_name = $billing_company . ' (' . $customer_name . ')';
}

$buyer_nat_id        = ! empty( $buyer_national_id ) ? $buyer_national_id : '—';
$buyer_eco_code      = ! empty( $buyer_economic_code ) ? $buyer_economic_code : '—';
$buyer_reg           = ! empty( $buyer_registration_no ) ? $buyer_registration_no : '—';

// Look & feel customization
$header_note_value   = ! empty( $header_note ) ? $header_note : __( 'سامانه رسمی استعلام و سفارشات سازمانی', 'woo-smart-quote' );
$footer_text_value   = isset( $footer_text ) ? $footer_text : '';
$validity_days_num   = isset( $validity_days ) && (int) $validity_days > 0 ? (int) $validity_days : 7;
$expiry_date_display = ! empty( $date_expiry ) ? $date_expiry : $date;

// Financial summary
$discount_total      = (float) $order->get_total_discount();
$shipping_total      = (float) $order->get_shipping_total();
$tax_total           = (float) $order->get_total_tax();
$order_subtotal      = (float) $order->get_subtotal();
$order_grandtotal    = (float) $order->get_total();
$net_subtotal        = max( 0, $order_subtotal - $discount_total );

$amount_in_words     = class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::number_to_persian_words( $order_grandtotal ) : '';

// Clean Vector QR SVG for Electronic Tracking
$qr_code_svg = '<svg width="48" height="48" viewBox="0 0 29 29" xmlns="http://www.w3.org/2000/svg" shape-rendering="crispEdges">
<rect width="29" height="29" fill="#ffffff" />
<path fill="#0f172a" d="M0,0 h7 v7 h-7 z M1,1 v5 h5 v-5 z M2,2 h3 v3 h-3 z M0,22 h7 v7 h-7 z M1,23 v5 h5 v-5 z M2,24 h3 v3 h-3 z M22,0 h7 v7 h-7 z M23,1 v5 h5 v-5 z M24,2 h3 v3 h-3 z M8,2 h1 v3 h-1 z M11,1 h3 v1 h-3 z M16,2 h2 v2 h-2 z M19,1 h2 v2 h-2 z M9,8 h2 v2 h-2 z M13,7 h1 v3 h-1 z M16,8 h3 v1 h-3 z M20,8 h2 v2 h-2 z M2,9 h4 v1 h-4 z M8,12 h3 v1 h-3 z M13,11 h3 v2 h-3 z M18,12 h2 v2 h-2 z M22,10 h1 v4 h-1 z M25,12 h3 v2 h-3 z M0,15 h3 v1 h-3 z M5,15 h2 v2 h-2 z M9,15 h1 v3 h-1 z M12,15 h4 v1 h-4 z M18,15 h2 v2 h-2 z M22,16 h2 v2 h-2 z M26,15 h2 v2 h-2 z M2,18 h2 v3 h-2 z M6,18 h3 v1 h-3 z M11,18 h2 v2 h-2 z M15,18 h3 v2 h-3 z M20,19 h2 v2 h-2 z M24,18 h4 v2 h-4 z M9,22 h2 v2 h-2 z M13,22 h3 v1 h-3 z M18,22 h2 v2 h-2 z M22,23 h4 v2 h-4 z M27,22 h2 v4 h-2 z M9,26 h4 v2 h-4 z M15,25 h2 v3 h-2 z M19,26 h2 v2 h-2 z M23,26 h3 v2 h-3 z" />
</svg>';

// Authentic Iranian Corporate Seal & Executive Signature
$seal_stamp_text      = ! empty( $stamp_text ) ? $stamp_text : __( 'تأیید شد — پیش‌فاکتور رسمی', 'woo-smart-quote' );
$seal_vector_svg      = class_exists( 'WSQ_PDF_Generator' )
	? WSQ_PDF_Generator::get_vector_seal( $seller_name, $seller_reg_no, $seller_national_id, $date, $seal_stamp_text )
	: '';
$signature_vector_svg = class_exists( 'WSQ_PDF_Generator' )
	? WSQ_PDF_Generator::get_vector_signature()
	: '';
$signature_image_url  = ! empty( $signature_image_url ) ? $signature_image_url : '';
$show_signature       = isset( $show_signature ) ? (bool) $show_signature : ( get_option( 'wsq_show_digital_signature', 'yes' ) === 'yes' );
$seal_image_url       = ! empty( $seal_image_url ) ? $seal_image_url : '';
$show_stamp           = isset( $show_stamp ) ? (bool) $show_stamp : ( get_option( 'wsq_show_digital_stamp', 'yes' ) === 'yes' );
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
	<meta charset="UTF-8">
	<title><?php esc_html_e( 'پیش‌فاکتور رسمی', 'woo-smart-quote' ); ?> #<?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $order_number ) : $order_number ); ?></title>
	<style>
		@page {
			margin: 10mm 12mm 14mm 12mm;
			footer: html_DocFooter;
		}
		body {
			font-family: 'vazirmatn', 'Vazirmatn', sans-serif;
			direction: rtl;
			text-align: right;
			font-size: 9.5px;
			color: #1e293b;
			line-height: 1.45;
			margin: 0;
			padding: 0;
		}
		table {
			width: 100%;
			border-collapse: collapse;
			table-layout: fixed;
			margin-bottom: 6px;
		}
		tr {
			page-break-inside: avoid;
		}
		td, th {
			vertical-align: middle;
		}

		/* Header Table */
		.header-table {
			border: 1.5px solid #0f172a;
			background: #f8fafc;
			margin-bottom: 7px;
		}
		.header-table td {
			padding: 8px 10px;
		}
		.seller-brand h1 {
			font-size: 14px;
			font-weight: bold;
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
			font-weight: bold;
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
					<?php if ( ! empty( $logo_url ) ) : ?>
						<img src="<?php echo esc_url( $logo_url ); ?>" style="max-height: 40px; max-width: 110px; display: block; margin-bottom: 3px;" alt="Logo">
					<?php endif; ?>
					<h1><?php echo esc_html( $seller_name ); ?></h1>
					<p><?php echo esc_html( $header_note_value ); ?></p>
				</div>
			</td>
			<td class="doc-title-box" style="width: 37%;">
				<h2><?php esc_html_e( 'پیش‌فاکتور رسمی فروش کالا و خدمات', 'woo-smart-quote' ); ?></h2>
				<span><?php esc_html_e( 'منطبق با مقررات ماده ۱۶۹ و ۱۶۹ مکرر قانون مالیات‌های مستقیم', 'woo-smart-quote' ); ?></span>
			</td>
			<td style="width: 30%;">
				<table style="width: 100%; border-collapse: collapse; margin: 0;">
					<tr>
						<td style="width: 65%; vertical-align: middle; padding: 0;">
							<table class="doc-meta-table" style="margin: 0;">
								<tr>
									<td style="font-weight:bold; color:#475569;"><?php esc_html_e( 'شماره سند:', 'woo-smart-quote' ); ?></td>
									<td style="font-weight:bold; direction:ltr; text-align:left;">#<?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $order_number ) : $order_number ); ?></td>
								</tr>
								<tr>
									<td style="font-weight:bold; color:#475569;"><?php esc_html_e( 'تاریخ صدور:', 'woo-smart-quote' ); ?></td>
									<td style="font-weight:bold; direction:ltr; text-align:left;"><?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $date ) : $date ); ?></td>
								</tr>
								<tr>
									<td style="font-weight:bold; color:#475569;"><?php esc_html_e( 'مهلت اعتبار:', 'woo-smart-quote' ); ?></td>
									<td style="font-weight:bold; color:#0369a1; direction:ltr; text-align:left;"><?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $expiry_date_display ) : $expiry_date_display ); ?></td>
								</tr>
							</table>
						</td>
						<td style="width: 35%; text-align: left; vertical-align: middle; padding: 0;">
							<?php echo $qr_code_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<div style="font-size:6.5px; color:#64748b; text-align:center;"><?php esc_html_e( 'کد اصالت سند', 'woo-smart-quote' ); ?></div>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>

	<!-- Seller Info Table -->
	<table class="parties-table">
		<tr>
			<th colspan="4"><?php esc_html_e( 'مشخصات فروشنده (صادرکننده)', 'woo-smart-quote' ); ?></th>
		</tr>
		<tr>
			<td class="label-cell"><?php esc_html_e( 'نام شخص حقیقی/حقوقی:', 'woo-smart-quote' ); ?></td>
			<td class="val-cell"><strong><?php echo esc_html( $seller_name ); ?></strong></td>
			<td class="label-cell"><?php esc_html_e( 'شناسه ملی:', 'woo-smart-quote' ); ?></td>
			<td class="val-cell"><strong><?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $seller_national_id ) : $seller_national_id ); ?></strong></td>
		</tr>
		<tr>
			<td class="label-cell"><?php esc_html_e( 'شماره اقتصادی:', 'woo-smart-quote' ); ?></td>
			<td class="val-cell"><strong><?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $seller_economic ) : $seller_economic ); ?></strong></td>
			<td class="label-cell"><?php esc_html_e( 'شماره ثبت / پرونده:', 'woo-smart-quote' ); ?></td>
			<td class="val-cell"><strong><?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $seller_reg_no ) : $seller_reg_no ); ?></strong></td>
		</tr>
		<tr>
			<td class="label-cell"><?php esc_html_e( 'کد پستی ۱۰ رقمی:', 'woo-smart-quote' ); ?></td>
			<td class="val-cell"><strong><?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $seller_postcode ) : $seller_postcode ); ?></strong></td>
			<td class="label-cell"><?php esc_html_e( 'تلفن و نمابر:', 'woo-smart-quote' ); ?></td>
			<td class="val-cell" dir="ltr" style="text-align:right;"><?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $seller_phone ) : $seller_phone ); ?></td>
		</tr>
		<tr>
			<td class="label-cell"><?php esc_html_e( 'نشانی ثبتی اقامتگاه:', 'woo-smart-quote' ); ?></td>
			<td class="val-cell" colspan="3"><?php echo esc_html( $seller_address ); ?></td>
		</tr>
	</table>

	<!-- Buyer Info Table -->
	<table class="parties-table">
		<tr>
			<th colspan="4"><?php esc_html_e( 'مشخصات خریدار (متقاضی)', 'woo-smart-quote' ); ?></th>
		</tr>
		<tr>
			<td class="label-cell"><?php esc_html_e( 'نام شخص حقیقی/حقوقی:', 'woo-smart-quote' ); ?></td>
			<td class="val-cell"><strong><?php echo esc_html( $buyer_display_name ); ?></strong></td>
			<td class="label-cell"><?php esc_html_e( 'شناسه ملی / کد ملی:', 'woo-smart-quote' ); ?></td>
			<td class="val-cell"><strong><?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $buyer_nat_id ) : $buyer_nat_id ); ?></strong></td>
		</tr>
		<tr>
			<td class="label-cell"><?php esc_html_e( 'شماره اقتصادی:', 'woo-smart-quote' ); ?></td>
			<td class="val-cell"><strong><?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $buyer_eco_code ) : $buyer_eco_code ); ?></strong></td>
			<td class="label-cell"><?php esc_html_e( 'شماره ثبت / پرونده:', 'woo-smart-quote' ); ?></td>
			<td class="val-cell"><strong><?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $buyer_reg ) : $buyer_reg ); ?></strong></td>
		</tr>
		<tr>
			<td class="label-cell"><?php esc_html_e( 'کد پستی ۱۰ رقمی:', 'woo-smart-quote' ); ?></td>
			<td class="val-cell"><strong><?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $billing_postcode ) : $billing_postcode ); ?></strong></td>
			<td class="label-cell"><?php esc_html_e( 'تلفن همراه / تماس:', 'woo-smart-quote' ); ?></td>
			<td class="val-cell" dir="ltr" style="text-align:right;"><?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $billing_phone ) : $billing_phone ); ?></td>
		</tr>
		<tr>
			<td class="label-cell"><?php esc_html_e( 'نشانی کامل متقاضی:', 'woo-smart-quote' ); ?></td>
			<td class="val-cell" colspan="3"><?php echo esc_html( $full_buyer_address ); ?></td>
		</tr>
	</table>

	<!-- Items Table -->
	<table class="items-table">
		<thead>
			<tr>
				<th style="width: 4%;"><?php esc_html_e( 'ردیف', 'woo-smart-quote' ); ?></th>
				<th style="width: 9%;"><?php esc_html_e( 'کد کالا', 'woo-smart-quote' ); ?></th>
				<th style="width: 29%;"><?php esc_html_e( 'شرح کالا یا خدمات درخواستی', 'woo-smart-quote' ); ?></th>
				<th style="width: 6%;"><?php esc_html_e( 'مقدار', 'woo-smart-quote' ); ?></th>
				<th style="width: 6%;"><?php esc_html_e( 'واحد', 'woo-smart-quote' ); ?></th>
				<th style="width: 11%;"><?php esc_html_e( 'مبلغ واحد', 'woo-smart-quote' ); ?> (<?php echo esc_html( $currency_symbol ); ?>)</th>
				<th style="width: 11%;"><?php esc_html_e( 'مبلغ کل', 'woo-smart-quote' ); ?> (<?php echo esc_html( $currency_symbol ); ?>)</th>
				<th style="width: 7%;"><?php esc_html_e( 'تخفیف', 'woo-smart-quote' ); ?></th>
				<th style="width: 7%;"><?php esc_html_e( 'مالیات ۱۰٪', 'woo-smart-quote' ); ?></th>
				<th style="width: 10%;"><?php esc_html_e( 'مبلغ نهایی', 'woo-smart-quote' ); ?> (<?php echo esc_html( $currency_symbol ); ?>)</th>
			</tr>
		</thead>
		<tbody>
			<?php
			$row_num = 1;
			$has_zero_price_item = false;

			foreach ( $items as $item_id => $item ) {
				$product       = $item->get_product();
				$sku           = ( $product && $product->get_sku() ) ? $product->get_sku() : '—';
				$qty           = (int) $item->get_quantity();
				$line_subtotal = (float) $item->get_subtotal(); // gross before item discount
				$line_total    = (float) $item->get_total();    // net after item discount
				$line_discount = $line_subtotal > $line_total ? ( $line_subtotal - $line_total ) : 0;
				$line_tax      = (float) $item->get_total_tax();
				$line_final    = $line_total + $line_tax;

				if ( $line_subtotal <= 0 ) {
					$has_zero_price_item = true;
				}

				$unit_price = $qty > 0 ? ( $line_subtotal / $qty ) : 0;

				// Custom unit detection from product attributes or metadata
				$unit = $product ? ( $product->get_meta( '_unit' ) ?: $product->get_meta( 'unit' ) ?: '' ) : '';
				if ( empty( $unit ) ) {
					$unit = __( 'عدد', 'woo-smart-quote' );
				}

				$unit_price_text    = $unit_price > 0 ? ( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::format_persian_number( $unit_price ) : number_format( $unit_price ) ) : __( 'استعلام', 'woo-smart-quote' );
				$subtotal_text      = $line_subtotal > 0 ? ( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::format_persian_number( $line_subtotal ) : number_format( $line_subtotal ) ) : __( 'استعلام', 'woo-smart-quote' );
				$discount_text      = $line_discount > 0 ? ( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::format_persian_number( $line_discount ) : number_format( $line_discount ) ) : '۰';
				$tax_text           = $line_tax > 0 ? ( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::format_persian_number( $line_tax ) : number_format( $line_tax ) ) : '۰';
				$final_text         = $line_final > 0 ? ( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::format_persian_number( $line_final ) : number_format( $line_final ) ) : __( 'استعلام', 'woo-smart-quote' );
				?>
				<tr>
					<td><?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $row_num++ ) : $row_num++ ); ?></td>
					<td style="color:#64748b;"><?php echo esc_html( $sku ); ?></td>
					<td class="text-right">
						<strong><?php echo esc_html( $item->get_name() ); ?></strong>
						<?php
						$meta_data = $item->get_formatted_meta_data( '' );
						if ( ! empty( $meta_data ) ) {
							$meta_parts = array();
							foreach ( $meta_data as $meta ) {
								$meta_parts[] = esc_html( wp_strip_all_tags( $meta->display_key ) ) . ': ' . esc_html( wp_strip_all_tags( $meta->display_value ) );
							}
							echo '<div style="font-size: 7.5px; color: #64748b; margin-top: 2px;">' . implode( ' | ', $meta_parts ) . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						}
						?>
					</td>
					<td><strong><?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $qty ) : $qty ); ?></strong></td>
					<td><?php echo esc_html( $unit ); ?></td>
					<td class="text-left"><?php echo esc_html( $unit_price_text ); ?></td>
					<td class="text-left"><?php echo esc_html( $subtotal_text ); ?></td>
					<td class="text-left" style="<?php echo $line_discount > 0 ? 'color:#dc2626;' : ''; ?>"><?php echo esc_html( $discount_text ); ?></td>
					<td class="text-left"><?php echo esc_html( $tax_text ); ?></td>
					<td class="text-left"><strong><?php echo esc_html( $final_text ); ?></strong></td>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>

	<!-- Bottom Grid: Bank & Terms (Right), Totals Table (Left) -->
	<table class="bottom-grid">
		<tr>
			<!-- Right Cell: Bank Info & Terms -->
			<td style="width: 52%; vertical-align: top; padding-left: 8px;">
				<?php if ( ! empty( $seller_bank_name ) || ! empty( $seller_bank_shaba ) || ! empty( $seller_bank_account ) ) : ?>
					<table class="bank-box-table">
						<tr>
							<th colspan="2"><?php esc_html_e( 'مشخصات حساب بانکی فروشنده جهت واریز وجه:', 'woo-smart-quote' ); ?></th>
						</tr>
						<?php if ( ! empty( $seller_bank_name ) ) : ?>
						<tr>
							<td style="width: 25%; font-weight: bold; color: #475569;"><?php esc_html_e( 'بانک و شعبه:', 'woo-smart-quote' ); ?></td>
							<td style="width: 75%;"><?php echo esc_html( $seller_bank_name ); ?></td>
						</tr>
						<?php endif; ?>
						<?php if ( ! empty( $seller_bank_shaba ) ) : ?>
						<tr>
							<td style="font-weight: bold; color: #475569;"><?php esc_html_e( 'شماره شبا (IBAN):', 'woo-smart-quote' ); ?></td>
							<td dir="ltr" style="text-align: right; font-weight: bold; color: #0369a1;"><?php echo esc_html( $seller_bank_shaba ); ?></td>
						</tr>
						<?php endif; ?>
						<?php if ( ! empty( $seller_bank_account ) ) : ?>
						<tr>
							<td style="font-weight: bold; color: #475569;"><?php esc_html_e( 'شماره حساب / کارت:', 'woo-smart-quote' ); ?></td>
							<td dir="ltr" style="text-align: right;"><?php echo esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $seller_bank_account ) : $seller_bank_account ); ?></td>
						</tr>
						<?php endif; ?>
						<?php if ( ! empty( $seller_bank_owner ) ) : ?>
						<tr>
							<td style="font-weight: bold; color: #475569;"><?php esc_html_e( 'به نام:', 'woo-smart-quote' ); ?></td>
							<td><strong><?php echo esc_html( $seller_bank_owner ); ?></strong></td>
						</tr>
						<?php endif; ?>
					</table>
				<?php endif; ?>

				<table class="terms-table">
					<tr>
						<th><?php esc_html_e( 'شرایط و ضوابط پیش‌فاکتور:', 'woo-smart-quote' ); ?></th>
					</tr>
					<tr>
						<td>
							• <?php printf( esc_html__( 'مهلت اعتبار: این سند تا تاریخ %s به مدت %s روز کاری از تاریخ صدور معتبر است.', 'woo-smart-quote' ), esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $expiry_date_display ) : $expiry_date_display ), esc_html( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::to_persian_digits( $validity_days_num ) : $validity_days_num ) ); ?><br>
							• <?php esc_html_e( 'شرایط تحویل: موجودی اقلام در زمان واریز پیش‌پرداخت ملاک عمل بوده و تسویه پیش از بارگیری الزامی است.', 'woo-smart-quote' ); ?><br>
							<?php if ( ! empty( $order->get_customer_note() ) ) : ?>
								• <strong><?php esc_html_e( 'توضیحات خریدار:', 'woo-smart-quote' ); ?></strong> <?php echo esc_html( $order->get_customer_note() ); ?>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</td>

			<!-- Left Cell: Financial Totals -->
			<td style="width: 48%; vertical-align: top;">
				<table class="totals-table">
					<tr>
						<td class="label"><?php esc_html_e( 'جمع کل اقلام (ناخالص):', 'woo-smart-quote' ); ?></td>
						<td class="val"><?php echo $order_subtotal > 0 ? esc_html( ( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::format_persian_number( $order_subtotal ) : number_format( $order_subtotal ) ) . $nbsp . $currency_symbol ) : esc_html__( 'نیازمند بررسی', 'woo-smart-quote' ); ?></td>
					</tr>
					<?php if ( $discount_total > 0 ) : ?>
					<tr>
						<td class="label"><?php esc_html_e( 'تخفیف ویژه تجاری:', 'woo-smart-quote' ); ?></td>
						<td class="val" style="color: #dc2626;">- <?php echo esc_html( ( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::format_persian_number( $discount_total ) : number_format( $discount_total ) ) . $nbsp . $currency_symbol ); ?></td>
					</tr>
					<?php endif; ?>
					<tr>
						<td class="label"><?php esc_html_e( 'جمع پس از کسر تخفیف:', 'woo-smart-quote' ); ?></td>
						<td class="val"><?php echo esc_html( ( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::format_persian_number( $net_subtotal ) : number_format( $net_subtotal ) ) . $nbsp . $currency_symbol ); ?></td>
					</tr>
					<?php if ( $tax_total > 0 ) : ?>
					<tr>
						<td class="label"><?php esc_html_e( 'مالیات بر ارزش افزوده (۱۰٪):', 'woo-smart-quote' ); ?></td>
						<td class="val">+ <?php echo esc_html( ( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::format_persian_number( $tax_total ) : number_format( $tax_total ) ) . $nbsp . $currency_symbol ); ?></td>
					</tr>
					<?php endif; ?>
					<?php if ( $shipping_total > 0 ) : ?>
					<tr>
						<td class="label"><?php esc_html_e( 'هزینه حمل و نقل:', 'woo-smart-quote' ); ?></td>
						<td class="val">+ <?php echo esc_html( ( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::format_persian_number( $shipping_total ) : number_format( $shipping_total ) ) . $nbsp . $currency_symbol ); ?></td>
					</tr>
					<?php endif; ?>
					<tr class="grand-row">
						<td><?php esc_html_e( 'مبلغ نهایی قابل پرداخت:', 'woo-smart-quote' ); ?></td>
						<td class="val">
							<?php
							if ( $has_zero_price_item ) {
								echo esc_html__( 'در انتظار قیمت‌گذاری', 'woo-smart-quote' );
							} elseif ( $order_grandtotal > 0 ) {
								echo esc_html( ( class_exists( 'WSQ_PDF_Generator' ) ? WSQ_PDF_Generator::format_persian_number( $order_grandtotal ) : number_format( $order_grandtotal ) ) . $nbsp . $currency_symbol );
							} elseif ( ! empty( $total ) ) {
								echo wp_kses_post( $total );
							} else {
								echo esc_html__( 'در انتظار قیمت‌گذاری', 'woo-smart-quote' );
							}
							?>
						</td>
					</tr>
					<?php if ( ! empty( $amount_in_words ) && ! $has_zero_price_item && $order_grandtotal > 0 ) : ?>
					<tr class="words-row">
						<td colspan="2">
							<strong><?php esc_html_e( 'مبلغ به حروف:', 'woo-smart-quote' ); ?></strong> <?php echo esc_html( $amount_in_words ); ?>
						</td>
					</tr>
					<?php endif; ?>
				</table>
			</td>
		</tr>
	</table>

	<!-- Signatures Table -->
	<table class="sign-table">
		<tr>
			<!-- Seller Box (Right) -->
			<td class="sign-cell" style="width: 50%;">
				<div class="sign-title"><?php esc_html_e( 'مهر و امضای مجاز فروشنده:', 'woo-smart-quote' ); ?></div>
				<div class="sign-sub"><?php esc_html_e( 'تأییدیه مشخصات، نرخ و شرایط پیش‌فاکتور رسمی', 'woo-smart-quote' ); ?></div>
				<table style="width: 100%; border: none; margin: 0;">
					<tr>
						<td style="border: none; padding: 0; width: 48%; vertical-align: top;">
							<div style="font-size: 8px; color: #334155; line-height: 1.5;">
								<?php esc_html_e( 'مدیر امور مالی و فروش:', 'woo-smart-quote' ); ?><br>
								<strong style="color: #0f172a;"><?php echo esc_html( $seller_name ); ?></strong>
							</div>
							<?php if ( ! empty( $signature_image_url ) ) : ?>
								<img src="<?php echo esc_url( $signature_image_url ); ?>" style="max-height: 52px; max-width: 130px; display: block; margin-top: 4px;" alt="Signature">
							<?php elseif ( ! empty( $show_signature ) ) : ?>
								<?php echo $signature_vector_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
						</td>
						<td style="border: none; padding: 0; width: 52%; text-align: left; vertical-align: middle;">
							<?php if ( ! empty( $seal_image_url ) ) : ?>
								<img src="<?php echo esc_url( $seal_image_url ); ?>" style="max-height: 95px; max-width: 120px; display: inline-block;" alt="Seal">
							<?php elseif ( ! empty( $show_stamp ) ) : ?>
								<?php echo $seal_vector_svg; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
							<?php endif; ?>
						</td>
					</tr>
				</table>
			</td>

			<!-- Spacer -->
			<td style="width: 2%; border: none; background: transparent;">&nbsp;</td>

			<!-- Buyer Box (Left) -->
			<td class="sign-cell" style="width: 48%;">
				<div class="sign-title"><?php esc_html_e( 'تأیید و امضای خریدار (متقاضی):', 'woo-smart-quote' ); ?></div>
				<div class="sign-sub"><?php esc_html_e( 'مشخصات اقلام، بهای پیشنهادی و شرایط فوق مورد تأیید می‌باشد.', 'woo-smart-quote' ); ?></div>
				<div style="margin-top: 36px; font-size: 8px; color: #334155; line-height: 1.8;">
					<?php esc_html_e( 'نام و سمت نماینده تام‌الاختیار: ....................................................', 'woo-smart-quote' ); ?><br>
					<?php esc_html_e( 'تاریخ و امضا: .................... / .................... / ....................', 'woo-smart-quote' ); ?>
				</div>
			</td>
		</tr>
	</table>

	<!-- Running Footer for mPDF -->
	<htmlpagefooter name="DocFooter">
		<table style="width: 100%; border-top: 1px solid #cbd5e1; font-size: 7.5px; color: #64748b; padding-top: 3px; margin: 0;">
			<tr>
				<td style="width: 75%; text-align: right; border: none; padding: 0;">
					<?php if ( ! empty( $footer_text_value ) ) : ?>
						<?php echo wp_kses_post( wpautop( $footer_text_value ) ); ?>
					<?php else : ?>
						<?php
						printf(
							/* translators: %s: seller name */
							esc_html__( 'این سند رسمی الکترونیکی مطابق با دستورالعمل اجرایی ماده ۱۶۹ قانون مالیات‌های مستقیم توسط افزونه «نرخنگار» برای %s صادر گردیده است.', 'woo-smart-quote' ),
							'<b>' . esc_html( $seller_name ) . '</b>'
						);
						?>
					<?php endif; ?>
				</td>
				<td style="width: 25%; text-align: left; direction: rtl; border: none; padding: 0;">
					<?php esc_html_e( 'صفحه {PAGENO} از {nbpg}', 'woo-smart-quote' ); ?>
				</td>
			</tr>
		</table>
	</htmlpagefooter>

</body>
</html>