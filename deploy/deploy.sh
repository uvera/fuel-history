#!/usr/bin/env bash
#
# Deploy/update script for running this app natively (no Docker) -- e.g. on
# a Debian LXC with system PHP-FPM, Caddy, and PostgreSQL, per this repo's
# deploy/README.md.
#
# Run as the app's own service user, from the app's root directory, after
# the new code is already checked out there (git pull/checkout is the
# caller's job -- this script only does the "make the checked-out code
# live" half: dependencies, migrations, caches). Restarting PHP-FPM is the
# caller's job too, since the service/socket name is host-specific.
#
# Usage: deploy/deploy.sh

set -euo pipefail
cd "$(dirname "${BASH_SOURCE[0]}")/.."

if [ ! -f .env ]; then
    echo "deploy.sh: no .env in $(pwd) -- copy .env.example, fill in real values, and run 'php artisan key:generate' before first deploy." >&2
    exit 1
fi

composer install --no-dev --optimize-autoloader --no-interaction

php artisan migrate --force

php artisan optimize
php artisan filament:optimize

echo "deploy.sh: done. Reload/restart PHP-FPM for the new opcache-compiled code to take effect."
