# Plataforma Operativa Clínica — Crecer Reproducción

> **Documento maestro** — actualizado al 2026-07-11, reflejando el estado real del código en esa fecha.
> Escrito para dos audiencias: la **Parte I** la puede leer cualquiera (dirección, ventas,
> administración) sin conocimientos técnicos; las **Partes II a VI** son el onboarding técnico
> completo para un equipo de desarrollo que arranca de cero.
>
> Donde este documento contradiga a `README-OPERATIVO.md` o `ARQUITECTURA.md`, **vale este**
> (esos documentos tienen secciones anteriores a la salida de Baileys de junio 2026 y están
> pendientes de actualización — ver §23 Roadmap).

---

## Índice

**PARTE I — Visión general (no técnica)**
1. Qué es la plataforma
2. El problema que resuelve
3. Propuesta de valor — los diferenciales
4. Los módulos, en lenguaje de negocio
5. Números actuales del sistema
6. Qué NO es (límites honestos)

**PARTE II — Funcionalidad detallada**
7. Atención WhatsApp (el corazón del sistema)
8. Recepción, sala de espera y llamador
9. Panel del médico e integración Omnia
10. Contactos, legajo documental y OCR
11. Tareas, agenda e historial
12. Chat interno del equipo
13. Administración, reportes y usuarios

**PARTE III — Arquitectura técnica**
14. Vista de bloques y stack
15. Los 11 servicios Docker
16. El bot de WhatsApp por dentro
17. La aplicación Laravel por dentro
18. Frontend: V2, design system y chat React
19. Seguridad
20. Datos: dónde vive cada cosa

**PARTE IV — Despliegue y operación**
21. Operación diaria, tareas programadas y autocuración
22. Backup, restore y playbooks validados

**PARTE V — Histórico de evolución**
23. Cronología del proyecto y lecciones aprendidas

**PARTE VI — Plan a futuro**
24. Roadmap inmediato, corto, mediano y visión

**Apéndices**
A. Mapa de URLs y puertos · B. Variables de entorno · C. Índice de documentación · D. Glosario

---
---

# PARTE I — VISIÓN GENERAL

## 1. Qué es la plataforma

Es el **sistema operativo diario de una clínica de reproducción asistida**: una plataforma web
autoalojada (corre en un servidor propio de la clínica, no en la nube) que unifica en una sola
pantalla todo lo que el equipo administrativo y médico necesita para operar:

- **Tres líneas de WhatsApp** (una por área: atención clínica, administración y ovodonación)
  atendidas de forma colaborativa por el equipo, con un asistente de inteligencia artificial
  **local** que clasifica cada consulta entrante, la resume en una oración y la deriva a la
  bandeja del área correcta.
- **La sala de espera física**: cola de pacientes, auto-registro por tablet, y un llamador en
  la TV de sala con voz sintetizada.
- **El circuito del médico**: sus pacientes en sala, su agenda del día (traída del sistema de
  turnos Omnia Salud) y el botón de "llamar al paciente".
- **El archivo del paciente**: contactos, documentos con búsqueda por contenido (OCR),
  historial completo de conversaciones.
- **La coordinación interna**: tareas con vencimiento, chat de equipo en tiempo real,
  reportes de operación.

Todo con un sistema de permisos por rol (secretaria, supervisora, administración, médico,
técnico) y auditoría de cada acción sobre una conversación.

## 2. El problema que resuelve

Antes de la plataforma, la operación diaria de la clínica tenía estos dolores:

| Dolor | Cómo lo resuelve la plataforma |
|---|---|
| El WhatsApp de la clínica vivía en **un celular**: una sola persona podía responder, sin visibilidad de qué quedó sin contestar. | Inbox web compartido: todo el equipo ve las tres líneas, cada conversación se "toma", se delega o se deriva; nada queda sin dueño y todo queda auditado. |
| Los mensajes llegaban **mezclados**: turnos, resultados, pagos, donantes — todo junto. | La IA local clasifica cada consulta en 16 categorías, responde automáticamente las triviales (si se habilita) y deriva a un humano las que importan, con un resumen de una oración. |
| Los **audios** de pacientes obligaban a escuchar uno por uno. | Transcripción automática (Whisper, local): el audio llega como texto al panel. |
| **Sin registro**: si una secretaria resolvía algo por WhatsApp, no quedaba rastro. | Cada mensaje, archivo, nota interna y acción (tomar, delegar, resolver, derivar) queda en la base de datos, consultable en el historial. |
| La sala de espera se manejaba **a viva voz**. | Auto-registro por tablet con DNI (valida contra los turnos de Omnia), cola digital y llamador en TV con voz. |
| Los documentos de pacientes se pedían y **se perdían en el chat**. | Legajo documental por paciente: lo que llega por WhatsApp se indexa solo, con OCR para buscar por contenido. |
| Coordinar al equipo era **por grupos de WhatsApp personales**. | Chat interno propio (canal de equipo + mensajes directos) en tiempo real, separado del WhatsApp de pacientes. |

## 3. Propuesta de valor — los diferenciales

Para quien tenga que presentar o vender esto, los cinco argumentos centrales:

1. **Privacidad real: la IA es local.** La clasificación y los resúmenes de conversaciones los
   hace un modelo de lenguaje que corre **en la GPU del servidor de la clínica**. Ningún
   mensaje de paciente sale a OpenAI, Google ni ningún tercero. En un rubro donde las
   conversaciones incluyen resultados de embarazo, tratamientos y donación de gametos, esto no
   es un detalle: es la diferencia entre poder usar IA y no poder usarla. Lo mismo con la
   transcripción de audios: Whisper corre local.

2. **WhatsApp multi-área operado en equipo.** No es "un bot que contesta": es una plataforma
   de atención donde tres números de WhatsApp (clínica, administración, ovodonación) caen en
   bandejas colaborativas con asignación, urgencias, notas internas que el paciente no ve,
   derivación entre áreas, reenvío a externos y trazabilidad completa. El bot automatiza lo
   repetitivo; las personas hacen lo importante, con contexto.

3. **Integrado al circuito físico de la clínica.** Tablet de auto-registro, cola de sala,
   llamador en TV y panel del médico están conectados entre sí y con el sistema de turnos
   existente (Omnia Salud, solo lectura). No reemplaza el sistema de turnos: lo aprovecha.

4. **Autoalojado y autosuficiente.** Corre entero en un servidor de la clínica con Docker.
   Sin cuotas mensuales de SaaS por usuario, sin dependencia de conectividad a servicios
   externos para operar (la única dependencia externa es WhatsApp mismo). Backup diario
   automático y procedimiento de restauración probado, incluyendo la restauración de las
   sesiones de WhatsApp **sin re-escanear códigos QR**.

5. **Resiliencia operativa demostrada.** El sistema se auto-repara en tres capas (healthchecks
   de contenedores, watchdog interno del navegador de WhatsApp, watchdog externo del host) y
   avisa por WhatsApp al técnico si algo se cae más de 10 minutos. En producción real desde
   mayo 2026, con incidentes documentados y resueltos de forma autónoma.

## 4. Los módulos, en lenguaje de negocio

| Módulo | Qué resuelve | Quién lo usa |
|---|---|---|
| **Atención** (3 colas WA) | Responder y gestionar todo el WhatsApp entrante por área | Secretarias, administración |
| **Mis conversaciones** | Ver "lo mío": las conversaciones que cada uno tiene asignadas | Todo el equipo |
| **Recepción** | La cola física de la sala + lo que el bot derivó | Recepción |
| **Tablet** | Auto-registro del paciente al llegar (DNI → turno) | Pacientes |
| **Llamador** | Pantalla TV de sala: "Juan P. → Consultorio 2", con voz | Sala de espera |
| **Médico / Mi consultorio** | Pacientes en sala + agenda Omnia del día + llamar | Médicos |
| **Contactos** | Directorio de pacientes (con importación desde Omnia) | Todo el equipo |
| **Documentos / Legajo** | Archivo documental por paciente con búsqueda por contenido | Secretarias, médicos |
| **Centro de tareas** | Tareas con asignado, vencimiento, prioridad y comentarios | Todo el equipo |
| **Agenda** | Vista de tareas agendadas / llamadas a hacer | Todo el equipo |
| **Historial** | Conversaciones cerradas, consultables | Supervisión |
| **Chat interno** | Comunicación del equipo (canal + directos), tiempo real | Todo el equipo |
| **Reportes** | Actividad de hoy, rendimiento por secretaria, tendencias | Supervisión, dirección |
| **Admin** | Estado de los bots, QRs, textos del bot, usuarios, modo prueba | Técnico, supervisión |

