<?php
/**
 * Settings-page structure check: the dashboard tab must render on its own
 * (no settings form), the settings tabs must keep their form, and the routing
 * tab must keep its dedicated form.
 *
 * Run: wp eval-file test-dashboard-page.php --path=<site>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['wsq_pass'] = 0;
$GLOBALS['wsq_fail'] = 0;
function okp( $label, $cond, $extra = '' ) {
	if ( $cond ) {
		$GLOBALS['wsq_pass']++;
		echo "PASS: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	} else {
		$GLOBALS['wsq_fail']++;
		echo "FAIL: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	}
}

function wsq_render_page( $tab = null ) {
	$_GET = array( 'page' => 'woo-smart-quote' );
	if ( null !== $tab ) {
		$_GET['tab'] = $tab;
	}

	ob_start();
	WSQ_Admin::instance()->settings_page_html();
	return ob_get_clean();
}

require_once ABSPATH . 'wp-admin/includes/template.php';
require_once WP_PLUGIN_DIR . '/woo-smart-quote/includes/class-wsq-admin.php';

$user_id = (int) get_users( array( 'role' => 'administrator', 'number' => 1, 'fields' => 'ID' ) )[0];
wp_set_current_user( $user_id );
okp( 'admin user can manage WooCommerce', current_user_can( 'manage_woocommerce' ), 'user=' . $user_id );

$dashboard = wsq_render_page( 'dashboard' );
okp( 'dashboard tab renders', false !== strpos( $dashboard, 'wsq-dashboard' ), 'len=' . strlen( $dashboard ) );
okp( 'dashboard tab is marked active', false !== strpos( $dashboard, 'nav-tab nav-tab-active' ) );
okp( 'dashboard tab shows all KPI cards', 4 === substr_count( $dashboard, 'class="wsq-kpi ' ), 'kpi=' . substr_count( $dashboard, 'class="wsq-kpi ' ) );
okp( 'dashboard tab has no settings form', false === strpos( $dashboard, '<form' ) );
okp( 'dashboard tab has no save bar', false === strpos( $dashboard, 'wsq-savebar' ) );
okp( 'dashboard tab keeps the settings header', false !== strpos( $dashboard, 'wsq-setup-progress' ) );
okp( 'dashboard tab keeps every settings tab link', 8 === substr_count( $dashboard, 'class="nav-tab ' ), 'tabs=' . substr_count( $dashboard, 'class="nav-tab ' ) );
okp( 'dashboard tab shows the pending queue table or its empty state', false !== strpos( $dashboard, 'wsq-pending-table' ) || false !== strpos( $dashboard, 'هیچ استعلامی در انتظار بررسی نیست' ) );
okp( 'active tab is announced to assistive tech', false !== strpos( $dashboard, 'nav-tab-active" aria-current="page"' ) );
okp( 'chart is a labelled group, not one opaque image', false !== strpos( $dashboard, 'class="wsq-chart" role="group"' ) );
okp( 'every chart day is keyboard focusable with a label', 14 <= substr_count( $dashboard, 'role="img" tabindex="0" aria-label=' ), 'cols=' . substr_count( $dashboard, 'role="img" tabindex="0" aria-label=' ) );
okp( 'chart columns no longer rely on title tooltips only', false === strpos( $dashboard, 'wsq-chart-col is-today" title=' ) && false === strpos( $dashboard, 'class="wsq-chart-col " title=' ) );
okp( 'icons are local inline SVGs, not emoji images', 10 <= substr_count( $dashboard, '<svg class="wsq-icon"' ), 'svg=' . substr_count( $dashboard, '<svg class="wsq-icon"' ) );
okp( 'no emoji characters are left in the dashboard markup', 0 === preg_match( '/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{FE0F}]/u', $dashboard ) );
okp( 'an unknown icon name renders nothing', '' === WSQ_Icons::get( 'does-not-exist' ) );

$default = wsq_render_page();
okp( 'the page opens on the dashboard by default', false !== strpos( $default, 'wsq-dashboard' ) );

$general = wsq_render_page( 'general' );
okp( 'settings tab still renders its form', false !== strpos( $general, 'options.php' ) );
okp( 'settings tab posts to its own group', false !== strpos( $general, 'wsq_general_group' ) );
okp( 'settings tab does not leak another tab group', false === strpos( $general, 'wsq_messengers_group' ) );
okp( 'settings tab keeps the save bar', false !== strpos( $general, 'wsq-savebar' ) );
okp( 'settings tab does not render the dashboard', false === strpos( $general, 'wsq-kpi-grid' ) );
okp( 'settings tab keeps the PDF company fields', false !== strpos( $general, 'name="wsq_company_name"' ) );
okp( 'general tab exposes registration number and retention days', false !== strpos( $general, 'name="wsq_registration_no"' ) && false !== strpos( $general, 'name="wsq_pdf_retention_days"' ) );

$bot = wsq_render_page( 'bot' );
okp( 'bot tab still renders its inbox', false !== strpos( $bot, 'wsq-inbox-toolbar' ) );
okp( 'bot tab keeps bot template fields', false !== strpos( $bot, 'name="wsq_bot_welcome"' ) );
okp( 'bot tab posts to the bot group', false !== strpos( $bot, 'wsq_bot_group' ) );
okp( 'bot inbox renders after the settings form', strpos( $bot, '</form>' ) < strpos( $bot, 'wsq-inbox-toolbar' ) );

$routing = wsq_render_page( 'routing' );
okp( 'routing tab keeps its own form', false !== strpos( $routing, 'wsq-routes-table' ) );
okp( 'routing tab saves through options.php', false !== strpos( $routing, 'options.php' ) );
okp( 'routing tab posts to the routing group', false !== strpos( $routing, 'wsq_routing_group' ) );
okp( 'routing inputs use the registered option name', false !== strpos( $routing, 'name="wsq_category_routes[' ) );

$unknown = wsq_render_page( 'evil' );
okp( 'unknown tabs fall back to the dashboard', false !== strpos( $unknown, 'wsq-dashboard' ) && false === strpos( $unknown, 'name="option_page"' ) );

foreach ( array( 'dashboard' => $dashboard, 'general' => $general, 'bot' => $bot, 'routing' => $routing ) as $name => $html ) {
	$noise = array( 'Warning:', 'Notice:', 'Deprecated:', 'Fatal error', 'PHP Fatal' );
	$found = '';
	foreach ( $noise as $needle ) {
		if ( false !== strpos( $html, $needle ) ) {
			$found = $needle;
			break;
		}
	}
	okp( "no PHP notices in the $name tab", '' === $found, $found );
}

echo "\n=== Settings page Result: {$GLOBALS['wsq_pass']} passed, {$GLOBALS['wsq_fail']} failed ===\n";
