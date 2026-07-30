# Sistema de diseño — DailyHábitos

Este documento es el **brief visual** que se usó para generar el proyecto en Google Stitch ("DailyHábitos – Sistema de Vida", `projects/1557446845940558076`) y ahora guía la traducción de ese diseño a componentes Blade/Livewire + Tailwind v4.

**Estado:** el design system de Stitch (`assets/10199802669095720763`) y el Bloque 1 de pantallas (login, registro, dashboard, hábitos de hoy, todos los hábitos, crear hábito) ya fueron generados. La sección 2 refleja los tokens reales que Stitch resolvió a partir del brief.

## 1. Principios

- **SaaS premium de productividad**, no una app "gamificada" infantil: sin mascotas, sin confeti excesivo, sin colores saturados por doquier.
- **Jerarquía clara por encima de la densidad**: cada pantalla tiene un foco principal (ej. hábitos de hoy) y todo lo demás es secundario.
- **Evidencia antes que decoración**: cada métrica (racha, %, comparación) se muestra con datos reales, nunca placeholders permanentes.
- **Calma**: animaciones sutiles (150–250ms, ease-out), nunca rebote exagerado ni parpadeo.
- **Accesible por defecto**: contraste AA mínimo, foco visible en todo elemento interactivo, tamaños de toque ≥ 44px en móvil.

## 2. Tokens reales (resueltos por Stitch, `assets/10199802669095720763`)

### Color
Stitch generó una paleta Material 3 tipo "tonal spot" a partir del seed `#4F46E5` (índigo/violeta). Tokens clave en modo claro:

| Token | Hex | Uso |
|---|---|---|
| `primary` | `#5b5a8b` | Acciones primarias, estado "completado" |
| `primary_container` | `#bbb9f1` | Fondos suaves de elementos activos, celdas de heatmap de mayor intensidad |
| `on_primary` | `#fbf7ff` | Texto/íconos sobre `primary` |
| `secondary` | `#5e5d72` | Elementos secundarios, texto de apoyo con énfasis |
| `tertiary` | `#755478` | Acentos puntuales (evitar sobreusar) |
| `error` | `#a8364b` | Estado "fallado" — rojo desaturado, no alarmante, tal como pedía el brief |
| `background` / `surface` | `#fcf8fe` | Fondo base en modo claro |
| `surface_container` … `surface_container_highest` | `#f6f2fa` → `#e4e1ed` | Escalón de tarjetas/paneles sobre el fondo |
| `on_surface` | `#32313b` | Texto principal |
| `on_surface_variant` | `#5f5e68` | Texto secundario |
| `outline` / `outline_variant` | `#7b7984` / `#b3b0bc` | Bordes |

Los tonos exactos del modo oscuro se resuelven regenerando el mismo design system con `colorMode: DARK` (pendiente, ver §7) — la relación entre tokens (`primary`, `on_primary`, `surface_container_*`, etc.) se mantiene igual, solo cambian los valores.

- **Semántica de estado** (consistente en toda la app y en el calendario estilo GitHub): completado/racha activa → `primary`/`primary_container` en varias intensidades; pendiente/hoy sin registrar → `surface_variant` con borde `outline_variant`; fallado → `error` (desaturado, no un rojo de alarma); día de descanso → `surface_container` plano, visualmente "apagado" frente a los días evaluables.
- Paleta y contraste deben validarse en **modo claro y modo oscuro** por separado, no derivar oscuro solo invirtiendo claro.

### Tipografía
- **Encabezados:** Plus Jakarta Sans. **Cuerpo:** Inter. **Etiquetas/labels:** Public Sans.
- Esto **reemplaza** la fuente Instrument Sans que traía el esqueleto inicial de Laravel en `vite.config.js` — se actualiza en la Fase 0 para cargar Plus Jakarta Sans + Inter vía `laravel-vite-plugin/fonts`.
- Escala tipográfica reducida y consistente (ej. 12/14/16/20/24/32), evitar tamaños ad-hoc por pantalla.

### Espaciado y forma
- Sistema de espaciado en múltiplos de 4px (estándar Tailwind).
- Roundness `ROUND_EIGHT` (radio ~8px en tarjetas y controles, ~12–16px en contenedores grandes) — moderado, ni completamente cuadrado ni "burbuja". Sombra sutil solo donde aporte separación (evitar sombras pesadas tipo Material antiguo).

### Componentes reutilizables (mapeo Stitch → Blade)
| Componente visual | Componente Blade/Livewire |
|---|---|
| Tarjeta de hábito (hoy) | `x-habit-card` |
| Insignia de racha | `x-streak-badge` |
| Anillo/barra de progreso | `x-progress-ring`, `x-progress-bar` |
| Celda de calendario estilo GitHub | `x-heatmap-cell` dentro de `Livewire\Calendar\YearHeatmap` |
| Navegación lateral (escritorio) | `x-layout.sidebar` |
| Navegación inferior/hamburguesa (móvil) | `x-layout.mobile-nav` |
| Selector de tema claro/oscuro | `x-theme-toggle` |
| Modal de registro rápido | `x-modal` + `Livewire\Habits\QuickLogModal` |
| Tarjeta de estadística (KPI) | `x-stat-card` |

## 3. Modo claro / oscuro

- Se implementa con la estrategia de clase (`class="dark"` en `<html>`) + Alpine para persistir preferencia en `localStorage`, con fallback a `prefers-color-scheme` y opción "según el sistema" (se guarda en `users.theme`).
- Todo componente nuevo debe definirse con sus variantes `dark:` desde el primer commit — no se admite un componente "solo modo claro" pendiente de oscurecer después.

## 4. Responsive

- Punto de quiebre principal: `lg` (navegación lateral fija en escritorio, navegación inferior/drawer en móvil).
- El calendario anual estilo GitHub debe hacer scroll horizontal contenido en móvil (nunca desbordar la página).
- Gráficas y tablas anchas (historial, comparación de periodos) van dentro de contenedores `overflow-x-auto` propios.

## 5. Idioma

- Interfaz 100% en español (es-CO como referencia regional para formatos de fecha/número, ya que la zona horaria por defecto es `America/Bogota`).
- Textos claros y directos, tono motivador pero no infantil ("Racha de 12 días" en vez de "¡Wow, lo lograste! 🎉" excesivo).

## 6. Flujo de trabajo con Stitch

1. ~~Crear el proyecto **"DailyHábitos – Sistema de Vida"** en Stitch.~~ ✅ `projects/1557446845940558076`.
2. ~~Generar las 6 pantallas del Bloque 1~~ ✅ Login, Registro, Dashboard, Hábitos de hoy, Todos los hábitos, Crear hábito — todas `COMPLETE` con el design system `assets/10199802669095720763`.
3. ~~Extraer el design system y actualizar los tokens de la sección 2~~ ✅ (ver tabla de colores y tipografía arriba).
4. Traducir cada pantalla a componentes Blade/Livewire — **no se copia el HTML/CSS exportado tal cual**: se reinterpreta con las utilidades de Tailwind ya usadas en el proyecto y los componentes reutilizables de la tabla anterior. *(En curso — Fase 0/1 de implementación.)*
5. Repetir para los bloques 2 y 3 de pantallas a medida que avanzan las fases de implementación.

## 7. Pendiente

- Generar la variante en modo **oscuro** del design system (`colorMode: DARK`) para tener los tokens exactos, en vez de derivarlos manualmente.
- Revisar accesibilidad (contraste AA) del par `error` (`#a8364b`) sobre `surface` antes de usarlo en texto pequeño; si no pasa AA, usarlo solo en superficies con `on_error`/`error_container` y no como color de texto suelto.
