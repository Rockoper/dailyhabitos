# Arquitectura técnica — DailyHábitos

## 1. Stack recomendado

| Capa | Elección | Justificación |
|---|---|---|
| Backend | Laravel 13.23 / PHP 8.3 | Ya instalado en el repo. |
| Vistas | Blade + **Livewire 3** + Alpine.js | La app es un dashboard con muchas interacciones locales (marcar hábito, abrir modal de cantidad, alternar tema, filtrar calendario) pero no necesita ser una SPA. Livewire evita duplicar lógica en JS y en PHP, y Alpine cubre micro-interacciones (dropdowns, toggles) sin overhead. Se descarta Inertia+Vue/React: añadiría una capa de estado en el cliente innecesaria para un CRUD + dashboard personal, y complicaría mantener "una sola fuente de verdad" en PHP. |
| Autenticación | **Laravel Fortify** (headless) | Da 2FA, rate limiting y verificación de email listos, sin imponer vistas (a diferencia de Breeze), lo que evita fricción al integrar el diseño de Stitch. Sanctum se añade desde el inicio para dejar el camino libre a la futura app móvil/PWA (tokens de API). |
| CSS | Tailwind CSS v4 (`@tailwindcss/vite`) | Ya configurado en `vite.config.js`. |
| Build | Vite | Ya configurado. |
| Gestor JS | **pnpm** exclusivamente | Requisito explícito del proyecto. `.npmrc` ya tiene `ignore-scripts=true`. |
| Base de datos local | SQLite | Ya configurado (`database/database.sqlite`). |
| Base de datos futura | PostgreSQL | Se evitan features específicas de SQLite (sin `ENUM` nativo de MySQL/pg con dependencias fuertes; usar `string` + Enum de PHP a nivel de aplicación, o `enum` de Postgres solo si se confirma el motor final). Migraciones escritas con el query builder de Laravel, portables entre SQLite/PostgreSQL. |
| Gráficas | Librería JS ligera vía pnpm (ej. Chart.js) cargada solo en las vistas de estadísticas | Evita peso global; se decide en fase 5. |
| Colas/caché | `database` (ya configurado) | Suficiente para un solo usuario; migrable a Redis si se requiere. |

## 2. Estructura de módulos (Livewire + Services)

```
app/
  Enums/
    HabitType.php          # daily | weekly | quantity | scheduled | abstinence
    FrequencyType.php       # daily | specific_days | interval | weekly_count
    LogStatus.php           # completed | failed | skipped | partial
    GoalStatus.php          # pending | completed | failed
  Models/
    User.php
    Category.php
    Habit.php
    HabitLog.php
    Goal.php
    DailyReflection.php
  Policies/
    HabitPolicy.php
    HabitLogPolicy.php
    GoalPolicy.php
    DailyReflectionPolicy.php
    CategoryPolicy.php
  Services/Habits/
    ScheduleResolver.php        # calcula fechas "esperadas" de un hábito entre dos fechas según su FrequencyType
    StreakCalculator.php        # racha actual y mejor racha
    ConsistencyCalculator.php   # % de cumplimiento por periodo, independiente de racha
    HabitStatsService.php       # orquesta lo anterior + totales, mejor día/semana/mes, comparación de periodos
    HabitLogger.php             # acción de registrar/editar/eliminar un log (valida reglas de negocio)
  Livewire/
    Dashboard/Overview.php
    Habits/TodayList.php
    Habits/HabitIndex.php
    Habits/HabitForm.php
    Habits/HabitDetail.php
    Habits/QuickLogModal.php
    Calendar/YearHeatmap.php
    Stats/StatsOverview.php
    Goals/GoalList.php
    Goals/GoalForm.php
    Reflections/DailyReflectionForm.php
    History/HistoryTable.php
    Settings/ProfileSettings.php
    Settings/AppearanceSettings.php

resources/views/
  components/            # x-card, x-badge, x-streak-badge, x-progress-ring, x-heatmap-cell,
                          # layout/x-sidebar, layout/x-topbar, layout/x-mobile-nav, x-modal, x-theme-toggle
  livewire/              # vistas de cada componente Livewire
  layouts/app.blade.php  # layout autenticado (sidebar + topbar)
  layouts/guest.blade.php
```

Los controladores tradicionales se usan solo donde no aporta valor un componente Livewire (ej. exportación futura de PDF/JSON, endpoints de Fortify).

## 3. Modelo de datos propuesto

### `users` (extender la tabla existente)
- `timezone` (string, default `America/Bogota`)
- `locale` (string, default `es`)
- `theme` (string: `light` | `dark` | `system`, default `system`)

### `categories`
- `id`, `user_id` (FK), `name`, `color` (hex), `icon` (string, nombre de ícono), `position` (int), timestamps.

