#!/bin/sh
set -eu
mkdir -p storage/database storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs storage/app/public
if [ ! -f .env ]; then cp .env.example .env; fi
if [ ! -f storage/database/app-key ]; then php -r 'echo "base64:".base64_encode(random_bytes(32));' > storage/database/app-key; fi
export APP_KEY="$(cat storage/database/app-key)"
new_database=false
if [ ! -s "$DB_DATABASE" ]; then touch "$DB_DATABASE"; new_database=true; fi
php artisan migrate --force
if [ "$new_database" = true ] && [ "${DEMO_SEED:-false}" = true ]; then php artisan db:seed --class=PlatformSeeder --force; fi
php artisan storage:link || true
exec php -S 0.0.0.0:8000 -t public server.php
