# Migración a la interfaz V2

> Estado: **en progreso**. Última actualización: 2026-06-29.
> V2 corre en `/v2/*` en paralelo a producción, **consumiendo los mismos
> endpoints y permisos**. Por eso la migración es casi 100% UI + cutover:
> el backend y los datos ya están compartidos (riesgo bajo de datos).

Layout V2: `layouts/v2.blade.php` (sidebar con secciones Uso diario / WhatsApp /
Trabajo / Supervisión). Design system: `public/css/crecer-v2.css` +
`public/js/crecer-v2.js` (módulo `V2Conv`). Ver [DESIGN-SYSTEM.md](DESIGN-SYSTEM.md).

---

## 1. Inventario de áreas y estado V2

Leyenda: ✅ paridad/nativo · 🟡 parcial · 🔴 falta · ⚪ fuera de alcance inmediato

| # | Área | Ruta prod | Ruta V2 | Estado | Nota |
|---|------|-----------|---------|--------|------|
| 1 | Login | `/login` (Livewire) | — | 🔴 | Restyle menor, baja prioridad |
| 2 | Mi día (home) | redirect por permiso | `/v2/mi-dia` | ✅ | **Solo existe en V2** (nativo) |
| 3 | Atención — colas WA (×3) | `/atencion/{area}` | `/v2/atencion/{area}` | ✅ | La más madura; agendar tareas + legajo |
| 4 | Mis conversaciones | `/mis-conversaciones` | `/v2/mis-conversaciones` | ✅ | |
| 5 | Centro de tareas | `/centro-tareas` | `/v2/centro-tareas` | ✅ | |
| 6 | Historial | `/historial` | `/v2/historial` | ✅ | Reusa query de prod |
| 7 | Contactos | `/contactos` | `/v2/contactos` | ✅ | |
| 8 | Agenda | `/agenda` | `/v2/agenda` | ✅ | |
| 9 | Reportes / Estadísticas | `/admin/estadisticas` | `/v2/reportes` | ✅ | Nativo, 3 tabs |
| 10 | Admin (10 subpáginas) | `/admin/*` | `/v2/admin/{pag}` | ✅ | **Shell-wrap definitivo** (auditado 2026-06-29): se ve bien en V2, no se rediseña |
| 11 | Recepción / Secretaría (turnos) | `/secretaria`, `/cola-bot`, `/inbox-wa` | `/v2/recepcion` | ✅ | 2 tabs (sala + bot). `InboxWA` lo absorbe `/v2/atencion` (no se porta) |
| 12 | Médico (Mi consultorio) | `/medico` | `/v2/medico` | ✅ | Reusa endpoints `/medico/*`; sala/llamar/rellamar/atendido + tareas + agenda Omnia |
| 13 | Documentos de paciente | `/pacientes/{id}/documentos` | — | 🔴 | V2 hoy linkea a la UI de prod |
| 14 | Chat interno | widget | (widget en layout V2) | ✅ | Mismo widget React, ya incluido |
| 15 | Declarar colas | `/declarar-colas` (Livewire) | — | 🟡 | Hay botón ⇄; restyle menor |
| 16 | Llamador (TV pública) | `/llamador` | — | ⚪ | Pantalla pública, no usa el shell |
| 17 | Tablet (sala pública) | `/tablet` | — | ⚪ | Pantalla pública, no usa el shell |

**Resumen:** 12 áreas cubiertas en V2 (11 nativas + Admin como shell-wrap
definitivo), 1 gap cosmético (Login/Declarar), 2 fuera de alcance (pantallas
públicas). Documentos (13), Médico (12), Recepción (11) migrados y Admin (10)
decidido. `InboxWA` queda absorbido por `/v2/atencion`. **Fase 2 cerrada.**

---

## 2. Checklist por área (para llegar a "listo para cutover")

### Bloque A — Gaps de paridad (lo que falta para que V2 tenga TODO)

- [x] **Recepción / Turnos** (`/v2/recepcion`) — *2026-06-29*
  - [x] Portar `ColaSecretaria` (cola de recepción / turnos de sala) → tab "Sala de espera"
  - [x] Portar `ColaBot` (derivaciones del bot a recepción) → tab "Mensajes del bot"
  - [x] `InboxWA`: **NO se porta** — se solapa 1:1 con `/v2/atencion` (mismas tablas ConversacionWA/MensajeWA/TareaWA). La gestión de chat WA vive en Atención V2.
  - [x] Decisión tomada: **reescrito Livewire → JS + endpoints** (`RecepcionController`, 12 rutas). Los Livewire no exponían REST, así que se crearon endpoints JSON que replican cada acción.
  - [x] Bugfix de paso: `Derivacion::$fillable` no incluía `atendido_at` (el `ColaBot` de prod nunca lo seteaba al resolver); agregado.
  - [ ] Pulido: drag-drop real (hoy reordena con ↑/▼); `confirm()` de "resolver sin liberar" → modal V2
