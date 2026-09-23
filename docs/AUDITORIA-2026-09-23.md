# Auditoría integral — 23/09/2026

Revisión de todo lo construido hasta acá: estado vivo del sistema (bots, colas, tareas,
backups, disco), datos en la base, logs de Laravel y de los bots, y código (web, bot,
scripts, docs). Se corrigió en el momento lo que era seguro corregir; lo que necesita una
decisión o a alguien con un celular en la mano quedó en la sección final.

El patrón que atraviesa los hallazgos graves: **fallas silenciosas con todo "en verde"**.
Los bots figuraban `listo`, el watchdog daba OK cada 5 minutos y los contenedores
`healthy`, mientras se perdían adjuntos durante dos meses o un bot pasaba cuatro días sin
recibir un mensaje. Por eso el arreglo de fondo más importante de esta auditoría es un
medidor que mira el resultado (qué entra a la base), no el proceso.

---

## 1. Hallazgos críticos

### 1.1 Desde el 17/07 no se guardan los adjuntos de pacientes ni el id de los mensajes

**Qué pasaba.** Desde el 17/07 (rollout de Chrome 146 + versión de WhatsApp Web pineada),
el id que devuelve `serialize()` en WhatsApp Web ya no trae `_serialized`. whatsapp-web.js
lo copia tal cual, así que en Node `msg.id._serialized` llega vacío. Dos consecuencias:

- `wa_id` vacío en el 97 % de los entrantes (28.626 de 29.469 desde el 17/07): sin él no
  se deduplican los reintentos del bot, no se puede responder citando y el backfill no es
  idempotente.
- `downloadMedia()` busca el mensaje por `this.id._serialized`: con el id vacío no lo
  encuentra y el adapter tragaba el error sin loguear. **2.447 de 2.595 adjuntos perdidos
  desde el 17/07** (1.409 imágenes, 757 documentos, 166 audios, el resto videos y
  stickers). El panel mostraba la burbuja sin archivo, los audios quedaron sin
  transcripción y el legajo recibió 143 documentos entrantes en dos meses contra 1.728 en
  las seis semanas anteriores.

También explica por qué el arreglo del 21/09 para que el eco de un envío del bot no quede
como "saliente externo" no alcanzaba: comparaba ids vacíos.

**Cómo se confirmó.** Tres lecturas de solo lectura por CDP sobre la página viva del bot
de ovodonación (conectar, evaluar, desconectar; el bot siguió `listo`): el `MsgKey` vivo
no tiene `_serialized`, pero `fromMe_remote._serialized_id` (+ `_participante` en grupos)
es exactamente `m.id.toString()`, y `Msg.get()` encuentra el mensaje con esa clave, en
chats `@lid` y en grupos.

**Arreglo** (`bot/clientes/wwebjs.js`): `completarIdSerializado()` rearma la clave y la
deja puesta en el objeto, así `downloadMedia()` y `getQuotedMessage()` vuelven a andar.
Se aplica en los eventos `message` y `message_create`, en el envoltorio, en las citas y en
los envíos. Los fallos de `downloadMedia` ahora se loguean. Probado aislado (9 casos) y
**aplicado en administración y ovodonación el 23/09 10:45-10:52** (reinicio limpio).
**Validado con tráfico real:** desde las 10:51 todos sus mensajes traen `wa_id` y cada
adjunto entrante quedó guardado e indexado al legajo (a las 14:30: 4 imágenes, 1 audio y
5 documentos). **Atención sigue con el código viejo hasta que se reinicie** (ver pendientes).

**Lo perdido.** Los archivos nunca se bajaron; siguen en los celulares de cada área. Un
rescate automático es posible en teoría pero no con el backfill actual: las filas
existentes no tienen `wa_id`, así que el backfill crearía duplicados en vez de
completarlas. Requiere un proceso específico (emparejar por conversación + hora + tipo).

### 1.2 Atención estuvo "sordo" del 15/09 21:51 al 20/09 02:30

Cero entrantes en cuatro días hábiles con el bot `listo`, la página respondiendo al
watchdog interno (`página: ok`) y el watchdog externo en OK los 288 chequeos diarios. Lo
destrabó el reinicio del ciclo de backup del domingo 20/09. El equipo siguió atendiendo
desde los celulares, pero el panel quedó vacío. El watchdog interno solo declara "zombie"
una página que además no responde, así que una sesión que responde pero no recibe eventos
no se detecta.

**Arreglo:** `php artisan salud:ingesta` (ver 3.1).

### 1.3 El log de Laravel quedaba con dueño root y la web no podía escribirlo

Las tareas nocturnas corrían `docker exec ... artisan` como root. El primer warning del
día (a las 04:00, el sync de Omnia) creaba `laravel-AAAA-MM-DD.log` con dueño root y 0644;
desde ahí la web y el queue-worker (www-data) no podían escribir y **cada `Log::warning`
tiraba una excepción**. Efectos:

