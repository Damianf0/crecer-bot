# Análisis de arquitectura — octubre 2026

> 2026-10-26. Pregunta: "sentimos que nos falta solidez para consolidar este
> producto; consideremos una opción no basada en web para el cliente".
> Objetivo acordado: **primero Crecer firme, después instalable en otras clínicas**.
> Del cliente no web se espera: avisos y sonido fiables, control de versión en cada
> puesto, que siga sirviendo si el servidor falla, e imagen de producto.
> Antecedente: [EVALUACION-2026-07.md](EVALUACION-2026-07.md).

## 1. Qué cambió desde julio

| | Junio 2026 | Oct 2026 (05/10 → 26/10) |
|---|---|---|
| Incidentes de bots WhatsApp | ~138 por mes (solo Secretaría) | ~10 en 3 semanas, todos curados solos |
| Reinicios inútiles por corte de internet | ~150 en una noche | 0 (10 cortes detectados y respetados) |
| Incidentes propios de Laravel / MySQL | 0 | 0 |
| Volumen | ~31 mil mensajes por mes | meseta similar |

La capa de WhatsApp (wwebjs) quedó estabilizada lo suficiente. La API Cloud
sigue descartada. A vigilar: la versión de WA Web vence aproximadamente en diciembre.

## 2. Dónde está hoy la falta de solidez

Incidentes con impacto entre agosto y octubre:

| Incidente | Duración / impacto | Causa estructural |
|---|---|---|
| Sin entrada de mensajes (14-20/08) | 7 días | 2 — falla silenciosa |
| Adjuntos sin guardar (17/07-23/09) | ~2 meses | 2 — falla silenciosa |
| Secretaría sin recibir (15-20/09) | 5 días | 2 — falla silenciosa |
| Log creado por root | mensajes perdidos | 1 — operación sobre producción en vivo |
| Cola de descargas de Administración trabada (23/10) | medio día | 2 — falla silenciosa |
| Ollama sin tope de memoria (22/10) | panel y bots colgados | 3 — un solo host al límite |
| Web arrancó con código a medio escribir (06/10) | 4 errores 500 | 1 — producción = carpeta de desarrollo |
| Saneado del repo rompió el token del bot (mayo) | envíos caídos | 1 — repo público = producción |

**Ninguno fue "se cayó el servidor".** Hay cuatro causas:

1. **Producción corre desde la carpeta de desarrollo.** `docker-compose.yml` monta
   `./app` y `./bot` en los contenedores. Lo que se edita queda en vivo. No hay
   staging, versiones ni vuelta atrás que no sea git. El repo público es la misma carpeta.
2. **Los procesos de fondo fallan en silencio.** Adjuntos, resúmenes, transcripción,
   Omnia y backups no tienen una tasa de éxito medida. Solo la entrada de mensajes
   tiene medidor (`salud:ingesta`). Cada falla nueva se descubrió tarde.
3. **Un solo equipo Windows + WSL.** RAM al límite, port forwarding frágil, logs que
   se congelan y un corte de 10 a 60 s en cada reinicio del web. La migración a Linux
   ya está planificada.
4. **El cliente es un navegador sobre HTTP plano.** No hay notificaciones del sistema
   ni portapapeles, y hay que configurar Chrome PC por PC. Hay problemas de caché por
   PC. Cada pantalla consulta el servidor cada 6-8 s. Con la pestaña cerrada no llegan
   avisos. Los datos de salud viajan sin cifrar por la LAN.

**Un cliente de escritorio resuelve la causa 4, pero no la 1, la 2 ni la 3.** Por eso
va después de la base del servidor.

## 3. Hoy → objetivo

```
HOY                                        OBJETIVO
───                                        ────────
C:\crecer (git + producción)               repo (desarrollo) ──tag──► imágenes vX.Y
   │ montado en vivo                                                   │
   ▼                                                                   ▼
contenedores en Windows/WSL                staging ──OK──► producción (deploy / rollback)
   │ HTTP plano, polling 6-8 s                │ HTTPS, eventos en tiempo real (Reverb)
   ▼                                          ▼
Chrome en cada PC                          Cliente "Crecer" (Electron, autoactualizable)
                                             · avisos nativos, bandeja, sonido
                                             · caché local para consultar sin servidor
vigilante + salud:ingesta                  tablero de salud: cada proceso de fondo
                                           con tasa de éxito y alerta por mail
```

