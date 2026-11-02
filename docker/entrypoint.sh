#!/bin/sh
set -e

# artisan serve starts its PHP workers with a scrubbed environment, so they read only
# .env. Write the container's settings there so web requests see the same DB as artisan.
env | grep -E '^(APP|DB|OJS|QUEUE|LOG|DASHBOARD)_' > .env

# The app container owns schema setup; worker and scheduler wait for it via depends_on.
if [ "$1" = "serve" ]; then
    php artisan migrate --force
    if [ "$(php artisan tinker --execute='echo App\Models\User::count();')" = "0" ]; then
        # Users only: the dashboard data comes from ojs:sync against the mock fixtures.
        php artisan db:seed --class=DemoUserSeeder --force
    fi
    php artisan ojs:sync
    exec php artisan serve --host=0.0.0.0 --port=8000
fi

exec php artisan "$@"