## 5. Números actuales del sistema

*(al 2026-07-11)*

- **13 usuarios** activos, todos operando la interfaz V2 (la nueva) desde el 30/06.
- **3 números de WhatsApp** productivos, cada uno con su bot: atención (+54 9 223 599-7247),
  administración (+54 9 223 346-9767), ovodonación (+54 9 223 456-5067).
- **~7.300 contactos** en el directorio, con foto de perfil de WhatsApp sincronizada y
  vínculo al ID interno de WhatsApp resuelto por proceso nocturno.
- **96,1% de cobertura de resúmenes IA** sobre las conversaciones que lo ameritan (el resto
  tiene el motivo de falla registrado).
- **2,7 GB de media** de pacientes (audios, imágenes, documentos) + legajo documental con OCR.
- Base de datos liviana: el dump comprimido diario pesa ~6 MB.
- **117 commits** en el repositorio actual (2 meses), un solo desarrollador, con dos tags de
  checkpoint antes de cada migración riesgosa.
- Backup automático completo todas las noches (02:30) + dump adicional (03:00) con retención
  7 diarios / 4 semanales / 12 mensuales.

## 6. Qué NO es (límites honestos)

Para vender bien también hay que saber qué no prometer:

- **No es una historia clínica electrónica.** No guarda datos médicos estructurados,
  prescripciones ni resultados de laboratorio. El legajo documental es archivo de documentos,
  no HCE.
- **No reemplaza al sistema de turnos.** Omnia Salud sigue siendo la fuente de turnos y
  pacientes; la plataforma **lee** de Omnia (agenda del médico, validación de llegada,
  importación de contactos) pero no escribe.
- **WhatsApp no oficial.** Los bots usan WhatsApp Web automatizado (whatsapp-web.js), no la
  API oficial de Meta. Funciona de forma estable con mucho hardening (ver §16), pero implica
  un riesgo estructural: Meta puede cambiar WhatsApp Web o suspender números. La migración a
  la **WhatsApp Business API oficial** es el único pendiente estratégico declarado (§24).
- **Single-tenant.** Está construida para una clínica, en su servidor. La generalización a
  producto multi-cliente existe como proyecto paralelo (plantilla CRM), no en este código.
- **El asistente IA no conversa.** Clasifica, resume y deriva. La decisión vigente
  (deliberada) es que el bot **no auto-responde** en las áreas donde el "modo prueba" está
  activo: los textos automáticos existen y son configurables, pero la clínica eligió que
  respondan humanos con la IA como apoyo.

---
---

# PARTE II — FUNCIONALIDAD DETALLADA

## 7. Atención WhatsApp (el corazón del sistema)

### 7.1 El circuito de un mensaje de paciente

1. Un paciente escribe al número de un área. El bot de esa área recibe el mensaje.
2. Si es **audio**, se transcribe automáticamente (Whisper local, castellano). Si es imagen,
   documento o video, se descarga y almacena.
3. El mensaje se **persiste inmediatamente** en Laravel (tabla `mensajes_wa`), con reintentos
   si la app está caída — no se pierde nada.
4. El bot **acumula** los mensajes del mismo contacto durante una ventana de silencio (8 s,
   tope 45 s) para clasificar la consulta completa y no fragmentos.
5. El **clasificador LLM local** (Ollama) asigna uno de 16 códigos (primera consulta, turno,
   resultado beta, consulta clínica, derivar a secretaría, ignorar, etc.) con confianza y,
   si va a un humano, un resumen de una oración en castellano rioplatense.
6. Según el código: los triviales podrían auto-responderse con textos configurables (hoy
   desactivado por decisión operativa — "modo prueba" activo); los que necesitan humano se
   **derivan**: aparecen en la cola del área con su resumen.
7. Una secretaria **toma** la conversación (o se la delegan), responde desde el panel, puede
   dejar **notas internas** que el paciente no ve, adjuntar archivos, usar **respuestas
   rápidas** (plantillas por área), responder a un mensaje puntual (reply), marcar **urgente**,
   crear una tarea vinculada, **derivar a otra área** (el paciente recibe el aviso y la
   conversación pasa a la otra cola/número), **reenviar el hilo completo a un contacto
   externo** (y archivar), o **resolver**.
8. Todo queda auditado en `conversacion_eventos` (quién tomó, delegó, resolvió, derivó, cuándo).

### 7.2 La bandeja

- **Tres colas separadas** en el menú (Atención, Administración, Ovodonación), cada una con su
  badge de pendientes. Decisión de diseño explícita: no se agrupan.
- Vistas por estado: En espera / En proceso / Urgentes, más búsqueda por nombre o teléfono.
- Cada tarjeta muestra: estado, urgencia, contacto (con avatar), resumen IA y quién la tiene.
- **Notificaciones de navegador**: urgente nueva (con tono) y "te delegaron una conversación".
- **Iniciar conversación**: botón "+ Nueva" — busca un contacto o acepta número manual, valida
  que el número exista en WhatsApp (`/check-numero`) antes de crear, con plantillas de
  mensaje inicial.
- El panel de conversación pagina de a 100 mensajes con "ver anteriores" (scroll infinito
  hacia atrás).
- **Mis conversaciones** es la misma experiencia filtrada a "lo mío".

### 7.3 Detalles finos que importan

- Los mensajes **salientes desde el celular** (si alguien responde desde el teléfono físico)
  también se capturan y quedan en el hilo (deduplicados por ID de WhatsApp).
- El **legajo lateral** de cada conversación muestra los datos del contacto, lo agendado, la
  actividad y accesos al legajo documental; si el número no está en contactos, botón de alta
  rápida con vinculación automática de la conversación.
- El ID interno de WhatsApp (`@lid`) **no siempre revela el número real** (privacidad de
  WhatsApp): el alta de contacto desde una conversación huérfana pide el teléfono a mano; un
  proceso nocturno (`contactos:mapear-wa`) resuelve los vinculables.

## 8. Recepción, sala de espera y llamador

- **Tablet** (`/tablet`, pantalla pública): el paciente llega, ingresa su DNI, el sistema
  valida contra los turnos pendientes del día en Omnia y lo mete en la cola de sala
  (`cola_atencion`) con su profesional y práctica.
- **Recepción** (`/v2/recepcion`): dos pestañas — la **cola de sala** (esperando →
  en atención → liberado/resuelto, con reordenamiento, checklist y notas) y la **cola del
  bot** (derivaciones que aún no se convirtieron en conversación gestionada).
- **Llamador** (`/llamador`, pantalla pública por token para la TV): cartel grande con nombre
  abreviado (privacidad) y consultorio, con síntesis de voz. El médico dispara el llamado
  desde su panel; recepción también puede.

## 9. Panel del médico e integración Omnia

- El médico ve **sus pacientes en sala** (los que la tablet/recepción registraron para él) y
  **su agenda del día** traída en vivo de Omnia Salud (mapeada por su `omnia_id`).
- Acciones: **llamar** (dispara el llamador de TV), **re-llamar**, **marcar atendido**.
- La integración Omnia es **server-to-server y 100% de lectura**: login FHIR con JWT (TTL 30
  min, cacheado), endpoints de pacientes por DNI, turnos pendientes y agenda ambulatoria del
  día. Documentada en `docs/omnia-integration-report.md`.
- Los servicios de Omnia se mapean a **planta física** (para el llamador y la cola).

## 10. Contactos, legajo documental y OCR

- **Contactos**: directorio de pacientes con teléfono normalizado (formato argentino,
  incluyendo el histórico `15 + 7 dígitos` sin código de área), DNI, email, `wa_id` (el JID
  real de WhatsApp) y `omnia_patient_id`. Importación masiva desde CSV de Omnia con
  vista previa y deduplicación; importación desde vCard (`contactos:importar-vcf`).
- **Avatares**: foto de perfil de WhatsApp cacheada localmente (TTL 7 días, sync semanal).
- **Legajo documental** (`/v2/pacientes/{id}/documentos`): todo archivo que llega por
  WhatsApp se indexa automáticamente al legajo del paciente; también hay subida manual.
  Vista previa, descarga individual o ZIP, destacar, notas, **reenviar por WhatsApp** y
  eliminación con permiso.
