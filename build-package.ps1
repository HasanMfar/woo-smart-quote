# PowerShell helper to build production package with the configured PHP runtime
$root = Split-Path -Parent $MyInvocation.MyCommand.Path
$localPhp = 'F:\Programmes\Local\resources\extraResources\lightning-services\php-8.2.29+0\bin\win64\php.exe'
if (Test-Path $localPhp) {
    $php = $localPhp
} else {
    $php = (Get-Command php -ErrorAction Stop).Source
}
$ini = Join-Path $root 'wp-cli.ini'
if (-not (Test-Path $ini)) {
    $ini = Join-Path $root 'php-test.ini'
}
$pkg = Join-Path $root 'build-package.php'

& $php -c $ini $pkg $args

