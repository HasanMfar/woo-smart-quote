<?php
/**
 * Builds the delivery zip for Nerkhnegar (woo-smart-quote).
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
// Auto-load ZipArchive extension via local ini if running default CLI PHP
if ( ! class_exists( 'ZipArchive' ) ) {
    if ( empty( getenv( 'WSQ_REEXEC' ) ) ) {
        putenv( 'WSQ_REEXEC=1' );
        $ini = file_exists( __DIR__ . '/php-test.ini' ) ? __DIR__ . '/php-test.ini' : ( file_exists( __DIR__ . '/wp-cli.ini' ) ? __DIR__ . '/wp-cli.ini' : '' );
        if ( $ini ) {
            $pass_args = array_map( 'escapeshellarg', array_slice( $argv, 1 ) );
            passthru( 'php -c ' . escapeshellarg( $ini ) . ' ' . escapeshellarg( __FILE__ ) . ( $pass_args ? ' ' . implode( ' ', $pass_args ) : '' ), $ret );
            exit( $ret );
        }
    }
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
	'assets/images/infographics/',
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

// ============================================================
// Build Rastchin Delivery Package (package-nerkhnegar-X.Y.Z.zip)
// Contains:
//   1. woo-smart-quote.zip (Installable plugin / Update file)
//   2. راهنمای نصب و راه‌اندازی.pdf & manual.pdf (PDF User Manual)
//   3. راهنمای نصب و راه‌اندازی.html (Standalone HTML user guide)
//   4. مستندات قبل از نصب.txt (Pre-install notes)
// ============================================================
echo "\nBuilding Rastchin Delivery Package...\n";

$plugin_clean_zip = $out_dir . '/woo-smart-quote.zip';
copy( $zip_name, $plugin_clean_zip );

$manual_html_src = $root . '/docs/راهنمای نصب و راه‌اندازی.html';
$manual_html_dst = $out_dir . '/راهنمای نصب و راه‌اندازی.html';
if ( file_exists( $manual_html_src ) ) {
	copy( $manual_html_src, $manual_html_dst );
}

$manual_pdf_src = $root . '/docs/manual.pdf';
$manual_pdf_dst = $out_dir . '/راهنمای نصب و راه‌اندازی.pdf';
if ( file_exists( $manual_pdf_src ) ) {
	copy( $manual_pdf_src, $manual_pdf_dst );
}

$notes_src = $root . '/docs/مستندات قبل از نصب.txt';
$notes_dst = $out_dir . '/مستندات قبل از نصب.txt';
if ( file_exists( $notes_src ) ) {
	copy( $notes_src, $notes_dst );
}

$legacy_pkg = $out_dir . '/package-mazankar-' . $version . '.zip';
if ( file_exists( $legacy_pkg ) ) {
	@unlink( $legacy_pkg );
}

$pkg_name = $out_dir . '/package-nerkhnegar-' . $version . '.zip';
$pkg_tmp  = $pkg_name . '.building-' . getmypid();

$pkg_zip = new ZipArchive();
if ( true !== $pkg_zip->open( $pkg_tmp, ZipArchive::CREATE | ZipArchive::OVERWRITE ) ) {
	fwrite( STDERR, "Could not create delivery package: $pkg_tmp\n" );
	exit( 1 );
}

$pkg_zip->addFile( $plugin_clean_zip, 'woo-smart-quote.zip' );
if ( file_exists( $manual_pdf_dst ) ) {
	$pkg_zip->addFile( $manual_pdf_dst, 'راهنمای نصب و راه‌اندازی.pdf' );
	$pkg_zip->addFile( $manual_pdf_dst, 'manual.pdf' );
}
if ( file_exists( $manual_html_dst ) ) {
	$pkg_zip->addFile( $manual_html_dst, 'راهنمای نصب و راه‌اندازی.html' );
}
if ( file_exists( $notes_dst ) ) {
	$pkg_zip->addFile( $notes_dst, 'مستندات قبل از نصب.txt' );
}
$pkg_zip->close();

if ( file_exists( $pkg_name ) ) {
	@unlink( $pkg_name );
}
rename( $pkg_tmp, $pkg_name );

printf(
	"Master Delivery Package Built: %s\n  Package size: %s bytes\n  Includes:\n    - woo-smart-quote.zip (Update File)\n    - راهنمای نصب و راه‌اندازی.pdf / manual.pdf (PDF Guide)\n    - راهنمای نصب و راه‌اندازی.html (HTML Guide)\n    - مستندات قبل از نصب.txt\n",
	basename( $pkg_name ),
	number_format( (int) filesize( $pkg_name ) )
);