### `habits`
- `id`, `user_id` (FK), `category_id` (FK nullable)
- `name`, `description` (nullable), `icon`, `color`
- `type` (enum `HabitType`: daily | weekly | quantity | scheduled | abstinence)
- `frequency_type` (enum `FrequencyType`: daily | specific_days | interval | weekly_count)
- `frequency_config` (json) — según `frequency_type`:
  - `specific_days`: `{ "days": [1,3,5] }` (ISO: 1=lunes)
  - `interval`: `{ "every_n_days": 3 }`
  - `weekly_count`: `{ "times_per_week": 4 }`
- `target_quantity` (decimal nullable), `unit` (string nullable: minutos, páginas, km, vasos, repeticiones, $...)
- `start_date` (date), `end_date` (date nullable)
- `allow_rest_days` (bool) — si aplica el concepto de "día de descanso" fuera del cálculo
- `never_fail_twice` (bool) — activa la regla opcional descrita en §5
- `is_private` (bool) — oculta detalle en vistas compartidas/exportaciones (pensado para hábitos de abstinencia)
- `is_archived` (bool), `archived_at` (timestamp nullable)
- `position` (int)
- timestamps, `deleted_at` (soft delete — cumple "eliminación lógica")

### `habit_logs`
- `id`, `habit_id` (FK), `user_id` (FK, denormalizado para políticas/consultas rápidas)
- `date` (date) — día de hábito en la zona horaria del usuario
- `status` (enum `LogStatus`: completed | failed | skipped | partial)
- `quantity_value` (decimal nullable) — para hábitos de cantidad
- `note` (text nullable)
- timestamps
- **Único:** `(habit_id, date)`

### `goals`
- `id`, `user_id` (FK), `habit_id` (FK nullable — meta puede o no estar ligada a un hábito)
- `title`, `description` (nullable)
- `target_date` (date)
- `target_value` (decimal nullable), `current_value` (decimal default 0)
- `status` (enum `GoalStatus`: pending | completed | failed)
- timestamps

### `daily_reflections`
- `id`, `user_id` (FK), `date` (date), `content` (text), `mood` (string nullable)
- timestamps
- **Único:** `(user_id, date)`

### Estadísticas
No se propone una tabla `habit_stats` persistida en el MVP: racha actual, mejor racha y consistencia se calculan bajo demanda por los servicios de `app/Services/Habits/*` y se cachean por `(habit_id, fecha_de_hoy)` con el driver `cache` (tabla `database`, ya configurado) para no recalcular en cada request. Si el volumen de datos lo justifica más adelante, se puede materializar en una tabla `habit_stats` recalculada por Observer/Job — se deja como optimización futura, no como parte del modelo inicial.

## 4. Migraciones iniciales propuestas

1. `xxxx_add_preferences_to_users_table` (timezone, locale, theme)
2. `xxxx_create_categories_table`
3. `xxxx_create_habits_table`
4. `xxxx_create_habit_logs_table`
5. `xxxx_create_goals_table`
6. `xxxx_create_daily_reflections_table`

(No se generan todavía — se crean al iniciar la Fase 1, tras tu aprobación.)

## 5. Cálculo de rachas, consistencia y cumplimiento

Todo lo siguiente vive en `app/Services/Habits/*`, con tests unitarios dedicados (`tests/Unit/Services/Habits/*`).

### 5.1 Resolución de fechas esperadas (`ScheduleResolver`)
Para cualquier hábito y rango `[start_date, min(end_date, hoy)]`, genera el conjunto de "fechas esperadas":
- `daily`: todas las fechas del rango.
- `specific_days`: solo las fechas cuyo día ISO de la semana está en `frequency_config.days`.
- `interval`: fechas separadas por `every_n_days` desde `start_date`.
- `weekly_count`: no genera fechas puntuales; genera **semanas** (lunes–domingo, según configuración regional) como unidad de evaluación.

Las fechas fuera de este conjunto son **días de descanso**: no cuentan para racha, consistencia ni se marcan como fallo, y se pintan en el calendario en un tono neutro (no verde, no rojo).

### 5.2 Racha actual (`StreakCalculator::current()`)
1. Se obtienen las fechas esperadas hasta "hoy" (en la zona horaria del usuario) vía `ScheduleResolver`.
2. Se recorre hacia atrás desde la fecha esperada más reciente:
   - Si hay `HabitLog` con `status = completed` (o `quantity_value >= target_quantity` para hábitos de cantidad) → cuenta y continúa.
   - Si la fecha esperada es **hoy** y aún no hay registro → no rompe la racha todavía (el día no ha terminado); se muestra como "pendiente hoy".
   - Si la fecha esperada es pasada y no hay registro, o el registro es `failed` → rompe la racha, salvo que `never_fail_twice = true` y sea la **primera** falla consecutiva (ver 5.5).
3. Para `weekly_count`: la unidad es la semana; una semana "cuenta" si `count(logs completados en la semana) >= times_per_week`. La racha actual es el número de semanas consecutivas cumplidas hasta la semana actual (la semana en curso no rompe la racha si aún puede alcanzar la meta con los días restantes).

