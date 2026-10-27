<?php
/**
 * Formal RTL Persian PDF Template (Optimized for mPDF / Dompdf)
 *
 * Variables available:
 * $order, $order_id, $customer_name, $date, $date_gregorian, $items, $total, $logo_url,
 * $company_name, $economic_code, $national_id, $registration_no, $company_phone,
 * $company_address, $show_stamp, $stamp_text
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Helper variables & fallbacks
$seller_name      = ! empty( $company_name ) ? $company_name : get_bloginfo( 'name' );
$order_number     = method_exists( $order, 'get_order_number' ) ? $order->get_order_number() : $order_id;
$currency_symbol  = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol( $order->get_currency() ) : __( 'تومان', 'woo-smart-quote' );
// Non-breaking space keeps the currency unit attached to the amount in RTL text.
$nbsp             = "\xC2\xA0";

// Buyer details
$billing_company  = $order->get_billing_company();
$billing_phone    = $order->get_billing_phone();
$billing_email    = $order->get_billing_email();
$billing_postcode = $order->get_billing_postcode();
$billing_state    = $order->get_billing_state();
$billing_city     = $order->get_billing_city();
$billing_address  = trim( $order->get_billing_address_1() . ' ' . $order->get_billing_address_2() );

$full_buyer_address = array_filter( array( $billing_state, $billing_city, $billing_address ) );
$full_buyer_address = implode( '، ', $full_buyer_address );

// Financial summary checks
$discount_total   = (float) $order->get_total_discount();
$shipping_total   = (float) $order->get_shipping_total();
$tax_total        = (float) $order->get_total_tax();
$order_subtotal   = (float) $order->get_subtotal();
$order_Grandtotal = (float) $order->get_total();

// Look & feel customization (fallbacks mirror the generator defaults)
$header_note_value  = isset( $header_note ) ? $header_note : __( 'سیستم جامع استعلام و سفارشات سازمانی', 'woo-smart-quote' );
$footer_text_value  = isset( $footer_text ) ? $footer_text : '';
$validity_days_num  = isset( $validity_days ) ? (int) $validity_days : 7;

// Persian digit helper for the validity box
$fa_digit_map = array( '0' => '۰', '1' => '۱', '2' => '۲', '3' => '۳', '4' => '۴', '5' => '۵', '6' => '۶', '7' => '۷', '8' => '۸', '9' => '۹' );
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
	<meta charset="UTF-8">
	<title><?php esc_html_e( 'پیش‌فاکتور', 'woo-smart-quote' ); ?> #<?php echo esc_html( $order_number ); ?></title>
	<style>
		@page {
			margin: 14mm 12mm;
		}
		body {
			font-family: 'Vazirmatn', 'vazir', 'xbriyaz', 'DejaVu Sans', sans-serif;
			direction: rtl;
			text-align: right;
			font-size: 10.5px;
			color: #1e293b;
			line-height: 1.5;
			margin: 0;
			padding: 0;
		}
		table {
			width: 100%;
			border-collapse: collapse;
			table-layout: fixed;
		}
		tr {
			page-break-inside: avoid;
		}

		/* Header */
		.header-table {
			margin-bottom: 4px;
		}
		.header-rule {
			margin-bottom: 10px;
		}
		.header-rule td {
			border-top: 2px solid #312e81;
			height: 1px;
			font-size: 1px;
			line-height: 1px;
		}
		.logo {
			max-width: 120px;
			max-height: 55px;
			display: block;
			margin-bottom: 4px;
		}
		.company-title {
			font-size: 14px;
			font-weight: bold;
			color: #1e1b4b;
		}
		.company-subtitle {
			font-size: 9px;
			color: #64748b;
		}
		.doc-title-box {
			text-align: center;
		}
		/* mPDF draws borders/backgrounds of block divs per text line, so every
		   boxed element in this template is a table (mPDF handles those). */
		.doc-title {
			width: 100%;
			background-color: #eef2ff;
			border: 1px solid #c7d2fe;
		}
		.doc-title td {
			font-size: 16px;
			font-weight: bold;
			color: #3730a3;
			text-align: center;
			padding: 5px 12px;
		}
		.doc-subtitle {
			font-size: 9.5px;
			color: #64748b;
			margin-top: 4px;
		}
		.doc-meta-table {
			width: 100%;
			font-size: 9.5px;
		}
		.doc-meta-table td {
			padding: 2px 4px;
			color: #334155;
		}
		.doc-meta-label {
			font-weight: bold;
			color: #475569;
			text-align: left;
			width: 55%;
		}
		.doc-meta-val {
			text-align: left;
			width: 45%;
			font-weight: bold;
			color: #0f172a;
		}

		/* Parties (Seller & Buyer) */
		.parties-wrapper {
			margin-bottom: 14px;
		}
		.party-box {
			border: 1px solid #cbd5e1;
			background-color: #f8fafc;
		}
		.party-box > tbody > tr > td {
			padding: 8px 10px;
		}
		.party-title {
			font-weight: bold;
			color: #3730a3;
			font-size: 10.5px;
			border-bottom: 1px solid #e2e8f0;
			padding-bottom: 4px;
			margin-bottom: 6px;
		}
		.party-info-table td {
			font-size: 9.5px;
			padding: 2px 0;
			color: #334155;
			vertical-align: top;
		}

		/* Items Table */
		.items-table {
			margin-bottom: 14px;
		}
		.items-table th {
			background-color: #312e81;
			color: #ffffff;
			padding: 7px 6px;
			text-align: center;
			font-size: 9.5px;
			font-weight: bold;
			border: 1px solid #312e81;
		}
		.items-table td {
			border: 1px solid #cbd5e1;
			padding: 6px;
			font-size: 9.5px;
			vertical-align: middle;
		}
		.items-table tr:nth-child(even) td {
			background-color: #f8fafc;
		}
		.col-num {
			width: 6%;
			text-align: center;
			color: #64748b;
		}
		.col-sku {
			width: 14%;
			text-align: center;
			color: #475569;
			font-size: 8.5px;
		}
		.col-desc {
			width: 40%;
			text-align: right;
		}
		.col-qty {
			width: 8%;
			text-align: center;
			font-weight: bold;
		}
		.col-unit {
			width: 16%;
			text-align: left;
		}
		.col-total {
			width: 16%;
			text-align: left;
			font-weight: bold;
		}
		.item-meta {
			font-size: 8.5px;
			color: #64748b;
			margin-top: 3px;
		}

		/* Summary & Notes */
		.summary-table {
			margin-bottom: 15px;
			page-break-inside: avoid;
		}
		.customer-note-box {
			background-color: #fffbeb;
			border: 1px solid #fde68a;
			margin-bottom: 6px;
		}
		.customer-note-box > tbody > tr > td {
			padding: 8px 10px;
			font-size: 9.5px;
			color: #92400e;
		}
		.terms-box {
			margin-top: 6px;
			font-size: 8.5px;
			color: #64748b;
			line-height: 1.6;
		}
		.totals-table {
			width: 100%;
			border: 1px solid #cbd5e1;
		}
		.totals-table th {
			background-color: #f1f5f9;
			padding: 5px 8px;
			border: 1px solid #cbd5e1;
			text-align: right;
			font-size: 9.5px;
			color: #334155;
			width: 50%;
		}
		.totals-table td {
			padding: 5px 8px;
			border: 1px solid #cbd5e1;
			text-align: left;
			font-size: 10px;
			font-weight: bold;
			color: #0f172a;
			width: 50%;
		}
		.totals-table tr.grand-total th {
			background-color: #eef2ff;
			color: #312e81;
			font-size: 10.5px;
		}
		.totals-table tr.grand-total td {
			background-color: #eef2ff;
			color: #3730a3;
			font-size: 11px;
		}

		/* Signatures & Seal */
		.signature-table {
			margin-top: 15px;
			border-top: 1px dashed #cbd5e1;
			padding-top: 12px;
			page-break-inside: avoid;
		}
		/* Stamp alignment is done with a wrapper table (Dompdf ignores
		   auto margins / floats, but aligns table cells reliably). */
		.stamp-wrap {
			width: 100%;
		}
		.stamp-spacer {
			width: 62%;
			vertical-align: middle;
		}
		.stamp-pos {
			width: 38%;
			vertical-align: middle;
			text-align: left;
		}
		.stamp-box {
			width: 170px;
			border: 2px double #dc2626;
			background-color: #fff5f5;
			color: #dc2626;
		}
		.stamp-box > tbody > tr > td {
			text-align: center;
			padding: 8px 10px;
		}
		.stamp-title {
			font-weight: bold;
			font-size: 10px;
			border-bottom: 1px dashed #fca5a5;
			padding-bottom: 3px;
			margin-bottom: 4px;
		}
		.stamp-sub {
			font-size: 8.5px;
			line-height: 1.4;
		}

		/* Footer */
		.footer {
			margin-top: 20px;
			text-align: center;
			font-size: 8.5px;
			color: #94a3b8;
			border-top: 1px solid #e2e8f0;
			padding-top: 6px;
		}
	</style>
