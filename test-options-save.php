<?php
/**
 * End-to-end settings save isolation: submit each tab through the real
 * wp-admin/options.php handler and prove that no other tab's options change.
 *
 * Run: wp eval-file test-options-save.php --path=<site>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once WP_PLUGIN_DIR . '/woo-smart-quote/includes/class-wsq-admin.php';

$GLOBALS['wsq_pass'] = 0;
$GLOBALS['wsq_fail'] = 0;
function okv( $label, $cond, $extra = '' ) {
	if ( $cond ) {
		$GLOBALS['wsq_pass']++;
		echo "PASS: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	} else {
		$GLOBALS['wsq_fail']++;
		echo "FAIL: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	}
}

$user_id = (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
wp_set_current_user( $user_id );

// admin_init does not fire under WP-CLI; register the groups explicitly.
WSQ_Admin::instance()->register_settings();

$tabs = array(
	'general'    => array(
		'group'  => 'wsq_general_group',
		'fields' => array(
			'wsq_pdf_logo'            => 'https://example.test/logo.png',
			'wsq_company_name'        => 'شرکت آزمون',
			'wsq_national_id'         => '10320049182',
			'wsq_economic_code'       => '411376958214',
			'wsq_registration_no'     => '543210',
			'wsq_company_phone'       => '02112345678',
			'wsq_company_address'     => 'تهران، خیابان آزمون',
			'wsq_show_digital_stamp'  => 'yes',
			'wsq_stamp_text'          => 'تأیید شد',
			'wsq_pdf_header_note'     => 'سربرگ آزمون',
			'wsq_pdf_validity_days'   => '10',
			'wsq_pdf_retention_days'  => '45',
			'wsq_pdf_footer_text'     => 'پانویس آزمون',
		),
	),
	'messengers' => array(
		'group'  => 'wsq_messengers_group',
		'fields' => array(
			'wsq_bale_token'              => 'bale-token-new',
			'wsq_bale_default_chat_ids'   => "111\n222",
			'wsq_eitaa_token'             => 'eitaa-token-new',
			'wsq_eitaa_default_chat_ids'  => '333',
			'wsq_message_template'        => 'template-new',
		),
	),
	'buyer'      => array(
		'group'  => 'wsq_buyer_group',
		'fields' => array(
			'wsq_buyer_sms_enabled'   => 'yes',
			'wsq_buyer_sms_provider'  => 'ippanel',
			'wsq_buyer_sms_apikey'    => 'api-key-new',
			'wsq_buyer_sms_sender'    => '+983000505',
			'wsq_buyer_sms_pattern'   => 'pattern-new',
			'wsq_buyer_bale_token'    => 'buyer-bale-token-new',
		),
	),
	'bot'        => array(
		'group'  => 'wsq_bot_group',
		'fields' => array(
			'wsq_polling_enabled'      => 'yes',
			'wsq_bale_admin_chat_ids'  => '444',
			'wsq_eitaa_admin_chat_ids' => '555',
			'wsq_buyer_bot_enabled'    => 'yes',
			'wsq_bot_welcome'          => 'welcome-new',
			'wsq_bot_orders_header'    => 'header-new',
			'wsq_bot_order_card'       => 'card-new',
		),
	),
	'routing'    => array(
		'group'  => 'wsq_routing_group',
		'fields' => array( 'wsq_category_routes' => array() ),
	),
	'display'    => array(
		'group'  => 'wsq_display_group',
		'fields' => array(
			'wsq_button_text'             => 'button-new',
			'wsq_enable_archive_button'   => 'no',
			'wsq_enable_floating_cart'    => 'yes',
			'wsq_hide_add_to_cart'        => 'no',
		),
	),
);

$initial = array(
	'wsq_pdf_logo'               => 'https://example.test/old.png',
	'wsq_company_name'           => 'شرکت قدیمی',
	'wsq_national_id'            => '11111111111',
	'wsq_economic_code'          => '222222222222',
	'wsq_registration_no'        => '111111',
	'wsq_company_phone'          => '02100000000',
	'wsq_company_address'        => 'نشانی قدیمی',
	'wsq_show_digital_stamp'     => 'no',
	'wsq_stamp_text'             => 'مهر قدیمی',
	'wsq_pdf_header_note'        => 'سربرگ قدیمی',
	'wsq_pdf_validity_days'      => 7,
	'wsq_pdf_retention_days'     => 30,
	'wsq_pdf_footer_text'        => 'پانویس قدیمی',
	'wsq_bale_token'             => 'bale-token-old',
	'wsq_bale_default_chat_ids'  => "999",
	'wsq_eitaa_token'            => 'eitaa-token-old',
	'wsq_eitaa_default_chat_ids' => '888',
	'wsq_message_template'       => 'template-old',
	'wsq_buyer_sms_enabled'      => 'no',
	'wsq_buyer_sms_provider'     => 'none',
	'wsq_buyer_sms_apikey'       => 'api-key-old',
	'wsq_buyer_sms_sender'       => '+983000000',
	'wsq_buyer_sms_pattern'      => 'pattern-old',
	'wsq_buyer_bale_token'       => 'buyer-bale-token-old',
	'wsq_polling_enabled'        => 'no',
	'wsq_bale_admin_chat_ids'    => '777',
	'wsq_eitaa_admin_chat_ids'   => '666',
	'wsq_buyer_bot_enabled'      => 'no',
	'wsq_bot_welcome'            => 'welcome-old',
	'wsq_bot_orders_header'      => 'header-old',
	'wsq_bot_order_card'         => 'card-old',
	'wsq_category_routes'        => array(),
	'wsq_button_text'            => 'button-old',
	'wsq_enable_archive_button'  => 'yes',
	'wsq_enable_floating_cart'   => 'yes',
	'wsq_hide_add_to_cart'       => 'no',
);

$all_options = array_keys( $initial );
$originals   = array();
foreach ( $all_options as $option ) {
	$originals[ $option ] = get_option( $option, '__wsq_missing__' );
	update_option( $option, $initial[ $option ] );
}
delete_transient( 'settings_errors' );

/**
 * Replicate the wp-admin/options.php save loop for one option group.
 *
 * Core iterates every option registered to the submitted group and calls
 * update_option() with null when the field was not posted. Emulating that
 * loop here catches the exact data-loss regression: if a tab's options were
 * registered to a shared group, saving one tab would null every other tab.
 *
 * @return string|false Redirect location, false when capability/nonce fails.
 */
