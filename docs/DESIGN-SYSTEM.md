# Design System Crecer — v1 (fase 3)

**Archivo:** `app/public/css/crecer-ds.css` · **Cargado por:** `layouts/app.blade.php` (con `?v=filemtime` para bustear el cache `immutable` de 30 días que nginx aplica a los `.css`).

## Por qué existe

Antes de esto, los tokens de tema vivían inline en el layout y cada vista redeclaraba sus propios componentes en `@push('styles')` — el modal, los badges, el hilo de mensajes, etc. estaban copiados (y derivados con drift) en 4+ archivos. Aplicar el nuevo lenguaje visual hubiera implicado editar ~14 vistas a mano.

Ahora: **tokens y componentes compartidos viven en un solo archivo**. El nuevo lenguaje visual se aplica acá — primero los tokens (§1), después los componentes — y las vistas lo heredan.

## Cómo funciona la cascada

```
<head>
  <link crecer-ds.css>      ← base canónica
  @stack('styles')           ← CSS por vista: carga DESPUÉS → gana la cascada
</head>
```

Una vista puede sobreescribir cualquier regla del DS declarándola en su bloque local. **Regla de oro:** si una vista redefine una clase del DS, tiene que redeclarar *todas* las propiedades que el DS setea para esa clase (si omite una, la del DS "se filtra"). Las variantes locales existentes ya cumplen esto (verificado por script en la migración).

## Secciones del DS

| § | Contenido |
|---|---|
| 1 | Tokens `:root` / `html.dark` (colores, accent, surface...) |
| 2 | Base: reset, body, scrollbar, sortable, `fadeIn` |
| 3 | Navbar (`nav`, `.nav-brand`, `.nav-link`) |
| 4 | Layout (`main`, `.card`) |
| 5 | Toasts (`.toast-fixed` del layout; `.toast`/`.toast.show` de vistas — los colores `.ok`/`.error` siguen por vista) |
| 6 | Modal (`.modal-*`, `.btn-modal-*`) — único dueño: era exclusivo de atención |
| 7 | Chips (`.filter-chip*`) y badges (`.badge`, `.badge-bot/wa/urg/test/unread`) |
| 8 | Avatares (`.av-circle`, `.av-fallback`) |
| 9 | Hilo de mensajes WA — solo lo idéntico entre vistas (`.msg-wrap`, `.msg-quoted*`, `.msg-time`, `.msg-evento-inline`, `.msg-resol-divider`, `.msg-reply-link`, `msgFlash`...) |
| 10 | Cabecera de panel (`.panel-head-info/-name/-sub/-actions`) |
| 11 | Composer (`.input-modes`, `.mode-btn`, `.input-row`, `.clip-label`, `.file-preview*`, `.reply-pill*`) |
| 12 | Respuestas rápidas (`.rr-menu*`) |
| 13 | Delegar dropdown (`.delegar-*`) |
| 14 | Empty states (`.col-empty`) |
| 15 | Overrides dark de componentes |

## Qué quedó deliberadamente FUERA del DS (v1)

- **`.btn` y variantes** (`.btn-ver/tomar/resolver/urg/del/ok/primary/ghost/sm`): definidas en 6+ vistas con semánticas distintas (en atención `.btn-del` = delegar/info; en otras = delete/rojo). Consolidarlas requiere decisión de diseño → es trabajo del nuevo lenguaje visual.
- **`.msg-list`, `.msg-date`, `.msg-bubble` base/`.out`/`.nota`, `.msg-textarea`, `.send-btn`, `.panel-head`, `.panel-input`, suite `.seg-*`**: variantes intencionales por pantalla (atención usa panel con fondo y padding; mis-conversaciones usa panel plano full-width).
- **`audio { }`** (selector de elemento): solo en atención; globalizarlo afectaría otras vistas.
- **Layouts `minimal` y `tablet`**: pantallas standalone (login/kiosko) con su propio CSS; migran cuando llegue el nuevo lenguaje.
- **Vistas admin / contactos / documentos / medico / llamador / agenda / livewire**: sus estilos siguen 100% locales (usan prefijos propios: `lg-*`, `rr-modal-*`, `mc-*`, `ct-*`...). Se migran al DS cuando se les aplique el nuevo lenguaje.

## Drift conocido (inventario para el rediseño)

Detectado al extraer el DS — copias forkeadas que divergieron sin decisión de diseño aparente. El nuevo lenguaje visual debería unificarlas:

- `.toast.ok/.error`: atención usa pastel sólido (`#dcfce7`) + overrides dark; las demás usan `rgba` translúcido. → unificar en una sola variante theme-agnostic.
- `.msg-bubble`: `font-size` 14px (atención) vs 13px (mis-conversaciones); `.out` con 12% vs 18% de accent.
- `.btn`: padding 4×9 (atención) vs 5×12 + inline-flex (resto de la familia).
- `.seg-*`: dos diseños del seguimiento (atención: con bordes y uppercase; mis-conversaciones: compacto baseline).
- `.badge-bot/wa` en historial: estilo `rgba` viejo vs `color-mix` del DS. En dark mode historial ahora hereda los overrides dark del DS (mejora de consistencia; antes mostraba los colores light).
- Consolidaciones menores ya hechas en la migración (cambio imperceptible, documentado): `.msg-quoted` ahora tiene `cursor:pointer` también en mis-conversaciones; fondo de `.msg-quoted` en salientes 8% (antes 10% en mis-conversaciones); `.mode-btn.active` usa `color-mix` (mismo color que el `rgba` anterior).

## Cómo se verificó la migración (y cómo repetirlo)

Script de equivalencia de cascada: extrae todas las reglas CSS (por selector) del estado viejo (`git show HEAD:...` del layout + vista) y del nuevo (DS + vista), y compara el resultado efectivo selector por selector. Resultado de la migración: **atención 0/204, mis-conversaciones 3/117 (las 3 equivalencias listadas arriba), centro-tareas 0/114, historial 0/68**.

## Aplicar el nuevo lenguaje visual

1. Tokens primero (§1): paleta, radios, tipografía → impacto inmediato en todo lo que consume `var(--*)`.
2. Componentes después (§5-§14), resolviendo el drift de arriba.
3. Vistas no migradas: a medida que se rediseñan, mover su CSS local al DS (o borrar lo que el DS ya cubre).
4. Tocar `crecer-ds.css` actualiza el `?v=` automáticamente (filemtime) — no hay build step. Los cambios de Blade siguen necesitando `docker restart crecer-web-1` (OPcache).

## Rollback

```
git reset --hard estable-pre-fase3   # tag previo a la fase 3
docker exec crecer-web-1 php artisan view:clear
docker restart crecer-web-1
```
