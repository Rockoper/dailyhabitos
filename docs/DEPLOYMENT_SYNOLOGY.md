# Despliegue de DailyHábitos en Synology (Docker Compose)

Guía operativa para desplegar y mantener DailyHábitos en el Synology NAS
(arquitectura x86_64), de forma completamente aislada de cualquier otro
proyecto (por ejemplo VYA) que corra en el mismo equipo.

Ruta del repositorio en el NAS: `/volume1/docker/dailyhabitos/app`.

Todos los comandos de este documento se ejecutan desde esa carpeta, vía SSH
o la terminal del Container Manager de DSM.

---

## 0. Requisitos previos

- Container Manager (Docker) instalado en DSM, en un Synology x86_64.
- Acceso SSH al NAS (o la terminal integrada de Container Manager).
- El repositorio ya clonado en `/volume1/docker/dailyhabitos/app`.
- Una carpeta para respaldos, por ejemplo `/volume1/docker/dailyhabitos/backups`.

Todos los nombres de contenedor, red y volúmenes usados aquí empiezan con
`dailyhabitos-`/`dailyhabitos_`. Ningún comando de esta guía toca, reinicia
ni borra recursos de otros proyectos.

---

## 1. Obtener la última versión del código (`git pull`)

```bash
cd /volume1/docker/dailyhabitos/app
git status              # confirma que no hay cambios locales sin commitear
git pull origin main
```

Si `git status` muestra cambios locales inesperados, revísalos antes de
continuar (no deberían existir en un servidor de producción).

---

## 2. Copiar `.env.production.example` a `.env`

```bash
cp .env.production.example .env
```

Si ya existe un `.env` de un despliegue anterior, **no lo sobreescribas** sin
respaldarlo primero:

```bash
cp .env .env.backup.$(date +%Y%m%d-%H%M%S)
```

`.env` nunca se sube a Git (ver `.gitignore`); vive únicamente en el NAS.

---

## 3. Generar contraseñas fuertes

Genera una contraseña distinta para PostgreSQL y otra para Redis:

```bash
openssl rand -base64 32   # ejecútalo dos veces, una por cada contraseña
```

Edita `.env` y reemplaza:

- `DB_PASSWORD=CHANGE_ME_STRONG_DB_PASSWORD` → la primera contraseña generada.
- `REDIS_PASSWORD=CHANGE_ME_STRONG_REDIS_PASSWORD` → la segunda.

No reutilices contraseñas de otros proyectos (incluyendo VYA).

---

## 4. Generar `APP_KEY`

La forma más simple es generarla localmente sin depender de que la imagen ya
esté construida:

```bash
openssl rand -base64 32 | awk '{print "base64:"$0}'
```

Copia el resultado completo (incluyendo el prefijo `base64:`) en `APP_KEY=`
dentro de `.env`.

Alternativa, una vez construida la imagen (paso 5), usando el propio Artisan:

```bash
docker compose run --rm dailyhabitos-app php artisan key:generate --show
```

y pega el valor mostrado en `.env` (`--show` no escribe el `.env` dentro del
contenedor, que de todas formas no existe ahí: el `.env` real vive solo en
el host y se inyecta vía `env_file`).

---

## 5. Construir las imágenes

```bash
docker compose build
```

Esto construye `dailyhabitos-app` (PHP-FPM, incluye Composer + assets de
Vite compilados con pnpm) y `dailyhabitos-nginx` (estáticos horneados desde
la misma etapa de build, ver `Dockerfile`). Los servicios `worker` y
`scheduler` reutilizan la imagen `dailyhabitos-app:latest`, no se reconstruyen
aparte.

---

## 6. Iniciar el stack

```bash
docker compose up -d
```

Levanta, en orden de dependencias (gracias a los `healthcheck`):
`dailyhabitos-postgres`, `dailyhabitos-redis`, y luego
`dailyhabitos-app`, `dailyhabitos-nginx`, `dailyhabitos-worker`,
`dailyhabitos-scheduler`.

---

## 7. Ejecutar las migraciones

```bash
docker compose exec dailyhabitos-app php artisan migrate --force
```

`--force` es obligatorio porque `APP_ENV=production` (Artisan pide
confirmación interactiva en producción si no se pasa este flag).

---

## 8. Crear el enlace de storage

```bash
docker compose exec dailyhabitos-app php artisan storage:link
```

---

## 9. Ejecutar `config:cache`, `route:cache` y `view:cache`

```bash
docker compose exec dailyhabitos-app php artisan config:cache
docker compose exec dailyhabitos-app php artisan route:cache
docker compose exec dailyhabitos-app php artisan view:cache
```

