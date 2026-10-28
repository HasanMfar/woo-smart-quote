<?php
/** Standalone quote-session regression tests; no WordPress bootstrap or external I/O. */
define( 'ABSPATH', __DIR__ . '/' );

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}

function wp_parse_args( $args, $defaults = array() ) {
	return array_merge( $defaults, $args );
}

class WSQ_Test_Memory_Session {
	public $data = array();
	public $writes = 0;

	public function get( $key, $default = null ) {
		return isset( $this->data[ $key ] ) ? $this->data[ $key ] : $default;
	}

	public function set( $key, $value ) {
		$this->data[ $key ] = $value;
		++$this->writes;
	}
}

$GLOBALS['wsq_test_wc'] = new stdClass();
function WC() {
	return $GLOBALS['wsq_test_wc'];
}

require_once __DIR__ . '/woo-smart-quote/includes/class-wsq-session.php';

$tests = 0;
$assertions = 0;
$failures = array();

function check_same( $expected, $actual, $message ) {
	global $assertions;
	++$assertions;
	if ( $expected !== $actual ) {
		throw new RuntimeException( $message );
	}
}

function row( $id, $quantity = 1, $variations = array(), $variation_id = 0 ) {
	return array(
		'product_id' => $id,
		'variation_id' => $variation_id,
		'variations' => $variations,
		'quantity' => $quantity,
	);
}

function item_key( $id ) {
	return WSQ_Session::instance()->make_key( row( $id ) );
}

function fill_rows( $session, $first, $last, $quantity = 1 ) {
	for ( $id = $first; $id <= $last; ++$id ) {
		check_same( true, $session->add_item( row( $id, $quantity ) ), 'Fixture row must be added' );
	}
}

function memory_state() {
	return array( WC()->session->data, WC()->session->writes );
}

function test_case( $name, $callback ) {
	global $tests, $failures;
	++$tests;
	WC()->session = new WSQ_Test_Memory_Session();
	try {
		$callback( WSQ_Session::instance() );
	} catch ( Throwable $error ) {
		$failures[] = $name . ': ' . $error->getMessage();
	}
}

test_case( 'add bounds and integer quantities', function ( $s ) {
	foreach ( array( -50 => 1, 0 => 1, 1 => 1, 9999 => 9999, 10000 => 9999, PHP_INT_MAX => 9999 ) as $input => $expected ) {
		$s->clear();
		check_same( true, $s->add_item( row( 1, $input ) ), 'Add succeeds for clamped input' );
		check_same( $expected, $s->get_product_quantity( 1 ), 'New quantity must be within 1..9999' );
	}
	$s->clear();
	$s->add_item( row( 1, '3.7' ) );
	check_same( 3, $s->get_items()[ item_key( 1 ) ]['quantity'], 'Quantity is stored as an integer' );
} );

test_case( 'duplicate additions cannot overflow or subtract', function ( $s ) {
	$s->add_item( row( 1, 2 ) );
	check_same( true, $s->add_item( row( 1, -20 ) ), 'Negative duplicate is clamped' );
	check_same( 3, $s->get_item_count(), 'Negative additions contribute one, not a subtraction' );
	$s->add_item( row( 1, 0 ) );
	check_same( 4, $s->get_item_count(), 'Zero additions contribute one' );
	$s->add_item( row( 1, PHP_INT_MAX ) );
	check_same( 9999, $s->get_item_count(), 'Duplicate quantity is capped before integer overflow' );
	$s->add_item( row( 1, PHP_INT_MAX ) );
	check_same( 9999, $s->get_item_count(), 'Further additions remain capped' );
} );

test_case( 'defaults and raw get/set API remain unchanged', function ( $s ) {
	check_same( true, $s->add_item( array( 'product_id' => 1 ) ), 'Default add succeeds' );
	check_same( array( item_key( 1 ) => row( 1 ) ), $s->get_items(), 'Add retains defaults' );
	$raw = array( 'original-key' => row( 2, 20000 ) );
	check_same( null, $s->set_items( $raw ), 'Setter still has no return value' );
	check_same( $raw, $s->get_items(), 'Raw setter/getter do not rekey or normalize data' );
	$s->set_items( null );
	check_same( array(), $s->get_items(), 'Non-array storage reads as empty' );
} );