- **OCR full-text**: PDFs e imágenes pasan por poppler/tesseract (castellano) para poder
  **buscar documentos por su contenido**, no solo por nombre.

## 11. Tareas, agenda e historial

- **Tareas** (`tareas` + comentarios): título, descripción, asignado, creador, vencimiento,
  prioridad (baja/normal/alta), estado (pendiente/en progreso/completada) y vínculo opcional
  a una conversación WA. Se crean desde el Centro de tareas o desde el panel de una
  conversación ("🗓 Agendar", que también sirve para agendar llamadas).
- **Centro de tareas**: bandeja con filtros por ámbito (mías / asignadas a mí / creadas por
  mí / todas), toggle de **vencidas**, vista de completadas, edición completa, comentarios,
  y la pestaña de **derivaciones del bot** que el usuario tomó.
- **Agenda**: vista de las tareas con vencimiento (no confundir con turnos — los turnos son
  de Omnia).
- **Historial**: conversaciones archivadas/resueltas, de solo lectura, con búsqueda (permiso
  `historial`).

## 12. Chat interno del equipo

- Un canal **Equipo** (todos) + **mensajes directos** entre usuarios.
- **Tiempo real** vía WebSockets (Laravel Reverb + Echo): sin recargar, con presencia
  (quién está online, TTL 90 s), no-leídos, notificaciones de navegador y eliminación de
  mensajes propios.
- Vive como **widget flotante React** presente en toda la aplicación (V1 y V2), con burbuja
  y panel expandible. Diseñado portable (pensado para reutilizarse en otros proyectos).

## 13. Administración, reportes y usuarios

- **/admin** (permiso `admin`): dashboard con el estado de los **3 bots** (estado, teléfono,
  uptime, **QR de pareo** cuando corresponde), textos de auto-respuesta editables en
  caliente, **modo prueba** por área (el bot clasifica pero no responde) con stream en vivo
  de clasificaciones, logs de los bots en vivo (SSE), configuración del legajo, gestión de
  usuarios (roles, permisos, reset de contraseña con política fuerte), respuestas rápidas
  por área, médicos, control del **túnel de acceso remoto** y estado de los backups.
- **Reportes** (`/v2/reportes`): pestañas Hoy (pulso de la operación), Secretarias
  (rendimiento por persona) y Tendencias, con cache para no castigar la DB.
- **Roles y permisos**: `secretaria`, `supervisora`, `admin`, `tecnico`, `medico`; permisos
  granulares (`secretaria`, `atencion`, `contactos`, `agenda`, `historial`, `admin`,
  `medico`) con defaults por rol y override individual por usuario.
- **Panel Electron** (aplicación de escritorio en el host): utilidad local para levantar/bajar
  Docker, ver los 3 QRs y el estado del sistema sin abrir el navegador. En retirada: casi
  todo migró a `/admin` web (queda como herramienta de emergencia del host).

---
---

# PARTE III — ARQUITECTURA TÉCNICA

## 14. Vista de bloques y stack

```
                                   PACIENTES (WhatsApp)
                                          │
              ┌───────────────────────────┼───────────────────────────┐
              ▼                           ▼                           ▼
      ┌──────────────┐            ┌──────────────┐            ┌──────────────┐
      │ bot atención │            │  bot admin.  │            │   bot ovo    │
      │  Node 3001   │            │  Node 3002   │            │  Node 3003   │
      │ wwebjs+Chrome│            │ wwebjs+Chrome│            │ wwebjs+Chrome│
      └──────┬───────┘            └──────┬───────┘            └──────┬───────┘
             │      clasificar/resumir   │        transcribir        │
             │   ┌─────────────────┐     │     ┌──────────────┐      │
             ├──►│ OLLAMA (LLM GPU)│◄────┼────►│ WHISPER (ASR)│◄─────┤
             │   │ qwen2.5:3b VRAM │     │     │ faster_whisp │      │
             │   └─────────────────┘     │     └──────────────┘      │
             │  POST /bot/* (Bearer BOT_TOKEN)                       │
             └───────────────┬───────────┴───────────────┬───────────┘
                             ▼                           │
   navegadores      ┌──────────────────┐          ┌──────┴──────┐
   del equipo ────► │  NGINX :80       │          │   MYSQL 8   │
   (LAN + túnel)    │  └► WEB php-fpm  │◄────────►│  (interno,  │
                    │     Laravel 11   │          │  sin puerto)│
                    └──────┬───────────┘          └─────────────┘
                           │ broadcast                   ▲
                    ┌──────┴───────┐   ┌──────────────┐  │
                    │ REVERB :8080 │   │ QUEUE-WORKER │──┘ (jobs resumen LLM)
                    │ (WebSockets) │   │ cola resumen │
                    └──────────────┘   └──────────────┘
        ┌──────────┐  ┌─────────┐
        │ AUTOHEAL │  │ WARMUP  │   + host Windows: watchdog-bot.ps1 (5 min),
        │ (docker) │  │(OPcache)│     backups 02:30/03:00, túnel ngrok+broker
        └──────────┘  └─────────┘
```

**Stack:**

| Capa | Tecnología |
|---|---|
| Backend web | Laravel 11 (PHP 8.2-FPM, OPcache+JIT+preload), MySQL 8, Redis (cache/colas/sesión) |
| Tiempo real | Laravel Reverb (WebSockets) + laravel-echo/pusher-js |
| Bots WhatsApp | Node 20 + whatsapp-web.js (Chromium headless vía Puppeteer), 1 contenedor por área |
| IA | Ollama (GPU NVIDIA GTX 1660 6 GB, modelo residente en VRAM) — clasificación y resúmenes |
| Transcripción | onerahmet/openai-whisper-asr-webservice (faster_whisper, modelo `small`, es) |
| Frontend V2 | Blade + JS vanilla (`crecer-v2.js`) + design system CSS propio (`crecer-ds.css`/`crecer-v2.css`) |
| Chat interno | React + TypeScript + Vite + Tailwind v4 (widget embebido) |
| UI legacy V1 | Livewire + Blade (en retiro, ver §23-24) |
| Infra | Docker Compose sobre Docker Desktop + WSL2, host Windows 11 Pro |
| Ops host | PowerShell + Tareas Programadas de Windows (backups, watchdog, túnel) |

Dimensionamiento: single-tenant, ~10-15 usuarios concurrentes, DB de decenas de MB. El
recurso crítico del host es la **RAM de WSL** (12 GB asignados; el bot de atención es el
proceso más pesado, ~1,5 GB por el Chromium de WhatsApp Web).

## 15. Los 11 servicios Docker

Red única `clinica-net`, TZ `America/Argentina/Buenos_Aires` en todos, `restart:
unless-stopped` (salvo warmup). Sin límites de memoria/CPU declarados (salvo la reserva de
GPU de Ollama).

| Servicio | Imagen/Build | Puerto | Rol y detalles clave |
|---|---|---|---|
| **nginx** | nginx:alpine | **80** | Reverse proxy. Gzip, cache 30 d en assets, `client_max_body_size 25M`, bloquea `/.env` y `/.git`, oculta `X-Powered-By`. Espera a `web` healthy. |
| **web** | ./docker/php (8.2-fpm) | — | Laravel. Monta `./app`, `./bot/media:ro` (indexar legajo sin HTTP) y `./backups:ro` (estado de respaldos en /admin). **OPcache con `validate_timestamps=0`**: los cambios PHP requieren `docker compose restart web` (ver §21.1). |
| **warmup** | curlimages/curl | — | Efímero: pega 2 requests a `/login` tras el arranque para compilar OPcache. |
| **mysql** | mysql:8.0 | — (**no publicado**) | DB `clinica`. Binlogs con expiración de 3 días (`--binlog-expire-logs-seconds=259200`). Falla el arranque si falta `DB_ROOT_PASSWORD` en `.env`. Volumen `mysql-data`. |
| **queue-worker** | ./docker/php | — | `queue:work --queue=resumen --tries=2 --timeout=120 --max-time=3600`. Aísla los jobs LLM del FPM. **Sale limpio cada hora por diseño** (`--max-time`) y Docker lo relanza: ~24 "restarts"/día es normal, no un bug. Corre como `www-data`. |
| **reverb** | ./docker/php | **8080** | WebSockets del chat interno. Label `autoheal=true`. |
| **bot** | ./docker/node | **3001** | Bot WhatsApp **atención**. Volumen de sesión `wa-session`. |
| **bot-administracion** | ./docker/node | **3002** | Bot **administración**. Volumen `wa-session-administracion`. |
| **bot-ovodonacion** | ./docker/node | **3003** | Bot **ovodonación**. Volumen `wa-session-ovodonacion`. `WATCHDOG_ZOMBIE_MIN=120` (cola de bajo tráfico: 25 min de silencio son normales). |
| **autoheal** | willfarrell/autoheal | — | Reinicia contenedores `unhealthy` con label `autoheal=true` cada 15 s (gracia 300 s). Cubre el hueco de `restart: unless-stopped`, que no actúa sobre procesos vivos-pero-colgados. |
| **whisper** | onerahmet/...-asr-webservice | — (interno :9000) | ASR audio→texto, modelo `small`, `faster_whisper`, español. |
| **ollama** | ollama/ollama | **11434** | LLM local con GPU (reserva NVIDIA en compose). `OLLAMA_KEEP_ALIVE=-1`: el modelo queda residente en VRAM. Volumen `ollama-data`. |

