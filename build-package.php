<?php
/**
 * Builds the delivery zip for WooCommerce Smart Quote.
 *
 * Only the plugin folder is packaged. Development files (reference templates,
 * unused font duplicates, static-analysis configs of the vendored mPDF) are
 * stripped, and the archive root is the `woo-smart-quote/` folder so that
 * WordPress installs it as a plugin.
 *
 * Run: php -c wp-cli.ini build-package.php [version]
 * Default version: read from WSQ_VERSION in woo-smart-quote.php
 */

$root = __DIR__;
$src  = $root . '/woo-smart-quote';

if ( ! is_dir( $src ) ) {
	fwrite( STDERR, "Source folder not found: $src\n" );
	exit( 1 );
}

// Version: argument wins, otherwise read from the plugin header.
$version = isset( $argv[1] ) ? trim( $argv[1] ) : '';
if ( '' === $version ) {
	$header = file_get_contents( $src . '/woo-smart-quote.php' );
	if ( preg_match( "/define\(\s*'WSQ_VERSION',\s*'([^']+)'/", $header, $m ) ) {
		$version = $m[1];
	}
}
if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
	fwrite( STDERR, "Could not resolve a valid version (got: '$version')\n" );
	exit( 1 );
}

// Path patterns (relative to the plugin root) that must not ship.
$excluded_paths = array(
	'templates/pdf/gemini-code-',
	'assets/fonts/',
	'assets/images/marketplace-banner-',
	'assets/images/marketplace-cover-',
	'assets/images/mazanneh-logo-3d.jpg',
	'assets/images/logo-3d.jpg',
	'lib/mpdf/vendor/mpdf/mpdf/.github/',
	'lib/mpdf/vendor/mpdf/mpdf/.gitignore',
	'lib/mpdf/vendor/mpdf/mpdf/phpstan',
	'lib/mpdf/vendor/mpdf/mpdf/phpunit.xml',
	'lib/mpdf/vendor/mpdf/mpdf/ruleset.xml',
	'lib/mpdf/vendor/mpdf/mpdf/tmp/.gitignore',
	'lib/mpdf/vendor/mpdf/psr-log-aware-trait/.gitignore',
	'lib/mpdf/vendor/paragonie/random_compat/psalm',
	'lib/mpdf/vendor/setasign/fpdi/vendor-bin/',
);

$excluded_files = array();
$added          = 0;
$added_bytes    = 0;

$zip_name = $root . '/woo-smart-quote-' . $version . '.zip';
if ( file_exists( $zip_name ) ) {
	unlink( $zip_name );
}

if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "Error: PHP ZipArchive extension is required to build the release zip package.\n" );
	fwrite( STDERR, "Run via: powershell -File build-package.ps1\n" );
	exit( 1 );
}

$zip = new ZipArchive();
if ( true !== $zip->open( $zip_name, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Could not create archive: $zip_name\n" );
	exit( 1 );
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);

foreach ( $iterator as $item ) {
	$path = $item->getPathname();
	$rel  = str_replace( '\\', '/', substr( $path, strlen( $src ) + 1 ) );

	$skip = false;
	foreach ( $excluded_paths as $pattern ) {
		if ( 0 === strpos( $rel, $pattern ) ) {
			$skip = true;
			break;
		}
	}
	// Any leftover VCS / OS noise.
	if ( preg_match( '/(^|\/)(\.git|\.svn|__MACOSX|\.DS_Store|Thumbs\.db)/', $rel ) ) {
		$skip = true;
	}
	if ( preg_match( '/\.(log|bak|orig|rej)$/i', $rel ) ) {
		$skip = true;
	}

	if ( $skip ) {
		if ( $item->isFile() ) {
			$excluded_files[] = $rel;
		}
		continue;
	}

	if ( $item->isFile() ) {
		$zip->addFile( $path, 'woo-smart-quote/' . $rel );
		$added++;
		$added_bytes += $item->getSize();
	}
}

$zip->close();

printf(
	"Built %s\n  files added:    %d\n  bytes (raw):    %s\n  files excluded: %d\n  archive size:   %s\n",
	basename( $zip_name ),
	$added,
	number_format( $added_bytes ),
	count( $excluded_files ),
	number_format( filesize( $zip_name ) )
);

if ( $excluded_files ) {
	echo "  excluded:\n";
	foreach ( $excluded_files as $file ) {
		echo "    - $file\n";
	}
}
