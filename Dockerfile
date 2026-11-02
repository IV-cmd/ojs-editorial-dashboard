# Local development image for docker compose (mock OJS, artisan serve).
# Not a production image: no PHP-FPM, no opcache tuning, dev dependencies included.

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY vite.config.js postcss.config.js tailwind.config.js ./
COPY resources ./resources
RUN npm run build

FROM php:8.3-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-interaction --no-progress --no-scripts --no-autoloader
COPY . .
COPY --from=assets /app/public/build ./public/build
# .env first: without it APP_ENV defaults to production, and the mock-driver guard
# refuses to boot during package discovery.
RUN cp .env.example .env \
    && composer dump-autoload --optimize \
    && chmod +x docker/entrypoint.sh
ENTRYPOINT ["docker/entrypoint.sh"]