- [x] **Médico / Mi consultorio** (`/v2/medico`) — *2026-06-29*
  - [x] Lista de pacientes en espera + llamar / rellamar / atendido
  - [x] Crear tarea desde el panel (modal V2)
  - [x] Integración con el Llamador (TV): vía `cola_atencion.llamado_consultorio_at`, sin cambios — llamar desde V2 dispara el anuncio igual que prod
  - [x] Agenda Omnia del día + atajos de chat a secretarias (reusa `/chat/usuarios` + widget)
  - [ ] Pulido a nativo: `confirm()` de "marcar atendido" → modal V2; usar `.v2-dialog` en vez del overlay propio
- [x] **Documentos de paciente** (`/v2/pacientes/{id}/documentos`) — *2026-06-29*
  - [x] Legajo en shell V2: listado, filtros, upload manual, preview (modal), descargar, zip, destacar, notas, reenviar, eliminar, mini-player de audio — reusa los endpoints de prod sin tocar backend
  - [x] El link del legajo de Atención V2 ahora apunta a `/v2/pacientes/{id}/documentos`
  - [ ] Pulido a nativo: reemplazar `prompt()`/`confirm()` por modales V2 (`.v2-dialog`); chips/cards con componentes V2 propios (hoy usan tokens de prod vía puente)
  - [ ] Link inverso "volver" hacia la conversación de origen (no solo al directorio)
- [x] **Admin** (decisión tomada 2026-06-29) — **shell-wrap definitivo, NO rediseño nativo**
  - [x] Decisión: dejar shell-wrap. Razón: Admin lo usan pocos usuarios power, con poca
        frecuencia; un rewrite nativo de 10 páginas es esfuerzo alto y valor sólo estético.
  - [x] Auditoría (mínimo "que se vean bien"): las 10 vistas usan `@extends($layout ?? 'layouts.app')`
        (el wrap se aplica), son **auto-estiladas** con clases locales prefijadas + tokens
        bridgeados por `crecer-v2.css` (sin clases-componente huérfanas del DS), el **tema
        claro/oscuro funciona** vía el puente (`var(--card)`→`--v2-bg-card`, sigue `[data-theme]`),
        el sub-nav (`admin/_nav`) es V2-aware (tabs quedan dentro del shell) y Estadísticas
        redirige a `/v2/reportes` (versión nativa). **No requirió cambios de código.**
- [ ] **Login / Declarar colas** (cosmético)
  - [ ] Restyle al design system V2 (opcional, no bloquea cutover)

### Bloque B — Endurecer las V2 que ya existen (paridad fina)

Para cada una, comparar 1:1 contra la versión de prod y cerrar diferencias:

- [ ] **Atención** — repasar: iniciar conversación, derivar área, reenviar externo, adjuntos, respuestas rápidas (todo ya está; auditar casos borde)
- [ ] **Mis conversaciones** — avatares (hoy `avatar_url=null`), filtros
- [ ] **Centro de tareas** — paridad con `/centro-tareas` + derivaciones
- [ ] **Historial** — filtros, paginación, export si existe en prod
- [ ] **Contactos** — import CSV (preview/confirm), performance (ya optimizado en prod)
- [ ] **Agenda** — CRUD completo
- [ ] **Reportes** — 3 tabs vs estadísticas de prod
- [ ] Revisar **permisos** en cada vista V2 (que respeten los mismos `permiso:*`)
- [ ] Revisar **responsive** (el bug de burbujas mostró que conviene testear anchos chicos)

### Bloque C — Cutover (hacer V2 el default)