**Importante:** si más adelante cambias cualquier variable de `.env` sin
reconstruir/recrear el contenedor, el `config:cache` seguirá sirviendo los
valores viejos. Después de editar `.env`:

```bash
docker compose exec dailyhabitos-app php artisan config:clear
docker compose exec dailyhabitos-app php artisan config:cache
docker compose restart dailyhabitos-app dailyhabitos-worker dailyhabitos-scheduler
```

---

## 10. Verificar los contenedores

```bash
docker compose ps
```

Todos los servicios deben mostrar `healthy` (puede tardar 15–30 s tras el
arranque). Prueba end-to-end contra el puerto local:

```bash
curl -I http://192.168.1.31:8180/up
```

Debe responder `HTTP/1.1 200 OK`. Esa ruta (`/up`) es el healthcheck nativo
de Laravel, ya registrado en `bootstrap/app.php`.

---

## 11. Revisar logs

```bash
docker compose logs -f dailyhabitos-app
docker compose logs -f dailyhabitos-nginx
docker compose logs -f dailyhabitos-worker
docker compose logs -f dailyhabitos-scheduler
docker compose logs -f dailyhabitos-postgres
docker compose logs -f dailyhabitos-redis
```

Log de aplicación de Laravel (dentro del volumen persistente):

```bash
docker compose exec dailyhabitos-app tail -f storage/logs/laravel.log
```

---

## 12. Actualizar el proyecto en el futuro

```bash
cd /volume1/docker/dailyhabitos/app
git pull origin main
docker compose build
docker compose up -d
docker compose exec dailyhabitos-app php artisan migrate --force
docker compose exec dailyhabitos-app php artisan config:cache
docker compose exec dailyhabitos-app php artisan route:cache
docker compose exec dailyhabitos-app php artisan view:cache
```

Nota: este stack no hace *zero-downtime deploys*; al recrear
`dailyhabitos-app`/`dailyhabitos-nginx` hay un corte breve (segundos) mientras
los healthchecks confirman que el nuevo contenedor está listo.

---

## 13. Respaldo de PostgreSQL

```bash
mkdir -p /volume1/docker/dailyhabitos/backups
docker compose exec -T dailyhabitos-postgres \
    pg_dump -U dailyhabitos -d dailyhabitos -F c \
    > /volume1/docker/dailyhabitos/backups/dailyhabitos_$(date +%Y%m%d_%H%M%S).dump
```

`-F c` genera un dump en formato "custom" de PostgreSQL (comprimido, apto
para `pg_restore`). Para automatizarlo, crea una tarea programada en
DSM (**Panel de control → Tareas programadas**) que ejecute ese mismo
comando por SSH periódicamente.

---

## 14. Restaurar PostgreSQL

**Antes de restaurar**, detén los procesos que escriben en la base de datos
para evitar condiciones de carrera:

```bash
docker compose stop dailyhabitos-app dailyhabitos-worker dailyhabitos-scheduler
```

Restaura desde un archivo de respaldo:

```bash
cat /volume1/docker/dailyhabitos/backups/dailyhabitos_XXXXXXXX_XXXXXX.dump \
    | docker compose exec -T dailyhabitos-postgres \
      pg_restore -U dailyhabitos -d dailyhabitos --clean --if-exists
```

`--clean --if-exists` elimina los objetos existentes antes de recrearlos, así
que **esto sobreescribe todos los datos actuales** de la base `dailyhabitos`.
Al terminar, vuelve a levantar los servicios detenidos:

```bash
docker compose start dailyhabitos-app dailyhabitos-worker dailyhabitos-scheduler
```

---

## 15. Detener el stack sin borrar volúmenes

```bash
docker compose stop
```

Detiene todos los contenedores conservando contenedores, red y volúmenes
(los datos de Postgres/Redis/storage quedan intactos). Para volver a
arrancar: `docker compose start` (o `docker compose up -d`).

`docker compose down` (sin `-v`) también es seguro: elimina los contenedores
y la red, pero conserva los volúmenes con nombre (`dailyhabitos_postgres_data`,
`dailyhabitos_redis_data`, `dailyhabitos_storage`).

> ⚠️ **Nunca ejecutes `docker compose down -v`** salvo que quieras borrar
> permanentemente la base de datos, el estado de Redis y `storage/`. Ese
> flag elimina también los volúmenes con nombre.

---

## 16. Conectar Cloudflare Tunnel

Ruta pública: `dailyhabitos.com`. Servicio interno objetivo:
`http://dailyhabitos-nginx:80`.

