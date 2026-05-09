# Plataforma Operativa Clínica — WORKBENCH IT

## Contexto

Plataforma web interna para **Crecer Reproducción** — Centro de Reproducción y Genética Humana, Mar del Plata. Complementa a **Omnia Salud** (sistema de gestión existente) cubriendo las brechas operativas que ese sistema no resuelve: bot de WhatsApp, gestión de atención presencial, documentación interna y estadísticas operativas.

**Principio fundamental:** Omnia es la fuente de verdad para turnos, cobros, historia clínica, facturación, obra social y resultados. El sistema NO duplica ni reemplaza nada de eso. El sistema orquesta el flujo físico de atención que Omnia no cubre.

**Sitio web de la clínica:** https://crecerreproduccion.com.ar

**Identidad visual:**
- Color principal: `#C0273A` (rojo carmín)
- Color oscuro: `#8C1B29`
- Ícono: tres círculos superpuestos (óvulos) en blanco
- Tipografía: sans-serif geométrica, mayúsculas, bold
- Tono de comunicación: cálido, profesional, esperanzador

---

## Infraestructura de producción — INSTALADA Y FUNCIONANDO

### Servidor de la clínica — Fase 0 completada ✅
- **OS:** Windows 11 Pro — instalado
- **GPU:** NVIDIA con CUDA — verificada y funcionando con Docker
- **Acceso:** pantalla física + RDP disponible
- **Red:** IP interna fija, acceso por navegador desde PCs de la red local (`http://192.168.X.X`)
- **Sin dominio ni SSL** — red local cerrada

### Stack corriendo en producción
```
C:\crecer\
├── docker-compose.yml
├── app\                  ← Laravel instalado y funcionando
├── bot\                  ← carpeta lista, desarrollo pendiente
└── docker\
    ├── php\Dockerfile
    ├── nginx\default.conf
    └── node\Dockerfile
```

### Contenedores activos
| Contenedor | Estado | Detalle |
|---|---|---|
| nginx | ✅ corriendo | puerto 80 |
| web (php-fpm) | ✅ corriendo | Laravel 11 instalado |
| mysql | ✅ corriendo | DB: clinica, user: crecer |
| bot (node) | ✅ listo | carpeta bot/ vacía, pendiente desarrollo |
| ollama | ✅ corriendo | GPU CUDA verificada |

### docker-compose.yml

```yaml
version: '3.9'

services:
  nginx:
    image: nginx:alpine
    ports:
      - "80:80"
    volumes:
      - ./app:/var/www/html
      - ./docker/nginx/default.conf:/etc/nginx/conf.d/default.conf
    depends_on:
      - web
    networks:
      - clinica-net

  web:
    build: ./docker/php
    volumes:
      - ./app:/var/www/html
    depends_on:
      - mysql
    networks:
      - clinica-net

  mysql:
    image: mysql:8.0
    environment:
      MYSQL_ROOT_PASSWORD: ${DB_ROOT_PASSWORD}
      MYSQL_DATABASE: clinica
      MYSQL_USER: crecer
      MYSQL_PASSWORD: ${DB_PASSWORD}
    volumes:
      - mysql-data:/var/lib/mysql
    ports:
      - "3306:3306"
    networks:
      - clinica-net

  bot:
    build: ./docker/node
    volumes:
      - ./bot:/app
      - wa-session:/app/.wwebjs_auth
    depends_on:
      - web
    networks:
      - clinica-net

  ollama:
    image: ollama/ollama
    deploy:
      resources:
        reservations:
          devices:
            - driver: nvidia
              count: 1
              capabilities: [gpu]
    volumes:
      - ollama-data:/root/.ollama
    ports:
      - "11434:11434"
    networks:
      - clinica-net

volumes:
  mysql-data:
  ollama-data:
  wa-session:

networks:
  clinica-net:
```

### Credenciales de desarrollo
- **MySQL:** host=mysql, db=clinica, user=crecer, password=${DB_PASSWORD}
- **Ollama:** http://host.docker.internal:11434 (desde contenedores) / http://localhost:11434 (desde Windows)
- **Laravel:** http://localhost (desde el servidor)

### Dispositivos relevados — CONFIRMADO
- **Tablet de autoregistro:** Xiaomi, WiFi — ya existe
- **PCs en consultorios:** todos los consultorios tienen PC
- **Impresoras en recepción:** 2
- **Monitor/TV:** solo en planta alta
- **Posiciones en mostrador:** 4
- **Salas de espera:** 2 — planta alta y planta baja
- **Tablet:** justo antes del mostrador
- **Entrada/salida:** una sola
- **Nombres de áreas en Omnia:** coinciden con nombres físicos