### 5.3 Mejor racha (`StreakCalculator::longest()`)
Recorre el historial completo de fechas/semanas esperadas de principio a fin, acumulando la racha más larga con la misma lógica de "completado vs. no completado", sin el trato especial de "hoy". Se cachea junto con la racha actual y se invalida cuando se crea/edita/borra un `HabitLog` del hábito.

### 5.4 Consistencia
Métrica independiente y complementaria a la racha (que penaliza duro un solo fallo):
```
consistencia(periodo) = completados(periodo) / fechas_esperadas(periodo) * 100
```
Se calcula para ventanas móviles (30/90/365 días) y para el periodo de vida del hábito. Permite ver "voy bien en general" aunque la racha se haya roto ayer.

### 5.5 Regla "nunca fallar dos veces"
Cuando `never_fail_twice = true`: una fecha esperada fallida aislada (con fechas esperadas cumplidas inmediatamente antes y después) no rompe la racha, pero cuenta como "comodín usado"; **dos fallos en fechas esperadas consecutivas sí rompen la racha**. Se implementa llevando un contador de fallos consecutivos dentro del recorrido de `StreakCalculator`: al llegar a 1 fallo se marca `strike = true` y se continúa; si el siguiente resultado también es fallo, se corta la racha en ese punto (retrocediendo el conteo al primer fallo).

### 5.6 Cumplimiento semanal/mensual/anual
- Semanal: agregación de logs por semana ISO (lunes–domingo) en la zona horaria del usuario.
- Mensual/anual: agregación por mes/año calendario. Se usa siempre la fecha `habit_logs.date` (ya normalizada a la zona del usuario), nunca timestamps UTC crudos.

### 5.7 Días de descanso vs. fechas omitidas
- **Día de descanso**: fecha fuera del conjunto de fechas esperadas (no aplica el hábito ese día). Neutral, no cuenta para nada.
- **Fecha omitida/sin registro**: fecha esperada sin `HabitLog`. Mientras la fecha sea "hoy" se trata como pendiente; en cuanto pasa la medianoche (zona horaria del usuario) sin registro, un job programado (`habits:close-day`, ejecutado a las 00:05 `America/Bogota`, o evaluación perezosa al leer estadísticas si el job aún no corrió) la marca implícitamente como fallo para efectos de cálculo — sin necesidad de crear una fila `failed` automática, basta con que el cálculo trate "fecha esperada pasada sin log" como fallo. Esto evita escribir miles de filas "failed" innecesarias y mantiene `habit_logs` como una tabla de eventos reales (solo lo que el usuario sí marcó).

### 5.8 Zona horaria
- Config `app.timezone = America/Bogota` (por defecto de la instancia).
- `users.timezone` permite ajuste por usuario (útil si viajas).
- Toda fecha "de hábito" se deriva con `now()->setTimezone($user->timezone)->toDateString()`, nunca con `now()->toDateString()` a secas (que usaría la zona de la app/servidor).
- `HabitLog.date` se castea como `date` (Carbon sin hora) para evitar ambigüedad de comparación entre zonas.

## 6. Rutas y módulos (borrador, sujeto a Fase 1)

```
GET   /                          → redirect a /dashboard o /login
GET   /dashboard                 → Dashboard\Overview
GET   /habitos                   → Habits\HabitIndex (todos los hábitos)
GET   /habitos/hoy               → Habits\TodayList
GET   /habitos/crear              → Habits\HabitForm (modo crear)
GET   /habitos/{habit}/editar     → Habits\HabitForm (modo editar)
GET   /habitos/{habit}            → Habits\HabitDetail
POST  /habitos/{habit}/archivar   → acción de archivar
DELETE /habitos/{habit}           → soft delete
GET   /calendario                 → Calendar\YearHeatmap
GET   /estadisticas                → Stats\StatsOverview
GET   /objetivos                   → Goals\GoalList
GET   /objetivos/crear              → Goals\GoalForm
GET   /reflexion                   → Reflections\DailyReflectionForm
GET   /historial                   → History\HistoryTable
GET   /perfil                      → Settings\ProfileSettings
GET   /perfil/apariencia            → Settings\AppearanceSettings
```
Todas bajo middleware `auth` + `verified` (si se activa verificación de email) y protegidas por Policies por usuario.

## 7. Seguridad y buenas prácticas

- Cada modelo de dominio implementa `belongsTo(User::class)` y cada acción pasa por una Policy — nunca se confía en el `id` recibido del cliente sin verificar propiedad.
- Rate limiting de Fortify para login/registro.
- `is_private` en hábitos de abstinencia: se excluyen de exportaciones compartidas y se puede mostrar con nombre genérico ("Hábito privado") si se habilita un modo de "pantalla compartida" en el futuro.
- Ninguna credencial o secreto en el repositorio; `.env` ya está en `.gitignore`.
- Tests obligatorios para `ScheduleResolver`, `StreakCalculator`, `ConsistencyCalculator` (casos límite: cambio de año, hábitos con `interval`, regla `never_fail_twice`, zona horaria distinta a UTC).
