<?php
/**
 * Verification test for Mobile Boost Support:
 * 1. Touch Target Ergonomics: Stepper +/-, remove buttons, channel pills, clear all, copy button (>= 44x44px).
 * 2. Virtual Keyboard Optimization: HTML5 input attributes (inputmode, enterkeyhint, autocomplete, pattern), Persian/Arabic digit typing tolerance.
 * 3. iOS / Mobile Zoom Prevention: font-size: 16px on mobile viewports (<768px).
 * 4. Safe Area Insets: env(safe-area-inset-bottom) & horizontal safe area insets on sticky bar, floating cart, toast, savebar.
 * 5. Mobile Card Transformation: Responsive table-to-card transformation (<640px) without horizontal scrolling.
 * 6. Mobile Admin Views: Responsive adaptation for tabs, KPI grid, chart, savebar, and form tables.
 *
 * Run: wp eval-file test-mobile-boost.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['mobile_pass'] = 0;
$GLOBALS['mobile_fail'] = 0;

function mobile_ok( $label, $cond, $extra = '' ) {
	if ( $cond ) {
		$GLOBALS['mobile_pass']++;
		echo "PASS: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	} else {
		$GLOBALS['mobile_fail']++;
		echo "FAIL: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	}
}

echo "=== Verification for Mobile Boost Support ===\n";

$plugin_dir = WP_PLUGIN_DIR . '/woo-smart-quote';
$template   = file_get_contents( $plugin_dir . '/templates/quote-list.php' );
$front_css  = file_get_contents( $plugin_dir . '/assets/css/frontend.css' );
$front_js   = file_get_contents( $plugin_dir . '/assets/js/frontend.js' );
$admin_css  = file_get_contents( $plugin_dir . '/assets/css/admin.css' );
$front_php  = file_get_contents( $plugin_dir . '/includes/class-wsq-frontend.php' );

// -------------------------------------------------------------------------
// 1. Touch Target Ergonomics (Requirement 1)
// -------------------------------------------------------------------------
mobile_ok(
	'frontend.css provides >= 44x44px touch target for stepper buttons on mobile',
	1 === preg_match( '/\.wsq-qty-btn\s*\{[^}]*width:\s*44px;[^}]*min-width:\s*44px;[^}]*min-height:\s*44px;[^}]*touch-action:\s*manipulation;/s', $front_css )
);

mobile_ok(
	'frontend.css provides >= 44x44px touch target for remove button on mobile',
	1 === preg_match( '/\.wsq-remove-item\s*\{[^}]*width:\s*44px;[^}]*height:\s*44px;[^}]*touch-action:\s*manipulation;/s', $front_css )
);

mobile_ok(
	'frontend.css provides >= 44px accessible touch area for clear all button on mobile',
	1 === preg_match( '/\.wsq-clear-all-btn\s*\{[^}]*min-width:\s*44px;[^}]*min-height:\s*44px;[^}]*touch-action:\s*manipulation;/s', $front_css )
);

mobile_ok(
	'frontend.css provides >= 44px accessible touch area for order copy button on mobile',
	1 === preg_match( '/\.wsq-copy-order-btn\s*\{[^}]*min-width:\s*44px;[^}]*min-height:\s*44px;[^}]*touch-action:\s*manipulation;/s', $front_css )
);

mobile_ok(
	'frontend.css channel pill spans have min-height 48px with touch-action manipulation',
	1 === preg_match( '/\.wsq-channel > span\s*\{[^}]*min-height:\s*48px;[^}]*touch-action:\s*manipulation;/s', $front_css )
);

mobile_ok(
	'frontend.css scopes channel card styling to its direct child so the icon and recommended badge keep their own look',
	false !== strpos( $front_css, '.wsq-channel > span {' )
	&& false === strpos( $front_css, '.wsq-channel span {' )
	&& 1 === preg_match( '/\.wsq-channel:hover > span\s*\{[^}]*border-color:/s', $front_css )
	&& 1 === preg_match( '/\.wsq-channel-badge\s*\{\s*position:\s*absolute;/s', $front_css )
);

mobile_ok(
	'frontend.css keeps the 38px icon gutter on wrapped inputs despite the form padding shorthand',
	1 === preg_match( '/\.wsq-quote-form-wrapper \.wsq-input-wrap \.input-text\s*\{\s*padding-inline-start:\s*38px;/s', $front_css )
	&& 0 === preg_match( '/^\s*\.wsq-input-wrap \.input-text\s*\{\s*padding-inline-start:/m', $front_css )
);

mobile_ok(
	'admin.css enforces >= 44px touch targets on mobile for admin action buttons, tokens, and toggles',
	false !== strpos( $admin_css, '.wsq-inbox-filter, .wsq-copy-btn, .wsq-read-btn, .wsq-read-all-btn { min-height: 44px; min-width: 44px; touch-action: manipulation; }' )
	&& false !== strpos( $admin_css, '.wsq-pending-action, .wsq-test-btn, .wsq-webhook-btn { min-height: 44px;' )
	&& false !== strpos( $admin_css, '.wsq-token { min-height: 44px;' )
	&& false !== strpos( $admin_css, '.wsq-toggle { min-height: 44px; touch-action: manipulation; }' )
);

// -------------------------------------------------------------------------
// 2. Virtual Keyboard Optimization & Numeral Typing Tolerance (Requirement 2)
// -------------------------------------------------------------------------
mobile_ok(
	'quote-list.php sets enterkeyhint="next" and autocomplete="name" on name input',
	false !== strpos( $template, 'name="wsq_name"' )
	&& false !== strpos( $template, 'autocomplete="name"' )
	&& false !== strpos( $template, 'enterkeyhint="next"' )
);

mobile_ok(
	'quote-list.php sets inputmode="email", enterkeyhint="next", and autocomplete="email" on email input',
	false !== strpos( $template, 'name="wsq_email"' )
	&& false !== strpos( $template, 'inputmode="email"' )
	&& false !== strpos( $template, 'enterkeyhint="next"' )
	&& false !== strpos( $template, 'autocomplete="email"' )
);

mobile_ok(
	'quote-list.php sets inputmode="tel", enterkeyhint="next", and autocomplete="tel" on phone input',
	false !== strpos( $template, 'name="wsq_phone"' )
	&& false !== strpos( $template, 'inputmode="tel"' )
	&& false !== strpos( $template, 'enterkeyhint="next"' )
	&& false !== strpos( $template, 'autocomplete="tel"' )
);

mobile_ok(
	'quote-list.php sets pattern="[0-9]*" and enterkeyhint="done" on quantity stepper input',
	false !== strpos( $template, 'class="wsq-qty-input"' )
	&& false !== strpos( $template, 'inputmode="numeric"' )
	&& false !== strpos( $template, 'pattern="[0-9]*"' )
	&& false !== strpos( $template, 'enterkeyhint="done"' )
);

mobile_ok(
	'frontend.js supports virtual keyboard Enter key navigation between form fields',
	false !== strpos( $front_js, "\$(document).on('keydown', '.wsq-quote-form [name=\"wsq_name\"]'" )
	&& false !== strpos( $front_js, "find('[name=\"wsq_email\"]').trigger('focus');" )
	&& false !== strpos( $front_js, "\$(document).on('keydown', '.wsq-quote-form [name=\"wsq_email\"]'" )
	&& false !== strpos( $front_js, "find('[name=\"wsq_phone\"]').trigger('focus');" )
);

mobile_ok(
	'frontend.js normalizes Persian/Arabic digits on paste for phone and quantity inputs',
	false !== strpos( $front_js, "\$(document).on('input paste', '.wsq-quote-form [name=\"wsq_phone\"]'" )
	&& false !== strpos( $front_js, "\$(document).on('input paste', '.wsq-qty-input'" )
);

mobile_ok(
	'class-wsq-frontend.php normalizes Persian and Arabic numerals in ajax_add_to_quote',
	false !== strpos( $front_php, 'ajax_add_to_quote' )
	&& false !== strpos( $front_php, "\$fa_digits  = array( '۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹' );" )
	&& false !== strpos( $front_php, "\$ar_digits  = array( '٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩' );" )
);

mobile_ok(
	'class-wsq-frontend.php normalizes Persian and Arabic numerals in ajax_update_item',
	false !== strpos( $front_php, 'ajax_update_item' )
	&& false !== strpos( $front_php, "str_replace( \$fa_digits, \$en_digits, (string) \$raw_qty )" )
);

// -------------------------------------------------------------------------
// 3. iOS / Mobile Zoom Prevention (Requirement 3)
// -------------------------------------------------------------------------
mobile_ok(
	'frontend.css enforces font-size: 16px on mobile viewports (<768px) to prevent iOS Safari auto-zoom',
	1 === preg_match( '/@media \(max-width: 768px\).*?\.wsq-quote-wrapper input\[type="text"\].*?\.wsq-qty-input\s*\{\s*font-size:\s*16px;/s', $front_css )
);

mobile_ok(
	'admin.css enforces font-size: 16px on mobile admin viewports (<782px) to prevent iOS Safari auto-zoom',
	false !== strpos( $admin_css, '@media (max-width: 782px)' )
	&& false !== strpos( $admin_css, '.wsq-admin-wrap input[type="text"]' )
	&& false !== strpos( $admin_css, 'font-size: 16px !important;' )
);

// -------------------------------------------------------------------------
// 4. Safe Area Insets (Requirement 4)
// -------------------------------------------------------------------------
mobile_ok(
	'frontend.css sticky bar includes env(safe-area-inset-bottom) and horizontal safe areas',
	false !== strpos( $front_css, '.wsq-sticky-bar {' )
	&& false !== strpos( $front_css, 'env(safe-area-inset-bottom)' )
	&& false !== strpos( $front_css, 'env(safe-area-inset-left)' )
	&& false !== strpos( $front_css, 'env(safe-area-inset-right)' )
);

mobile_ok(
	'frontend.css floating cart widget includes env(safe-area-inset-bottom) and side inset',
	false !== strpos( $front_css, '.wsq-floating-cart {' )
	&& false !== strpos( $front_css, 'bottom: calc(25px + env(safe-area-inset-bottom));' )
	&& false !== strpos( $front_css, 'inset-inline-start: calc(25px + env(safe-area-inset-left));' )
);

mobile_ok(
	'frontend.css toast region handles env(safe-area-inset-bottom)',
	false !== strpos( $front_css, '.wsq-toast-region {' )
	&& false !== strpos( $front_css, 'bottom: calc(24px + env(safe-area-inset-bottom));' )
	&& false !== strpos( $front_css, 'bottom: calc(88px + env(safe-area-inset-bottom));' )
);

mobile_ok(
	'frontend.css quote wrapper applies safe area padding at 640px',
	false !== strpos( $front_css, 'padding-bottom: calc(84px + env(safe-area-inset-bottom));' )
	&& false !== strpos( $front_css, 'padding-inline: calc(6px + env(safe-area-inset-left)) calc(6px + env(safe-area-inset-right));' )
);

mobile_ok(
	'admin.css savebar includes env(safe-area-inset-bottom) on mobile viewports',
	false !== strpos( $admin_css, '.wsq-savebar { position: sticky; bottom: 0; padding: 12px 18px calc(12px + env(safe-area-inset-bottom));' )
);

// -------------------------------------------------------------------------
// 5. Mobile Card Transformation (Requirement 5)
// -------------------------------------------------------------------------
mobile_ok(
	'frontend.css hides table header and renders rows as block containers under 768px',
	1 === preg_match( '/@media \(max-width: 768px\).*?\.wsq-quote-table thead\s*\{\s*display:\s*none;/s', $front_css )
	&& 1 === preg_match( '/\.wsq-quote-table,\s*\.wsq-quote-table tbody,\s*\.wsq-quote-table tr\s*\{\s*display:\s*block;\s*width:\s*100%;/s', $front_css )
);

mobile_ok(
	'frontend.css transforms table rows into rounded mobile cards with relative positioning',
	1 === preg_match( '/\.wsq-quote-table tr\s*\{[^}]*position:\s*relative;[^}]*border-radius:\s*12px;/s', $front_css )
);

mobile_ok(
	'frontend.css docks remove button in top corner of card with 44x44px touch area',
	1 === preg_match( '/\.wsq-quote-table td\.product-remove\s*\{[^}]*position:\s*absolute;[^}]*top:\s*8px;[^}]*inset-inline-end:\s*8px;[^}]*width:\s*44px;[^}]*height:\s*44px;/s', $front_css )
);

mobile_ok(
	'frontend.css formats product thumbnail cleanly at top of card',
	1 === preg_match( '/\.wsq-quote-table td\.product-thumbnail\s*\{[^}]*justify-content:\s*center;[^}]*border-bottom:\s*none;[^}]*padding-top:\s*4px;[^}]*padding-bottom:\s*6px;/s', $front_css )
);

mobile_ok(
	'frontend.css formats product title with padding-inline-end to avoid collision with top corner button',
	1 === preg_match( '/\.wsq-quote-table td\.product-name strong\s*\{[^}]*font-size:\s*14px;[^}]*font-weight:\s*800;[^}]*padding-inline-end:\s*44px;/s', $front_css )
);

// -------------------------------------------------------------------------
// 6. Mobile Admin Views (Requirement 6)
// -------------------------------------------------------------------------
mobile_ok(
	'admin.css adapts navigation tabs to smooth horizontal touch scrolling without wrapping',
	false !== strpos( $admin_css, '.wsq-admin-wrap .nav-tab-wrapper { flex-wrap: nowrap; overflow-x: auto; overscroll-behavior-x: contain; -webkit-overflow-scrolling: touch;' )
);

mobile_ok(
	'admin.css adapts KPI grid to single column on small screens (<=600px)',
	false !== strpos( $admin_css, '@media (max-width: 600px)' )
	&& false !== strpos( $admin_css, '.wsq-kpi-grid { grid-template-columns: 1fr; }' )
);

mobile_ok(
	'admin.css supports touch-scrolling for 14-day chart on small screens',
	false !== strpos( $admin_css, '.wsq-chart { overflow-x: auto; -webkit-overflow-scrolling: touch;' )
);

mobile_ok(
	'admin.css expands category search to 100% width on mobile with touch-friendly clear button',
	false !== strpos( $admin_css, '.wsq-cat-search-bar { max-width: 100%; }' )
	&& false !== strpos( $admin_css, '.wsq-cat-search-clear { width: 34px; height: 34px; min-width: 34px; min-height: 34px; }' )
);

mobile_ok(
	'admin.css full-widths pending queue action button on mobile screens (<=600px)',
	false !== strpos( $admin_css, '.wsq-pending-action { width: 100%; }' )
);

// -------------------------------------------------------------------------
// 7. Functional Backend Verification: Persian & Arabic Digits in AJAX Quantity Handlers
// -------------------------------------------------------------------------
add_filter( 'wp_doing_ajax', '__return_true' );
add_filter( 'wp_die_ajax_handler', function() { return function() {}; } );
add_filter( 'wp_die_handler', function() { return function() {}; } );

$session = WSQ_Session::instance();
$session->clear();

$test_products = get_posts( array(
	'post_type'      => 'product',
	'post_status'    => 'publish',
	'fields'         => 'ids',
	'posts_per_page' => 1,
) );
$test_pid      = ! empty( $test_products[0] ) ? $test_products[0] : 1;

// Test add_item with Persian digits via simulated POST
$_POST['nonce']      = wp_create_nonce( 'wsq-add-to-quote' );
$_POST['product_id'] = $test_pid;
$_POST['quantity']   = '۴'; // Persian numeral 4

ob_start();
WSQ_Frontend::instance()->ajax_add_to_quote();
$add_output = ob_get_clean();
$add_json   = json_decode( $add_output, true );

mobile_ok(
	'ajax_add_to_quote accepts Persian digits in quantity',
	! empty( $add_json['success'] ) && 4 === $session->get_item_count(),
	'count=' . $session->get_item_count()
);

// Retrieve the item key
$items    = $session->get_items();
$item_key = key( $items );

// Test update_item with Arabic digits
$_POST['item_key'] = $item_key;
$_POST['quantity'] = '٩'; // Arabic numeral 9

ob_start();
WSQ_Frontend::instance()->ajax_update_item();
$up_output = ob_get_clean();
$up_json   = json_decode( $up_output, true );

mobile_ok(
	'ajax_update_item accepts Arabic digits in quantity and updates item count',
	! empty( $up_json['success'] ) && 9 === $session->get_item_count(),
	'count=' . $session->get_item_count()
);

echo "\n=== Mobile Boost Result: {$GLOBALS['mobile_pass']} passed, {$GLOBALS['mobile_fail']} failed ===\n";
