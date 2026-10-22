param([int]$Port = 8088)
$ErrorActionPreference='Stop'
Set-Location $PSScriptRoot
$phpCommand=Get-Command php -ErrorAction SilentlyContinue
if(!$phpCommand -and (Test-Path runtime/php/php.exe)) {$env:PATH="$PSScriptRoot/runtime/php;$env:PATH";$phpCommand=Get-Command php}
if(!$phpCommand) { throw 'Установите PHP 8.3 с pdo_sqlite, mbstring, fileinfo, openssl и zip или используйте docker compose up --build.' }
if(!(Test-Path vendor/autoload.php)) { composer install; if($LASTEXITCODE -ne 0){throw 'Не удалось установить PHP-зависимости.'} }
if(!(Test-Path .env)) {Copy-Item .env.example .env; php artisan key:generate}
if(!(Test-Path database/database.sqlite)) {New-Item database/database.sqlite -ItemType File | Out-Null}
php artisan migrate --no-interaction
if($LASTEXITCODE -ne 0){throw 'Не удалось применить миграции.'}
if(!(Test-Path public/build/manifest.json)){npm.cmd ci; npm.cmd run build; if($LASTEXITCODE -ne 0){throw 'Не удалось собрать интерфейс.'}}
if(!(Test-Path public/storage)){php artisan storage:link}
$databaseIsEmpty=php scripts/catalog-empty.php
if($databaseIsEmpty -eq 'yes'){php artisan db:seed --class=PlatformSeeder --no-interaction}
Write-Host "Откройте http://127.0.0.1:$Port. Остановка: Ctrl+C."
php -S "127.0.0.1:$Port" -t public server.php