El contenedor `cloudflared` de este Synology puede estar corriendo en la red
Docker de **otro** proyecto (p. ej. VYA). Hay dos formas de conectarlo;
empieza por la **alternativa B**, que es la más simple y no toca nada fuera
de este proyecto.

### Alternativa B (recomendada para empezar): apuntar el túnel a la IP local

En el dashboard de **Cloudflare Zero Trust → Networks → Tunnels**, edita (o
crea) el *Public Hostname* para `dailyhabitos.com` y configura:

- **Service:** `HTTP://192.168.1.31:8180`

Esto usa el puerto que `dailyhabitos-nginx` ya publica hacia la LAN
(`NGINX_PORT` en `.env`, por defecto `8180`) y **no requiere modificar la
configuración de `cloudflared`** ni conectarlo a la red de DailyHábitos: el
túnel simplemente hace una petición HTTP normal a esa IP:puerto, como lo
haría cualquier dispositivo de la LAN.

Requisitos:
- El Synology debe tener la IP `192.168.1.31` reservada de forma fija en el
  router/DHCP (si cambia, hay que actualizar el hostname en Cloudflare).
- El puerto `8180` debe seguir publicado en `compose.yaml` (ya lo está).

### Alternativa A: conectar `cloudflared` a la red de DailyHábitos

Si prefieres que el tráfico nunca salga a la pila de red del host (más
"nativo" en Docker, pero acopla la configuración de `cloudflared` a este
proyecto), puedes unir ese contenedor a `dailyhabitos-network`:

1. Asegúrate de que el stack de DailyHábitos ya se levantó al menos una vez
   (`docker compose up -d`), para que la red `dailyhabitos-network` exista.
2. En el `compose.yaml` (o configuración equivalente) del proyecto donde
   corre `cloudflared`, declara la red como externa y añádela al servicio:

   ```yaml
   services:
     cloudflared:
       # ... configuración existente ...
       networks:
         - default            # su red actual, no la toques
         - dailyhabitos-network

   networks:
     dailyhabitos-network:
       external: true
   ```

3. Recrea únicamente ese contenedor: `docker compose up -d cloudflared`
   (desde el proyecto de `cloudflared`, **no** desde el de DailyHábitos).
4. En Cloudflare Zero Trust, configura el *Public Hostname* con:

   - **Service:** `HTTP://dailyhabitos-nginx:80`

**Trade-off:** esta alternativa mezcla, aunque sea solo a nivel de red
Docker, la configuración de `cloudflared` (de otro proyecto) con la de
DailyHábitos. Úsala solo si controlas ese otro `compose.yaml` y entiendes
que un cambio ahí queda ligado a la existencia de `dailyhabitos-network`.

---

## Seguridad — resumen de lo ya aplicado

- PostgreSQL y Redis **no** publican puertos hacia el host ni internet
  (sin sección `ports:` en `compose.yaml`); solo son alcanzables desde
  `dailyhabitos-network`.
- `.env` nunca se copia a la imagen Docker (`.dockerignore`) ni se sube a
  Git (`.gitignore`); solo existe en el host y se inyecta vía `env_file`.
- `APP_DEBUG=false` en `.env.production.example`.
- Todos los servicios usan `restart: unless-stopped` y tienen `healthcheck`.
- El proceso PHP (FPM, worker, scheduler) corre como `www-data`, nunca como
  root (`USER www-data` en el `Dockerfile`).
- Imágenes con versión fijada (no `latest`) en todo lo crítico:
  `php:8.3-fpm-alpine`, `nginx:1.27-alpine`, `postgres:16-alpine`,
  `redis:7-alpine`.
- Nginx bloquea cualquier ruta que empiece por `.` (incluye `.env`, `.git`)
  y extensiones sensibles (`.log`, `.sql`, `.yml`, etc.) — ver
  `docker/nginx/default.conf`.

## Solución de problemas rápida

- **Un contenedor no llega a `healthy`:** `docker compose logs <servicio>`
  primero; si es `dailyhabitos-app` esperando Postgres/Redis, revisa que
  `DB_PASSWORD`/`REDIS_PASSWORD` en `.env` coincidan con lo que ya tienen
  persistido los volúmenes (si cambiaste la contraseña de Postgres en `.env`
  pero el volumen ya existía con la contraseña anterior, la autenticación
  fallará: hay que igualar el `.env` a la contraseña original, no al revés).
- **Error de permisos en `storage/`:** el contenedor corre como `www-data`
  sin privilegios para auto-corregir permisos. Como último recurso:
  `docker compose exec -u root dailyhabitos-app chown -R www-data:www-data storage bootstrap/cache`.
- **Cambié `.env` y no se refleja:** ver nota del paso 9 sobre
  `config:clear` + `config:cache` + `restart`.