- **84 mensajes entrantes perdidos** entre el 13/07 y el 13/08 (administración 49,
  ovodonación 35). El sync del avatar corría antes de guardar el mensaje; cuando el bot
  tardaba, el warning del timeout explotaba, el request devolvía 500 y el bot agotaba sus
  4 intentos (`PERDIDO`). Verificado en la base con casos puntuales: los entrantes que el
  log del bot da por perdidos no están.
- 111 resúmenes LLM fallidos (84 conversaciones seguían sin resumen).
- El mismo problema dejó 6.254 avatares y 6 carpetas del legajo con dueño root: la web no
  podía reescribirlos.

**Arreglo:** `-u www-data` en todas las tareas y en el aviso por mail del watchdog;
`permission => 0666` en los canales de log (red de seguridad para el `docker exec` a mano);
dueños corregidos; jobs reintentados (26 resúmenes nuevos; el resto eran chats sin nada que
resumir, ver 2.2). Test de regresión: `tests/Feature/BotMensajeEntranteTest.php`.

### 1.4 El entrante hacía lo lento antes de guardar el mensaje

`BotController::mensajeEntrante` pedía la foto de perfil al bot (15 s + 20 s de descarga)
**antes** de guardar el mensaje, y después indexaba el adjunto con OCR (hasta 10 páginas
de tesseract), todo dentro del request que el bot corta a los 8 s. Además del 1.3, eso
generaba duplicados: 19 mensajes repetidos en la misma conversación, creados 8 a 13 s
después del original (timeout del bot + espera del primer reintento).

**Arreglo:** el mensaje se guarda primero; avatar y legajo corren después de responder
(`defer()`), el avatar se pide al bot del área que recibió el mensaje y una sola vez por
ráfaga. Lo mismo para el envío de archivos desde el panel (la secretaria esperaba el OCR).
`AvisoDiferido` ahora sale antes que ese trabajo lento, así el panel se entera del mensaje
en el acto. Los 19 duplicados se borraron (respaldo en
`backups/manual/dedup-mensajes-wa-20260923.sql`).

---

## 2. Otros hallazgos (corregidos)

| # | Hallazgo | Arreglo |
|---|---|---|
| 2.1 | Sync de Omnia en tramos mensuales contra un límite de 7 días: 12 pedidos rechazados y 12 warnings por noche | Tramos de 7 días; verificado sin rechazos |
| 2.2 | El filtro de resúmenes no filtraba: `no_leidos > 0` lo hacía verdadero para todo entrante. El 96 % de los "fallos" eran chats de 1-2 mensajes cortos | Se sacó el atajo. Cobertura real: 98,9 % (el reporte mostraba ~78 %) |
| 2.3 | Tablet: al confirmar con turno se consultaba a Omnia en el acto (hasta ~17 s), y en cada check-in de un turno no ambulatorio | La consulta va después de responder y corrige la fila; un refresco del reporte cada 2 min como máximo. Test: `TabletCheckinTest` |
| 2.4 | XSS acotado: la extensión del adjunto sale del mimetype que declara quien manda y el panel la metía en un `onclick` | Extensión saneada en el bot; link en vez de `onclick` |
| 2.5 | El bot reintentaba 4 veces (2,5 min) respuestas 4xx de Laravel | Solo reintenta lo transitorio (red, 5xx, 408, 429) |
| 2.6 | 12 salientes con `wa_id = ''` | Pasados a NULL; el modelo normaliza |
| 2.7 | Los bots llegaban a Ollama por el puerto publicado en Windows (se cae tras `wsl --shutdown`) | Directo por la red de docker (aplica al reiniciar cada bot) |
| 2.8 | Archivado masivo con dos implementaciones (consola y panel); las corridas de consola no quedaban en el historial | El comando usa el service y deja un lote deshacible |
| 2.9 | Tablero de tareas de `/admin` desactualizado (CleanBotCache en rojo para siempre, SyncAvatares semanal medido como diario, faltaban BackupFull y SyncOmnia) | Refleja las tareas vigentes |
| 2.10 | Los tests escribían en el log de producción y corridos como root dejaban archivos de root | `LOG_CHANNEL=null` en phpunit; se corren con `-u www-data` |
| 2.11 | 4 migraciones solo-MySQL impedían levantar el esquema en SQLite (por eso no había tests de features) | Protegidas por driver (en producción no cambia nada) |
| 2.12 | `OcrService` dejaba un temporal huérfano por cada PDF | `pdftotext` a stdout |
| 2.13 | Sin cabeceras de seguridad en las respuestas de Laravel | `X-Frame-Options`, `X-Content-Type-Options`, `Referrer-Policy` |
| 2.14 | `README-OPERATIVO.md` describía Baileys como backend, la V1 y tareas que no existen | Reescrito como guía corta y vigente |
| 2.15 | Filtro de grupos del bot que nunca funcionó (`msg.isGroupMsg` no existe en wwebjs) | Sin cambio de comportamiento: ovo usa sus grupos desde el panel. Comentado en el código |
| 2.16 | Raíz del repo con capturas viejas, un PDF de mayo y el CSV de pacientes | Movidos a `backups/manual/archivo-raiz-20260923/`; `manual.html` (V1) fuera del árbol |

