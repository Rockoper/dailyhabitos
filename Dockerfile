# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# DailyHábitos — imágenes de producción (PHP-FPM + Nginx)
#
# Construcción multietapa, un único Dockerfile con dos targets finales:
#   1. vendor  -> dependencias PHP de producción (Composer)
#   2. assets  -> recursos de Vite compilados (pnpm, sin instalar npm global)
#   3. nginx   -> imagen final Nginx, con los estáticos de `assets` horneados
#                 dentro (docker compose build la selecciona con `target: nginx`)
#   4. app     -> imagen final PHP-FPM (target por defecto, última etapa)
#
# Nginx NO comparte un volumen con `app` para servir `public/`: si lo hiciera,
# tras cada rebuild el volumen ya existente conservaría los assets viejos y
# ocultaría los nuevos (el auto-seed de un volumen solo ocurre la primera vez
# que se crea vacío). Al hornear los estáticos directamente en la imagen de
# Nginx, cada `docker compose build` reconstruye ambas imágenes desde la
# misma fuente y quedan siempre sincronizadas.
# ---------------------------------------------------------------------------

ARG PHP_VERSION=8.3
ARG NODE_VERSION=22
ARG COMPOSER_VERSION=2
ARG PNPM_VERSION=11.5.0

# ---------------------------------------------------------------------------
# Etapa 1: dependencias PHP
# ---------------------------------------------------------------------------
FROM composer:${COMPOSER_VERSION} AS vendor

WORKDIR /app

# Primero solo los manifiestos: permite cachear la descarga de paquetes
# aunque cambie el resto del código fuente.
COPY composer.json composer.lock ./

RUN composer install \
        --no-dev \
        --no-interaction \
        --no-progress \
        --prefer-dist \
        --no-scripts \
        --no-autoloader

# Ahora sí el resto de la aplicación, para poder generar el autoloader
# optimizado (necesita la carpeta app/ real, no solo composer.json).
COPY . .

RUN composer dump-autoload --no-dev --optimize --classmap-authoritative

# ---------------------------------------------------------------------------
# Etapa 2: recursos de frontend (Vite + pnpm, sin npm/yarn)
# ---------------------------------------------------------------------------
FROM node:${NODE_VERSION}-alpine AS assets

WORKDIR /app

# Corepack ya viene con Node: activa pnpm sin instalar nada por npm global.
ARG PNPM_VERSION
RUN corepack enable && corepack prepare pnpm@${PNPM_VERSION} --activate

COPY .npmrc package.json pnpm-lock.yaml ./
RUN pnpm install --frozen-lockfile

COPY vite.config.js ./
COPY resources/ resources/
COPY public/ public/

RUN pnpm build

# ---------------------------------------------------------------------------
# Etapa 3: imagen final Nginx (solo estáticos; el PHP lo ejecuta `app`)
# ---------------------------------------------------------------------------
FROM nginx:1.27-alpine AS nginx

COPY docker/nginx/default.conf /etc/nginx/conf.d/default.conf

# Todo lo que Nginx necesita físicamente en disco: assets de public/build
# más los archivos estáticos que ya traía public/ (favicon, robots.txt...).
# index.php viaja también pero Nginx nunca lo lee: solo pasa la ruta por
# fastcgi_param a dailyhabitos-app, que sí tiene su propia copia completa.
COPY --from=assets /app/public /var/www/html/public

# ---------------------------------------------------------------------------
# Etapa 4: imagen final PHP-FPM
# ---------------------------------------------------------------------------
FROM php:${PHP_VERSION}-fpm-alpine AS app

# Extensiones del sistema necesarias en tiempo de ejecución (cliente de
# PostgreSQL). El resto de dependencias de compilación se instalan y
# desinstalan en la misma capa vía docker-php-extension-installer.
RUN apk add --no-cache postgresql-libs \
    && apk add --no-cache --virtual .build-deps curl \
    && curl -sSLf -o /usr/local/bin/install-php-extensions \
        https://github.com/mlocati/docker-php-extension-installer/releases/download/2.11.12/install-php-extensions \
    && chmod +x /usr/local/bin/install-php-extensions \
    # Extensiones realmente usadas por DailyHábitos:
    #   pdo_pgsql -> conexión a PostgreSQL (DB_CONNECTION=pgsql)
    #   redis     -> caché/sesiones/colas (REDIS_CLIENT=phpredis)
    #   pcntl     -> apagado ordenado de `queue:work` y `schedule:work`
    #   opcache   -> rendimiento en producción
    && install-php-extensions pdo_pgsql redis pcntl opcache \
    && apk del .build-deps \
    && rm -rf /var/cache/apk/* /tmp/pear

COPY docker/php/php.ini /usr/local/etc/php/conf.d/99-dailyhabitos.ini
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# Código de aplicación + vendor (de la etapa 1) + assets compilados (etapa 2).
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=assets /app/public/build ./public/build

# Únicamente storage/ y bootstrap/cache necesitan ser escribibles por el
# proceso PHP; el resto del código permanece de solo lectura para www-data.
RUN mkdir -p storage/framework/{cache,sessions,testing,views} storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache \
    && chmod -R u+rwX,g+rX storage bootstrap/cache

# El propio proceso PHP (FPM y los `php artisan` de worker/scheduler) corre
# como www-data, nunca como root.
USER www-data

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