**Healthcheck de los bots (diseño importante)**: valida el **contenido** de `/status` y
acepta como sano tanto `"listo"` como `"esperando_qr"`. Razón: si un bot está esperando QR,
reiniciarlo solo regenera el QR en loop (incidente del 01/07: autoheal reinició el bot de
atención cada ~7 minutos durante 6 horas). `start_period: 300s` porque la restauración de una
sesión grande tarda.

## 16. El bot de WhatsApp por dentro

Un solo código (`bot/`), tres instancias diferenciadas por `BOT_AREA`/`PORT`. Proceso Node
único por contenedor.

### 16.1 Estructura

| Archivo | Responsabilidad |
|---|---|
| `index.js` | Entrada. Logging triple (stdout + buffer 500 líneas para el panel + archivo en volumen). Handlers globales de errores (loguean, no matan). **Apagado limpio en SIGTERM**: cierra Chromium con `destroy()` (flushea LevelDB) y dispara el snapshot de sesión. |
| `server.js` | Express: todos los endpoints HTTP (ver 16.4), auth Bearer, CORS whitelist, SSE. |
| `whatsapp.js` | Shell de eventos: conecta el cliente WA con la lógica (mensajes, estado). |
| `cliente-wa.js` | Contrato del cliente WA (interfaz documentada). Hoy solo existe el backend `wwebjs`; el selector `BOT_WA_CLIENT` quedó como cáscara histórica tras eliminar Baileys. |
| `clientes/wwebjs.js` | **El adapter real**: Puppeteer/Chromium, sesión, watchdog interno, snapshots, reintentos, timeouts CDP. Es el archivo más crítico del bot (candidato a partirse en módulos — fase C del refactor, opcional). |
| `mensajes.js` | Flujo del mensaje entrante: extracción de texto, debounce, clasificación, respuesta/derivación. |
| `mensajesApi.js` | Persistencia a Laravel (mensajes + media + transcripción Whisper), con reintentos. |
| `cola.js` | Derivación e historial contra Laravel. |
| `ollama.js` | Pipeline LLM: clasificador y resumidor (ver 16.3). |
| `respuestas.js` / `textos.json` | Textos de auto-respuesta por código, editables en caliente desde /admin. |
| `estado-bot.js` | Estado compartido; **modo prueba persistido a disco por área** (`modo-prueba.{area}.json`). |
| `horario.js` / `area.js` / `logger.js` | Ventana horaria de atención, resolución de área, log rotado a archivo. |

### 16.2 Flujo del mensaje entrante (detallado)

1. Evento `message` → **dos caminos en paralelo**:
   - `guardarMensajeEntrante`: descarga media según tipo (audio→ogg, imagen→jpg,
     documento/video), transcribe audio vía Whisper (`/asr?task=transcribe&language=es`),
     y `POST {LARAVEL_URL}/bot/mensajes` con reintentos `[5s, 30s, 120s]` y tope de 200
     pendientes. **La persistencia nunca depende de la clasificación.**
   - `recibirMensaje`: la lógica de atención.
2. **Debounce por contacto**: acumula 8 s de silencio (tope 45 s) antes de clasificar, para
   procesar la consulta completa. La "conversación" se resetea tras 30 min de silencio; el
   historial previo (máx 3.000 chars) se trae de Laravel la primera vez.
3. **Clasificación** → Ollama (ver 16.3). `IGNORAR` corta ahí.
4. **Modo prueba** (por área, persistido): si está activo, **no** se auto-responde — solo se
   registra la clasificación (visible en vivo en /admin) y se deriva si corresponde. Es el
   modo operativo elegido por la clínica hoy.
5. **Derivación**: códigos que necesitan humano (`TURNO_*`, `CONSULTA_CLINICA`,
   `DERIVAR_SECRETARIA`, `RESULTADO_BETA`, `FALLBACK`) → `POST /bot/conversacion/derivar`
   con el resumen LLM → aparece en la cola del área.
6. Los salientes hechos **desde el celular físico** se capturan (`message_outgoing`) y se
   persisten (Laravel deduplica por `wa_id`).

### 16.3 Pipeline LLM

- **Dónde corre**: en el bot (`ollama.js`), contra el contenedor Ollama (GPU). Laravel nunca
  llama a Ollama directo: para resúmenes usa `POST {bot}/resumir` (job `GenerarResumenLLM`
  en la cola `resumen`, resuelto por área para que la caída de un bot no afecte a las otras).
- **Clasificador** (`procesarConversacion`): system prompt con 16 códigos cerrados, salida
  JSON forzada (`format:'json'`, `temperature:0`), timeout 25 s, parser robusto (busca el
  último bloque `{...}` válido). Los códigos que van a humano incluyen `resumen`.
- **Resumidor** (`generarResumen`): 1 oración ≤30 palabras, castellano rioplatense forzado,
  `temperature:0.2`, timeout 60 s. Post-proceso: strip de emojis (evita drift a portugués),
  descarte si <5 chars o si detecta portugués.
- **Circuit breaker**: 3 fallos seguidos de Ollama → 5 min sin llamar (devuelve `FALLBACK`),
  evita apilar timeouts en el event loop.
- **Modelo efectivo**: `qwen2.5:3b` (fijado en `bot/.env`, que pisa el default del código).
  ⚠ *Discrepancia conocida*: el default hardcodeado y los comentarios del compose dicen
  `qwen3:4b`, y los prompts están afinados para el comportamiento `think:false` de qwen3.
  Unificación pendiente (§24). Contexto histórico: qwen2.5 tuvo un bug grave donde el
  prefijo `/no_think` se convertía en la clave del JSON de salida (resuelto el 07/07).

### 16.4 Endpoints HTTP del bot

Auth: Bearer `BOT_INGRESS_TOKEN` en todo salvo `/status` (público, sin QR — decisión de
seguridad). SSE acepta `?token=`. **Fail-closed**: sin token configurado, todo devuelve 503.

| Endpoint | Uso |
|---|---|
| `GET /status` | `{status, has_qr, phone, uptime, area}` — healthcheck y monitoreo |
| `GET /qr` | Data-URL PNG del QR de pareo (para /admin y Electron) |
| `GET /logs` (SSE) | Stream de logs en vivo |
| `GET/POST /config` | Lee/escribe `bot/.env` (POST requiere reinicio) |
| `GET/POST /textos` | Textos de auto-respuesta (aplica en caliente) |
| `GET /pruebas`, `/pruebas/stream` (SSE), `POST /pruebas/modo` | Modo prueba + clasificaciones en vivo |
| `POST /clasificar`, `POST /resumir` | LLM (resumir lo llama Laravel) |
| `POST /check-numero` | ¿El número existe en WhatsApp? → JID normalizado |
| `POST /resolve-jid`, `POST /profile-pic` | Resolución de JID y avatar |
| `POST /enviar`, `POST /enviar-archivo` | Envío de texto (con reply opcional) y media |
| `GET /media/*` | Static de media (detrás del token; el navegador pasa por Laravel `/wa-media`) |

**Regla de oro operativa** (aprendida del incidente "bombardeo CDP" del 19/05): **nunca
llamar a los endpoints del bot en bucle desde Laravel**. Cada llamada es una operación CDP
contra Chromium; en loop (por request de panel o cron sin límite) satura y cuelga el bot.
Los procesos masivos van con `--limit`, throttle y caché.