test_case( 'attribute ordering produces a stable key', function ( $s ) {
	$a = row( 1, 2, array( 'attribute_size' => 'L', 'attribute_color' => 'blue' ), 10 );
	$b = row( 1, 7, array( 'attribute_color' => 'blue', 'attribute_size' => 'L' ), 10 );
	$original = $a;
	check_same( $s->make_key( $a ), $s->make_key( $b ), 'Attribute order and quantity must not change identity' );
	check_same( $original, $a, 'Key generation does not mutate caller attributes' );
	$b['variations']['attribute_size'] = 'S';
	check_same( false, $s->make_key( $a ) === $s->make_key( $b ), 'Different attribute values remain distinct' );
	$b = $a;
	$b['variation_id'] = 11;
	check_same( false, $s->make_key( $a ) === $s->make_key( $b ), 'Different variation IDs remain distinct' );
} );

test_case( 'reordered attributes merge on add and undo', function ( $s ) {
	$a = row( 1, 2, array( 'size' => 'L', 'color' => 'blue' ), 10 );
	$b = row( 1, 3, array( 'color' => 'blue', 'size' => 'L' ), 10 );
	$s->add_item( $a );
	$s->add_item( $b );
	check_same( 1, $s->get_line_count(), 'Reordered attributes must not create another row' );
	check_same( 5, $s->get_product_quantity( 1, 10 ), 'Duplicate attributes sum quantities' );
	$key = $s->make_key( $a );
	$s->remove_item( $key );
	$s->add_item( $b );
	check_same( true, $s->restore_item( $key ), 'Reordered re-add can be undone' );
	check_same( 1, $s->get_line_count(), 'Undo retains one row' );
	check_same( 8, $s->get_item_count(), 'Undo merges reordered re-add quantities' );
} );

test_case( '100-row add boundary is atomic and permits duplicates', function ( $s ) {
	fill_rows( $s, 1, 100 );
	check_same( 100, $s->get_line_count(), 'Exactly 100 rows are allowed' );
	$before = memory_state();
	check_same( false, $s->add_item( row( 101, PHP_INT_MAX ) ), 'A new 101st row is rejected' );
	check_same( $before, memory_state(), 'Rejected add performs no session writes' );
	check_same( true, $s->add_item( row( 1, 2 ) ), 'Duplicate add is allowed at capacity' );
	check_same( 100, $s->get_line_count(), 'Duplicate add uses no extra row' );
	check_same( 102, $s->get_item_count(), 'Counts include duplicate quantities' );
} );

test_case( 'remove re-add restore merges and consumes only its snapshot', function ( $s ) {
	$s->add_item( row( 1, 3 ) );
	$s->add_item( row( 2, 4 ) );
	$s->remove_item( item_key( 2 ) );
	$s->remove_item( item_key( 1 ) );
	$current = row( 1, 5 );
	$current['label'] = 'Current metadata';
	$s->add_item( $current );
	$s->add_item( row( 3, 7 ) );
	check_same( true, $s->restore_item( item_key( 1 ) ), 'Removed row is restored' );
	check_same( 8, $s->get_product_quantity( 1 ), 'Undo sums removed and current quantities' );
	check_same( 'Current metadata', $s->get_items()[ item_key( 1 ) ]['label'], 'Undo preserves current metadata' );
	check_same( 7, $s->get_product_quantity( 3 ), 'Undo retains new rows' );
	check_same( 15, $s->get_item_count(), 'Restored quantity count is correct' );
	check_same( 2, $s->get_line_count(), 'Restored line count is correct' );
	check_same( array( item_key( 2 ) => row( 2, 4 ) ), $s->get_recent(), 'Only restored snapshot is consumed' );
	$before = memory_state();
	check_same( false, $s->restore_item( item_key( 1 ) ), 'An item snapshot is consumed exactly once' );
	check_same( $before, memory_state(), 'Repeated undo performs no writes' );
	check_same( true, $s->restore_item( item_key( 2 ) ), 'Unrelated recent undo remains usable' );
} );