### WhatsApp — CONFIRMADO
- **Tecnología:** whatsapp-web.js (no oficial) — costo $0
- **No usan API paga de Meta Business**
- **Número:** dedicado para la clínica, a conseguir
- **Situación actual:** secretarias rotan día por medio con WhatsApp Web

---

## Módulos de la plataforma

### M1 — Bot WhatsApp ← PRÓXIMO A DESARROLLAR
### M2 — Gestión de Atención Presencial
### M3 — Documentación interna
### M4 — Estadísticas operativas

---

## M1 — Bot WhatsApp

### Árbol de decisiones (definido por la clínica)

```
Mensaje entrante
├── CONSULTA
│   ├── Primera vez → indicar llamado telefónico
│   └── No es primera vez → derivar al portal de turnos
│
├── TURNO
│   ├── Ecografía / estudios
│   │   ├── Tiene cuenta en portal → indicar cómo sacar turno + link
│   │   └── No tiene cuenta → enviar instructivo PDF automáticamente
│   ├── Diagnóstico Genético Preimplantatorio → solicitar orden → derivar secretaria
│   ├── Preservación de fertilidad → información + derivar secretaria
│   └── Presupuesto → derivar a secretaria
│
├── RESULTADOS
│   ├── Beta → solicitar datos → entregar por WhatsApp
│   └── Otros estudios → indicar proceso: portal → solicitar turno
│
├── MEDICACIÓN
│   └── Cómo aplicar → enviar instructivo PDF automáticamente
│
├── ÓRDENES Y RECETAS
│   ├── Es de Mar del Plata → presencial (dar horarios y dirección)
│   └── No es de MdP → enviar por mail (dar dirección de mail)
│
└── CONSULTA CLÍNICA / DIAGNÓSTICO / MEDICACIÓN ESPECÍFICA
    → Derivar SIEMPRE (código CONSULTA_CLINICA, sin excepción)
```

### Códigos de acción del LLM

El LLM devuelve SOLO uno de estos códigos en JSON. Nunca redacta respuestas.

```json
{ "codigo": "CODIGO_ACCION", "confianza": "alta|media|baja" }
```

| Código | Respuesta |
|---|---|
| `BIENVENIDA` | Mensaje inicial |
| `PRIMERA_CONSULTA` | Indicar llamado telefónico |
| `TURNO_PORTAL` | Redirigir al portal Omnia |
| `TURNO_ECO_CON_CUENTA` | Instrucciones para sacar turno |
| `TURNO_ECO_SIN_CUENTA` | Enviar PDF instructivo |
| `TURNO_DGP` | Pedir orden + derivar secretaria |
| `TURNO_PRESERVACION` | Info + derivar secretaria |
| `TURNO_PRESUPUESTO` | Derivar secretaria |
| `RESULTADO_BETA` | Solicitar datos + entregar |
| `RESULTADO_OTROS` | Indicar proceso portal |
| `MEDICACION_INSTRUCTIVO` | Enviar PDF medicación |
| `ORDEN_MDP` | Presencial — dar horarios |
| `ORDEN_OTRA_CIUDAD` | Enviar por mail |
| `CONSULTA_CLINICA` | Derivar siempre, sin responder |
| `DERIVAR_SECRETARIA` | Derivar a cola |
| `FALLBACK` | No entendió — derivar |

### Comportamiento del bot

**Audios:** Whisper transcribe → procesa como texto. Transparente para la paciente.

**Mensajes consecutivos — ventana de tiempo:**
```
Paciente: "hola quiero saber"      ← inicia timer (ESPERA_MENSAJES ms)
Paciente: "si puedo sacar"         ← reinicia timer
Paciente: "un turno para eco"      ← reinicia timer
[silencio]
Bot procesa todo junto
```

**Variables de entorno configurables (bot/.env):**
```env
OLLAMA_URL=http://host.docker.internal:11434
OLLAMA_MODEL=qwen3:8b
LARAVEL_URL=http://web/api
LARAVEL_TOKEN=${LARAVEL_TOKEN}
ESPERA_MENSAJES=8000
ESPERA_MAXIMA=45000
RESET_CONVERSACION=1800000
HORARIO_INICIO=8
HORARIO_FIN=18
HORARIO_SAB_FIN=13
```

