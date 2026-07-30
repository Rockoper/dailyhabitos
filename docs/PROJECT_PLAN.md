# Plan de proyecto — DailyHábitos

## 1. Resultado de la inspección del repositorio

- **Repositorio:** `C:\LOCAL\dailyhabitos` — **no es un repositorio git todavía** (no hay `.git`). Se recomienda inicializarlo antes de empezar a implementar, para poder trabajar por fases con commits revisables. *(Pendiente de tu confirmación — no se ha hecho `git init` todavía.)*
- **Laravel:** 13.23.0 (`laravel/framework: ^13.8` en `composer.json`).
- **PHP:** 8.3.30 (CLI, ZTS, Win32).
- **Composer:** 2.9.5.
- **Node:** v24.12.0.
- **pnpm:** 11.5.0 (disponible y funcional).
- **npm:** 11.6.2 (presente en el sistema, pero **no se usará**: `.npmrc` ya tiene `ignore-scripts=true` y `audit=true`, y no existe `package-lock.json` ni `yarn.lock` en el repo — coherente con la regla de usar solo pnpm).
- **`composer.json`:** esqueleto estándar de `laravel/laravel`, sin paquetes de dominio ni de autenticación (no hay Fortify, Breeze, Jetstream, Livewire ni Sanctum instalados aún).
- **`package.json`:** solo Tailwind v4, Vite y `laravel-vite-plugin` (sin dependencias de UI todavía). `vite.config.js` ya usa `@tailwindcss/vite` y carga la fuente **Instrument Sans**.
- **Base de datos:** SQLite ya configurada y con archivo `database/database.sqlite` creado; solo existen las migraciones base de Laravel (`users`, `cache`, `jobs`). No hay tablas de dominio.
- **`app/`:** solo el `User` model por defecto y la carpeta `Http/Controllers` vacía. Sin `Models` de dominio, sin `Policies`, sin `Services`.
- **`routes/web.php`:** una sola ruta (`/` → vista `welcome`). Sin rutas de auth ni de dominio.
- **`resources/views`:** solo el `welcome.blade.php` de plantilla de Laravel. Sin layout propio, sin componentes.
- **Config regional:** `config/app.php` tiene `timezone => UTC` y `locale => en` (heredado de `.env`: `APP_LOCALE=en`) — **hay que cambiarlos** a `America/Bogota` y `es` como parte de la Fase 0.
- **Tests:** solo los `ExampleTest` por defecto (Feature y Unit), sin lógica propia.
- **Conclusión:** proyecto en estado de esqueleto limpio, ideal para empezar sin necesidad de deshacer nada. No se detectó ningún archivo, configuración o dependencia que deba eliminarse o reemplazarse con cuidado especial — solo hay que construir sobre lo existente.

## 2. Estado de la conexión con Stitch

- El MCP de Stitch respondió correctamente a `list_projects`.
- Proyectos existentes en tu cuenta: 4, ninguno relacionado con este proyecto (todos son de "VYA Fashion..."). **No hay conflicto de nombre.**
- Se confirma que el servidor permite `create_project` — está listo para crear **"DailyHábitos – Sistema de Vida"** en cuanto lo apruebes.
- `list_design_systems` sin `projectId` devolvió un error de argumento inválido (la API espera un `projectId` o un filtro distinto); no es bloqueante — se resolverá al crear el proyecto y su design system asociado.

## 3. Arquitectura recomendada (resumen — detalle en `ARCHITECTURE.md`)

- **Laravel 13 + Blade + Livewire 3 + Alpine.js**, Tailwind v4, Vite, pnpm.
- **Fortify** (headless) para autenticación + **Sanctum** desde ya para dejar preparada la futura API móvil/PWA.
- Lógica de rachas/consistencia encapsulada en servicios (`app/Services/Habits/*`), nunca en controladores/Livewire.
- SQLite en local, migraciones escritas de forma portable para migrar a PostgreSQL sin reescritura.

## 4. Modelo de datos propuesto (resumen — detalle en `ARCHITECTURE.md`)

`users` (+ preferencias) · `categories` · `habits` (con `type`, `frequency_type`, `frequency_config` JSON) · `habit_logs` (evento real por día, único por hábito+fecha) · `goals` · `daily_reflections`. Sin tabla de estadísticas persistida en el MVP: racha/consistencia se calculan por servicio y se cachean.

## 5. Pantallas a diseñar en Stitch — orden propuesto

**Bloque 1 (desbloquea Fase 0–1 de implementación):**
1. Inicio de sesión
2. Registro
3. Dashboard principal
4. Hábitos de hoy
5. Todos los hábitos
6. Crear y editar hábito

**Bloque 2 (desbloquea Fase 2–4):**
7. Detalle de un hábito
8. Calendario anual (estilo GitHub)
9. Historial

**Bloque 3 (desbloquea Fase 5–6):**
10. Estadísticas
11. Objetivos
12. Reflexión diaria
13. Perfil y configuración

Se genera el Bloque 1 primero para poder implementar autenticación + CRUD de hábitos con el diseño real desde el principio, en vez de construir con vistas provisionales que luego haya que rehacer.

## 6. Plan de implementación por fases