test_case( 'single restore at capacity fails atomically and can retry', function ( $s ) {
	fill_rows( $s, 1, 100 );
	$s->remove_item( item_key( 1 ) );
	$s->add_item( row( 101 ) );
	$before = memory_state();
	check_same( false, $s->restore_item( item_key( 1 ) ), 'Single restore cannot add a 101st row' );
	check_same( $before, memory_state(), 'Failed single restore retains current items and undo snapshot without writes' );
	$s->remove_item( item_key( 2 ) );
	check_same( true, $s->restore_item( item_key( 1 ) ), 'Single restore retries after freeing space' );
	check_same( 100, $s->get_line_count(), 'Successful retry reaches the row cap' );
	check_same( array( item_key( 2 ) => row( 2 ) ), $s->get_recent(), 'Retry consumes only its own snapshot' );
	check_same( false, $s->restore_item( item_key( 1 ) ), 'Successful retry cannot repeat' );
} );

test_case( 'overlapping single restore is allowed at capacity and capped', function ( $s ) {
	$s->add_item( row( 1, 9000 ) );
	$s->remove_item( item_key( 1 ) );
	$s->add_item( row( 1, 5000 ) );
	fill_rows( $s, 2, 100 );
	check_same( true, $s->restore_item( item_key( 1 ) ), 'Overlapping restore at capacity succeeds' );
	check_same( 9999, $s->get_product_quantity( 1 ), 'Single restore caps the merged quantity' );
	check_same( 100, $s->get_line_count(), 'Overlap uses no additional row' );
	check_same( 10098, $s->get_item_count(), 'Capped merge count includes all other rows' );
} );

test_case( 'clear new additions and full restore merge without losing recent undo', function ( $s ) {
	$s->add_item( row( 90, 2 ) );
	$s->remove_item( item_key( 90 ) );
	$s->add_item( row( 1, 3 ) );
	$s->add_item( row( 2, 4 ) );
	check_same( true, $s->clear_all(), 'Clear stores an undo snapshot' );
	check_same( 0, $s->get_item_count(), 'Clear resets quantity count' );
	check_same( 0, $s->get_line_count(), 'Clear resets line count' );
	$current = row( 1, 5 );
	$current['label'] = 'New metadata';
	$s->add_item( $current );
	$s->add_item( row( 3, 6 ) );
	$recent = $s->get_recent();
	check_same( true, $s->restore_all(), 'Full undo succeeds' );
	check_same( 8, $s->get_product_quantity( 1 ), 'Full undo sums overlapping quantities' );
	check_same( 'New metadata', $s->get_items()[ item_key( 1 ) ]['label'], 'Full undo preserves current metadata' );
	check_same( 4, $s->get_product_quantity( 2 ), 'Full undo restores cleared-only rows' );
	check_same( 6, $s->get_product_quantity( 3 ), 'Full undo preserves newly added rows' );
	check_same( 18, $s->get_item_count(), 'Merged quantity count is correct' );
	check_same( 3, $s->get_line_count(), 'Merged distinct count is correct' );
	check_same( $recent, $s->get_recent(), 'Full undo does not consume unrelated recent undo' );
	check_same( null, WC()->session->get( WSQ_Session::CLEARED_ALL_KEY ), 'Full undo consumes its snapshot' );
	$before = memory_state();
	check_same( false, $s->restore_all(), 'Full undo is consumed exactly once' );
	check_same( $before, memory_state(), 'Repeated full undo performs no writes' );
	check_same( true, $s->restore_item( item_key( 90 ) ), 'Unrelated recent undo still works' );
} );

test_case( 'full restore rejects entire union and retries without double merging', function ( $s ) {
	$s->add_item( row( 1, 5 ) );
	$s->add_item( row( 2, 7 ) );
	$s->add_item( row( 3, 9 ) );
	$s->clear_all();
	$s->add_item( row( 1, 4 ) );
	fill_rows( $s, 4, 101 );
	check_same( 99, $s->get_line_count(), 'Fixture leaves room for only one of two missing rows' );
	$before = memory_state();
	check_same( false, $s->restore_all(), 'An oversized union is rejected' );
	check_same( $before, memory_state(), 'No overlapping merge, partial insertion, snapshot consumption or session write on failure' );
	$s->remove_item( item_key( 101 ) );
	$recent = $s->get_recent();
	check_same( true, $s->restore_all(), 'Full undo retries after freeing one row' );
	check_same( 100, $s->get_line_count(), 'Retry fills exactly 100 rows' );
	check_same( 9, $s->get_product_quantity( 1 ), 'Overlap merges only once across failure and retry' );
	check_same( 7, $s->get_product_quantity( 2 ), 'First missing row restored' );
	check_same( 9, $s->get_product_quantity( 3 ), 'Second missing row restored' );
	check_same( 122, $s->get_item_count(), 'Retry quantity count retains all new rows' );
	check_same( $recent, $s->get_recent(), 'Retry leaves unrelated recent undo intact' );
	$after = memory_state();
	check_same( false, $s->restore_all(), 'Successful full retry cannot repeat' );
	check_same( $after, memory_state(), 'Consumed full retry leaves state unchanged' );
} );

