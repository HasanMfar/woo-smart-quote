<?php
/**
 * Router for PHP's built-in server so the local WordPress site works with
 * pretty permalinks. The document root is passed via the WSQ_DOCROOT
 * environment variable.
 */

$docroot = getenv( 'WSQ_DOCROOT' );
if ( ! $docroot ) {
	$docroot = 'C:\\Users\\Hasan\\Local Sites\\plugins\\app\\public';
}

$uri  = parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH );
$path = urldecode( (string) $uri );
$file = $docroot . $path;

// Serve existing static files as-is.
if ( '/' !== $path && file_exists( $file ) && ! is_dir( $file ) ) {
	return false;
}

// Directory with its own index.php (e.g. /wp-admin/).
if ( is_dir( $file ) ) {
	$index = rtrim( $file, '/' ) . '/index.php';
	if ( file_exists( $index ) ) {
		$_SERVER['SCRIPT_NAME'] = rtrim( $path, '/' ) . '/index.php';
		require $index;
		return true;
	}
}

// Everything else goes through WordPress.
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $docroot . '/index.php';
return true;