**Lógica de horario:**
- Lunes a viernes 8-18hs → horario normal
- Sábados 8-13hs → horario normal
- Fuera de eso → responde lo que puede, aclara que es fuera de horario cuando deriva

**Fuera de horario:** responde todo lo que puede resolver solo. Cuando necesita secretaria, aclara horario y registra en cola.

| Situación | Comportamiento |
|---|---|
| Texto | Procesa directo |
| Audio | Whisper → texto → procesa |
| Mensajes rápidos | Acumula en ventana → procesa como uno |
| Consulta clínica / diagnóstico | CONSULTA_CLINICA → deriva siempre |
| Fuera de horario + necesita secretaria | Responde + aclara horario + registra cola |
| Fuera de horario + resuelve solo | Responde igual |
| Pide hablar con secretaria | Deriva directo |

### Estructura de archivos del bot

```
bot/
├── index.js          ← entrada, inicializa todo
├── whatsapp.js       ← conexión whatsapp-web.js, escucha mensajes
├── mensajes.js       ← acumulador de ventana de tiempo
├── ollama.js         ← clasificador LLM, devuelve código de acción
├── respuestas.js     ← textos predefinidos por código (editable por la clínica)
├── cola.js           ← POST a Laravel cuando hay derivación
├── horario.js        ← lógica de horario de atención
├── package.json
└── .env
```

### package.json

```json
{
  "name": "crecer-bot",
  "version": "1.0.0",
  "main": "index.js",
  "dependencies": {
    "whatsapp-web.js": "^1.23.0",
    "qrcode-terminal": "^0.12.0",
    "axios": "^1.6.0",
    "dotenv": "^16.0.0"
  }
}
```

### System prompt para Ollama

```
Sos el clasificador de mensajes del bot de WhatsApp de Crecer Reproducción, un centro de reproducción y genética humana.

Tu única tarea es clasificar el mensaje del paciente y devolver UN SOLO código de acción en formato JSON.
NUNCA redactes respuestas para el paciente.
NUNCA expliques tu razonamiento.
SOLO devolvé el JSON.

Códigos válidos:
PRIMERA_CONSULTA, TURNO_PORTAL, TURNO_ECO_CON_CUENTA, TURNO_ECO_SIN_CUENTA,
TURNO_DGP, TURNO_PRESERVACION, TURNO_PRESUPUESTO, RESULTADO_BETA,
RESULTADO_OTROS, MEDICACION_INSTRUCTIVO, ORDEN_MDP, ORDEN_OTRA_CIUDAD,
CONSULTA_CLINICA, DERIVAR_SECRETARIA, FALLBACK

IMPORTANTE: Cualquier consulta sobre diagnósticos, medicación específica, resultados médicos
o síntomas → CONSULTA_CLINICA siempre, sin excepción.

Formato de respuesta:
{"codigo": "CODIGO", "confianza": "alta|media|baja"}
```

### Textos de respuesta (respuestas.js)

Textos en tono cálido, con emoji moderado. La clínica los edita directamente en este archivo.

---

## M2 — Gestión de Atención Presencial

### Flujo completo

```
[TABLET — paciente]
Ingresa DNI (teclado numérico en pantalla)
    ↓
Sistema consulta Omnia en tiempo real
    ↓
┌── Tiene 1 turno → muestra turno, confirma llegada
├── Múltiples turnos → elige por cuál viene
├── Primera vez (flag Omnia) → bienvenida + turno
└── Sin turno → avisa que se acerque al mostrador → alerta ⚡ en secretaria
    ↓
Entra a la cola de recepción
Pantalla: "Tomá asiento en sala [X]. Te llamamos."
Reseteo automático en 15 segundos
    ↓
[MOSTRADOR — secretaria]
Ve la cola con prioridades, flags y tiempo de espera
Reordena manualmente con ↑ ↓ si lo considera necesario
Abre la ficha → datos pre-cargados de Omnia
Completa checklist (gestiona en Omnia)
Marca ítems como completados en el sistema
    ↓
[Liberar a sala] → aparece en lista del médico
    ↓
[CONSULTORIO — médico]
Ve sus pacientes listas (solo las suyas, ya procesadas)
[Llamar] → pantalla TV planta alta muestra el llamado
```

### Nota sobre Omnia
Turnos, cobros, obra social, HCE y facturación se gestionan en **Omnia**. La secretaria tiene Omnia y el sistema abiertos simultáneamente. El sistema muestra datos de Omnia pre-cargados para orientar, las acciones se ejecutan en Omnia.

