#!/bin/sh
# Punto de entrada compartido por dailyhabitos-app, dailyhabitos-worker y
# dailyhabitos-scheduler (mismo Dockerfile, distinto CMD por servicio).
#
# Responsabilidades deliberadamente mínimas: esperar a que las dependencias
# de red estén listas y arrancar el proceso indicado. NO ejecuta migraciones
# ni `artisan config/route/view:cache` — esos pasos son manuales y se
# documentan en docs/DEPLOYMENT_SYNOLOGY.md para que nunca ocurran de forma
# implícita en un reinicio de contenedor.
set -eu

wait_for_tcp() {
    host="$1"
    port="$2"
    label="$3"
    attempts=0
    max_attempts=60

    while ! nc -z "$host" "$port" >/dev/null 2>&1; do
        attempts=$((attempts + 1))
        if [ "$attempts" -ge "$max_attempts" ]; then
            echo "[entrypoint] Tiempo de espera agotado esperando a ${label} (${host}:${port})" >&2
            exit 1
        fi
        echo "[entrypoint] Esperando a ${label} (${host}:${port})... (${attempts}/${max_attempts})"
        sleep 2
    done

    echo "[entrypoint] ${label} disponible en ${host}:${port}"
}

if [ -n "${DB_HOST:-}" ] && [ -n "${DB_PORT:-}" ]; then
    wait_for_tcp "$DB_HOST" "$DB_PORT" "PostgreSQL"
fi

if [ -n "${REDIS_HOST:-}" ] && [ -n "${REDIS_PORT:-}" ]; then
    wait_for_tcp "$REDIS_HOST" "$REDIS_PORT" "Redis"
fi

# Idempotente y sin necesidad de base de datos: regenera el manifiesto de
# paquetes descubiertos si falta o quedó desactualizado entre despliegues.
php artisan package:discover --ansi || echo "[entrypoint] Aviso: package:discover falló, continuando de todas formas"

echo "[entrypoint] Iniciando: $*"
exec "$@"