function wsq_submit_options( $group, $fields ) {
	global $wp_registered_settings;

	$nonce    = wp_create_nonce( $group . '-options' );
	$cap      = apply_filters( 'option_page_capability_' . $group, 'manage_options' );
	$tab      = str_replace( '_group', '', str_replace( 'wsq_', '', $group ) );
	$referer  = 'http://example.test/wp-admin/admin.php?page=woo-smart-quote&tab=' . $tab;
	$_SERVER['REQUEST_METHOD'] = 'POST';

	if ( ! wp_verify_nonce( $nonce, $group . '-options' ) || ! current_user_can( $cap ) ) {
		return false;
	}

	$options = array();
	foreach ( $wp_registered_settings as $option => $args ) {
		if ( isset( $args['group'] ) && $group === $args['group'] ) {
			$options[] = $option;
		}
	}
	if ( empty( $options ) ) {
		return false;
	}

	foreach ( $options as $option ) {
		$value = null;
		if ( isset( $fields[ $option ] ) ) {
			$value = $fields[ $option ];
			if ( ! is_array( $value ) ) {
				$value = trim( $value );
			}
			$value = wp_unslash( $value );
		}
		update_option( $option, $value );
	}

	if ( ! count( get_settings_errors() ) ) {
		add_settings_error( 'general', 'settings_updated', __( 'Settings saved.' ), 'success' );
	}
	set_transient( 'settings_errors', get_settings_errors(), 30 );

	return add_query_arg( 'settings-updated', 'true', $referer );
}

