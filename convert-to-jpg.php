<?php
$png = 'docs/marketplace-banner-rastchin-fa.png';
$jpg = 'docs/marketplace-banner-rastchin-fa.jpg';
$img = imagecreatefrompng( $png );
if ( $img ) {
    imagejpeg( $img, $jpg, 95 );
    imagedestroy( $img );
    echo "Saved JPG: $jpg (" . number_format( filesize( $jpg ) ) . " bytes)\n";
    copy( $jpg, 'woo-smart-quote/assets/images/marketplace-banner-rastchin-fa.jpg' );
    copy( $png, 'woo-smart-quote/assets/images/marketplace-banner-rastchin-fa.png' );
    copy( $jpg, 'C:/Users/Hasan/.gemini/antigravity/brain/29d9023b-ec73-4887-9d0c-8bed0b723b7f/marketplace-banner-rastchin-fa.jpg' );
    copy( $png, 'C:/Users/Hasan/.gemini/antigravity/brain/29d9023b-ec73-4887-9d0c-8bed0b723b7f/marketplace-banner-rastchin-fa.png' );
    echo "Copied to plugin assets and artifacts directory.\n";
} else {
    echo "Failed to load PNG.\n";
}
