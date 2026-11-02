# Production image: nginx + PHP-FPM in one container, running as a non-root UID.
# Assets and vendor are built in earlier stages so the final image carries no
# node_modules, no dev Composer packages, and no .env — configuration comes only
# from environment variables.

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY resources ./resources
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
# The composer base image lacks the app's PHP extensions; platform checks run in
# the final image's environment instead.
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --no-autoloader --ignore-platform-reqs
COPY . .
RUN install -d bootstrap/cache \
    && composer dump-autoload --optimize --no-scripts --ignore-platform-reqs \
    # Package discovery needs a bootable app; APP_ENV is passed only so the
    # mock-driver guard does not fire during the build. No .env is written.
    && APP_ENV=local php artisan package:discover --ansi

FROM php:8.3-fpm
RUN apt-get update \
    && apt-get install -y --no-install-recommends nginx libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/conf.d/default.conf
WORKDIR /app
COPY --from=vendor /app/vendor ./vendor
COPY --from=vendor /app/bootstrap/cache ./bootstrap/cache
COPY . .
COPY --from=assets /app/public/build ./public/build
COPY docker/nginx.conf /etc/nginx/nginx.conf
COPY docker/php-fpm.conf /usr/local/etc/php-fpm.conf
COPY docker/php-fpm-www.conf /usr/local/etc/php-fpm.d/www.conf
RUN rm -f /usr/local/etc/php-fpm.d/docker.conf /usr/local/etc/php-fpm.d/zz-docker.conf \
    && groupadd -g 10001 app && useradd -u 10001 -g app app \
    && install -d /app/bootstrap/cache \
    && chown -R app:app /app/storage /app/bootstrap/cache \
    && install -d -o app -g app /run/php /run/nginx /var/log/nginx /var/lib/nginx \
    && chmod +x docker/entrypoint.sh
# psysh and other tools write to $HOME; there is no real home dir for UID 10001.
ENV HOME=/tmp
USER 10001
EXPOSE 8080
ENTRYPOINT ["docker/entrypoint.sh"]