### Flags automáticos en la cola

| Flag | Cuándo aparece |
|---|---|
| ⚠️ Alerta espera | Superó el umbral configurado (configurable, ej: 20 min) |
| ⭐ Primera vez | Omnia indica paciente nueva |
| ⚡ Sin turno | No se encontró turno en Omnia |
| 📋 Requiere orden | La práctica requiere orden médica (configurable por práctica) |
| 🕐 Turno próximo | Menos de 10 min para su turno y aún no fue atendida |
| 💬 WhatsApp | El caso vino derivado del bot |

### Checklist de recepción (configurable por práctica desde admin)

```
☐ Cobertura / obra social verificada      [gestionar en Omnia]
☐ Copago gestionado                        [gestionar en Omnia]
☐ Orden médica validada                    [según práctica]
☐ Primera vez registrada                   [gestionar en Omnia]
☐ Datos del paciente completos             [gestionar en Omnia]
────────────────────────────────────────────
[Liberar a sala] — se habilita cuando obligatorios están completos
```

### Colas especializadas

**Al iniciar turno** cada secretaria declara qué colas atiende:
- Recepción general (check-in, sin turno)
- Turnos y agenda
- Órdenes y resultados
- Facturación y cobros (siempre pre-atención)
- Coordinación con médicos

**Estados de un caso:**
```
NUEVO → EN ATENCIÓN → RESUELTO
              ↓
          DERIVADO (a otra cola, con nota)
          EN ESPERA (gestión externa, con recordatorio)
```

### Distribución por sala
El sistema indica a la paciente a qué sala ir (planta alta o baja) según la práctica/consultorio.
**Pendiente:** mapeo de qué prácticas/consultorios corresponden a cada planta.

---

## M3 — Documentación interna

- Repositorio centralizado de protocolos, formularios e instructivos
- Control de versiones
- Buscador full-text
- Permisos por rol
- Los PDFs para enviar por bot se sirven desde acá
- Alertas de documentos próximos a vencer

---

## M4 — Estadísticas operativas

- Dashboard: consultas WA (total / bot / derivadas), tiempos de espera
- Métricas del bot: tasa de resolución autónoma, ramas más usadas, horas pico
- Métricas de atención: tiempo por secretaria, casos por cola
- Exportación PDF/Excel
- Alertas configurables

---

## Panel de administración — dos niveles

### Nivel operativo (clínica)
- Textos del bot por código de acción
- Horarios de atención del bot
- Activar/desactivar ramas del árbol
- Subir PDFs (instructivo medicación, instructivo portal)
- Umbral de tiempo de espera para alertas por área
- Checklist por práctica (qué ítems son obligatorios)
- Mapeo consultorio Omnia → área física → planta
- Usuarios y roles
- Tiempos de ventana de mensajes

### Nivel técnico (WORKBENCH IT) — acceso por IP o 2FA
- Estado de contenedores Docker
- Iniciar / detener / reiniciar servicios
- Logs en vivo
- Credenciales API Omnia
- Variables de entorno (.env)
- Backup manual y automático
- Re-escaneo QR de WhatsApp

---

## Stack tecnológico

| Componente | Tecnología |
|---|---|
| Backend / API | PHP 8.2 + Laravel 11 |
| Base de datos | MySQL 8.0 |
| Frontend | Livewire (Laravel) |
| Tiempo real | Laravel Echo + Reverb |
| Bot WhatsApp | Node.js + whatsapp-web.js |
| Transcripción audio | Whisper (local) |
| IA clasificación | Ollama + qwen3:8b (CUDA) |
| Contenedores | Docker + docker-compose |
| Runtime | Windows 11 + WSL2 |

---

## Perfiles de usuario

| Perfil | Acceso |
|---|---|
| Secretaria | Cola, ficha de atención, colas especializadas |
| Médico | Lista de pacientes listas, llamado |
| Supervisora | Vista global de colas y secretarias |
| Administrativo | Panel admin — sección operativa |
| Técnico WORKBENCH IT | Panel admin — sección técnica completa |
| Paciente | Tablet de autoregistro + bot WhatsApp |

---

## Integración con Omnia Salud

### Autenticación
```
Authorization: Basic <base64(usuario:password)>
```
JWT OAuth2 para endpoints FHIR — token expira en 1800s, renovar cada 25 min.

### Base URL
```
https://apiturnos.apps.omniasalud.com
```

### Endpoints clave

