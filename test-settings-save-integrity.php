<?php
/**
 * Settings save integrity: every option belongs to exactly the tab that
 * renders it, each tab posts its own Settings API group, the group capability
 * stays with shop managers, and sanitizers keep explicit defaults.
 *
 * Run: wp eval-file test-settings-save-integrity.php --path=<site>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['wsq_pass'] = 0;
$GLOBALS['wsq_fail'] = 0;
function oks( $label, $cond, $extra = '' ) {
	if ( $cond ) {
		$GLOBALS['wsq_pass']++;
		echo "PASS: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	} else {
		$GLOBALS['wsq_fail']++;
		echo "FAIL: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	}
}

require_once ABSPATH . 'wp-admin/includes/template.php';
require_once WP_PLUGIN_DIR . '/woo-smart-quote/includes/class-wsq-admin.php';

$user_id = (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
wp_set_current_user( $user_id );

$groups = array(
	'general'    => array(
		'group'   => 'wsq_general_group',
		'options' => array(
			'wsq_pdf_logo',
			'wsq_company_name',
			'wsq_national_id',
			'wsq_economic_code',
			'wsq_registration_no',
			'wsq_company_postal_code',
			'wsq_company_phone',
			'wsq_company_address',
			'wsq_company_bank_name',
			'wsq_company_bank_account',
			'wsq_company_bank_shaba',
			'wsq_company_bank_owner',
			'wsq_company_seal_image',
			'wsq_show_digital_stamp',
			'wsq_stamp_text',
			'wsq_company_signature_image',
			'wsq_show_digital_signature',
			'wsq_pdf_header_note',
			'wsq_pdf_validity_days',
			'wsq_pdf_retention_days',
			'wsq_pdf_footer_text',
		),
	),
	'messengers' => array(
		'group'   => 'wsq_messengers_group',
		'options' => array(
			'wsq_bale_token',
			'wsq_bale_default_chat_ids',
			'wsq_eitaa_token',
			'wsq_eitaa_default_chat_ids',
			'wsq_message_template',
		),
	),
	'buyer'      => array(
		'group'   => 'wsq_buyer_group',
		'options' => array(
			'wsq_buyer_sms_enabled',
			'wsq_buyer_sms_provider',
			'wsq_buyer_sms_apikey',
			'wsq_buyer_sms_sender',
			'wsq_buyer_sms_pattern',
			'wsq_buyer_bale_token',
		),
	),
	'bot'        => array(
		'group'   => 'wsq_bot_group',
		'options' => array(
			'wsq_polling_enabled',
			'wsq_bale_admin_chat_ids',
			'wsq_eitaa_admin_chat_ids',
			'wsq_buyer_bot_enabled',
			'wsq_bot_welcome',
			'wsq_bot_orders_header',
			'wsq_bot_order_card',
		),
	),
	'routing'    => array(
		'group'   => 'wsq_routing_group',
		'options' => array( 'wsq_category_routes' ),
	),
	'display'    => array(
		'group'   => 'wsq_display_group',
		'options' => array(
			'wsq_button_text',
			'wsq_enable_archive_button',
			'wsq_enable_floating_cart',
			'wsq_hide_add_to_cart',
		),
	),
);

$all_options = array();
foreach ( $groups as $tab ) {
	$all_options = array_merge( $all_options, $tab['options'] );
}

// Registration may already have run through plugins_loaded; calling it again
// is harmless and makes the test independent of CLI bootstrap timing.
WSQ_Admin::instance()->register_settings();

$registered = isset( $GLOBALS['wp_registered_settings'] ) ? $GLOBALS['wp_registered_settings'] : array();
foreach ( $groups as $tab => $spec ) {
	$wrong_group = array();
	$missing     = array();
	foreach ( $spec['options'] as $option ) {
		if ( ! isset( $registered[ $option ] ) ) {
			$missing[] = $option;
			continue;
		}
		if ( $registered[ $option ]['group'] !== $spec['group'] ) {
			$wrong_group[] = $option . '=>' . $registered[ $option ]['group'];
		}
	}
	oks( "$tab: every option is registered", empty( $missing ), implode( ', ', $missing ) );
	oks( "$tab: every option belongs to {$spec['group']}", empty( $wrong_group ), implode( ', ', $wrong_group ) );
	oks( "$tab: group accepts shop managers", 'manage_woocommerce' === apply_filters( 'option_page_capability_' . $spec['group'], 'manage_options' ) );
}

$wsq_prefixed = array_values(
	array_filter(
		array_keys( $registered ),
		function ( $name ) {
			return 0 === strpos( $name, 'wsq_' );
		}
	)
);
$extra = array_diff( $wsq_prefixed, $all_options );
oks( 'no option is registered outside the six groups', empty( $extra ), implode( ', ', $extra ) );
oks( 'all 44 settings options are registered', 44 === count( $all_options ) && 44 === count( array_intersect( $all_options, $wsq_prefixed ) ), 'count=' . count( array_intersect( $all_options, $wsq_prefixed ) ) );

/**
 * Render one tab and return the HTML.
 */
