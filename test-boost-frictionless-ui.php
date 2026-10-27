<?php
/**
 * Verification test for frictionless UI & UX enhancements:
 * - Single-click "Clear All" with 1-click Undo capability in quote session & UI
 * - Instant inline validation on blur with valid/error visual cues
 * - Modern CSS SVG-like spinner on form submission
 * - 1-Click copy of quote tracking order ID with instant feedback
 * - Smart auto-focus jumping directly to the first unfilled required field
 * - Non-blocking order pricing feedback in admin metabox without browser alerts
 * - Persian/Arabic digit normalization on order metabox calculations
 *
 * Run: wp eval-file test-boost-frictionless-ui.php
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['fric_pass'] = 0;
$GLOBALS['fric_fail'] = 0;

function fric_ok( $label, $cond, $extra = '' ) {
	if ( $cond ) {
		$GLOBALS['fric_pass']++;
		echo "PASS: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	} else {
		$GLOBALS['fric_fail']++;
		echo "FAIL: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	}
}

echo "=== Verification for Frictionless UI & UX Enhancements ===\n";

// 1. Verify quote-list.php markup
$quote_template = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/templates/quote-list.php' );
fric_ok( 'quote-list.php has .wsq-clear-all-btn[data-wsq-clear-all] with trash icon',
	false !== strpos( $quote_template, 'class="wsq-clear-all-btn" data-wsq-clear-all' )
	&& false !== strpos( $quote_template, "WSQ_Icons::get( 'trash' )" )
);

fric_ok( 'quote-list.php wraps count and clear button in wsq-table-head-actions',
	false !== strpos( $quote_template, 'class="wsq-table-head-actions"' )
);

// 2. Verify frontend.js interactions
$front_js = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/assets/js/frontend.js' );
fric_ok( 'frontend.js handles [data-wsq-clear-all] click with wsq_clear_all and toast undo',
	false !== strpos( $front_js, "$(document).on('click', '[data-wsq-clear-all]'" )
	&& false !== strpos( $front_js, "action: 'wsq_clear_all'" )
	&& false !== strpos( $front_js, "action: 'wsq_restore_all'" )
);

fric_ok( 'frontend.js provides validateField helper with real-time blur event',
	false !== strpos( $front_js, 'function validateField($field)' )
	&& false !== strpos( $front_js, "$(document).on('blur', '.wsq-quote-form .input-text'" )
);

fric_ok( 'frontend.js sets .is-valid class on successfully validated fields',
	false !== strpos( $front_js, "\$wrap.removeClass('has-error').addClass('is-valid')" )
	&& false !== strpos( $front_js, "\$wrap.removeClass('is-valid').addClass('has-error')" )
);

fric_ok( 'frontend.js uses .wsq-spinner on submit button',
	false !== strpos( $front_js, '<span class="wsq-spinner" aria-hidden="true"></span>' )
);

fric_ok( 'frontend.js includes .wsq-copy-order-btn on success card and handles clipboard copy',
	false !== strpos( $front_js, 'class="wsq-copy-order-btn"' )
	&& false !== strpos( $front_js, "$(document).on('click', '.wsq-copy-order-btn'" )
);

fric_ok( 'frontend.js smartly focuses the first unfilled required input on sticky CTA click',
	false !== strpos( $front_js, "\$form.find('[required]')" )
	&& false !== strpos( $front_js, "!$.trim($(this).val())" )
);

// 3. Verify frontend.css styling
$front_css = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/assets/css/frontend.css' );
fric_ok( 'frontend.css has .wsq-clear-all-btn styling',
	false !== strpos( $front_css, '.wsq-clear-all-btn' )
);

fric_ok( 'frontend.css has .wsq-field.is-valid styling',
	false !== strpos( $front_css, '.wsq-field.is-valid .input-text' )
);

fric_ok( 'frontend.css has .wsq-spinner and keyframe animation',
	false !== strpos( $front_css, '.wsq-spinner' )
	&& false !== strpos( $front_css, '@keyframes wsqSpin' )
);

fric_ok( 'frontend.css has .wsq-copy-order-btn styling',
	false !== strpos( $front_css, '.wsq-copy-order-btn' )
);

// 4. Verify class-wsq-frontend.php AJAX registrations
$front_php = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/includes/class-wsq-frontend.php' );
fric_ok( 'class-wsq-frontend.php registers ajax_clear_all and ajax_restore_all',
	false !== strpos( $front_php, 'ajax_clear_all' )
	&& false !== strpos( $front_php, 'ajax_restore_all' )
);

// 5. Verify order metabox in class-wsq-order.php
$order_php = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/includes/class-wsq-order.php' );
fric_ok( 'class-wsq-order.php replaces blocking alert with non-blocking feedback for copy proposal',
	false !== strpos( $order_php, 'window.wsqCopyText' )
	&& false !== strpos( $order_php, "'copy_failed'" )
);

fric_ok( 'class-wsq-order.php includes in-place error notice container',
	false !== strpos( $order_php, 'id="wsq-pricing-error-notice"' )
);

fric_ok( 'class-wsq-order.php normalizes digits in metabox recalculateTotals',
	false !== strpos( $order_php, 'function normalizeDigits(str)' )
	&& false !== strpos( $order_php, 'var raw = normalizeDigits($(this).val());' )
);

// 6. Functional Backend Session Test: clear_all and restore_all
$session  = WSQ_Session::instance();
$products = wc_get_products( array( 'limit' => 2, 'return' => 'ids' ) );
$pid1     = ! empty( $products[0] ) ? $products[0] : 1;
$pid2     = ! empty( $products[1] ) ? $products[1] : 2;

$session->clear();
$session->add_item( array( 'product_id' => $pid1, 'quantity' => 2 ) );
$session->add_item( array( 'product_id' => $pid2, 'quantity' => 3 ) );

$count_before = $session->get_item_count();
fric_ok( 'Session has initial items seeded', 5 === $count_before, "count=$count_before" );

$cleared = $session->clear_all();
fric_ok( 'Session clear_all() returns true', true === $cleared );
$count_cleared = $session->get_item_count();
fric_ok( 'Session is empty after clear_all()', 0 === $count_cleared, "count=$count_cleared" );

$restored = $session->restore_all();
fric_ok( 'Session restore_all() returns true', true === $restored );
$count_restored = $session->get_item_count();
fric_ok( 'Session count restored to original value', 5 === $count_restored, "count=$count_restored" );

// Test clear_all on empty session
$session->clear();
fric_ok( 'Session clear_all() on empty session returns false', false === $session->clear_all() );

// 7. Verify unified validation & copy fallback in frontend.js
fric_ok( 'frontend.js unifies validateForm to call validateField for all fields',
	false !== strpos( $front_js, 'function validateForm($form)' )
	&& false !== strpos( $front_js, '!validateField($field)' )
);

fric_ok( 'frontend.js replaces window.alert with non-blocking toast in remove-item',
	false === strpos( $front_js, 'window.alert(' )
);

fric_ok( 'frontend.js provides robust copyText with execCommand fallback',
	false !== strpos( $front_js, 'function copyText(text, callback)' )
	&& false !== strpos( $front_js, 'function fallbackCopy(text)' )
	&& false !== strpos( $front_js, "document.execCommand('copy')" )
);

fric_ok( 'frontend.js restores rows and empty state on undo',
	false !== strpos( $front_js, 'function restoreAllRows($wrapper, $rows)' )
	&& false !== strpos( $front_js, "hideEmptyState(\$wrapper);" )
	&& false !== strpos( $front_js, "\$wrapper.find('.wsq-quote-table tbody').append(\$rows);" )
);

fric_ok( 'frontend.js pre-validates prefilled inputs on wrapper init',
	false !== strpos( $front_js, '.wsq-quote-form [name="wsq_name"]' )
	&& false !== strpos( $front_js, 'markFieldValid($field);' )
);

// 8. Verify enhanced CSS styling
fric_ok( 'frontend.css animates .wsq-btn-icon on button loading',
	false !== strpos( $front_css, '.wsq-add-to-quote-button.loading .wsq-btn-icon' )
);

fric_ok( 'frontend.css has .wsq-step.is-done completed styling',
	false !== strpos( $front_css, '.wsq-step.is-done' )
);

fric_ok( 'frontend.css has 480px responsive table-head-actions layout',
	false !== strpos( $front_css, '@media (max-width: 480px)' )
	&& false !== strpos( $front_css, '.wsq-table-head-actions' )
);

// 9. Verify admin routing savebar
$admin_php = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/includes/class-wsq-admin.php' );
fric_ok( 'class-wsq-admin.php includes wsq-savebar in Routing tab',
	substr_count( $admin_php, 'class="wsq-savebar"' ) >= 2
	&& false !== strpos( $admin_php, 'قوانین مسیریابی برای کارشناسان و گروه‌های بله و ایتا تنظیم می‌شود.' )
);

$admin_css = file_get_contents( WP_PLUGIN_DIR . '/woo-smart-quote/assets/css/admin.css' );
fric_ok( 'admin.css supports .rtl toggle thumb position',
	false !== strpos( $admin_css, '.rtl .wsq-toggle input:checked + .wsq-toggle-track .wsq-toggle-thumb' )
);

echo "\n=== Frictionless UI & UX Result: {$GLOBALS['fric_pass']} passed, {$GLOBALS['fric_fail']} failed ===\n";
