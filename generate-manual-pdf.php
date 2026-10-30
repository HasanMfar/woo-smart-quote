<?php
/**
 * Generates the official user manual PDF for Rastchin delivery package
 */

$root = __DIR__;
$chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe';
$user_data = sys_get_temp_dir() . '/chrome_wsq_render';

$html_manual = $root . '/docs/manual-for-pdf.html';
$pdf_manual  = $root . '/docs/manual.pdf';

if ( file_exists( $html_manual ) ) {
    $cmd = "cmd.exe /c \"\"$chrome\" --headless=new --user-data-dir=\"$user_data\" --no-pdf-header-footer --print-to-pdf=\"$pdf_manual\" \"file:///$html_manual\"\"";
    exec( $cmd, $out, $ret );
    echo "Generated $pdf_manual (code $ret, size: " . ( file_exists( $pdf_manual ) ? filesize( $pdf_manual ) : 0 ) . " bytes)\n";
    
    // Copy to docs/راهنمای نصب و راه‌اندازی.pdf and root
    $fa_pdf = $root . '/docs/راهنمای نصب و راه‌اندازی.pdf';
    copy( $pdf_manual, $fa_pdf );
    copy( $pdf_manual, $root . '/راهنمای نصب و راه‌اندازی.pdf' );
    copy( $pdf_manual, $root . '/manual.pdf' );
    echo "Copied to $fa_pdf and root\n";
} else {
    fwrite( STDERR, "Source HTML not found: $html_manual\n" );
    exit( 1 );
}