test_case( '100 overlapping cleared rows fit at capacity', function ( $s ) {
	fill_rows( $s, 1, 100, 2 );
	$s->clear_all();
	fill_rows( $s, 1, 100, 3 );
	check_same( true, $s->restore_all(), 'Capacity is based on union, not sum of row counts' );
	check_same( 100, $s->get_line_count(), 'All overlapping rows remain at capacity' );
	check_same( 500, $s->get_item_count(), 'All overlapping quantities are summed' );
} );

test_case( 'single restore clamps quantities in stored snapshots', function ( $s ) {
	foreach ( array( -10 => 1, 0 => 1, PHP_INT_MAX => 9999 ) as $quantity => $expected ) {
		$s->clear();
		$s->set_items( array( item_key( 1 ) => row( 1, $quantity ) ) );
		$s->remove_item( item_key( 1 ) );
		check_same( true, $s->restore_item( item_key( 1 ) ), 'Stored snapshot can be restored' );
		check_same( $expected, $s->get_item_count(), 'Single restore normalizes stored snapshot quantities' );
	}
} );

test_case( 'full restore clamps quantities in stored snapshots', function ( $s ) {
	$s->set_items( array( item_key( 1 ) => row( 1, -10 ), item_key( 2 ) => row( 2, PHP_INT_MAX ) ) );
	$s->clear_all();
	$s->add_item( row( 3, 2 ) );
	check_same( true, $s->restore_all(), 'Stored cleared snapshot can be restored' );
	check_same( 1, $s->get_product_quantity( 1 ), 'Full restore clamps negative stored quantities' );
	check_same( 9999, $s->get_product_quantity( 2 ), 'Full restore clamps oversized stored quantities' );
	check_same( 10002, $s->get_item_count(), 'Full restore retains new items when normalizing' );
} );

test_case( 'full restore caps overlapping quantities', function ( $s ) {
	$s->add_item( row( 1, 9000 ) );
	$s->clear_all();
	$s->add_item( row( 1, 5000 ) );
	check_same( true, $s->restore_all(), 'Overlapping full restore succeeds' );
	check_same( 9999, $s->get_item_count(), 'Full restore caps merged quantities' );
} );

test_case( 'missing undo returns false without writes', function ( $s ) {
	foreach ( array( null, array(), 'invalid' ) as $snapshot ) {
		WC()->session->set( WSQ_Session::RECENT_KEY, $snapshot );
		WC()->session->set( WSQ_Session::CLEARED_ALL_KEY, $snapshot );
		$before = memory_state();
		check_same( false, $s->restore_item( item_key( 1 ) ), 'Missing recent item returns false' );
		check_same( false, $s->restore_all(), 'Missing cleared snapshot returns false' );
		check_same( $before, memory_state(), 'Missing undo does not mutate storage' );
	}
} );

test_case( 'empty clear preserves previous undo and recent counts', function ( $s ) {
	$s->add_item( row( 1, 2 ) );
	$s->remove_item( item_key( 1 ) );
	$s->add_item( row( 2, 4 ) );
	$s->clear_all();
	$before = memory_state();
	check_same( false, $s->clear_all(), 'Clearing an empty quote returns false' );
	check_same( $before, memory_state(), 'Empty clear leaves both undo snapshots intact' );
	check_same( true, $s->restore_all(), 'Previous full snapshot remains usable' );
	check_same( 4, $s->get_item_count(), 'Full undo count is restored' );
	check_same( true, $s->restore_item( item_key( 1 ) ), 'Previous recent snapshot remains usable' );
	check_same( 6, $s->get_item_count(), 'Recent undo count is restored' );
} );