## 4. Plan en cuatro etapas

### Etapa 0 — Base firme del servidor (prerrequisito, ~2-3 semanas)
- **Producción separada de desarrollo:** imágenes construidas desde un tag, sin montar
  el working tree. Script `deploy` (construir, migrar, levantar, verificar) y `rollback`.
  **Staging** en el mismo host: segundo proyecto compose, copia anonimizada de la base,
  bot con un número de prueba. El repo público deja de compartir carpeta con producción.
- **Reinicio del web sin corte:** recargar PHP-FPM y OPcache (`kill -USR2`) en vez de
  reiniciar el contenedor.
- **Medir cada proceso de fondo** como se mide la ingesta: adjuntos, resúmenes,
  transcripción, Omnia y backups, en un tablero de salud único con alerta por mail.
  Que también se publique hacia la plataforma de observabilidad de WORKBENCH.
- **HTTPS en la LAN:** certificado de un dominio propio validado por DNS, o una CA interna.
- Con imágenes y deploy por script, la migración a Linux ya planificada se reduce a
  "instalar Docker y correr deploy".

### Etapa 1 — API versionada y tiempo real (~2 semanas)
- `/api/v1` en JSON con tokens (Sanctum), que cubra lo que hoy usa
  `public/js/crecer-v2.js`: colas, conversación, envío, tomar, delegar, archivar y avisos.
- Eventos por Reverb para colas, mensajes y avisos. Se reutilizan los canales existentes
  (`wa.area.{area}`, `recepcion`, chat interno) y `App\Support\AvisoDiferido`. El polling
  queda solo de respaldo.
- El panel web migra a la API de a poco, sin reescritura de golpe.

### Etapa 2 — Cliente de escritorio (Electron, ~3-5 semanas)
Va Electron y no Tauri: el equipo ya lo conoce y existe `panel/` con electron-builder.
- **Fase A — envoltorio nativo del panel:** notificaciones de Windows conectadas a los
  avisos del panel, ícono en la bandeja con contador, sonido, arranque con Windows,
  una sola instancia y pantalla de "sin conexión". **Se actualiza solo desde el servidor**,
  con versión mínima obligatoria, y el servidor registra la versión de cada puesto.
- **Fase B — pantallas nativas del flujo crítico** (bandeja, conversación, recepción
  del día) sobre la API v1, con **caché local SQLite**. Si el servidor o la red fallan,
  se siguen consultando contactos, conversaciones recientes, agenda, checklist y
  procedimientos, y las respuestas quedan en una bandeja de salida. Límite: con el
  servidor caído tampoco hay WhatsApp (los bots viven ahí). Es continuidad de consulta,
  no de mensajería.
- Administración y reportes siguen siendo web, dentro del mismo cliente. Tablet y
  llamador pueden usar un modo kiosco.

### Etapa 3 — Producto para otras clínicas (después de que Crecer esté firme)
- **Una instalación por clínica** (no SaaS multi-cliente): mismas imágenes, configuración propia.
- Configurable: áreas y números, nombres visibles, marca, obras sociales y checklist.
  Omnia pasa a ser un conector intercambiable.
- Instalador de un comando, actualización remota de servidor y cliente, y telemetría
  mínima de versión y licencia.
- Hay que sacar lo fijo de Crecer: IP incrustada en `archivo_url`, nombres de áreas,
  textos y la característica telefónica por defecto.

## 5. Qué NO hacer
- No reescribir Laravel ni el bot, y no partir en microservicios: el núcleo no tiene incidentes.
- No hacer SaaS multi-cliente ni app de celular por ahora.
- No arrancar por el cliente sin la Etapa 0.
- No volver sobre la API Cloud de WhatsApp (descartada).

## 6. Decisiones abiertas
- Dominio y certificado para el HTTPS interno.
- Número de WhatsApp de prueba para staging.
- Staging en el host actual (RAM justa) o directamente en el equipo Linux.
- Electron suma ~200-300 MB por puesto: confirmar qué PC hay en la clínica.

## 7. Primer paso
Reinicio del web sin corte (USR2) y staging, trabajados aislados sin tocar producción.