| Fase | Contenido | Pantallas Stitch necesarias |
|---|---|---|
| **0. Fundamentos** ✅ | `git init`, instalar Fortify + Livewire + Sanctum vía Composer, `pnpm install`, configurar `timezone=America/Bogota` y `locale=es`, layout base (sidebar/topbar/mobile-nav), toggle claro/oscuro, crear proyecto en Stitch y generar Bloque 1 | Bloque 1 |
| **1. Dominio y CRUD base** | Migraciones y modelos (`categories`, `habits`, `habit_logs`, `goals`, `daily_reflections`), Policies, factories/seeders de prueba, CRUD de hábitos (crear/editar/archivar/eliminar lógico), categorías | — |
| **2. Motor de registro y rachas** | `HabitLogger`, `ScheduleResolver`, `StreakCalculator`, `ConsistencyCalculator` + **tests unitarios** de todos los casos límite descritos en `ARCHITECTURE.md` §5 | Bloque 2 (detalle de hábito) |
| **3. Dashboard y "hoy"** | Lista de hábitos de hoy, acción rápida de registro (con modal de cantidad cuando aplique), rachas actuales, rachas en riesgo | — |
| **4. Calendario y vistas temporales** | Calendario anual estilo GitHub, vista semanal, vista mensual, historial completo | Bloque 2 (calendario, historial) |
| **5. Estadísticas** | Mejor día/semana/mes, comparación entre periodos, cantidad/tiempo acumulado, gráficas | Bloque 3 (estadísticas) |
| **6. Objetivos y reflexión** | Metas con fecha límite (ligadas o no a un hábito), reflexión diaria con notas | Bloque 3 (objetivos, reflexión) |
| **7. Perfil, configuración y pulido** | Perfil, preferencias (zona horaria, tema, idioma), regla "nunca fallar dos veces" configurable por hábito, accesibilidad, animaciones, revisión responsive completa | Bloque 3 (perfil) |
| **8. Exportación (futuro)** | Resumen anual exportable (PDF/JSON) | — |
| **9. Preparación PWA (futuro)** | Manifest, service worker, ajustes de caché — solo se deja preparado, no se implementa una app nativa | — |

Cada fase termina en un estado funcional y probado antes de pasar a la siguiente; no se mezclan fases.

### Fase 0 — resumen de lo entregado

- Autenticación real y funcional con Fortify (login, registro, recuperación de contraseña, actualización de perfil/contraseña), verificada con `php artisan test` y con un flujo completo de registro → dashboard vía `curl` (sin extensión de navegador conectada en este entorno para verificación visual).
- **Nota de dependencia:** `laravel/fortify ^1.37` ahora requiere `laravel/passkeys` (WebAuthn) de forma transitiva — no es una dependencia que se haya añadido a propósito, viene con el paquete. Las funcionalidades de 2FA y passkeys quedan **deshabilitadas** en `config/fortify.php` (comentadas) por estar fuera del alcance del MVP; se reevalúan en una fase futura si se decide ofrecer 2FA.
- Layout `x-layouts.guest` y `x-layouts.app` construidos a mano en Blade/Tailwind reinterpretando la estructura de las pantallas de Stitch (split-screen en auth, sidebar + drawer móvil en el área autenticada) — no se copió el HTML exportado por Stitch.
- Todas las rutas de navegación del área autenticada existen y están protegidas por `auth`; las que corresponden a fases futuras muestran una vista "próximamente" en vez de un error 404, para que la navegación se sienta completa desde ya.
- `pnpm build` corre sin errores; fuentes Plus Jakarta Sans + Inter cargando correctamente.

### Fase 0 — pendiente para fases futuras

- Generar el design system de Stitch en `colorMode: DARK` para reemplazar los valores de modo oscuro que hoy están derivados manualmente (ver `docs/DESIGN_SYSTEM.md` §7).
- La página de perfil (`/perfil`) es un placeholder; el formulario real de perfil/preferencias se construye en la Fase 7, aunque el backend de Fortify para actualizarlo ya está listo.

## 7. Archivos creados en esta etapa

- `CLAUDE.md`
- `docs/PROJECT_PLAN.md` (este archivo)
- `docs/ARCHITECTURE.md`
- `docs/DESIGN_SYSTEM.md`

Ningún archivo del esqueleto de Laravel fue modificado ni eliminado.

## 8. Aprobaciones recibidas (2026-07-29)

1. ✅ `git init` ejecutado. **El repositorio está inicializado pero sin commits** — los archivos quedan en *staging* (`git add`) para que el usuario revise `git diff`/`git status` y haga el commit manualmente. Claude no ejecuta `git commit` en este proyecto salvo instrucción explícita en el momento, y nunca añade menciones de coautoría de Claude/Anthropic en los mensajes.
2. ✅ Proyecto **"DailyHábitos – Sistema de Vida"** creado en Stitch (`projects/1557446845940558076`), design system `assets/10199802669095720763` generado y aplicado, y las 6 pantallas del Bloque 1 generadas (`COMPLETE`). Detalle de tokens en `docs/DESIGN_SYSTEM.md`.
3. ✅ Confirmado: **Fortify (headless) + Livewire 3 + Sanctum**.
4. ✅ Aprobado el modelo de datos y el orden de fases — **arrancando Fase 0**.
