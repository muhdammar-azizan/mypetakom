#!/bin/sh
set -e

# SQLite file, created on first start.
mkdir -p database storage/framework/cache storage/framework/sessions storage/framework/views storage/logs
[ -f database/database.sqlite ] || touch database/database.sqlite

php artisan storage:link --force >/dev/null 2>&1 || true
php artisan migrate --force

# Seed the demo data only the first time (when there are no users yet).
if [ "$(php artisan tinker --execute='echo \App\Models\User::count();' 2>/dev/null | tail -n1)" = "0" ]; then
    php artisan db:seed --force
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache

exec php artisan serve --host=0.0.0.0 --port="${PORT:-8080}"