### 16.5 Robustez (las 3 capas de autocuración + sesiones)

**Capa 1 — dentro del bot** (`clientes/wwebjs.js`, watchdog v3 del 07/07, cada 5 min):
- `bringToFront()` preventivo + flags anti-throttling de Chromium (headless congela
  pestañas de fondo).
- Sonda **"browser vivo"**: `pupBrowser.version()` (timeout 30 s); 3 fallos → reinicio
  interno (Chromium congelado).
- Sonda **"página zombie"**: `pupPage.evaluate(()=>1)`; si no responde **y** hay
  inactividad mayor al umbral (`WATCHDOG_ZOMBIE_MIN`, default 15 min, 120 en ovo) → 2
  strikes → reinicio. La actividad reciente protege contra falsos positivos.
- Reinicio interno = `destroy()` + limpieza de caché Chromium + re-inicialización.
- Timeouts en todas las operaciones CDP (texto 45 s, media 90 s, checks 15 s) para que los
  endpoints HTTP nunca queden colgados.
- Reintentos de conexión con backoff exponencial (15 s → 5 min) y espera de DNS al arrancar.

**Capa 2 — Docker**: healthcheck de contenido (§15) + `autoheal` reinicia los `unhealthy`.

**Capa 3 — host Windows** (`docker/watchdog-bot.ps1`, cada 5 min): verifica que Docker
Desktop corra (lo relanza si no), chequea `/status` de los 3 bots, y ante incidente
sostenido >10 min hace `docker restart` del contenedor afectado (rate-limit 1/5 min),
**salvo** en `esperando_qr` (solo alerta). **Notifica por WhatsApp al técnico** al cruzar el
umbral y al recuperarse, intentando el envío por 3001→3002→3003 (el caído puede ser el
emisor).

**Sesiones (lo más valioso del bot)**:
- Viven en volúmenes Docker (`wa-session*`) montados en `/app/.wwebjs_auth`: el perfil de
  Chromium (IndexedDB/LocalStorage/Cookies) **es** la sesión de WhatsApp.
- **Apagado limpio** (SIGTERM): `destroy()` flushea LevelDB → evita la corrupción que
  históricamente mataba sesiones en reinicios del host.
- **Snapshot + auto-restauración**: en cada apagado limpio se copia `session/` →
  `session-snapshot/` con un marker (`sesion-valida.json`). Si al arrancar aparece un QR
  pese a haber sesión válida marcada, se asume corrupción local y se restaura el snapshot
  (máx 2 intentos) antes de rendirse al QR.
- **Pin de versión de WhatsApp Web** (`WA_WEB_VERSION`, build conocida-buena) + user-agent
  fijo: protege de builds malas que WhatsApp rollea (incidente del 12/06).
- **Limpieza de caché Chromium** (preserva la sesión): el Code Cache de V8 crece y provoca
  cuelgues CDP (`Runtime.callFunctionOn timed out`). Se limpia en cada reinicio del
  watchdog y cada noche dentro del backup (§22). La tarea programada vieja `CleanBotCache`
  (04:00) quedó **deshabilitada** por redundante desde que el backup 02:30 limpia el caché
  en su misma ventana de parada.
- Backup nocturno de las sesiones en tars + **restauración sin QR validada** (§22.2).

## 17. La aplicación Laravel por dentro

### 17.1 Controllers (16)

| Controller | Responsabilidad |
|---|---|
| `AtencionController` | Núcleo del inbox WA: colas por área, conversación (con paginación por cursor), enviar texto/archivo, tomar/delegar/urgente/resolver/reabrir, derivar área, reenviar externo, iniciar conversación, respuestas rápidas, agregar contacto. Sirve V1 y V2. |
| `BotController` | API entrante **desde** los bots: mensajes, derivaciones, marcar leído, historial. |
| `RecepcionController` | Cola de sala + cola bot para V2 (reescritura REST de los Livewire). |
| `MedicoController` | Panel médico: sala, agenda Omnia, llamar/rellamar/atendido. |
| `LlamadorController` | Pantalla TV pública (token en URL, datos mínimos). |
| `ContactoController` | CRUD contactos + import CSV Omnia (preview/confirm). |
| `DocumentoController` | Legajo documental: upload, preview, ZIP, reenvío WA, OCR. |
| `TareaController` / `AgendaController` | Tareas: CRUD + comentarios / vista agendada con filtros. |
| `ChatController` | Chat interno: canales, mensajes, presencia, no-leídos. |
| `AdminController` | Panel admin: bots, textos, pruebas, logs SSE, usuarios, túnel, respuestas rápidas, médicos. |
| `EstadisticasController` | Reportes (hoy/secretarias/tendencias, con cache). |
| `UsuariosController` | API de usuarios (consumida por el panel Electron). |
| `V2Controller` | Data inicial de las pantallas `/v2/*`. |
| `WaMediaController` | Sirve la media de WA **con auth de sesión** (no URLs públicas). |
| `Controller` | Base. |

### 17.2 Modelos y tablas

- `users` (rol, permisos JSON, `ui_pref`, lockout) · `medicos` (con `omnia_id`) ·
  `sesiones_secretaria` (jornadas de trabajo).
- **WhatsApp**: `conversaciones_wa` (área, estado, asignada_a, `resumen_llm`, urgente,
  no_leidos) → `mensajes_wa` (dirección, tipo, contenido, archivo, `wa_id`, quoted_*) →
  `conversacion_eventos` (auditoría) → `tareas_wa` · `derivaciones` (lo que el bot deriva).
- `contactos` (teléfono normalizado, `wa_id`, DNI, `omnia_patient_id`, avatar) →
  `documentos_paciente` (legajo, con texto OCR).
- `tareas` + `tarea_comentarios` (con `ref_tipo/ref_id` hacia conversaciones).
- `cola_atencion` (sala de espera física, con `omnia_turno_id`).
- Chat interno: `chat_canales` + `chat_canal_user` (pivote, autoriza los broadcasts) +
  `chat_mensajes`.
- `respuestas_rapidas` (plantillas por área).

### 17.3 Middleware, jobs y comandos

- **Middleware**: `SecretariaAuth` (auth + activo + colas declaradas) envuelve la web;
  `permiso:{x}` (CheckPermiso) por sección; `BotTokenAuth` protege la API de los bots.
- **Job** `GenerarResumenLLM` (cola `resumen`): resume con los últimos 25 mensajes
  **entrantes**, `WithoutOverlapping` por conversación, throttle de reintento 10 min,
  llama al `/resumir` del bot del área.
- **Comandos artisan** (closures en `routes/console.php`, estilo Laravel 11 — no hay
  Kernel ni scheduler interno; la programación temporal vive en Windows):
  `contactos:importar-vcf`, `contactos:auditar-telefonos`, `contactos:sync-avatares`,
  `contactos:mapear-wa` (con `--limit` y `--max-errors` — ver regla de oro §16.4),
  `documentos:sync`, `documentos:ocr-rescan`, `conversaciones:regenerar-resumenes`
  (backfill de resúmenes).

## 18. Frontend: V2, design system y chat React

- **V2 (la interfaz actual, default desde el 30/06)**: Blade + JS vanilla. Sin framework:
  `public/js/crecer-v2.js` expone `window.V2` (helpers fetch/esc/avatar) y `window.V2Conv`
  (el panel de conversación completo: timeline con paginación, composer, reply, modales de
  derivar/reenviar/iniciar/tarea/contacto). Las vistas `resources/views/v2/*.blade.php`
  traen la data inicial server-side y pollean endpoints REST (bandeja 8 s con ETag/304,
  panel 10 s preservando scroll y texto tipeado). `crecer-notify.js` da el módulo
  `window.Notify` (Notification API).
- **Design system**: `crecer-ds.css` (tokens: colores — el rojo Crecer institucional —,
  tipografía, espaciado, componentes) + `crecer-v2.css` (el shell V2: bandeja/detalle/
  legajo, pills, cards, dialogs). Cache-busting automático por `?v=filemtime`.
- **Chat interno**: widget React+TS (`resources/js/chat/`), bundleado con Vite, realtime
  por Echo/Reverb. Es la única pieza React; el resto de V2 es deliberadamente vanilla
  (decisión: una sola arquitectura simple, sin build para el 90% de la UI).