test_case( 'full clear resets items and both undo snapshots', function ( $s ) {
	$s->add_item( row( 1, 2 ) );
	$s->remove_item( item_key( 1 ) );
	$s->add_item( row( 2, 3 ) );
	$s->clear_all();
	$s->add_item( row( 3, 4 ) );
	$s->clear();
	check_same( array(), $s->get_items(), 'Full clear removes current items' );
	check_same( array(), $s->get_recent(), 'Full clear removes recent undo' );
	check_same( null, WC()->session->get( WSQ_Session::CLEARED_ALL_KEY ), 'Full clear removes full undo' );
	check_same( 0, $s->get_item_count(), 'Full clear resets quantity count' );
	check_same( 0, $s->get_line_count(), 'Full clear resets line count' );
	check_same( false, $s->restore_item( item_key( 1 ) ), 'Recent undo unavailable after full clear' );
	check_same( false, $s->restore_all(), 'Full undo unavailable after full clear' );
} );

test_case( 'recent undo limit remains five', function ( $s ) {
	fill_rows( $s, 1, 6 );
	for ( $id = 1; $id <= 6; ++$id ) {
		check_same( true, $s->remove_item( item_key( $id ) ), 'Existing item removal succeeds' );
	}
	check_same( 5, count( $s->get_recent() ), 'Recent snapshots remain bounded' );
	check_same( false, $s->restore_item( item_key( 1 ) ), 'Oldest snapshot is evicted' );
	check_same( true, $s->restore_item( item_key( 2 ) ), 'Retained snapshot can be restored' );
	check_same( 4, count( $s->get_recent() ), 'Other retained snapshots remain available' );
	check_same( 1, $s->get_item_count(), 'Restored recent item contributes to count' );
} );

test_case( 'existing quantity updates and product counts remain intact', function ( $s ) {
	$s->add_item( row( 1, 2 ) );
	$s->add_item( row( 1, 3, array(), 10 ) );
	check_same( 5, $s->get_product_quantity( 1 ), 'Product count includes its variations' );
	check_same( 3, $s->get_product_quantity( 1, 10 ), 'Variation count remains selective' );
	check_same( true, $s->update_quantity( item_key( 1 ), -10 ), 'Existing update accepts clamped input' );
	check_same( 1, $s->get_items()[ item_key( 1 ) ]['quantity'], 'Update clamps low quantities' );
	$s->update_quantity( item_key( 1 ), PHP_INT_MAX );
	check_same( 9999, $s->get_items()[ item_key( 1 ) ]['quantity'], 'Update clamps high quantities' );
	check_same( false, $s->update_quantity( 'missing', 5 ), 'Missing update returns false' );
} );

test_case( 'absent WC session never reports successful add or undo', function ( $s ) {
	WC()->session = null;
	check_same( false, $s->add_item( row( 1 ) ), 'Add without session returns false' );
	check_same( false, $s->restore_item( item_key( 1 ) ), 'Single undo without session returns false' );
	check_same( false, $s->restore_all(), 'Full undo without session returns false' );
	check_same( false, $s->remove_item( item_key( 1 ) ), 'Remove without session returns false' );
	check_same( false, $s->clear_all(), 'Clear-all without session returns false' );
	check_same( false, $s->update_quantity( item_key( 1 ), 2 ), 'Update without session returns false' );
	$s->set_items( array( item_key( 1 ) => row( 1 ) ) );
	$s->set_recent( array( item_key( 1 ) => row( 1 ) ) );
	$s->clear();
	check_same( array(), $s->get_items(), 'Missing session reads as empty' );
	check_same( array(), $s->get_recent(), 'Missing session has no recent items' );
	check_same( 0, $s->get_item_count(), 'Missing session quantity count is zero' );
	check_same( 0, $s->get_line_count(), 'Missing session row count is zero' );
	unset( WC()->session );
	check_same( false, $s->add_item( row( 1 ) ), 'Unset session also rejects add' );
} );

foreach ( $failures as $failure ) {
	fwrite( STDERR, 'FAIL ' . $failure . PHP_EOL );
}
printf( "PHP %s: %d tests, %d assertions, %d passed, %d failed\n", PHP_VERSION, $tests, $assertions, $tests - count( $failures ), count( $failures ) );
exit( empty( $failures ) ? 0 : 1 );
