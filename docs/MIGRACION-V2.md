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
| 10 | Admin (10 subpáginas) | `/admin/*` | `/v2/admin/{pag}` | 🟡 | **Shell-wrap**: vistas de prod dentro del layout V2, no rediseñadas |
| 11 | Recepción / Secretaría (turnos) | `/secretaria`, `/cola-bot`, `/inbox-wa` | — | 🔴 | Subsistema de turnos de sala; Livewire |
| 12 | Médico (Mi consultorio) | `/medico` | `/v2/medico` | ✅ | Reusa endpoints `/medico/*`; sala/llamar/rellamar/atendido + tareas + agenda Omnia |
| 13 | Documentos de paciente | `/pacientes/{id}/documentos` | — | 🔴 | V2 hoy linkea a la UI de prod |
| 14 | Chat interno | widget | (widget en layout V2) | ✅ | Mismo widget React, ya incluido |
| 15 | Declarar colas | `/declarar-colas` (Livewire) | — | 🟡 | Hay botón ⇄; restyle menor |
| 16 | Llamador (TV pública) | `/llamador` | — | ⚪ | Pantalla pública, no usa el shell |
| 17 | Tablet (sala pública) | `/tablet` | — | ⚪ | Pantalla pública, no usa el shell |

**Resumen:** 10 áreas con paridad V2, 1 shell-wrap (Admin), 2 gaps reales
(Recepción, Login/Declarar), 2 fuera de alcance (pantallas públicas).
Documentos (13) y Médico (12) ya migrados.

---

## 2. Checklist por área (para llegar a "listo para cutover")

### Bloque A — Gaps de paridad (lo que falta para que V2 tenga TODO)

- [ ] **Recepción / Turnos** (`/v2/recepcion`)
  - [ ] Portar `ColaSecretaria` (cola de recepción / turnos de sala)
  - [ ] Portar `ColaBot` (derivaciones del bot a recepción)
  - [ ] Portar `InboxWA` si sigue en uso (evaluar si se solapa con Atención V2)
  - [ ] Definir: ¿se reescribe el Livewire a JS+endpoints como el resto de V2, o se shell-wrappea como Admin?
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
- [ ] **Admin nativo** (decisión)
  - [ ] Decidir: dejar shell-wrap (funciona) o rediseñar tab por tab (usuarios, textos, pruebas, logs, legajo-config, respuestas-rápidas, médicos, tunnel, dashboard)
  - [ ] Mínimo: que los 10 tabs se vean bien dentro del shell V2 (revisar cada uno)
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

- [ ] Mecanismo de preferencia por usuario: columna `users.ui_pref` (`v1`|`v2`)
- [ ] `/` y los redirects por permiso honran `ui_pref`
- [ ] Toggle visible en ambos navbars ("Probar V2" / "UI actual")
- [ ] Piloto: activar V2 a 1-2 usuarios power, iterar feedback
- [ ] Default V2 para todos los nuevos; opt-out disponible
- [ ] Flip global: V2 default para todos, V1 como escape hatch

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

**Orden recomendado dentro de Fase 2:** ~~Documentos~~ ✅ → ~~Médico~~ ✅ →
**Recepción/Turnos (el próximo, el más grande)** → Admin (decidir shell vs nativo).

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
- [ ] **Admin**: ¿shell-wrap definitivo o rediseño nativo? (esfuerzo alto, valor estético)
- [ ] **InboxWA**: ¿sigue vivo o lo absorbió Atención V2?
- [ ] **Pantallas públicas** (Llamador/Tablet): ¿entran al restyle o quedan como están?