- [x] Mecanismo de preferencia por usuario: columna `users.ui_pref` (`v1`|`v2`, default `v1`) — *2026-06-29*. Helper `User::prefiereV2()`.
- [x] `/` y el redirect post-login honran `ui_pref` — *2026-06-29*. `/` manda a `/v2/mi-dia` (secretaria/atención), `/v2/medico` o `/v2/admin` según permiso; `DeclaracionColas` ahora redirige a `/` (antes hardcodeaba `/secretaria`) para que el flag aplique en el login diario.
- [x] Toggle en ambos navbars — *2026-06-29*. Prod: "✨ Probar V2" → `/cambiar-ui/v2`. V2: "UI clásica" → `/cambiar-ui/v1`. El endpoint setea `ui_pref` y vuelve a `/` (que rutea según el flag).
- [ ] Piloto: activar V2 a 1-2 usuarios power, iterar feedback (poner su `ui_pref=v2` o que toquen "Probar V2")
- [ ] Default V2 para todos los nuevos; opt-out disponible
- [ ] Flip global: V2 default para todos, V1 como escape hatch

**Nota cutover:** no hay usuarios "admin puro" (los roles admin/supervisora/técnico
incluyen `secretaria` en `PERMISOS_DEFAULT`), así que todos caen en una rama V2
alcanzable. `default v1` = nadie se mueve hasta que toque el toggle (reversible).

### Bloque D — Retiro de V1

- [ ] N semanas estable sin opt-outs → deprecar V1
- [ ] Borrar vistas `resources/views/{atencion,contactos,agenda,...}` viejas
- [ ] Borrar Livewire components no usados
- [ ] Quitar `layouts/app.blade.php` y rutas duplicadas
- [ ] Renombrar `/v2/*` → rutas canónicas (o dejar redirects)

---

## 3. Plan de migración (fases)

**Premisa clave:** datos y endpoints ya compartidos → el riesgo está en UI y en
los 4 gaps, no en el backend.

| Fase | Qué | Entregable | Riesgo |
|------|-----|-----------|--------|
| **0. Este doc** | Inventario + checklist | `MIGRACION-V2.md` | — |
| **1. Endurecer existentes (Bloque B)** | Auditar las 9 áreas con paridad, cerrar diferencias finas | V2 confiable para uso diario real | Bajo |
| **2. Gaps de paridad (Bloque A)** | Recepción → Médico → Documentos → Admin | V2 cubre el 100% de prod | Medio (Recepción/Médico son lógica de turnos) |
| **3. Cutover gradual (Bloque C)** | Flag `ui_pref`, piloto, default V2 | Mayoría en V2, V1 como fallback | Bajo (reversible por flag) |
| **4. Retiro V1 (Bloque D)** | Borrar V1 cuando esté estable | Una sola UI | Bajo si se esperó |

**Fase 2 COMPLETA:** ~~Documentos~~ ✅ → ~~Médico~~ ✅ → ~~Recepción/Turnos~~ ✅ →
~~Admin (shell-wrap definitivo)~~ ✅. V2 cubre el 100% del uso operativo diario.

**Fase 3 EN PROGRESO:** mecanismo de cutover listo (flag `ui_pref` + toggle en
ambos navbars + `/` y login honran el flag). Falta: **piloto** con 1-2 power
users → default para nuevos → flip global. Default actual `v1` (nadie se mueve
hasta tocar "✨ Probar V2").

> Nota sobre Médico: resultó de bajo riesgo porque la UI de prod ya era JS+endpoints
> (no Livewire real). El port reusó `/medico/data` + `/medico/{id}/llamar|rellamar|
> atendido` + `/medico/tareas` sin tocar backend. La sincronización con el Llamador
> es por columna de DB, así que funciona idéntico desde el shell V2.

**Sugerencia de arranque:** Fase 1 sobre **Atención** (ya es la más usada y
madura) para fijar el patrón de auditoría, y en paralelo **Documentos** de
Fase 2 porque es chico y autocontenido.

---

## 4. Decisiones

Tomadas (2026-06-29):
- ✅ **Arranque**: empezar por **Documentos de paciente** (gap chico, autocontenido, desbloquea el legajo de Atención V2).
- ✅ **Recepción/Médico**: **reescribir Livewire → JS + endpoints** (consistencia con el resto de V2, una sola arquitectura). **Médico hecho** (2026-06-29); Recepción pendiente.
- ⏳ **Cutover**: mecanismo a definir más adelante (cerrar gaps primero).

Abiertas:
- ✅ **Admin**: resuelto — **shell-wrap definitivo** (auditado, se ve bien en V2). No se rediseña.
- ✅ **InboxWA**: resuelto — lo absorbe `/v2/atencion` (mismas tablas, mismo chat). No se porta.
- [ ] **Pantallas públicas** (Llamador/Tablet): ¿entran al restyle o quedan como están?
