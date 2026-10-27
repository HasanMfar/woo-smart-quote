<?php
/**
 * Quote List Template
 *
 * Displays the session quote cart (items table + request form).
 *
 * @var array $items Quote session items keyed by item key.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wsq_session = WSQ_Session::instance();
$wsq_uid     = wp_unique_id( 'wsq-' );

// The quote page stays usable after a refresh: validate the order key that the
// script stores in the URL and show the receipt instead of an empty cart.
$wsq_success_order = null;
if ( isset( $_GET['wsq_order'], $_GET['wsq_key'] ) && is_scalar( $_GET['wsq_order'] ) && is_scalar( $_GET['wsq_key'] ) ) {
	$wsq_success_id  = absint( wp_unslash( $_GET['wsq_order'] ) );
	$wsq_success_key = sanitize_text_field( wp_unslash( $_GET['wsq_key'] ) );

	if ( $wsq_success_id && '' !== $wsq_success_key ) {
		$wsq_candidate = wc_get_order( $wsq_success_id );
		if (
			$wsq_candidate
			&& '' !== (string) $wsq_candidate->get_meta( '_wsq_customer_phone' )
			&& hash_equals( (string) $wsq_candidate->get_order_key(), $wsq_success_key )
		) {
			$wsq_success_order = $wsq_candidate;
		}
	}
}

$wsq_shop_url     = wc_get_page_permalink( 'shop' );
$wsq_quote_url    = get_permalink() ? get_permalink() : home_url( '/' );
$wsq_new_quote_url = remove_query_arg( array( 'wsq_order', 'wsq_key' ), $wsq_quote_url );

// Sync PHP i18n for the channel cards (mirrors the backend whitelist).
$wsq_channel_labels = array(
	'all'   => __( 'پیامک + بله و ایتا (پیشنهادی)', 'woo-smart-quote' ),
	'bale'  => __( 'پیام‌رسان بله', 'woo-smart-quote' ),
	'eitaa' => __( 'پیام‌رسان ایتا', 'woo-smart-quote' ),
	'sms'   => __( 'فقط پیامک عادی', 'woo-smart-quote' ),
);

if ( $wsq_success_order ) {
	$wsq_order_number = $wsq_success_order->get_order_number();
	?>
	<div class="wsq-quote-wrapper">
		<section class="wsq-success-card wsq-msg-success" aria-labelledby="<?php echo esc_attr( $wsq_uid . 'success-title' ); ?>">
			<div class="wsq-success-icon-wrap" aria-hidden="true">&#10003;</div>
			<h2 class="wsq-success-title" id="<?php echo esc_attr( $wsq_uid . 'success-title' ); ?>" tabindex="-1"><?php esc_html_e( 'درخواست استعلام با موفقیت ثبت شد', 'woo-smart-quote' ); ?></h2>
			<p class="wsq-success-desc"><?php esc_html_e( 'استعلام شما با موفقیت ثبت شد. کارشناسان ما پس از بررسی، پیش‌فاکتور تفکیکی قیمت‌دار را برای شما ارسال خواهند کرد.', 'woo-smart-quote' ); ?></p>
			<div class="wsq-success-order-badge">
				<?php esc_html_e( 'کد پیگیری استعلام:', 'woo-smart-quote' ); ?>
				<span dir="ltr">#<?php echo esc_html( $wsq_order_number ); ?></span>
				<button type="button" class="wsq-copy-order-btn" data-order="<?php echo esc_attr( $wsq_order_number ); ?>" aria-label="<?php esc_attr_e( 'کپی کد پیگیری', 'woo-smart-quote' ); ?>">
					<span class="wsq-copy-text"><?php esc_html_e( 'کپی', 'woo-smart-quote' ); ?></span>
				</button>
				<span class="wsq-copy-status screen-reader-text" role="status" aria-live="polite"></span>
			</div>
			<p class="wsq-success-note"><?php esc_html_e( 'کد پیگیری را برای پیگیری وضعیت استعلام نگه دارید.', 'woo-smart-quote' ); ?></p>
			<?php if ( $wsq_success_order->get_checkout_order_received_url() ) : ?>
				<p class="wsq-success-actions">
					<a href="<?php echo esc_url( $wsq_success_order->get_checkout_order_received_url() ); ?>" class="wsq-success-action-btn"><span aria-hidden="true">&#10003;</span> <?php esc_html_e( 'مشاهده رسید استعلام', 'woo-smart-quote' ); ?></a>
					<?php if ( is_user_logged_in() ) : ?>
						<a href="<?php echo esc_url( $wsq_success_order->get_view_order_url() ); ?>" class="wsq-success-secondary-btn"><?php esc_html_e( 'مشاهده جزئیات استعلام', 'woo-smart-quote' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>
			<p class="wsq-success-secondary"><a href="<?php echo esc_url( $wsq_new_quote_url ); ?>"><?php esc_html_e( 'ثبت استعلام جدید', 'woo-smart-quote' ); ?></a></p>
		</section>
	</div>
	<?php
	return;
}

// Count valid items up-front so headings can show the live total.
$wsq_valid_items = array();
$wsq_valid_units = 0;
if ( ! empty( $items ) && is_array( $items ) ) {
	foreach ( $items as $wsq_key => $wsq_item ) {
		$wsq_product = wc_get_product( ! empty( $wsq_item['variation_id'] ) ? $wsq_item['variation_id'] : $wsq_item['product_id'] );
		if ( $wsq_product && $wsq_product->exists() ) {
			$wsq_valid_items[ $wsq_key ] = array( 'data' => $wsq_item, 'product' => $wsq_product );
			$wsq_valid_units            += isset( $wsq_item['quantity'] ) ? (int) $wsq_item['quantity'] : 0;
		}
	}
}

$wsq_initial_count = $wsq_session->get_item_count();
$wsq_initial_lines = $wsq_session->get_line_count();

$wsq_render_empty_state = function () use ( $wsq_shop_url ) {
	?>
	<div class="wsq-empty" role="status">
		<div class="wsq-empty-icon" aria-hidden="true"><?php echo WSQ_Icons::get( 'clipboard' ); ?></div>
		<h2 class="wsq-empty-title" tabindex="-1"><?php esc_html_e( 'لیست استعلام قیمت شما خالی است.', 'woo-smart-quote' ); ?></h2>
		<p><?php esc_html_e( 'هنوز محصولی را برای دریافت پیش‌فاکتور یا استعلام قیمت انتخاب نکرده‌اید.', 'woo-smart-quote' ); ?></p>
		<p class="wsq-empty-action"><a href="<?php echo esc_url( $wsq_shop_url ); ?>" class="wsq-empty-cta"><span class="wsq-empty-cta-icon" aria-hidden="true"><?php echo WSQ_Icons::get( 'bag' ); ?></span> <?php esc_html_e( 'مشاهده کاتالوگ محصولات و استعلام', 'woo-smart-quote' ); ?></a></p>
	</div>
	<?php
};

if ( empty( $wsq_valid_items ) ) {
	?>
	<div class="wsq-quote-wrapper">
		<?php $wsq_render_empty_state(); ?>
	</div>
	<?php
	return;
}

$wsq_trust_items = apply_filters(
	'wsq_trust_items',
	array(
		array(
			'icon' => 'bolt',
			'text' => __( 'پاسخ‌گویی مستقیم کارشناسان فروش', 'woo-smart-quote' ),
		),
		array(
			'icon' => 'seal',
			'text' => __( 'پیش‌فاکتور رسمی و قابل استناد', 'woo-smart-quote' ),
		),
		array(
			'icon' => 'clock',
			'text' => __( 'قیمت‌گذاری شفاف و مستند', 'woo-smart-quote' ),
		),
	)
);
?>

<div class="wsq-quote-wrapper" data-wsq-quote-root data-wsq-initial-count="<?php echo esc_attr( $wsq_initial_count ); ?>" data-wsq-initial-lines="<?php echo esc_attr( $wsq_initial_lines ); ?>" data-wsq-shop-url="<?php echo esc_url( $wsq_shop_url ); ?>">

	<span class="screen-reader-text" data-wsq-live role="status" aria-live="polite"></span>

	<ol class="wsq-steps" aria-label="<?php esc_attr_e( 'مراحل ثبت استعلام', 'woo-smart-quote' ); ?>">
		<li class="wsq-step is-now" aria-current="step"><span class="wsq-step-num" aria-hidden="true">1</span><span><?php esc_html_e( 'بازبینی اقلام', 'woo-smart-quote' ); ?><span class="wsq-steps-note"><?php esc_html_e( 'اقلام زیر را بررسی و در صورت نیاز حذف کنید.', 'woo-smart-quote' ); ?></span></span></li>
		<li class="wsq-step"><span class="wsq-step-num" aria-hidden="true">2</span><span><?php esc_html_e( 'ثبت مشخصات', 'woo-smart-quote' ); ?><span class="wsq-steps-note"><?php esc_html_e( 'نام، ایمیل و موبایل خود را وارد کنید.', 'woo-smart-quote' ); ?></span></span></li>
		<li class="wsq-step"><span class="wsq-step-num" aria-hidden="true">3</span><span><?php esc_html_e( 'دریافت پیش‌فاکتور', 'woo-smart-quote' ); ?><span class="wsq-steps-note"><?php esc_html_e( 'کارشناسان قیمت تفکیکی را برای شما ارسال می‌کنند.', 'woo-smart-quote' ); ?></span></span></li>
	</ol>

	<section class="wsq-table-card" aria-labelledby="<?php echo esc_attr( $wsq_uid . 'items-heading' ); ?>">
		<div class="wsq-table-card-head">
			<h2 class="wsq-table-card-title" id="<?php echo esc_attr( $wsq_uid . 'items-heading' ); ?>" tabindex="-1"><?php esc_html_e( 'اقلام درخواستی شما', 'woo-smart-quote' ); ?></h2>
			<div class="wsq-table-head-actions">
				<button type="button" class="wsq-clear-all-btn" data-wsq-clear-all title="<?php esc_attr_e( 'خالی کردن تمام اقلام استعلام', 'woo-smart-quote' ); ?>" aria-label="<?php esc_attr_e( 'خالی کردن تمام اقلام استعلام', 'woo-smart-quote' ); ?>"><span class="wsq-clear-icon" aria-hidden="true"><?php echo WSQ_Icons::get( 'trash' ); ?></span> <span><?php esc_html_e( 'پاک کردن همه', 'woo-smart-quote' ); ?></span></button>
				<span class="wsq-count" data-wsq-count><?php echo esc_html( sprintf( __( '%1$s قلم کالا · %2$s نوع کالا', 'woo-smart-quote' ), number_format_i18n( $wsq_initial_count ), number_format_i18n( $wsq_initial_lines ) ) ); ?></span>
			</div>
		</div>
		<div class="wsq-table-scroll" tabindex="0" role="region" aria-label="<?php esc_attr_e( 'فهرست اقلام استعلام', 'woo-smart-quote' ); ?>">
			<table class="shop_table wsq-quote-table">
				<caption class="screen-reader-text"><?php esc_html_e( 'اقلامی که برای دریافت پیش‌فاکتور انتخاب کرده‌اید.', 'woo-smart-quote' ); ?></caption>
				<thead>
					<tr>
						<th scope="col" class="product-remove"><span class="screen-reader-text"><?php esc_html_e( 'حذف', 'woo-smart-quote' ); ?></span></th>
						<th scope="col" class="product-thumbnail"><span class="screen-reader-text"><?php esc_html_e( 'تصویر', 'woo-smart-quote' ); ?></span></th>
						<th scope="col" class="product-name"><?php esc_html_e( 'شرح کالا', 'woo-smart-quote' ); ?></th>
						<th scope="col" class="product-price"><?php esc_html_e( 'قیمت پایه', 'woo-smart-quote' ); ?></th>
						<th scope="col" class="product-quantity"><?php esc_html_e( 'تعداد', 'woo-smart-quote' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wsq_valid_items as $item_key => $row ) : ?>
						<?php
						$_product   = $row['product'];
						$item       = $row['data'];
						$item_qty   = isset( $item['quantity'] ) ? (int) $item['quantity'] : 1;
						$price_html = $_product->get_price_html();
						if ( '' === $price_html ) {
							$price_html = '<span class="wsq-price-tbd">' . esc_html__( 'استعلام قیمت', 'woo-smart-quote' ) . '</span>';
						}
						?>
						<tr class="wsq-quote-item" data-wsq-row data-item_key="<?php echo esc_attr( $item_key ); ?>" data-item-key="<?php echo esc_attr( $item_key ); ?>" data-product_id="<?php echo esc_attr( ! empty( $item['product_id'] ) ? $item['product_id'] : 0 ); ?>" data-variation_id="<?php echo esc_attr( ! empty( $item['variation_id'] ) ? $item['variation_id'] : 0 ); ?>">
							<td class="product-remove" data-title="<?php esc_attr_e( 'حذف', 'woo-smart-quote' ); ?>">
								<button type="button" class="remove wsq-remove-item" aria-label="<?php echo esc_attr( sprintf( __( 'حذف %s از لیست استعلام', 'woo-smart-quote' ), wp_strip_all_tags( $_product->get_name() ) ) ); ?>" title="<?php esc_attr_e( 'حذف', 'woo-smart-quote' ); ?>"><span aria-hidden="true">&times;</span></button>
							</td>
							<td class="product-thumbnail" data-title="<?php esc_attr_e( 'تصویر', 'woo-smart-quote' ); ?>">
								<?php echo wp_kses_post( $_product->get_image( 'woocommerce_thumbnail' ) ); ?>
							</td>
							<td class="product-name" data-title="<?php esc_attr_e( 'شرح کالا', 'woo-smart-quote' ); ?>">
								<strong><?php echo wp_kses_post( $_product->get_name() ); ?></strong>
								<?php if ( ! empty( $item['variations'] ) && is_array( $item['variations'] ) ) : ?>
									<span class="wsq-item-var"><?php echo wp_kses_post( wc_get_formatted_variation( $item['variations'], true ) ); ?></span>
								<?php endif; ?>
							</td>
							<td class="product-price" data-title="<?php esc_attr_e( 'قیمت پایه', 'woo-smart-quote' ); ?>">
								<?php echo wp_kses_post( $price_html ); ?>
								<span class="wsq-item-var"><?php esc_html_e( 'قیمت نهایی توسط کارشناس اعلام می‌شود.', 'woo-smart-quote' ); ?></span>
							</td>
							<td class="product-quantity" data-title="<?php esc_attr_e( 'تعداد', 'woo-smart-quote' ); ?>">
								<div class="wsq-qty-stepper" data-wsq-stepper data-item_key="<?php echo esc_attr( $item_key ); ?>" data-item-key="<?php echo esc_attr( $item_key ); ?>" data-confirmed="<?php echo esc_attr( $item_qty ); ?>">
									<button type="button" class="wsq-qty-btn wsq-qty-dec" aria-label="<?php esc_attr_e( 'کاهش تعداد', 'woo-smart-quote' ); ?>"<?php disabled( $item_qty <= 1 ); ?>><span aria-hidden="true">−</span></button>
									<input type="text" class="wsq-qty-input" value="<?php echo esc_attr( $item_qty ); ?>" min="1" max="9999" step="1" inputmode="numeric" pattern="[0-9]*" enterkeyhint="done" autocomplete="off" aria-label="<?php echo esc_attr( sprintf( __( 'تعداد %s', 'woo-smart-quote' ), wp_strip_all_tags( $_product->get_name() ) ) ); ?>">
									<button type="button" class="wsq-qty-btn wsq-qty-inc" aria-label="<?php esc_attr_e( 'افزایش تعداد', 'woo-smart-quote' ); ?>"<?php disabled( $item_qty >= 9999 ); ?>><span aria-hidden="true">+</span></button>
								</div>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ( ! empty( $wsq_trust_items ) ) : ?>
			<div class="wsq-table-trust-bar">
				<?php foreach ( $wsq_trust_items as $wsq_trust_item ) : ?>
					<?php if ( empty( $wsq_trust_item['text'] ) ) { continue; } ?>
					<span class="wsq-trust-item"><span class="wsq-trust-icon" aria-hidden="true"><?php echo WSQ_Icons::get( ! empty( $wsq_trust_item['icon'] ) ? $wsq_trust_item['icon'] : 'seal' ); ?></span> <?php echo esc_html( $wsq_trust_item['text'] ); ?></span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>

	<section class="wsq-quote-form-wrapper" aria-labelledby="<?php echo esc_attr( $wsq_uid . 'form-heading' ); ?>">
		<div class="wsq-form-head">
			<span class="wsq-step-num" aria-hidden="true">2</span>
			<h3 id="<?php echo esc_attr( $wsq_uid . 'form-heading' ); ?>"><?php esc_html_e( 'مشخصات و ارسال درخواست استعلام', 'woo-smart-quote' ); ?></h3>
		</div>
		<p class="wsq-form-sub"><?php esc_html_e( 'پس از ثبت، کارشناسان فروش پیش‌فاکتور تفکیکی را همراه لینک پرداخت برای شما ارسال می‌کنند.', 'woo-smart-quote' ); ?></p>
		<?php
		$wsq_current_user  = is_user_logged_in() ? wp_get_current_user() : null;
		$wsq_prefill_name  = '';
		$wsq_prefill_email = '';
		$wsq_prefill_phone = '';

		if ( $wsq_current_user && $wsq_current_user->exists() ) {
			$wsq_first_name   = get_user_meta( $wsq_current_user->ID, 'billing_first_name', true );
			$wsq_last_name    = get_user_meta( $wsq_current_user->ID, 'billing_last_name', true );
			$wsq_full_name    = trim( $wsq_first_name . ' ' . $wsq_last_name );
			$wsq_prefill_name = '' !== $wsq_full_name ? $wsq_full_name : $wsq_current_user->display_name;

			$wsq_billing_email = get_user_meta( $wsq_current_user->ID, 'billing_email', true );
			$wsq_prefill_email = ! empty( $wsq_billing_email ) ? $wsq_billing_email : $wsq_current_user->user_email;

			$wsq_prefill_phone = get_user_meta( $wsq_current_user->ID, 'billing_phone', true );
		}
		?>
		<form id="<?php echo esc_attr( $wsq_uid . 'form' ); ?>" class="wsq-quote-form" data-wsq-form method="post" action="" novalidate="novalidate">
			<noscript><p class="wsq-noscript"><?php esc_html_e( 'برای ارسال درخواست استعلام باید جاوااسکریپت مرورگر فعال باشد. در صورت غیرفعال بودن، لطفاً با پشتیبانی تماس بگیرید.', 'woo-smart-quote' ); ?></p></noscript>
			<div class="wsq-form-errors" data-wsq-form-errors role="alert" tabindex="-1" hidden></div>
			<p class="form-row form-row-first wsq-field">
				<label for="<?php echo esc_attr( $wsq_uid . 'name' ); ?>"><?php esc_html_e( 'نام و نام خانوادگی', 'woo-smart-quote' ); ?> <abbr class="wsq-required" title="<?php esc_attr_e( 'ضروری', 'woo-smart-quote' ); ?>">*</abbr></label>
				<span class="wsq-input-wrap">
					<span class="wsq-field-icon" aria-hidden="true"><?php echo WSQ_Icons::get( 'user' ); ?></span>
					<input type="text" class="input-text" name="wsq_name" id="<?php echo esc_attr( $wsq_uid . 'name' ); ?>" value="<?php echo esc_attr( $wsq_prefill_name ); ?>" autocomplete="name" enterkeyhint="next" autocapitalize="words" spellcheck="false" aria-describedby="<?php echo esc_attr( $wsq_uid . 'name-err' ); ?>" required>
				</span>
				<small class="wsq-field-error" id="<?php echo esc_attr( $wsq_uid . 'name-err' ); ?>" role="alert"></small>
			</p>
			<p class="form-row form-row-last wsq-field">
				<label for="<?php echo esc_attr( $wsq_uid . 'email' ); ?>"><?php esc_html_e( 'پست الکترونیک', 'woo-smart-quote' ); ?> <abbr class="wsq-required" title="<?php esc_attr_e( 'ضروری', 'woo-smart-quote' ); ?>">*</abbr></label>
				<span class="wsq-input-wrap">
					<span class="wsq-field-icon" aria-hidden="true"><?php echo WSQ_Icons::get( 'mail' ); ?></span>
					<input type="email" class="input-text" name="wsq_email" id="<?php echo esc_attr( $wsq_uid . 'email' ); ?>" value="<?php echo esc_attr( $wsq_prefill_email ); ?>" autocomplete="email" inputmode="email" enterkeyhint="next" dir="ltr" autocapitalize="none" spellcheck="false" aria-describedby="<?php echo esc_attr( $wsq_uid . 'email-err' ); ?>" required>
				</span>
				<small class="wsq-field-error" id="<?php echo esc_attr( $wsq_uid . 'email-err' ); ?>" role="alert"></small>
			</p>
			<p class="form-row form-row-wide wsq-field">
				<label for="<?php echo esc_attr( $wsq_uid . 'phone' ); ?>"><?php esc_html_e( 'شماره موبایل', 'woo-smart-quote' ); ?> <abbr class="wsq-required" title="<?php esc_attr_e( 'ضروری', 'woo-smart-quote' ); ?>">*</abbr></label>
				<span class="wsq-input-wrap">
					<span class="wsq-field-icon" aria-hidden="true"><?php echo WSQ_Icons::get( 'phone' ); ?></span>
					<input type="tel" class="input-text wsq-phone-ltr" name="wsq_phone" id="<?php echo esc_attr( $wsq_uid . 'phone' ); ?>" value="<?php echo esc_attr( $wsq_prefill_phone ); ?>" placeholder="09123456789" autocomplete="tel" inputmode="tel" enterkeyhint="next" aria-describedby="<?php echo esc_attr( $wsq_uid . 'phone-err ' . $wsq_uid . 'phone-hint' ); ?>" required>
				</span>
				<small class="wsq-field-error" id="<?php echo esc_attr( $wsq_uid . 'phone-err' ); ?>" role="alert"></small>
				<small class="wsq-field-hint" id="<?php echo esc_attr( $wsq_uid . 'phone-hint' ); ?>"><?php esc_html_e( 'پیشنهاد قیمت تفکیکی و لینک پرداخت به این شماره (پیامک / بله / ایتا) ارسال خواهد شد.', 'woo-smart-quote' ); ?></small>
			</p>
			<fieldset class="form-row form-row-wide wsq-channel-fieldset">
				<legend><?php esc_html_e( 'نحوه دریافت پیش‌فاکتور', 'woo-smart-quote' ); ?></legend>
				<div class="wsq-channel-cards" role="radiogroup" aria-label="<?php esc_attr_e( 'نحوه دریافت پیش‌فاکتور', 'woo-smart-quote' ); ?>">
					<?php
					$wsq_channels = array(
						'all'   => array( 'icon' => 'sparkles', 'label' => $wsq_channel_labels['all'] ),
						'bale'  => array( 'icon' => 'chat', 'label' => $wsq_channel_labels['bale'] ),
						'eitaa' => array( 'icon' => 'send', 'label' => $wsq_channel_labels['eitaa'] ),
						'sms'   => array( 'icon' => 'phone', 'label' => $wsq_channel_labels['sms'] ),
					);
					$wsq_first = true;
					foreach ( $wsq_channels as $wsq_value => $wsq_meta ) :
						?>
						<label class="wsq-channel" for="<?php echo esc_attr( $wsq_uid . 'channel-' . $wsq_value ); ?>">
							<input type="radio" id="<?php echo esc_attr( $wsq_uid . 'channel-' . $wsq_value ); ?>" name="wsq_channel" value="<?php echo esc_attr( $wsq_value ); ?>" <?php checked( $wsq_first ); ?> />
							<span>
								<?php if ( 'all' === $wsq_value ) : ?>
									<span class="wsq-channel-badge"><?php esc_html_e( 'پیشنهادی', 'woo-smart-quote' ); ?></span>
								<?php endif; ?>
								<span class="wsq-channel-icon" aria-hidden="true"><?php echo WSQ_Icons::get( $wsq_meta['icon'] ); ?></span> <?php echo esc_html( $wsq_meta['label'] ); ?>
							</span>
						</label>
						<?php $wsq_first = false; ?>
					<?php endforeach; ?>
				</div>
			</fieldset>
			<p class="form-row form-row-wide">
				<label for="<?php echo esc_attr( $wsq_uid . 'message' ); ?>"><?php esc_html_e( 'توضیحات تکمیلی (اختیاری)', 'woo-smart-quote' ); ?></label>
				<textarea class="input-text" name="wsq_message" id="<?php echo esc_attr( $wsq_uid . 'message' ); ?>" rows="4" enterkeyhint="done" placeholder="<?php esc_attr_e( 'مثال: لطفاً قیمت عمده و زمان تحویل را هم اعلام کنید.', 'woo-smart-quote' ); ?>"></textarea>
			</p>
			<p class="form-row">
				<?php wp_referer_field(); ?>
				<input type="hidden" name="wsq_submit_nonce" value="<?php echo esc_attr( wp_create_nonce( 'wsq_submit_quote' ) ); ?>">
				<input type="hidden" name="action" value="wsq_submit_quote">
				<button type="submit" class="button alt wsq-submit-btn" data-wsq-submit><span class="wsq-submit-icon" aria-hidden="true"><?php echo WSQ_Icons::get( 'send' ); ?></span> <?php esc_html_e( 'ثبت و ارسال درخواست استعلام', 'woo-smart-quote' ); ?></button>
			</p>
			<div class="wsq-submit-message" data-wsq-submit-message role="status" aria-live="polite" hidden></div>
		</form>
	</section>

	<div class="wsq-sticky-bar" data-wsq-sticky hidden>
		<span class="wsq-sticky-count">
			<span class="wsq-sticky-count-num" data-wsq-sticky-count><?php echo esc_html( number_format_i18n( $wsq_initial_count ) ); ?></span>
			<span class="wsq-sticky-count-label"><?php esc_html_e( 'قلم کالا', 'woo-smart-quote' ); ?></span>
		</span>
		<button type="button" class="wsq-sticky-cta" data-wsq-sticky-cta aria-controls="<?php echo esc_attr( $wsq_uid . 'form' ); ?>"><?php esc_html_e( 'تکمیل و ارسال درخواست', 'woo-smart-quote' ); ?></button>
	</div>

	<template data-wsq-empty-template>
		<?php $wsq_render_empty_state(); ?>
	</template>
</div>
