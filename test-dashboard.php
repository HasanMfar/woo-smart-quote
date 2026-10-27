<?php
/**
 * Dashboard stats check: seeds a few quote orders across dates, asserts the
 * computed numbers against a baseline taken before seeding, renders the tab
 * markup, then removes the seeded orders.
 *
 * Run: wp eval-file test-dashboard.php --path=<site>
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$GLOBALS['wsq_pass'] = 0;
$GLOBALS['wsq_fail'] = 0;
function okd( $label, $cond, $extra = '' ) {
	if ( $cond ) {
		$GLOBALS['wsq_pass']++;
		echo "PASS: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	} else {
		$GLOBALS['wsq_fail']++;
		echo "FAIL: $label" . ( '' !== $extra ? " | $extra" : '' ) . "\n";
	}
}

function wsq_make_order( $status, $date_ymd, $phone, $total = 100000 ) {
	$order = wc_create_order( array( 'status' => $status ) );
	$order->set_billing_first_name( 'تست' );
	$order->set_billing_last_name( 'داشبورد' );
	$order->set_billing_email( 'dash@example.test' );
	$order->set_billing_phone( '09120000000' );
	if ( '' !== $phone ) {
		$order->update_meta_data( '_wsq_customer_phone', $phone );
	}
	$item = new WC_Order_Item_Fee();
	$item->set_name( 'Item' );
	$item->set_total( $total );
	$order->add_item( $item );
	$order->set_date_created( $date_ymd . ' 10:00:00' );
	$order->calculate_totals();
	$order->save();
	return $order;
}

$stats  = WSQ_Stats::instance();
$today  = $stats->today();
$before = $stats->get_summary();

// --- Date helpers -------------------------------------------------------
okd( 'shift_date crosses a month boundary', '2026-02-28' === $stats->shift_date( '2026-03-01', -1 ), $stats->shift_date( '2026-03-01', -1 ) );
okd( 'shift_date crosses a year boundary', '2026-01-01' === $stats->shift_date( '2025-12-31', 1 ), $stats->shift_date( '2025-12-31', 1 ) );
$week_start = $stats->week_start( $today );
okd( 'week_start is not after today', $week_start <= $today, 'week_start=' . $week_start . ' today=' . $today );
okd( 'week_start is within the last seven days', $stats->shift_date( $week_start, 7 ) > $today, 'week_start=' . $week_start );
list( $wy, $wm, $wd ) = array_map( 'intval', explode( '-', $week_start ) );
okd(
	'week_start matches the site start_of_week option',
	(int) get_option( 'start_of_week', 6 ) === (int) gmdate( 'w', gmmktime( 12, 0, 0, $wm, $wd, $wy ) ),
	'week_start=' . $week_start . ' start_of_week=' . get_option( 'start_of_week' )
);

// --- Seed --------------------------------------------------------------
$created   = array();
$yesterday = $stats->shift_date( $today, -1 );

$created[] = wsq_make_order( 'quote-request', $today, '09121111111' );      // pending, today
$created[] = wsq_make_order( 'quote-request', $today, '09122222222' );      // pending, today
$created[] = wsq_make_order( 'processing', $yesterday, '09123333333', 500000 ); // converted, yesterday
$created[] = wsq_make_order( 'processing', $today, '', 900000 );            // normal paid order: not a quote
$created[] = wsq_make_order( 'quote-request', $stats->shift_date( $today, -45 ), '09124444444' ); // outside the window

$after = $stats->get_summary();

okd( 'today counts only the two new quote requests', $before['today']['current'] + 2 === $after['today']['current'], 'before=' . $before['today']['current'] . ' after=' . $after['today']['current'] );
okd( 'a paid order without quote meta is not counted', $before['today']['current'] + 3 !== $after['today']['current'], 'after=' . $after['today']['current'] );

$window_delta = $after['conversion']['total'] - $before['conversion']['total'];
okd( 'window total grows by three (old quote excluded, normal order excluded)', 3 === $window_delta, 'delta=' . $window_delta );
okd( 'converted grows by one', $before['conversion']['converted'] + 1 === $after['conversion']['converted'], 'before=' . $before['conversion']['converted'] . ' after=' . $after['conversion']['converted'] );
okd( 'revenue adds the converted quote total', abs( ( $before['conversion']['revenue'] + 500000 ) - $after['conversion']['revenue'] ) < 0.01, 'revenue=' . $after['conversion']['revenue'] );
okd( 'processing count grows by one', $before['statuses']['processing'] + 1 === $after['statuses']['processing'], 'processing=' . $after['statuses']['processing'] );
okd( 'pending backlog grows by three', $before['pending']['total'] + 3 === $after['pending']['total'], 'before=' . $before['pending']['total'] . ' after=' . $after['pending']['total'] );
okd( 'conversion rate is converted/total', $after['conversion']['total'] > 0 && abs( $after['conversion']['rate'] - ( $after['conversion']['converted'] / $after['conversion']['total'] ) * 100 ) < 0.001, 'rate=' . round( $after['conversion']['rate'], 2 ) );

// --- Chart -------------------------------------------------------------
okd( 'chart has a 14-day series', 14 === count( $after['chart'] ), 'count=' . count( $after['chart'] ) );
okd( 'chart is ordered oldest first', $after['chart'][0]['date'] < $after['chart'][13]['date'], $after['chart'][0]['date'] . ' -> ' . $after['chart'][13]['date'] . ' (today=' . $today . ')' );

$chart_today = $after['chart'][13];
okd( 'chart last bucket is today', $today === $chart_today['date'] && $chart_today['is_today'], $chart_today['date'] );
okd( 'chart today matches the KPI card', $chart_today['total'] === $after['today']['current'], 'chart=' . $chart_today['total'] . ' kpi=' . $after['today']['current'] );

$chart_yesterday = $after['chart'][12];
okd( 'chart yesterday carries the converted quote', $yesterday === $chart_yesterday['date'] && $chart_yesterday['converted'] >= 1, 'conv=' . $chart_yesterday['converted'] );
okd( 'chart bar heights stay within range', $after['chart_max'] >= 1 );

// --- Pending queue -----------------------------------------------------
$pending = $after['pending'];
okd( 'pending rows are capped', count( $pending['orders'] ) <= WSQ_Stats::PENDING_ROWS, 'rows=' . count( $pending['orders'] ) );
$first = $pending['orders'][0];
okd( 'pending row has an edit link', '' !== (string) $first['edit_url'], $first['edit_url'] );
okd( 'pending row carries the buyer phone', '' !== (string) $first['phone'], 'phone=' . $first['phone'] );
okd( 'pending row has a day counter', null !== $first['days'], 'days=' . $first['days'] );
okd( 'oldest pending day is reported', null !== $pending['oldest_days'] && $pending['oldest_days'] >= 44, 'oldest=' . $pending['oldest_days'] );

// --- Render ------------------------------------------------------------
require_once WP_PLUGIN_DIR . '/woo-smart-quote/includes/class-wsq-admin.php';
$admin  = WSQ_Admin::instance();
$method = new ReflectionMethod( 'WSQ_Admin', 'render_dashboard_tab' );
$method->setAccessible( true );
ob_start();
$method->invoke( $admin );
$html = ob_get_clean();

okd( 'dashboard renders markup', strlen( $html ) > 2000, 'len=' . strlen( $html ) );
okd( 'dashboard has four KPI cards', 4 === substr_count( $html, 'class="wsq-kpi ' ), 'kpi=' . substr_count( $html, 'class="wsq-kpi ' ) );
okd( 'dashboard has 14 chart columns', 14 === substr_count( $html, 'wsq-chart-col' ), 'cols=' . substr_count( $html, 'wsq-chart-col' ) );
okd( 'dashboard lists five status rows', 5 === substr_count( $html, 'wsq-dist-row' ), 'rows=' . substr_count( $html, 'wsq-dist-row' ) );
okd( 'dashboard does not reuse the header chip row class', false === strpos( $html, 'wsq-status-row' ) );
okd( 'dashboard labels the conversion card', false !== strpos( $html, 'نرخ تبدیل به سفارش' ) );
okd( 'dashboard links to the pending queue', false !== strpos( $html, 'post_status=wc-quote-request' ) || false !== strpos( $html, 'status=quote-request' ), '' );
okd( 'dashboard prints no unresolved placeholders', false === strpos( $html, '%s' ) && false === strpos( $html, '%1$s' ) );
okd( 'dashboard pending table shows the seeded quote', false !== strpos( $html, '09124444444' ) || false !== strpos( $html, 'شنبه' ) );

// --- Accessibility (Web Interface Guidelines) ---------------------------
okd( 'every table header has a column scope', 6 === preg_match_all( '/<th scope="col">/u', $html ), 'th=' . preg_match_all( '/<th scope="col">/u', $html ) );
okd( 'pending table has an accessible caption', false !== strpos( $html, '<caption class="screen-reader-text">' ) );
okd( 'chart exposes a data summary, not just "chart"', false !== strpos( $html, 'نمودار استعلام‌های ۱۴ روز اخیر: در مجموع' ), '' );
okd( 'distribution rows have descriptive accessible names', 5 === preg_match_all( '/class="wsq-dist-row"[^>]*aria-label=/u', $html ), 'rows=' . preg_match_all( '/class="wsq-dist-row"[^>]*aria-label=/u', $html ) );
okd( 'decorative distribution bars are hidden from AT', false !== strpos( $html, 'wsq-dist-track is-warn" aria-hidden="true"' ), '' );
okd( 'price buttons name their quote', 6 === preg_match_all( '/aria-label="قیمت‌گذاری استعلام #/u', $html ) || false !== strpos( $html, 'aria-label="قیمت‌گذاری استعلام #' ), '' );
okd( 'delta pills keep the percentage in the accessible name', 1 === preg_match( '/aria-label="(افزایش|کاهش) [۰-۹0-9]+ درصد نسبت به دوره قبل"/u', $html ), '' );
okd( 'no three-dot ellipsis in UI copy', false === strpos( $html, '...' ), '' );
okd( 'scroll region is keyboard reachable and labelled', false !== strpos( $html, 'class="wsq-table-scroll" tabindex="0" role="region" aria-label=' ), '' );

// Keep the rendered markup next to the check so it can be eyeballed.
file_put_contents( 'F:/Programming/WordPress/Quote/ui-checks/dashboard-markup.html', $html );

// --- Clean up ----------------------------------------------------------
foreach ( $created as $order ) {
	if ( $order && ! is_wp_error( $order ) ) {
		$order->delete( true );
	}
}

echo "\n=== Dashboard Result: {$GLOBALS['wsq_pass']} passed, {$GLOBALS['wsq_fail']} failed ===\n";
