#!/bin/sh
set -e

# With a read-only root filesystem (Kubernetes), Laravel's writable dirs are
# emptyDir mounts and start empty — create the layout the framework expects.
mkdir -p \
    /app/storage/app /app/storage/framework/cache \
    /app/storage/framework/sessions /app/storage/framework/views \
    /app/storage/logs /run/php /run/nginx

case "${1:-serve}" in
    migrate)
        php artisan migrate --force
        ;;
    seed)
        php artisan db:seed --class=DemoUserSeeder --force
        ;;
    sync)
        php artisan ojs:sync
        ;;
    serve)
        php artisan migrate --force
        if [ "$(php artisan tinker --execute='echo App\Models\User::count();')" = "0" ]; then
            # Users only: the dashboard data comes from ojs:sync against the driver.
            php artisan db:seed --class=DemoUserSeeder --force
        fi
        php artisan ojs:sync
        php-fpm -D
        exec nginx -g 'daemon off;'
        ;;
    *)
        exec php artisan "$@"
        ;;
esac
