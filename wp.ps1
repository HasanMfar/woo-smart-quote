$php  = 'F:\Programmes\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe'
$wp   = 'F:\Programmes\Local\resources\extraResources\bin\wp-cli\wp-cli.phar'
$root = 'C:\Users\Hasan\Local Sites\plugin-playground\app\public'
& $php -c 'F:\Programming\WordPress\Quote\wp-cli.ini' $wp --path=$root --allow-root $args
