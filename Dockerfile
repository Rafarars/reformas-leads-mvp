# syntax=docker/dockerfile:1

# ---------------------------------------------------------------- base
# PHP con las extensiones que necesita el proyecto y un usuario sin privilegios.
FROM dunglas/frankenphp:1.12.7-php8.4-alpine AS base

RUN install-php-extensions pdo_pgsql pcntl

COPY --from=composer:2.8.3 /usr/bin/composer /usr/bin/composer

# Mismo UID que el usuario del host: lo que Laravel escribe en el código montado
# (logs, caché) queda editable fuera del contenedor.
ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} app \
    && adduser -D -u ${UID} -G app app \
    && mkdir -p /data/caddy /config/caddy \
    && chown -R app:app /data /config /app

# El Caddyfile de la imagen lee SERVER_NAME: con ":8010" sirve HTTP sin certificados.
ENV SERVER_NAME=:8010
WORKDIR /app
EXPOSE 8010

# La imagen base comprueba el panel de Caddy (:2019): diría "healthy" con la base caída,
# y lo heredaría el contenedor de la cola, que no tiene servidor web.
HEALTHCHECK NONE

# ---------------------------------------------------------------- dev
# El código llega por un volumen desde el host; la imagen solo aporta el entorno.
FROM base AS dev
USER app

# ---------------------------------------------------------------- vendor
FROM base AS vendor
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-autoloader --prefer-dist --no-interaction

# ---------------------------------------------------------------- assets
# Wayfinder ejecuta `php artisan` durante el build de Vite: esta etapa necesita PHP y Node.
FROM base AS assets
RUN apk add --no-cache nodejs npm
COPY package.json package-lock.json .npmrc ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN composer dump-autoload --no-dev --optimize && npm run build

# ---------------------------------------------------------------- prod
FROM base AS prod
ENV APP_ENV=production \
    APP_DEBUG=false
COPY --chown=app:app . .
COPY --chown=app:app --from=vendor /app/vendor ./vendor
COPY --chown=app:app --from=assets /app/public/build ./public/build
RUN composer dump-autoload --no-dev --optimize --classmap-authoritative \
    && rm /usr/bin/composer
USER app

HEALTHCHECK --interval=10s --timeout=5s --start-period=10s --retries=6 \
    CMD php -r "exit(@file_get_contents('http://localhost:8010/health') === false ? 1 : 0);"
