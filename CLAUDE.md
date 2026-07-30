# DailyHábitos — Guía para Claude Code

Sistema personal para registrar, medir y visualizar hábitos y compromisos anuales. Ver `docs/PROJECT_PLAN.md`, `docs/ARCHITECTURE.md` y `docs/DESIGN_SYSTEM.md` para el detalle completo.

## Stack

- **Backend:** Laravel 13 (PHP 8.3), SQLite en local (preparado para migrar a PostgreSQL).
- **Frontend:** Blade + Livewire 3 + Alpine.js, Tailwind CSS v4 (vía `@tailwindcss/vite`).
- **Auth:** Laravel Fortify (headless) con vistas propias en Blade/Livewire diseñadas a partir de Stitch.
- **Build:** Vite. Gestor de paquetes JS: **pnpm exclusivamente** (ya configurado en `.npmrc` con `ignore-scripts=true`). Nunca usar `npm` ni `yarn`; nunca debe existir `package-lock.json` ni `yarn.lock`, solo `pnpm-lock.yaml`.
- **Zona horaria de la app:** `America/Bogota`. Cada usuario puede tener su propia zona horaria (`users.timezone`); toda lógica de "día de hábito" usa la zona del usuario, no UTC.
- **Idioma de la interfaz:** español (es) en todo momento.

## Convenciones de código

- Lógica de negocio (cálculo de rachas, consistencia, resolución de horarios) vive en `app/Services/Habits/*`, nunca en controladores ni componentes Livewire. Controladores/Livewire quedan delgados: reciben input, delegan a un servicio, devuelven la vista.
- Tipos y estados de hábitos se representan con `app/Enums` (`HabitType`, `FrequencyType`, `LogStatus`, `GoalStatus`), no con strings sueltos.
- Toda fecha relacionada con un registro de hábito (`habit_logs.date`) se guarda como `DATE` puro (sin hora), representando el "día de hábito" en la zona horaria del usuario — no un timestamp UTC.
- Los hábitos de tipo "abstinencia" (ej. no fap) deben tratarse como datos sensibles: no exponerlos con nombres explícitos en notificaciones, logs de aplicación, ni vistas compartidas; ofrecer alias/etiqueta privada configurable.
- Autorización de cada hábito/registro/objetivo vía Policies de Laravel (un usuario nunca debe poder leer ni mutar datos de otro).
- Toda la lógica de rachas y cumplimiento debe tener tests (`tests/Unit` para los servicios de cálculo, `tests/Feature` para flujos completos).

## Diseño

- Stitch es la fuente visual (proyecto "DailyHábitos – Sistema de Vida"). No se copia el código exportado de Stitch tal cual: se reinterpreta como componentes Blade/Livewire reutilizables y coherentes con `docs/DESIGN_SYSTEM.md`.
- Modo claro/oscuro obligatorio en todo componente nuevo.
- Componentes de UI reutilizables en `resources/views/components/*` (tarjetas, badges, barra de progreso, calendario estilo GitHub, navegación lateral/móvil).

## Gestión de paquetes

```
pnpm install
pnpm dev
pnpm build
composer install
```

No ejecutar `npm install` ni `yarn install` bajo ninguna circunstancia.

## Estado del proyecto

**Fase 0 (fundamentos) completada** — ver `docs/PROJECT_PLAN.md` §6:

- Repositorio con `git init` hecho, **sin commits todavía** (el usuario es el único autor de commits; nunca añadir `Co-Authored-By` ni menciones de Claude/Anthropic en los mensajes; nunca ejecutar `git commit` salvo instrucción explícita en el momento).
- Fortify (headless) + Livewire 4 + Sanctum instalados. Autenticación funcional: login, registro, recuperación de contraseña, actualización de perfil/contraseña. 2FA y passkeys (WebAuthn) vienen con Fortify ^1.37 como dependencia transitiva pero están **deshabilitados** en `config/fortify.php` (fuera de alcance del MVP).
- `config/app.php`: `timezone => America/Bogota`, `locale`/`fallback_locale` → `es` vía `.env`.
- Tipografía y colores actualizados a los tokens reales que resolvió Stitch (Plus Jakarta Sans + Inter, acento índigo `#5b5a8b`) en `resources/css/app.css` — ver `docs/DESIGN_SYSTEM.md` §2.
- Layout base: `x-layouts.guest` (login/registro, split-screen) y `x-layouts.app` (sidebar fija en escritorio, drawer en móvil, topbar, toggle de tema claro/oscuro persistido en `localStorage`).
- Rutas de navegación (`dashboard`, `habits.today`, `habits.index`, `calendar.index`, `stats.index`, `goals.index`, `reflections.index`, `history.index`, `profile.edit`) registradas y protegidas por `auth`; todas menos `dashboard` apuntan a una vista "próximamente" (`pages.coming-soon`) hasta que se construyan en su fase correspondiente.
- Verificado con `php artisan test`, `pnpm build` y un flujo real de registro → dashboard → rutas autenticadas vía `curl` (no hay extensión de navegador conectada en este entorno para verificación visual).

**Pendiente (Fase 1 en adelante):** modelos de dominio (`Category`, `Habit`, `HabitLog`, `Goal`, `DailyReflection`), policies, CRUD real de hábitos, motor de rachas/consistencia con tests, y el resto de las pantallas de Stitch (Bloques 2 y 3).
