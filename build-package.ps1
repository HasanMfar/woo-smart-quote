# PowerShell helper to build production package with the configured PHP runtime
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$php  = 'F:\Programmes\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe'
$ini  = Join-Path $root 'wp-cli.ini'
$pkg  = Join-Path $root 'build-package.php'

& $php -c $ini $pkg $args