foreach ( $tabs as $tab => $spec ) {
	$snapshot = array();
	foreach ( $all_options as $option ) {
		$snapshot[ $option ] = get_option( $option, '__wsq_missing__' );
	}

	$redirect = wsq_submit_options( $spec['group'], $spec['fields'] );
	okv( "$tab: save redirects with settings-updated", is_string( $redirect ) && false !== strpos( $redirect, 'settings-updated=true' ), is_string( $redirect ) ? $redirect : 'no redirect' );
	okv( "$tab: redirect returns to the same tab", is_string( $redirect ) && false !== strpos( $redirect, 'tab=' . $tab ) );

	$changed = array();
	foreach ( array_keys( $spec['fields'] ) as $option ) {
		$now = get_option( $option, '__wsq_missing__' );
		if ( 'wsq_category_routes' === $option ) {
			if ( ! is_array( $now ) ) {
				$changed[] = $option . ' is not an array';
			}
			continue;
		}
		if ( 'yes' === $spec['fields'][ $option ] || 'no' === $spec['fields'][ $option ] ) {
			if ( $now !== $spec['fields'][ $option ] ) {
				$changed[] = $option . '=>' . var_export( $now, true );
			}
			continue;
		}
		if ( is_numeric( $spec['fields'][ $option ] ) ) {
			if ( (string) $now !== (string) $spec['fields'][ $option ] ) {
				$changed[] = $option . '=>' . var_export( $now, true );
			}
			continue;
		}
		if ( $now !== $spec['fields'][ $option ] ) {
			$changed[] = $option . '=>' . var_export( $now, true );
		}
	}
	okv( "$tab: submitted values are stored", empty( $changed ), implode( ', ', $changed ) );

	$leaked = array();
	foreach ( $all_options as $option ) {
		if ( array_key_exists( $option, $spec['fields'] ) ) {
			continue;
		}
		$now = get_option( $option, '__wsq_missing__' );
		if ( $now !== $snapshot[ $option ] ) {
			$leaked[] = $option . '=' . var_export( $now, true );
		}
	}
	okv( "$tab: all other tab options are untouched", empty( $leaked ), implode( ', ', $leaked ) );
}

// An unchecked checkbox must store an explicit "no", not null/''.
$display_fields = $tabs['display']['fields'];
$display_fields['wsq_enable_floating_cart'] = 'no';
wsq_submit_options( 'wsq_display_group', $display_fields );
okv( 'unchecked checkbox stores explicit no', 'no' === get_option( 'wsq_enable_floating_cart' ), var_export( get_option( 'wsq_enable_floating_cart' ), true ) );

// Out-of-range numbers clamp and surface a settings error.
$general_fields = $tabs['general']['fields'];
$general_fields['wsq_pdf_validity_days'] = '9999';
wsq_submit_options( 'wsq_general_group', $general_fields );
okv( 'validity clamps to 365', '365' === (string) get_option( 'wsq_pdf_validity_days' ), var_export( get_option( 'wsq_pdf_validity_days' ), true ) );

$errors = get_transient( 'settings_errors' );
$codes  = array();
if ( is_array( $errors ) ) {
	foreach ( $errors as $error ) {
		$codes[] = isset( $error['code'] ) ? $error['code'] : '';
	}
}
okv( 'clamping adds a visible settings error', in_array( 'wsq_pdf_validity_days_clamped', $codes, true ), implode( ', ', $codes ) );

// Restore the dev site to its pre-test state.
foreach ( $originals as $option => $value ) {
	if ( '__wsq_missing__' === $value ) {
		delete_option( $option );
	} else {
		update_option( $option, $value );
	}
}
delete_transient( 'settings_errors' );

echo "\n=== Options save isolation Result: {$GLOBALS['wsq_pass']} passed, {$GLOBALS['wsq_fail']} failed ===\n";
