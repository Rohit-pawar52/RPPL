#!/bin/bash
# Container entrypoint on Render. Runs on every start/spin-up, so everything here must be safe to repeat.
set -e
cd /var/www/html

# Render passes the port to listen on in $PORT (default 10000).
PORT="${PORT:-10000}"
echo "Listen ${PORT}" > /etc/apache2/ports.conf
sed -i -E "s/<VirtualHost \*:[0-9]+>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# Defaults for Render's free tier (no .env file, no queue worker, no Reverb). Any of these can be
# overridden by an environment variable in the Render dashboard.
export APP_ENV="${APP_ENV:-production}"
export APP_DEBUG="${APP_DEBUG:-false}"
export DB_CONNECTION="${DB_CONNECTION:-pgsql}"
export DB_URL="${DB_URL:-$DATABASE_URL}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"
export BROADCAST_CONNECTION="${BROADCAST_CONNECTION:-log}"
export LOG_CHANNEL="${LOG_CHANNEL:-stderr}"

if [ -z "$APP_KEY" ]; then
  echo "APP_KEY is not set. Add it in the Render dashboard (php artisan key:generate --show --no-ansi)." >&2
  exit 1
fi

rm -f bootstrap/cache/*.php
php artisan config:clear

# The database must survive restarts: never migrate:fresh here. No "|| true" either, so a failed
# migration fails the deploy instead of starting a broken site.
php artisan migrate --force

# First start only: a database with no users yet gets the demo dataset, loaded in ONE transaction so
# a failure can never leave it half-seeded (which the next start would then skip). Users, not roles:
# a migration already inserts a role, so a freshly migrated database is never "role-less".
USERS=$(php artisan tinker --no-ansi --execute='echo \App\Models\User::count();' 2>/dev/null | tail -1 || true)
if [ "$USERS" = "0" ]; then
  echo "Empty database: loading the demo dataset..."
  php artisan tinker --no-ansi --execute='\Illuminate\Support\Facades\DB::transaction(fn () => \Illuminate\Support\Facades\Artisan::call("db:seed", ["--force" => true]));'
fi

# The cache store is the database, so this has to come after migrate.
php artisan cache:clear || true
php artisan storage:link --force || true
php artisan config:cache
php artisan view:cache

# Optional: the Laravel scheduler (match/registration reminders, announcements). Render free has no
# cron, so set RUN_SCHEDULER=true to run it once a minute from inside the container.
if [ "$RUN_SCHEDULER" = "true" ]; then
  ( while true; do php artisan schedule:run > /dev/null 2>&1; sleep 60; done ) &
fi

chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

exec apache2-foreground