- **V1 (legacy)**: Livewire + Blade, accesible por el toggle "UI clásica" (`users.ui_pref`).
  Programada su eliminación (gate 13/07, ver §24).

## 19. Seguridad

- **Perímetro**: solo nginx (:80), Reverb (:8080), los 3 bots (:3001-3003, con token) y
  Ollama (:11434) publican puertos. MySQL **no** publica — solo red interna Docker.
- **Tokens**: `BOT_INGRESS_TOKEN` (Laravel/panel → bot, 256 bit, fail-closed) y `BOT_TOKEN`
  (bot → Laravel, middleware). Rotación = actualizar `app/.env`, `bot/.env` y
  `panel/preload.js` + restart. El QR **no** está en `/status`: endpoint propio con token.
- **Media de pacientes**: los archivos de WhatsApp se sirven vía `WaMediaController` con
  **sesión autenticada** (reemplazó URLs públicas del bot que exponían audios con el
  teléfono del paciente en el nombre a toda la LAN). Validación anti path-traversal.
- **Uploads**: whitelist de MIME + blacklist de extensiones peligrosas aunque el MIME pase.
- **Login**: política de contraseñas (≥10 chars, no solo dígitos, lista de comunes
  prohibidas, no contener nombre/email) + lockout 5 intentos / 15 min con
  **anti-enumeración** (mensajes genéricos, solo cuenta intentos de usuarios reales).
- **CORS** del bot: whitelist explícita. **nginx**: bloquea `/.env`/`.git`.
- **Backups**: contienen credenciales y datos de pacientes → la carpeta `backups/` exige
  destino restringido (advertido en el README de restore).
- **Pendientes declarados** (compliance "Etapa 6", §24): 2FA, cifrado en reposo de
  media/backups, export Habeas Data (Ley 25.326), auditoría de lecturas.

## 20. Datos: dónde vive cada cosa

| Dato | Dónde | Respaldo |
|---|---|---|
| Código | `C:\crecer` (repo git) + GitHub `Damianf0/crecer-bot` | git + bundle nocturno |
| Secretos | `.env` raíz, `app/.env`, `bot/.env` (**no** en git) | `backups/full/config/` |
| Base de datos | volumen `mysql-data` | dumps 02:30 y 03:00 con retención |
| Sesiones WhatsApp | volúmenes `wa-session*` | tars nocturnos + snapshot interno |
| Media WA (entrante) | `C:\crecer\bot\media` (bind mount) | espejo nocturno |
| Storage Laravel (docs, avatares, salientes) | `C:\crecer\app\storage` | espejo nocturno |
| Modelos LLM | volumen `ollama-data` | no (re-descargable) |
| Logs de bots | `C:\crecer\bot\logs` (rotados) | no (operativos) |
| **Todo junto** | **`C:\crecer` (~11 GB) es autocontenido**: copiar esa carpeta = copia total del sistema | copia externa manual |

---
---

# PARTE IV — DESPLIEGUE Y OPERACIÓN

## 21. Operación diaria, tareas programadas y autocuración

### 21.1 Cómo aplicar cambios (la trampa de OPcache)

- **PHP/Blade**: el contenedor `web` corre OPcache con `validate_timestamps=0` (máxima
  performance). Los cambios **no** se ven hasta `docker compose restart web` (hay un 502
  transitorio de ~15-30 s mientras FPM recompila; `warmup` ayuda). Aplica también a vistas.
- **JS/CSS**: sin restart — se sirven estáticos con cache-busting `?v=filemtime`.
- **Código del bot**: `docker compose restart bot bot-administracion bot-ovodonacion`
  (cada bot tarda 1-2 min en volver a `listo`; la sesión se preserva por el apagado limpio).
- **compose/.env**: `docker compose up -d` (recrea lo cambiado).

### 21.2 Tareas programadas (Windows Task Scheduler — no hay cron interno de Laravel)

| Tarea | Cuándo | Qué hace |
|---|---|---|
| `Crecer\BackupFull` | diaria 02:30 | Backup total (§22.1), incluye limpieza de caché Chromium por bot |
| `Crecer\BackupMySQL` | diaria 03:00 | Dump adicional con retención 7d/4w/12m |
| `Crecer\CleanBotCache` | **deshabilitada** | Redundante desde que el backup limpia caché (se conserva el script) |
| `Crecer\MapearWA` | diaria 04:30 | `contactos:mapear-wa --limit=300 --max-errors=10` |
| `Crecer\SyncAvatares` | domingos 05:00 | Refresco de fotos de perfil (TTL 7 días) |
| `Crecer\WatchdogBot` | cada 5 min | Watchdog de host (§16.5 capa 3) |
| `CrecerTunnelWatchdog` | cada 2 min + logon + boot | Revive el broker del túnel |
| `CrecerCleanWSLDumps` | programada | Borra dumps de crash de WSL que inflan disco |

### 21.3 Acceso remoto (túnel)

- `ngrok http 80` con dominio reservado estático, gestionado por un **broker** HTTP local
  (`127.0.0.1:9091`, token autogenerado persistido) que Laravel consume: `/admin` tiene
  start/stop/status del túnel. El broker se auto-arranca (shortcut de Startup) y arranca el
  túnel solo; el watchdog lo revive con probe funcional (cualquier respuesta HTTP = vivo,
  incluso 401 — el "puerto escuchando" no alcanza porque HTTP.sys retiene la URL).
- Uso declarado: **testing/soporte**, no operación crítica.

### 21.4 Peculiaridades del host (Windows + WSL2) — leer antes de operar

- **`wsl --shutdown` rompe el port-forwarding**: al reanudar, Docker Desktop levanta los
  contenedores pero los puertos publicados no responden hasta `docker compose restart` de
  los servicios con puertos. Con arranque fresco de Docker Desktop + `up -d` no pasa.
- **`docker logs` puede congelarse** tras eventos de WSL aunque el contenedor siga vivo:
  **no diagnosticar por `docker logs`** — usar `/status` y los logs a archivo
  (`bot/logs/bot-{area}.log`).
- **Compactar el VHDX de Docker** (recupera decenas de GB): playbook validado 2 veces
  (§22.3).
- El cuello de botella del host es la **RAM de WSL** (12 GB asignados), no CPU/DB.

## 22. Backup, restore y playbooks validados

### 22.1 Qué respalda el backup nocturno (`backup-full.ps1`, 02:30 → `backups/full/`)

1. **Dump MySQL** comprimido (retiene 3).
2. **Espejo de `bot/media`** (robocopy /MIR).
3. **Espejo de `app/storage`** (sin `framework/`).
4. **Config**: los 3 `.env` + `docker-compose.yml` (lo que no está en git).
5. **`repo/crecer.bundle`**: historia git completa.
6. **Sesiones WA**: por bot — stop limpio → limpieza de caché → tar del volumen → start
  (~1 min de corte por bot; rota el tar anterior a `.prev` y lo restaura si el nuevo sale
  vacío). Única parte con corte de servicio.
7. Domingos: higiene Docker (`builder prune` + `image prune`).

Más `backup-mysql.ps1` (03:00) con retención 7 diarios / 4 semanales / 12 mensuales.
**Pendiente estructural conocido: copia offsite automática** (hoy la copia externa es manual).

### 22.2 Restore (resumen; el paso a paso vive en `docker/README-RESTAURAR.md`)

En máquina nueva: clonar del bundle → copiar los `.env` → levantar mysql y cargar el dump →
**restaurar las sesiones WA descomprimiendo los tars dentro de volúmenes recién creados
(los bots levantan `listo` sin re-escanear QR — validado el 05/07 con un tar del día
anterior)** → copiar media/storage → `up -d` → verificar `/status` de los 3 bots.
Restauraciones parciales típicas: una sola sesión, solo la DB, o un archivo puntual.

### 22.3 Playbooks operativos validados

- **Restaurar una sesión WA sin QR** (05/07): parar el bot, vaciar el volumen, destarar el
  backup, arrancar. WhatsApp no invalida el pareo por el reemplazo de archivos.
- **Compactar `docker_data.vhdx`** (26/05: 71,6→43,9 GB; 09/07: 59→36,4 GB): prune →
  `compose stop -t 60` (apagado limpio de bots) → cerrar Docker Desktop → `wsl --shutdown`
  → diskpart elevado (`attach readonly` → `compact` → `detach`) → relanzar. Nota: el
  servicio `com.docker.service` hoy viene Manual/Stopped y ya no interrumpe el compact
  (en mayo había que deshabilitarlo).
