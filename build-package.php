<?php
/**
 * Builds the delivery zip for WooCommerce Smart Quote.
 *
 * Only the plugin folder is packaged. Development files (reference templates,
 * unused font duplicates, static-analysis configs of the vendored mPDF) are
 * stripped, and the archive root is the `woo-smart-quote/` folder so that
 * WordPress installs it as a plugin.
 *
 * Run: php -c wp-cli.ini build-package.php [version] [output-directory]
 * Default version: WSQ_VERSION in woo-smart-quote.php, checked against the
 * plugin header and readme stable tag. Default output: this directory.
 */

$root    = __DIR__;
$src     = $root . '/woo-smart-quote';
$version = isset( $argv[1] ) ? trim( $argv[1] ) : '';
$out_dir = isset( $argv[2] ) ? rtrim( str_replace( '\\', '/', $argv[2] ), '/' ) : $root;

if ( ! is_dir( $src ) ) {
	fwrite( STDERR, "Source folder not found: $src\n" );
	exit( 1 );
}
if ( '' === $out_dir || ! is_dir( $out_dir ) || ! is_writable( $out_dir ) ) {
	fwrite( STDERR, "Output directory is missing or not writable: $out_dir\n" );
	exit( 1 );
}
if ( ! class_exists( 'ZipArchive' ) ) {
	fwrite( STDERR, "PHP ZipArchive extension is required. Run via: powershell -File build-package.ps1\n" );
	exit( 1 );
}

$entry_file = $src . '/woo-smart-quote.php';
$readme     = $src . '/readme.txt';
$entry      = is_file( $entry_file ) ? (string) file_get_contents( $entry_file ) : '';
if ( '' === $entry || ! is_file( $readme ) ) {
	fwrite( STDERR, "Plugin entrypoint or readme is missing.\n" );
	exit( 1 );
}

$defined = preg_match( "/define\(\s*'WSQ_VERSION',\s*'([^']+)'/", $entry, $m ) ? $m[1] : '';
$header  = preg_match( '/^\s*\*?\s*Version:\s*(\S+)\s*$/mi', $entry, $m ) ? $m[1] : '';
$stable  = preg_match( '/^Stable tag:\s*(\S+)\s*$/mi', (string) file_get_contents( $readme ), $m ) ? $m[1] : '';

if ( '' !== $version && $version !== $defined ) {
	fwrite( STDERR, "Requested version '$version' does not match WSQ_VERSION '$defined'. Bump the source first.\n" );
	exit( 1 );
}
if ( '' === $defined || $defined !== $header || $defined !== $stable ) {
	fwrite( STDERR, "Version metadata disagrees (define: '$defined', header: '$header', stable tag: '$stable').\n" );
	exit( 1 );
}
$version = $defined;
if ( ! preg_match( '/^\d+\.\d+\.\d+$/', $version ) ) {
	fwrite( STDERR, "Invalid version: '$version'\n" );
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
	'lib/mpdf/vendor/psr-log-aware-trait/.gitignore',
	'lib/mpdf/vendor/paragonie/random_compat/psalm',
	'lib/mpdf/vendor/setasign/fpdi/vendor-bin/',
);

$zip_name = $out_dir . '/woo-smart-quote-' . $version . '.zip';
$tmp_name = $zip_name . '.building-' . getmypid();

$added       = 0;
$added_bytes = 0;
$excluded    = array();

$zip = new ZipArchive();
if ( true !== $zip->open( $tmp_name, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Could not create archive: $tmp_name\n" );
	exit( 1 );
}

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator( $src, FilesystemIterator::SKIP_DOTS ),
	RecursiveIteratorIterator::SELF_FIRST
);

$failed = '';
foreach ( $iterator as $item ) {
	$path = $item->getPathname();
	$rel  = str_replace( '\\', '/', substr( $path, strlen( $src ) + 1 ) );
	if ( '' === $rel ) {
		continue;
	}

	$skip = false;
	foreach ( $excluded_paths as $pattern ) {
		if ( 0 === strpos( $rel, $pattern ) ) {
			$skip = true;
			break;
		}
	}
	if ( preg_match( '/(^|\/)(\.git|\.svn|__MACOSX|\.DS_Store|Thumbs\.db)/', $rel ) ) {
		$skip = true;
	}
	if ( preg_match( '/\.(log|bak|orig|rej)$/i', $rel ) ) {
		$skip = true;
	}

	if ( $skip ) {
		if ( $item->isFile() ) {
			$excluded[] = $rel;
		}
		continue;
	}

	if ( $item->isFile() && ! $zip->addFile( $path, 'woo-smart-quote/' . $rel ) ) {
		$failed = $rel;
		break;
	}
	if ( $item->isFile() ) {
		$added++;
		$added_bytes += (int) $item->getSize();
	}
}

if ( '' !== $failed || ! $zip->close() ) {
	@$zip->close();
	@unlink( $tmp_name );
	fwrite( STDERR, 'Archive build failed' . ( '' === $failed ? '.' : ' while adding: ' . $failed ) . "\n" );
	exit( 1 );
}

// Re-open what was written and prove the archive is complete before replacing
// any previously built file.
$verify = new ZipArchive();
if ( true !== $verify->open( $tmp_name, ZipArchive::CHECKCONS ) || $verify->numFiles !== $added ) {
	@$verify->close();
	@unlink( $tmp_name );
	fwrite( STDERR, "Archive verification failed (expected $added entries).\n" );
	exit( 1 );
}
$has_entry = false;
for ( $i = 0; $i < $verify->numFiles; $i++ ) {
	$name = $verify->getNameIndex( $i );
	if ( ! is_string( $name ) || 0 !== strpos( $name, 'woo-smart-quote/' ) ) {
		$verify->close();
		@unlink( $tmp_name );
		fwrite( STDERR, "Archive contains an unexpected entry: " . (string) $name . "\n" );
		exit( 1 );
	}
	if ( 'woo-smart-quote/woo-smart-quote.php' === $name ) {
		$has_entry = true;
	}
}
$verify->close();
if ( ! $has_entry ) {
	@unlink( $tmp_name );
	fwrite( STDERR, "Archive is missing the plugin entrypoint.\n" );
	exit( 1 );
}

$backup = $zip_name . '.previous';
if ( file_exists( $backup ) ) {
	@unlink( $backup );
}
if ( file_exists( $zip_name ) && ! rename( $zip_name, $backup ) ) {
	@unlink( $tmp_name );
	fwrite( STDERR, "Could not move the previous archive aside.\n" );
	exit( 1 );
}
if ( ! rename( $tmp_name, $zip_name ) ) {
	if ( file_exists( $backup ) ) {
		rename( $backup, $zip_name );
	}
	@unlink( $tmp_name );
	fwrite( STDERR, "Could not publish the verified archive.\n" );
	exit( 1 );
}
if ( file_exists( $backup ) ) {
	unlink( $backup );
}

printf(
	"Built %s\n  files added:    %d\n  bytes (raw):    %s\n  files excluded: %d\n  archive size:   %s\n",
	basename( $zip_name ),
	$added,
	number_format( $added_bytes ),
	count( $excluded ),
	number_format( (int) filesize( $zip_name ) )
);

if ( $excluded ) {
	echo "  excluded:\n";
	foreach ( $excluded as $file ) {
		echo "    - $file\n";
	}
}
