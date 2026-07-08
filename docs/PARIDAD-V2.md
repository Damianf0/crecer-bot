# Matriz de paridad V1 → V2

> Auditoría exhaustiva 2026-07-08, disparada por el hallazgo del reply faltante.
> Método: inventario de CADA acción de usuario en las vistas V1 (con línea como
> evidencia) verificado contra V2 (blades + crecer-v2.js). Los faltantes ❌
> fueron verificados a mano (grep), no solo reportados por los agentes.
> **Esta matriz es el criterio objetivo del gate de retiro de V1 (13/07).**

## Estado por área

| Área | Paridad | Detalle |
|------|---------|---------|
| Agenda | ✅ total | — |
| Médico / Mi consultorio | ✅ total | — |
| Recepción (sala + bot) | ✅ funcional | drag de reorden → botones ↑↓ (degradación cosmética, pulido conocido) |
| Contactos | ✅ total | V2 suma botón "Enviar WhatsApp" desde la ficha |
| Documentos / legajo | ✅ total | — |
| Historial | ✅ total | — |
| Mis conversaciones | ✅ (comparte V2Conv) | los gaps del panel se arreglan en V2Conv (ver Atención) |
| Centro de tareas | 🟡 gaps menores | ver lista |
| Atención (panel V2Conv) | 🟡 gaps cerrados 08/07 + menores pendientes | ver lista |

## Gaps funcionales encontrados y su estado

| # | Gap | Gravedad | Estado |
|---|-----|----------|--------|
| 1 | Reply a mensaje puntual | ALTA | ✅ restaurado 08/07 (commit 72c23df) |
| 2 | Derivar a otra área (modal) | ALTA | ✅ restaurado 08/07 |
| 3 | Reenviar conversación a otro contacto + archivar | ALTA | ✅ restaurado 08/07 |
| 4 | Paginación de mensajes ("ver anteriores"; backend `before_id` listo) | ALTA | ⬜ pendiente |
| 5 | Iniciar conversación desde Atención (modal Nueva) | MEDIA | ⬜ pendiente — workaround: desde Contactos (paridad total ahí) |
| 6 | Notificaciones de browser por conversación entrante | MEDIA | ⬜ pendiente (las del chat interno sí andan — viven en el widget React) |
| 7 | Centro tareas: filtros ámbito (mías/asignadas/creadas/todas) y "vencidas" | MEDIA | ⬜ pendiente |
| 8 | Centro tareas: editar tarea existente | MEDIA | ⬜ pendiente |
| 9 | Centro tareas: vincular conversación al crear | BAJA | ⬜ pendiente |
| 10 | Filtro "Mías" en bandeja atención | BAJA | ⬜ N/A-ish: /v2/mis-conversaciones ES esa vista |
| 11 | Modal ficha de contacto desde avatar + lightbox | BAJA | ⬜ pendiente (el legajo lateral cubre la info) |
| 12 | Modal fullscreen de adjuntos (V2 abre en pestaña nueva) | BAJA | ⬜ decisión de diseño aceptable |
| 13 | Popup del resumen IA (V2 lo muestra inline, no clickeable) | BAJA | ⬜ aceptable |
| 14 | Botón cerrar panel / Esc para cancelar reply | BAJA | ⬜ pulido |
| 15 | Recepción: drag de reorden | BAJA | ⬜ pulido conocido (botones ↑↓ funcionan) |
| 16 | Historial colapsable de resoluciones previas en el hilo | BAJA | ⬜ dudoso, verificar uso real |

## Criterio de gate (13/07)

Para retirar V1: **los gaps ALTA (1-4) cerrados + decisión explícita sobre los MEDIA (5-8)**
(cerrarlos o aceptar el workaround por escrito). Los BAJA no bloquean.

## Lección de proceso

El flip a V2 se aprobó con un smoke-test de endpoints (¿renderiza sin 500?) pero
sin inventario de features. Un endpoint que responde no prueba que el botón que
lo llamaba exista. Esta matriz queda como plantilla: cualquier migración futura
de UI se auditea así ANTES del flip, no después.
