<?php
$f = 'docs/marketplace-banner-rastchin-fa.png';
if ( file_exists( $f ) ) {
    $sz = getimagesize( $f );
    echo "Banner File: $f\n";
    echo "Size: " . number_format( filesize( $f ) ) . " bytes\n";
    echo "Dimensions: " . $sz[0] . "x" . $sz[1] . "\n";
} else {
    echo "File not found: $f\n";
}