- **Cuelgue "autenticada sin ready"**: el watchdog/autoheal lo resuelve solo (reinicio
  limpio); si reincide, re-pareo limpio (wipe de sesión + QR).
- **Bot cuelga con `Runtime.callFunctionOn timed out`**: caché Chromium hinchada → limpiar
  caché sin tocar la sesión (automatizado; manual: `clean-bot-cache.ps1`).

---
---

# PARTE V — HISTÓRICO DE EVOLUCIÓN

## 23. Cronología del proyecto y lecciones aprendidas

### 23.0 Prehistoria (abril – comienzos de mayo 2026, pre-repo actual)

El proyecto nació antes del repositorio actual (el repo se re-inicializó el 09/05 con un
saneo de credenciales para poder publicarlo). De esa etapa vienen: el bot único original y
el panel de atención, el **hardening del 27/04** (auth del bot, CORS, MySQL cerrado,
healthchecks, backup automático, paginación), la feature de **iniciar conversación**
(27/04), la **auditoría de teléfonos** (29/04), los **avatares de WhatsApp** (03/05), la
**política de contraseñas y lockout** (03/05), las **notificaciones de navegador** (04/05)
y la **normalización de teléfonos argentinos** (04/05) que recuperó el 91% del CSV de Omnia.

### 23.1 Arco 1 — Construcción y multi-área (mayo, 61 commits)

- **09/05** — repo actual: "Plataforma Operativa Clínica" + saneo de credenciales.
- **10/05** — panel del médico, llamador para TV, protección CSRF.
- **11-12/05** — **multi-WhatsApp por área**: el salto de 1 bot a 3 bots (uno por número),
  con QRs en /admin, derivación entre áreas y watchdog v1 (Chromium congelado + caché).
- **13/05** — split de `/mis-tareas` en mis-conversaciones + centro de tareas; respuestas
  rápidas por área; primer fix del LLM (resumen en portugués → JSON forzado + guard).
- **18-19/05** — capa adapter del cliente WA (wwebjs/Baileys) y **migración a Baileys**;
  refactor del chat interno a **tiempo real** (Reverb + React + Echo).
- **19/05** — incidente **"bombardeo CDP"**: Laravel llamaba al bot en bucle (resolución de
  JIDs desde el panel + cron sin límite) y lo colgaba cada 20-30 min. Origen de la regla de
  oro de §16.4.
- **20-29/05** — la **guerra Baileys vs wwebjs**: rollbacks sucesivos; Baileys resultó
  inestable (el peor bug: tras un re-pareo, los receptores con sesión cacheada vieja
  recibían mensajes ilegibles — asimétrico y silencioso).
- **25-26/05** — watchdog del túnel; **compactación VHDX** (71,6→43,9 GB) y expiración de
  binlogs.

### 23.2 Arco 2 — Reinvención visual: la V2 (junio, 34 commits)

- **04/06** — `ARQUITECTURA.md` (primer doc formal).
- **09/06** — tag `estable-pre-fase3` + **design system** (`crecer-ds.css`).
- **10-17/06** — **PoC V2** (`/v2/atencion` en paralelo a producción, sin tocar lo estable)
  y portado pantalla por pantalla: mis-conversaciones, centro de tareas, historial,
  contactos, agenda, mi-día, chat, admin, reportes.
- **15-16/06** — **salida definitiva de Baileys** (los 3 bots a wwebjs, cada uno pareado
  con su número correcto — se resolvió de paso un cruce de sesiones que venía del ciclo de
  rollbacks) + pin de versión de WA Web y `protocolTimeout` 6 min.
- **26/06** — logger a archivo (los `docker logs` congelados por WSL dejaron de ser el
  único ojo), autoheal, re-pareo limpio documentado.
- **29-30/06** — plan de migración formal, `ui_pref` por usuario, tag
  `estable-pre-cutover-v2` y **FLIP: V2 default para los 13 usuarios** (30/06). V1 queda
  como toggle "UI clásica".

### 23.3 Arco 3 — Robustez operativa y paridad (julio, 22 commits)

- **01/07** — incidente de reinicio de WSL que mató la sesión de atención → **hardening
  wwebjs** (corte del loop de QR, circuit breaker Ollama, reintentos), watchdog a los 3
  bots, `backup-full.ps1`, RAM WSL a 12 GB, Ollama residente en VRAM.
- **05/07** — **playbook de restauración de sesión sin QR validado** + **apagado limpio +
  snapshot + auto-restauración** (fin de la dependencia del QR); watchdog v2 (sonda al
  proceso browser, mató un falso positivo que reiniciaba ovo cada 15 min).
- **06/07** — noche perfecta (ciclo autónomo completo, 0 incidentes); **fase A del
  refactor**: eliminación total de Baileys del código + cluster Livewire muerto.
- **07/07** — incidente del fin de semana (el watchdog v2 dejaba **páginas zombie**:
  atención 21 h sin recibir) → **watchdog v3** (sonda de página × inactividad +
  bringToFront preventivo). Saga de cobertura de resúmenes LLM resuelta (4 causas
  apiladas; cobertura 66% → 96,1%).
- **08-09/07** — **auditoría de paridad V1→V2** (disparada por un reply faltante):
  inventario de cada acción de usuario, matriz `docs/PARIDAD-V2.md`, y cierre de los 8
  gaps ALTA+MEDIA (reply, derivar área, reenviar, paginación, iniciar conversación,
  notificaciones, filtros de tareas, editar tarea). **Compactación VHDX #2** (59→36,4 GB).

### 23.4 Lecciones aprendidas (pagadas con incidentes)

1. **Nunca llamar al bot en bucle desde Laravel** — cada request es una operación CDP;
   caché, `--limit` y fuera del hot path (bombardeo CDP, 19/05).
2. **Un smoke-test de endpoints no prueba paridad de UI**: "el endpoint responde" no
   implica "el botón que lo llamaba existe". Toda migración de UI se audita con inventario
   de features **antes** del flip (lección del reply perdido, 08/07).
3. **Checkpoint antes de cada migración riesgosa**: tags `estable-pre-*` + backup. Los dos
   flips grandes (design system, V2) tuvieron su punto de rollback declarado.
4. **Apagado limpio > recuperación heroica**: el 90% de las sesiones WA "perdidas" eran
   corrupción de LevelDB por apagados sucios. SIGTERM manejado + snapshot lo erradicó.
5. **No confiar en `docker logs` en este host** (WSL los congela): logs a archivo + `/status`.
6. **Los watchdogs también fallan**: v1 (reinicio ciego) → v2 (falso positivo) → v3
   (sonda de página × inactividad). Cada iteración salió de un incidente real; los umbral
   es son configurables por bot porque las colas tienen tráficos distintos.
7. **Con LLMs chicos, desconfiar del éxito silencioso**: el bug `/no_think` de qwen2.5
   producía JSON "válido" con la clave equivocada y se descartaba mudo. Stats honestas +
   motivo de falla logueado (anti-null-mudo).
8. **Evitar rollbacks de librería de sesión WA**: el ida-y-vuelta wwebjs↔Baileys generó
   cruces de sesión e identidades rotas asimétricas. Elegir y quedarse.

---
---

# PARTE VI — PLAN A FUTURO

## 24. Roadmap

### Inmediato (esta semana)

- **Gate Fase 4 — retiro de V1 (lunes 13/07)**: con los 8 gaps ALTA+MEDIA cerrados y 13/13
  usuarios en V2 sin opt-outs, borrar la UI legacy: ~8.200 líneas (Livewire `InboxWA`,
  `GestionAtencion` ya eliminado, blades V1, `layouts/app` y el toggle `ui_pref`).
  Criterio objetivo en `docs/PARIDAD-V2.md`; los gaps BAJA (9-16) no bloquean.
- **Actualizar la documentación con drift**: `README-OPERATIVO.md` aún describe Baileys y
  `bot-test`; `ARQUITECTURA.md` y comentarios del compose difieren en el modelo LLM.
  (Este documento es la referencia vigente mientras tanto.)

### Corto plazo (semanas)

- **Backup offsite automático** — el único faltante estructural del esquema de respaldo
  (hoy la copia externa es manual; el destino sugerido ya está parametrizado en el script).