function oks_render( $tab ) {
	$_GET = array(
		'page' => 'woo-smart-quote',
		'tab'  => $tab,
	);
	ob_start();
	WSQ_Admin::instance()->settings_page_html();
	return ob_get_clean();
}

foreach ( $groups as $tab => $spec ) {
	$html = oks_render( $tab );
	oks( "$tab: form renders its group", false !== strpos( $html, $spec['group'] ) );
	oks( "$tab: form action is options.php", false !== strpos( $html, 'options.php' ) );

	preg_match_all( '/<(?:input|textarea|select)\b[^>]*\bname=["\']([^"\']+)["\']/i', $html, $matches );
	$posted = array();
	foreach ( $matches[1] as $name ) {
		if ( 0 === strpos( $name, 'wsq_' ) ) {
			$posted[] = preg_replace( '/\[.*$/', '', $name );
		}
	}
	$posted = array_values( array_unique( $posted ) );
	$foreign = array_diff( $posted, $spec['options'] );
	oks( "$tab: no control posts another tab's option", empty( $foreign ), implode( ', ', $foreign ) );

	if ( 'routing' !== $tab ) {
		$unrendered = array_diff( $spec['options'], $posted );
		oks( "$tab: every option in the group has a control", empty( $unrendered ), implode( ', ', $unrendered ) );
	}

	oks( "$tab: no PHP warnings in markup", ! preg_match( '/Warning:|Notice:|Fatal error/', $html ) );
}

// Sanitizers run after rendering so added settings errors cannot leak into the
// markup assertions above.
$admin = WSQ_Admin::instance();
oks( 'checkbox sanitizer stores explicit no', 'no' === $admin->sanitize_checkbox( '' ) && 'no' === $admin->sanitize_checkbox( 'no' ) );
oks( 'checkbox sanitizer keeps yes', 'yes' === $admin->sanitize_checkbox( 'yes' ) );
oks( 'text sanitizer rejects arrays', '' === $admin->sanitize_text( array( 'x' ) ) );
oks( 'textarea sanitizer rejects arrays', '' === $admin->sanitize_textarea( array( 'x' ) ) );
oks( 'chat id sanitizer rejects arrays', '' === $admin->sanitize_chat_ids( array( '1' ) ) );
oks( 'validity defaults to 7 days', 7 === $admin->sanitize_validity_days( '' ) );
oks( 'validity clamps at 365 days', 365 === $admin->sanitize_validity_days( '9999' ) );
oks( 'retention defaults to 30 days', 30 === $admin->sanitize_retention_days( '' ) );
oks( 'retention clamps at 3650 days', 3650 === $admin->sanitize_retention_days( '99999' ) );
oks( 'ippanel provider survives sanitizing', 'ippanel' === $admin->sanitize_provider( 'ippanel' ) );
oks( 'unknown provider falls back to none', 'none' === $admin->sanitize_provider( 'bogus' ) );
oks( 'scalar routes are rejected', array() === $admin->sanitize_category_routes( 'bogus' ) );
oks( 'unknown route terms are discarded', array() === $admin->sanitize_category_routes( array( 99999999 => array( 'bale' => '123' ) ) ) );

// Buyer readiness must require an enabled switch, a provider and a credential.
$previous_enabled  = get_option( 'wsq_buyer_sms_enabled', null );
$previous_provider = get_option( 'wsq_buyer_sms_provider', null );
$previous_token    = get_option( 'wsq_buyer_bale_token', null );

update_option( 'wsq_buyer_sms_enabled', 'yes' );
update_option( 'wsq_buyer_sms_provider', 'none' );
$setup = $admin->get_setup_progress();
oks( 'buyer chip stays off without a provider', false === $setup['buyer_on'] );

update_option( 'wsq_buyer_sms_provider', 'safir_bale' );
update_option( 'wsq_buyer_bale_token', '' );
$setup = $admin->get_setup_progress();
oks( 'buyer chip stays off without a credential', false === $setup['buyer_on'] );

update_option( 'wsq_buyer_bale_token', 'test-token' );
$setup = $admin->get_setup_progress();
oks( 'buyer chip turns on when fully configured', true === $setup['buyer_on'] );

foreach ( array(
	'wsq_buyer_sms_enabled'  => $previous_enabled,
	'wsq_buyer_sms_provider' => $previous_provider,
	'wsq_buyer_bale_token'   => $previous_token,
) as $option => $value ) {
	if ( null === $value ) {
		delete_option( $option );
	} else {
		update_option( $option, $value );
	}
}

echo "\n=== Settings integrity Result: {$GLOBALS['wsq_pass']} passed, {$GLOBALS['wsq_fail']} failed ===\n";