```php
# Buscar paciente por DNI
GET /api/v1/external/patients/by-personal-id?personal_id={DNI}&personal_id_type=DNI

# Turnos pendientes del paciente — filtrar por hoy
GET /api/v1/external/patients/{id}/appointments/pending

# Agenda del día completa
GET /api/v1/external/reports/appointments/ambulatory?start={unix}&end={unix}

# Campos clave del response de agenda:
# Id, Nombre, ApellidoPaterno
# NúmeroDeDocumento, TipoDeDocumento
# FechaYHora → formato "d/M/yyyy HH:mm" SIN ceros
#            → parsear: DateTime::createFromFormat('j/n/Y H:i', $fecha)
# NombreDelProfesional
# Estado: "pendiente", "atendido", "cancelado", "ausente"
# PrimeraVez: "Sí" si es primera vez
# ObraSocialDelPaciente, PlanDelPaciente
```

### Notas de implementación
- Rate limit: 120 req/hora → caché MySQL TTL 5 min para agenda del día
- Fechas en reportes sin ceros → `DateTime::createFromFormat('j/n/Y H:i', $fecha)`
- Token JWT: refresh automático cada 25 minutos

---

## Fases de desarrollo

| Fase | Entregable | Estado |
|---|---|---|
| **Fase 0** | Docker + Laravel + MySQL + Ollama CUDA | ✅ Completada |
| **Fase 1 — M1** | Bot WhatsApp: árbol + Ollama + cola derivaciones | 🟡 Próxima — arrancar con Claude Code |
| **Fase 2 — M2** | Tablet autoregistro + cola secretaria + ficha + vista médico | 🔴 Esperando credenciales Omnia |
| **Fase 3 — M3** | Documentación interna | ⚪ Independiente |
| **Fase 4 — M4** | Estadísticas | ⚪ Requiere F1 + F2 |
| **Fase 5** | Bot avanzado: turnos, resultados, Whisper | ⚪ Sistema estable |
| **Fase 6** | Panel de admin completo | ⚪ Todo lo anterior |

---

## Pendientes críticos

### 🔴 Bloqueantes para Fase 2
- Credenciales API de Omnia (contactar soporte)
- Mapeo de consultorios Omnia (IDs → áreas físicas → planta)
- Lista de profesionales con IDs de Omnia
- Checklist de recepción por práctica

### 🟡 Importantes para Fase 1
- Número de WhatsApp dedicado para el bot
- Textos del bot redactados por la clínica
- Horarios de atención confirmados

---

## Estructura de carpetas del proyecto

```
C:\crecer\
├── CLAUDE-clinica.md          ← este archivo, contexto completo
├── .env                       ← variables globales
├── docker-compose.yml
├── docker\
│   ├── php\Dockerfile
│   ├── nginx\default.conf
│   └── node\Dockerfile
├── app\                       ← Laravel 11
│   ├── app\
│   │   ├── Http\Controllers\
│   │   ├── Models\
│   │   └── Services\
│   │       └── OmniaService.php
│   ├── resources\views\
│   │   ├── tablet\
│   │   ├── secretaria\
│   │   ├── medico\
│   │   ├── supervisora\
│   │   └── admin\
│   └── routes\
└── bot\                       ← Node.js — EN DESARROLLO
    ├── index.js
    ├── whatsapp.js
    ├── mensajes.js
    ├── ollama.js
    ├── respuestas.js
    ├── cola.js
    ├── horario.js
    ├── package.json
    └── .env
```

---

## Decisiones tomadas — no replantear

- Windows 11 Pro — instalado en el servidor de la clínica
- Docker + docker-compose — stack completo en contenedores
- WSL2 como backend de Docker (CUDA nativo)
- Laravel 11 + Livewire (sin SPA separada)
- whatsapp-web.js no oficial — costo $0
- Número de WhatsApp dedicado — no puede estar abierto en otro dispositivo
- Omnia es la fuente de verdad — el sistema no duplica nada
- Ollama + qwen3:8b local — costo $0, privacidad total, CUDA disponible
- El LLM solo clasifica con códigos de acción — respuestas las redacta la clínica
- Tablet Xiaomi WiFi — solo pide DNI, reseteo 15 segundos
- Todos los consultorios tienen PC — médicos pueden usar pantalla de llamado
- 2 salas de espera (planta alta y baja) — sistema indica a cuál ir
- Arrancar por M1 (bot) mientras se gestionan credenciales de Omnia para M2
- Panel admin en dos niveles: operativo (clínica) y técnico (WORKBENCH IT)


---

## Estado de instalación — Fase 0 completada