Tests: la suite pasó de 56 a 60, incluidas regresiones de 1.3/1.4 y del tablet.

## 3. Mejoras

### 3.1 Medidor de ingesta (`salud:ingesta` + watchdog)

Por área: silencio (cero entrantes en las últimas 2 h, en horario hábil, cuando la mediana
del mismo tramo en los días hábiles de las últimas 4 semanas es de 6 o más) y calidad
(más de la mitad de los entrantes sin `wa_id` o de los adjuntos sin archivo en las últimas
6 h). El watchdog lo corre una vez por hora de 8 a 20 y avisa por WhatsApp y mail: una
alerta que no estaba en el último aviso sale en el acto, lo ya avisado se recuerda cada
4 h y, cuando todo vuelve a la normalidad, avisa que se normalizó. Con esto, 1.1 se habría
visto el 17/07 y 1.2 el 16/09 a media mañana. Primera alerta real: 23/09 10:55.

En el camino apareció otro defecto del watchdog: PowerShell 5.1 manda el cuerpo del POST
en Latin-1, así que cualquier acento llegaba al WhatsApp como "�" (los avisos viejos no
tenían acentos a propósito). Ahora va en UTF-8.

### 3.2 Rendimiento

La base no es cuello de botella: en 25 h de uptime toda la aplicación sumó menos de 10 s
de tiempo de MySQL (lo más caro del día es el `mysqldump` del backup). No hacen falta
índices nuevos. RAM de WSL: 6,2 de 11,7 GB. Lo que sí se optimizó es la latencia de los
caminos críticos: el entrante del bot (ya no espera avatar ni OCR), el tablet (ya no
espera a Omnia), el envío de archivos del panel (ya no espera el OCR) y el aviso en
tiempo real (sale antes del trabajo lento).

---

## 4. Pendientes que necesitan a Damián

1. **Reiniciar el bot de atención hoy, con el celular de atención a mano.** Toma el
   arreglo de 1.1 (cada hora que pasa se pierden sus adjuntos) y la versión nueva de
   WhatsApp Web (la que corre venció el 22/09). Si no se hace, lo aplica solo el ciclo del
   domingo 27/09 02:30, sin nadie mirando.
2. **Configurar el mail del watchdog.** El canal fuera de banda está programado pero
   `MAIL_MAILER=log`: no sale nada. En `app/.env`: `MAIL_MAILER=smtp`, `MAIL_HOST`,
   `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD` (con Gmail, contraseña de aplicación),
   `MAIL_FROM_ADDRESS` y `MAIL_FROM_NAME="Crecer - Watchdog"`. **No tocar `APP_NAME`:**
   también nombra la cookie de sesión y cambiarlo desloguea a todos. Probar con
   `docker exec -u www-data crecer-web-1 php artisan alerta:mail <base64> <base64>`.
3. **Decidir el rescate de los adjuntos perdidos** (2.447 desde el 17/07). Siguen en los
   celulares; un rescate automático necesita un proceso nuevo (ver 1.1).
4. **Reserva DHCP de 192.168.1.115** en el router (la IP ya cambió una vez y rompió los
   adjuntos históricos).
5. **Ollama**: cuando los tres bots se hayan reiniciado con la URL nueva, publicar su
   puerto solo en `127.0.0.1` (hoy la API del LLM queda abierta a toda la LAN).
6. **Grupos en la cola de ovo**: hoy entran y se usan; si no se quieren, es una decisión
   de producto.
7. **Experimento Baileys** (`bot-baileys-test`, 3009): lleva 37 h esperando QR.
   Escanearlo con el celular de administración o apagarlo.
8. **Conflictos de DNI del sync de Omnia** (revisar a mano): contactos 37194, 34683 y 2081
   (el DNI cargado no coincide con el de Omnia; figuran en `backups/auto/sync-omnia.log`).
9. **Compactar el VHDX de Docker** en una ventana de mantenimiento (55,7 GB, ~20
   recuperables; playbook en `DOCUMENTACION-GENERAL.md` §22.3).
10. **Backups viejos**: ~2,4 GB de tars de sesiones de mayo y julio que ya no sirven
    (las sesiones se re-parearon después) en `backups/manual/` y `backups/`.
11. **Opcional:** los adjuntos que el equipo manda desde el celular se guardan pero nunca
    se indexaron al legajo (solo los que salen desde el panel). Si se quieren en el legajo
    del paciente, es un agregado chico en `BotController::mensajeSaliente`.