- **Unificar `OLLAMA_MODEL`** (`.env` dice qwen2.5:3b, el código default qwen3:4b) y
  evaluar qwen3:4b/8b vs qwen2.5:3b para clasificación y resumen (los prompts ya están
  afinados para qwen3).
- Pendientes de infra menores: tarea post-boot del host, rotar password root de MySQL,
  arreglar el healthcheck de reverb (falso negativo con `nc`), formalizar el broker del
  túnel como tarea programada (hoy es shortcut de Startup + watchdog).
- **Aislar la lentitud percibida** reportada el 03/06 (el backend mide bien; sospecha en
  frontend/red local/polling): medir de punta a punta ahora que V2 es la única UI.
- Soft-cleanup del **panel Electron** (quitar las tabs ya migradas a /admin; conservar
  QRs + control Docker local).

### Mediano plazo (meses)

- **WhatsApp Business API oficial** — el único pendiente estratégico declarado. Elimina el
  riesgo estructural de la automatización de WhatsApp Web (§6). Implica: proveedor/BSP,
  plantillas aprobadas, ventana de 24 h, y rediseño del flujo de auto-respuesta. El
  adapter `cliente-wa.js` ya define el contrato que un backend "waba" debería implementar.
- **Compliance Etapa 6**: 2FA, cifrado en reposo de media/backups, export Habeas Data
  (Ley 25.326), auditoría de lecturas/exports.
- **Fase C del refactor** (opcional, baja prioridad): partir `clientes/wwebjs.js` en
  módulos (sesión/watchdog/envío).
- **Features IA de la V2** (del mockup original, hoy señalizadas como "a futuro" en la UI):
  sugerencias de respuesta, detección de sentimiento/urgencia más fina, resumen del día.

### Visión (trimestres)

- **Generalización a producto**: existe un proyecto paralelo (plantilla CRM,
  `github.com/Damianf0/crm-template`) que extrae los patrones de esta plataforma
  (inbox multi-canal colaborativo + chat interno portable + design system) para
  instanciarlos en otros rubros/clientes. Este repo sigue siendo single-tenant; la
  separación limpia entre "árbol publicable" y deploy productivo es un pendiente conocido.
- **Observabilidad**: hoy hay logs + watchdogs + notificaciones WhatsApp; una capa de
  métricas históricas (uptime por bot, latencias, volumen por área) daría soporte a
  decisiones de capacidad y al argumento comercial de resiliencia.

---
---

# APÉNDICES

## A. Mapa de URLs y puertos

**Aplicación (LAN, `http://192.168.1.125` / `http://localhost`):**

| URL | Qué es |
|---|---|
| `/` | Home según rol y preferencia |
| `/v2/mi-dia`, `/v2/atencion/{area}`, `/v2/mis-conversaciones`, `/v2/recepcion`, `/v2/medico`, `/v2/contactos`, `/v2/pacientes/{id}/documentos`, `/v2/centro-tareas`, `/v2/agenda`, `/v2/historial`, `/v2/reportes`, `/v2/admin/*` | La interfaz V2 (default) |
| `/admin` | Panel de administración (bots, QRs, usuarios, túnel) |
| `/llamador?token=…` | TV de sala (pública por token) |
| `/tablet` | Auto-registro (pública en la tablet) |
| `/wa-media/{file}` | Media de WhatsApp (requiere sesión) |
| `/cambiar-ui/{v1\|v2}` | Toggle de interfaz (hasta el retiro de V1) |

**Servicios (host):** web **:80** · Reverb **:8080** (WebSocket) · bots **:3001/:3002/:3003**
(Bearer token, `/status` público) · Ollama **:11434** · broker túnel **127.0.0.1:9091**
(Bearer token local). MySQL y Whisper: solo red interna Docker.

## B. Variables de entorno (nombres, sin valores)

- **`.env` raíz**: `DB_ROOT_PASSWORD` (la exige el compose).
- **`app/.env`** (Laravel): DB_*, `QUEUE_CONNECTION`, `REVERB_*`/`VITE_REVERB_*`,
  `BOT_URL` / `BOT_URL_ADMINISTRACION` / `BOT_URL_OVODONACION`, `BOT_INGRESS_TOKEN`
  (saliente hacia bots), `BOT_TOKEN` (entrante desde bots), `OMNIA_BASE_URL/USER/PASSWORD`,
  `TUNNEL_BROKER_URL/TOKEN`, `AVATAR_TTL_DAYS`.
- **`bot/.env`** (compartido por los 3 bots): `OLLAMA_URL`, `OLLAMA_MODEL`, `WHISPER_URL`,
  `LARAVEL_URL`, `LARAVEL_TOKEN`, `BOT_INGRESS_TOKEN`, `BOT_PUBLIC_URL`, `ALLOWED_ORIGINS`,
  `ESPERA_MENSAJES`, `ESPERA_MAXIMA`, `RESET_CONVERSACION`, `HORARIO_INICIO/FIN/SAB_FIN`,
  `NGROK_AUTHTOKEN` (la usa el broker, no el bot).
- **Por contenedor (compose)**: `BOT_AREA`, `PORT`, `BOT_PUBLIC_URL`,
  `WATCHDOG_ZOMBIE_MIN` (solo ovo). Opcionales con default: `BOT_WA_CLIENT`,
  `WA_WEB_VERSION`, `WATCHDOG_*`, `BOT_LOG_*`.

## C. Índice de la documentación existente

| Documento | Cubre | Estado |
|---|---|---|
| **`docs/DOCUMENTACION-GENERAL.md`** | **Este documento** — visión, funcionalidad, arquitectura, operación, historia, roadmap | vigente (2026-07-11) |
| `README-OPERATIVO.md` | Guía día-a-día: comandos, troubleshooting, URLs | ⚠ secciones WA desactualizadas (describe Baileys) |
| `ARQUITECTURA.md` | Primer doc de arquitectura con diagrama | ⚠ parcialmente superado por este |
| `CLAUDE-clinica.md` | Contexto histórico y propósito | vigente como historia |
| `docker/README-RESTAURAR.md` | Restore paso a paso en máquina nueva | vigente |
| `docs/MIGRACION-V2.md` | Plan e inventario de la migración V1→V2 | vigente hasta el retiro de V1 |
| `docs/PARIDAD-V2.md` | Matriz de paridad, criterio del gate 13/07 | vigente |
| `docs/DESIGN-SYSTEM.md` | Tokens y componentes CSS | vigente |
| `docs/omnia-integration-report.md` | Integración Omnia (lectura) | vigente |
| `manual.html` | Onboarding de secretarias (usuario final) | vigente |
| `brochure/` | Material comercial | complementa la Parte I |
| memoria del agente (`~/.claude/projects/C--crecer/memory/`) | Detalle de implementación por feature + playbooks | viva, se actualiza por sesión |

## D. Glosario

- **Área**: cada una de las 3 líneas de WhatsApp (atención/administración/ovodonación),
  con su bot, su número y su cola.
- **Bot**: contenedor Node que automatiza un número de WhatsApp vía WhatsApp Web.
- **wwebjs**: whatsapp-web.js, la librería que controla WhatsApp Web con Chromium headless.
- **Baileys**: librería alternativa (sin navegador) probada y abandonada en mayo-junio 2026.
- **JID**: identificador de WhatsApp (`549…@c.us` para números, `…@lid` para IDs de
  privacidad que no revelan el número, `…@g.us` para grupos).
- **CDP**: Chrome DevTools Protocol — cómo Puppeteer le habla a Chromium; sus cuelgues son
  el modo de falla típico del bot.
- **Derivación**: lo que el bot manda a la cola humana con código + resumen IA.
- **Modo prueba**: el bot clasifica y deriva pero no auto-responde (estado operativo actual,
  deliberado, por área).
- **Sesión WA**: el perfil de Chromium que mantiene el pareo con el teléfono; el activo más
  delicado del sistema.
- **V1/V2**: la interfaz Livewire original / la interfaz actual (default desde 30/06).
- **Gate 13/07**: la decisión programada de borrar V1, condicionada a la matriz de paridad.
- **Omnia (Salud)**: el sistema de turnos externo de la clínica; la plataforma solo lee.
- **Reverb**: servidor WebSocket de Laravel (chat interno en tiempo real).
- **OPcache `validate_timestamps=0`**: los cambios PHP no aplican sin reiniciar `web`.
- **VHDX**: disco virtual de WSL2 donde vive Docker; crece y se compacta con diskpart.