### Servidor de la clínica — INSTALADO Y FUNCIONANDO

| Componente | Estado | Detalle |
|---|---|---|
| Windows 11 Pro | ✅ | Instalado y listo |
| WSL2 + Ubuntu | ✅ | Ubuntu corriendo en VERSION 2 |
| Docker Desktop | ✅ | Corriendo con backend WSL2 |
| Nginx | ✅ | Escuchando en puerto 80 |
| Laravel 11 + PHP 8.2 | ✅ | Instalado en /var/www/html |
| MySQL 8.0 | ✅ | Base de datos: clinica / usuario: crecer |
| Ollama + GPU CUDA | ✅ | Corriendo en Windows, accesible desde Docker |
| Contenedor Node (bot) | ✅ | Listo, dependencias a instalar |

### Credenciales MySQL (desarrollo)
```
DB_HOST=mysql
DB_PORT=3306
DB_DATABASE=clinica
DB_USER=crecer
DB_PASSWORD=${DB_PASSWORD}
DB_ROOT_PASSWORD=${DB_ROOT_PASSWORD}
```

### Estructura de carpetas en el servidor
```
C:\crecer\
├── docker-compose.yml
├── app\                  ← Laravel (montado en /var/www/html)
├── bot\                  ← Bot Node.js
│   ├── package.json
│   ├── index.js
│   ├── whatsapp.js
│   ├── ollama.js
│   ├── arbol.js
│   └── config.js
└── docker\
    ├── php\Dockerfile
    ├── nginx\default.conf
    └── node\Dockerfile
```

### Comandos útiles de operación

```powershell
# Ver estado de todos los contenedores
docker-compose ps

# Ver logs en vivo
docker-compose logs -f

# Logs de un servicio específico
docker-compose logs -f bot
docker-compose logs -f web

# Reiniciar un servicio
docker-compose restart bot

# Entrar a un contenedor
docker exec -it crecer-bot-1 sh
docker exec -it crecer-web-1 bash

# Levantar todo
cd C:\crecer
docker-compose up -d

# Bajar todo
docker-compose down
```

---

## Bot de WhatsApp — código base implementado

### Archivos del bot (C:\crecer\bot\)

**config.js** — configuración centralizada
- URL de Ollama: `http://host.docker.internal:11434` (accede a Ollama de Windows desde Docker)
- Modelo: `qwen3:8b`
- Espera entre mensajes: 8000ms
- Espera máxima: 45000ms
- Reset de conversación: 30 minutos

**arbol.js** — textos de respuesta por código de acción
- Todos los textos editables sin tocar lógica
- Códigos: BIENVENIDA, FUERA_HORARIO, TURNO_ECO_CON_CUENTA, TURNO_ECO_SIN_CUENTA, TURNO_GENETICA, TURNO_PRESUPUESTO, RESULTADO_BETA, RESULTADO_OTROS, ORDEN_MDP, ORDEN_OTRA_CIUDAD, MEDICACION, CONSULTA_CLINICA, PRIMERA_VEZ, URGENCIA, FALLBACK, DERIVADA

**ollama.js** — clasificación con LLM
- System prompt en español con todos los códigos válidos
- Temperature: 0, num_ctx: 1024, modo /no_think
- Fallback a FALLBACK si el código no es válido o hay error

**whatsapp.js** — conexión y lógica principal
- Ventana de mensajes consecutivos con timer corto + timer máximo
- Detección de fuera de horario — agrega aclaración automáticamente
- Ignora mensajes propios, de grupos y de estado
- Reset de conversación por inactividad

**index.js** — entrada principal

### Próximos pasos del bot

- [ ] Instalar dependencias: `docker exec -it crecer-bot-1 sh -c "cd /app && npm install"`
- [ ] Levantar bot y escanear QR con el número dedicado de la clínica
- [ ] Probar clasificación con mensajes de prueba
- [ ] Ajustar textos de respuesta con la clínica
- [ ] Agregar manejo de audios con Whisper
- [ ] Integrar verificación de paciente por DNI con API de Omnia
- [ ] Conectar cola de derivaciones con el panel de secretaria (Laravel)

### Pendientes críticos para el bot

- [ ] Número de WhatsApp dedicado para la clínica (chip físico)
- [ ] Textos finales redactados por la clínica (arbol.js)
- [ ] Teléfono de guardia para urgencias (arbol.js — buscar XXX-XXXX)
- [ ] Mail de contacto para órdenes de otras ciudades (arbol.js)