</head>
<body>

	<!-- Header -->
	<table class="header-table">
		<tr>
			<!-- Right: Logo & Company -->
			<td style="width: 38%; vertical-align: middle;">
				<?php if ( ! empty( $logo_url ) ) : ?>						<img src="<?php echo esc_url( $logo_url ); ?>" class="logo" alt="Logo">
				<?php endif; ?>
				<div class="company-title"><?php echo esc_html( $seller_name ); ?></div>
				<div class="company-subtitle"><?php echo esc_html( $header_note_value ); ?></div>
			</td>

			<!-- Center: Title -->
			<td class="doc-title-box" style="width: 30%; vertical-align: middle;">
				<table class="doc-title"><tr><td><?php esc_html_e( 'پیش‌فاکتور رسمی', 'woo-smart-quote' ); ?></td></tr></table>
				<div class="doc-subtitle"><?php esc_html_e( 'استعلام بهای کالا و خدمات', 'woo-smart-quote' ); ?></div>
			</td>

			<!-- Left: Meta Info -->
			<td style="width: 32%; vertical-align: middle;">
				<table class="doc-meta-table">
					<tr>
						<td class="doc-meta-label"><?php esc_html_e( 'شماره سند:', 'woo-smart-quote' ); ?></td>
						<td class="doc-meta-val">#<?php echo esc_html( $order_number ); ?></td>
					</tr>
					<tr>
						<td class="doc-meta-label"><?php esc_html_e( 'تاریخ صدور:', 'woo-smart-quote' ); ?></td>
						<td class="doc-meta-val">
							<?php echo esc_html( $date ); ?>
							<?php if ( ! empty( $date_gregorian ) ) : ?>
								<br><span dir="ltr" style="font-size: 8px; color: #64748b; font-weight: normal;"><?php echo esc_html( $date_gregorian ); ?></span>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<td class="doc-meta-label"><?php esc_html_e( 'مدت اعتبار:', 'woo-smart-quote' ); ?></td>
						<td class="doc-meta-val"><?php echo esc_html( str_replace( array_keys( $fa_digit_map ), array_values( $fa_digit_map ), (string) $validity_days_num ) ); ?> <?php esc_html_e( 'روز کاری', 'woo-smart-quote' ); ?></td>
					</tr>
				</table>
			</td>
		</tr>
	</table>

	<table class="header-rule"><tr><td>&nbsp;</td></tr></table>

	<!-- Parties (Seller & Buyer) -->
	<table class="parties-wrapper">
		<tr>
			<!-- Seller Box -->
			<td style="width: 49%; vertical-align: top;">
				<table class="party-box"><tr><td>
					<div class="party-title"><?php esc_html_e( 'مشخصات فروشنده', 'woo-smart-quote' ); ?></div>
					<table class="party-info-table">
						<tr>
							<td style="width: 55%;"><b><?php esc_html_e( 'نام واحد تجاری:', 'woo-smart-quote' ); ?></b> <?php echo esc_html( $seller_name ); ?></td>
							<td style="width: 45%;">
								<?php if ( ! empty( $economic_code ) ) : ?>
									<b><?php esc_html_e( 'کد اقتصادی:', 'woo-smart-quote' ); ?></b> <?php echo esc_html( $economic_code ); ?>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<td>
								<?php if ( ! empty( $national_id ) ) : ?>
									<b><?php esc_html_e( 'شناسه ملی:', 'woo-smart-quote' ); ?></b> <?php echo esc_html( $national_id ); ?>
								<?php endif; ?>
							</td>
							<td>
								<?php if ( ! empty( $registration_no ) ) : ?>
									<b><?php esc_html_e( 'شماره ثبت:', 'woo-smart-quote' ); ?></b> <?php echo esc_html( $registration_no ); ?>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( ! empty( $company_phone ) ) : ?>
						<tr>
							<td colspan="2"><b><?php esc_html_e( 'تلفن تماس:', 'woo-smart-quote' ); ?></b> <?php echo esc_html( $company_phone ); ?></td>
						</tr>
						<?php endif; ?>
						<?php if ( ! empty( $company_address ) ) : ?>
						<tr>
							<td colspan="2"><b><?php esc_html_e( 'نشانی:', 'woo-smart-quote' ); ?></b> <?php echo esc_html( $company_address ); ?></td>
						</tr>
						<?php endif; ?>
					</table>
				</td></tr></table>
			</td>

			<!-- Spacer -->
			<td style="width: 2%;">&nbsp;</td>

			<!-- Buyer Box -->
			<td style="width: 49%; vertical-align: top;">
				<table class="party-box"><tr><td>
					<div class="party-title"><?php esc_html_e( 'مشخصات خریدار (متقاضی)', 'woo-smart-quote' ); ?></div>
					<table class="party-info-table">
						<tr>
							<td colspan="2">
								<b><?php esc_html_e( 'نام شخص / شرکت:', 'woo-smart-quote' ); ?></b>
								<?php
								echo esc_html( $customer_name );
								if ( ! empty( $billing_company ) && $billing_company !== $customer_name ) {
									echo ' (' . esc_html( $billing_company ) . ')';
								}
								?>
							</td>
						</tr>
						<tr>
							<td style="width: 55%;">
								<?php if ( ! empty( $billing_phone ) ) : ?>
									<b><?php esc_html_e( 'تلفن تماس:', 'woo-smart-quote' ); ?></b> <?php echo esc_html( $billing_phone ); ?>
								<?php endif; ?>
							</td>
							<td style="width: 45%;">
								<?php if ( ! empty( $billing_postcode ) ) : ?>
									<b><?php esc_html_e( 'کد پستی:', 'woo-smart-quote' ); ?></b> <?php echo esc_html( $billing_postcode ); ?>
								<?php endif; ?>
							</td>
						</tr>
						<?php if ( ! empty( $billing_email ) ) : ?>
						<tr>
							<td colspan="2"><b><?php esc_html_e( 'ایمیل:', 'woo-smart-quote' ); ?></b> <?php echo esc_html( $billing_email ); ?></td>
						</tr>
						<?php endif; ?>
						<?php if ( ! empty( $full_buyer_address ) ) : ?>
						<tr>
							<td colspan="2"><b><?php esc_html_e( 'نشانی:', 'woo-smart-quote' ); ?></b> <?php echo esc_html( $full_buyer_address ); ?></td>
						</tr>
						<?php endif; ?>
					</table>
				</td></tr></table>
			</td>
		</tr>
	</table>

	<!-- Items Table -->
	<table class="items-table">
		<thead>
			<tr>
				<th class="col-num"><?php esc_html_e( 'ردیف', 'woo-smart-quote' ); ?></th>
				<th class="col-sku"><?php esc_html_e( 'کد کالا', 'woo-smart-quote' ); ?></th>
				<th class="col-desc"><?php esc_html_e( 'شرح کالا یا خدمات', 'woo-smart-quote' ); ?></th>
				<th class="col-qty"><?php esc_html_e( 'تعداد', 'woo-smart-quote' ); ?></th>
				<th class="col-unit"><?php esc_html_e( 'مبلغ واحد', 'woo-smart-quote' ); ?> (<?php echo esc_html( $currency_symbol ); ?>)</th>
				<th class="col-total"><?php esc_html_e( 'مبلغ کل', 'woo-smart-quote' ); ?> (<?php echo esc_html( $currency_symbol ); ?>)</th>
			</tr>
		</thead>
		<tbody>
			<?php
			$i = 1;
			$has_zero_price_item = false;

			foreach ( $items as $item_id => $item ) {
				$product       = $item->get_product();
				$sku           = ( $product && $product->get_sku() ) ? $product->get_sku() : '—';
				$qty           = (int) $item->get_quantity();
				$line_subtotal = (float) $item->get_subtotal();
				$unit_price    = $qty > 0 ? ( $line_subtotal / $qty ) : 0;

				if ( $line_subtotal <= 0 ) {
					$has_zero_price_item = true;
				}

				$unit_price_text = $unit_price > 0 ? number_format( $unit_price ) : __( 'استعلام قیمت', 'woo-smart-quote' );
				$subtotal_text   = $line_subtotal > 0 ? number_format( $line_subtotal ) : __( 'استعلام قیمت', 'woo-smart-quote' );
				?>
				<tr>
					<td class="col-num"><?php echo esc_html( $i++ ); ?></td>
					<td class="col-sku"><?php echo esc_html( $sku ); ?></td>
					<td class="col-desc">
						<b><?php echo esc_html( $item->get_name() ); ?></b>
						<?php
						$meta_data = $item->get_formatted_meta_data( '' );
						if ( ! empty( $meta_data ) ) {
							echo '<div class="item-meta">';
							$meta_parts = array();
							foreach ( $meta_data as $meta ) {
								$meta_parts[] = esc_html( wp_strip_all_tags( $meta->display_key ) ) . ': ' . esc_html( wp_strip_all_tags( $meta->display_value ) );
							}
							echo implode( ' | ', $meta_parts );
							echo '</div>';
						}
						?>
					</td>
					<td class="col-qty"><?php echo esc_html( $qty ); ?></td>
					<td class="col-unit"><?php echo esc_html( $unit_price_text ); ?></td>
					<td class="col-total"><?php echo esc_html( $subtotal_text ); ?></td>
				</tr>
				<?php
			}
			?>
		</tbody>
	</table>

	<!-- Summary & Notes -->
	<table class="summary-table">
		<tr>
			<!-- Right side: Notes & Terms -->
			<td style="width: 56%; vertical-align: top; padding-left: 12px;">
				<?php if ( ! empty( $order->get_customer_note() ) ) : ?>
					<table class="customer-note-box"><tr><td>
						<b><?php esc_html_e( 'توضیحات و شرایط درخواستی خریدار:', 'woo-smart-quote' ); ?></b><br>
						<?php echo nl2br( esc_html( $order->get_customer_note() ) ); ?>
					</td></tr></table>
				<?php endif; ?>
				<div class="terms-box">
					<b><?php esc_html_e( 'شرایط و ضوابط عمومی:', 'woo-smart-quote' ); ?></b><br>
					• <?php esc_html_e( 'قیمت‌های درج‌شده تا پایان مهلت اعتبار پیش‌فاکتور معتبر بوده و پس از آن منوط به تأیید مجدد واحد فروش است.', 'woo-smart-quote' ); ?><br>
					• <?php esc_html_e( 'موجودی کالا در زمان قطعی شدن سفارش و واریز وجه ملاک عمل خواهد بود.', 'woo-smart-quote' ); ?>
				</div>
			</td>

			<!-- Left side: Totals -->
			<td style="width: 44%; vertical-align: top;">
				<table class="totals-table">
					<tr>
						<th><?php esc_html_e( 'جمع کل اقلام', 'woo-smart-quote' ); ?></th>
						<td>
							<?php echo $order_subtotal > 0 ? esc_html( number_format( $order_subtotal ) . $nbsp . $currency_symbol ) : esc_html__( 'نیازمند بررسی', 'woo-smart-quote' ); ?>
						</td>
					</tr>

					<?php if ( $discount_total > 0 ) : ?>
					<tr>
						<th><?php esc_html_e( 'کسر می‌شود: تخفیف', 'woo-smart-quote' ); ?></th>
						<td style="color: #dc2626;">
							<?php echo esc_html( number_format( $discount_total ) . '- ' . $currency_symbol ); ?>
						</td>
					</tr>
					<?php endif; ?>

					<?php if ( $shipping_total > 0 ) : ?>
					<tr>
						<th><?php esc_html_e( 'هزینه حمل و نقل', 'woo-smart-quote' ); ?></th>
						<td><?php echo esc_html( number_format( $shipping_total ) . $nbsp . $currency_symbol ); ?></td>
					</tr>
					<?php endif; ?>

					<?php if ( $tax_total > 0 ) : ?>
					<tr>
						<th><?php esc_html_e( 'مالیات و عوارض', 'woo-smart-quote' ); ?></th>
						<td><?php echo esc_html( number_format( $tax_total ) . $nbsp . $currency_symbol ); ?></td>
					</tr>
					<?php endif; ?>

					<tr class="grand-total">
						<th><?php esc_html_e( 'مبلغ قابل پرداخت', 'woo-smart-quote' ); ?></th>
						<td>
							<?php
							// Any unpriced line means the total is not final yet; showing a
							// partial sum as "payable" would mislead the buyer.
							if ( $has_zero_price_item ) {
								echo esc_html__( 'در انتظار قیمت‌گذاری', 'woo-smart-quote' );
							} elseif ( $order_Grandtotal > 0 ) {
								echo esc_html( number_format( $order_Grandtotal ) . $nbsp . $currency_symbol );
							} elseif ( ! empty( $total ) ) {
								echo wp_kses_post( $total );
							} else {
								echo esc_html__( 'در انتظار قیمت‌گذاری', 'woo-smart-quote' );
							}
							?>
						</td>
					</tr>
				</table>
			</td>
		</tr>
	</table>

	<!-- Signatures & Digital Seal -->
	<table class="signature-table">
		<tr>
			<td style="width: 35%; vertical-align: middle; text-align: right;">
				<div style="font-size: 9.5px; color: #334155;">
					<b><?php esc_html_e( 'مهر و امضای واحد فروش', 'woo-smart-quote' ); ?></b><br>
					<span style="font-size: 8.5px; color: #94a3b8;"><?php esc_html_e( 'تأییدیه شرایط و بهای اعلام‌شده', 'woo-smart-quote' ); ?></span>
				</div>
			</td>
			<td style="width: 30%; vertical-align: middle; text-align: center;">
				<div style="font-size: 9.5px; color: #334155;">
					<b><?php esc_html_e( 'مهر و امضای خریدار', 'woo-smart-quote' ); ?></b><br>
					<span style="font-size: 8.5px; color: #94a3b8;"><?php esc_html_e( 'تأیید مشخصات و شرایط پیش‌فاکتور', 'woo-smart-quote' ); ?></span>
				</div>
			</td>
			<td style="width: 35%; vertical-align: middle; text-align: left;">
				<?php if ( ! empty( $show_stamp ) ) : ?>
					<table class="stamp-wrap">
						<tr>
							<td class="stamp-spacer">&nbsp;</td>
							<td class="stamp-pos">
								<table class="stamp-box"><tr><td>
									<div class="stamp-title">★<?php echo esc_html( $seller_name ); ?>★</div>
									<?php if ( ! empty( $stamp_text ) ) : ?>
										<div class="stamp-sub"><b><?php echo esc_html( $stamp_text ); ?></b></div>
									<?php endif; ?>
									<div class="stamp-sub"><?php esc_html_e( 'تاریخ:', 'woo-smart-quote' ); ?> <?php echo esc_html( $date ); ?></div>
									<div class="stamp-sub"><?php esc_html_e( 'شماره سند:', 'woo-smart-quote' ); ?> #<?php echo esc_html( $order_number ); ?></div>
								</td></tr></table>
							</td>
						</tr>
					</table>
				<?php endif; ?>
			</td>
		</tr>
	</table>

	<!-- Footer -->
	<div class="footer">
		<?php if ( ! empty( $footer_text_value ) ) : ?>
			<?php echo wp_kses_post( wpautop( $footer_text_value ) ); ?>
		<?php else : ?>
			<?php
			printf(
				/* translators: %s: seller name */
				esc_html__( 'این پیش‌فاکتور به‌صورت سیستمی صادر شده و دارای کد رهگیری در سامانه فروش %s می‌باشد.', 'woo-smart-quote' ),
				'<b>' . esc_html( $seller_name ) . '</b>'
			);
			?>
		<?php endif; ?>
	</div>

</body>
</html>