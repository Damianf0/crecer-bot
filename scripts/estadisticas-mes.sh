#!/bin/sh
# Estadísticas de un mes cerrado (base del tablero mensual).
# Uso (Git Bash):  sh scripts/estadisticas-mes.sh 2026-11
# Salida: TSV por pantalla, una fila por dato, con un prefijo que dice de qué es.
# Solo lee la base. El mes de comparación es el anterior.
set -e
MES=${1:?Uso: estadisticas-mes.sh AAAA-MM}
DESDE="$MES-01"
q() { echo "$1" | docker exec -i crecer-mysql-1 sh -c 'mysql --default-character-set=utf8mb4 -uroot -p"$MYSQL_ROOT_PASSWORD" "$MYSQL_DATABASE" 2>/dev/null'; }

q "
SET @d='$DESDE', @h=DATE_ADD('$DESDE', INTERVAL 1 MONTH), @p=DATE_SUB('$DESDE', INTERVAL 1 MONTH);
SELECT 'MSG_MES', DATE_FORMAT(m.created_at,'%Y-%m'), c.area, m.direccion, COUNT(*) FROM mensajes_wa m JOIN conversaciones_wa c ON c.id=m.conversacion_id WHERE m.created_at>=@p AND m.created_at<@h GROUP BY 2,3,4;
SELECT 'MSG_DIA', DATE(created_at), direccion, COUNT(*) FROM mensajes_wa WHERE created_at>=@d AND created_at<@h GROUP BY 2,3;
SELECT 'MSG_HORA', WEEKDAY(created_at), HOUR(created_at), COUNT(*) FROM mensajes_wa WHERE direccion='entrante' AND created_at>=@d AND created_at<@h GROUP BY 2,3;
SELECT 'MSG_TIPO', tipo, direccion, COUNT(*) FROM mensajes_wa WHERE created_at>=@d AND created_at<@h GROUP BY 2,3;
SELECT 'CONV_ACT', DATE_FORMAT(m.created_at,'%Y-%m'), c.area, COUNT(DISTINCT m.conversacion_id) FROM mensajes_wa m JOIN conversaciones_wa c ON c.id=m.conversacion_id WHERE m.direccion='entrante' AND m.created_at>=@p AND m.created_at<@h GROUP BY 2,3;
SELECT 'CONV_NUEVA', DATE_FORMAT(created_at,'%Y-%m'), area, COUNT(*) FROM conversaciones_wa WHERE created_at>=@p AND created_at<@h GROUP BY 2,3;
SELECT 'EVT', DATE_FORMAT(created_at,'%Y-%m'), tipo, COUNT(*) FROM conversacion_eventos WHERE created_at>=@p AND created_at<@h GROUP BY 2,3;
SELECT 'EVT_USR', u.name, e.tipo, COUNT(*) FROM conversacion_eventos e JOIN users u ON u.id=e.usuario_id WHERE e.created_at>=@d AND e.created_at<@h AND e.tipo IN ('resuelta','tomada','iniciada','derivada_area') GROUP BY 2,3;
SELECT 'CLASIF', codigo, COUNT(*), SUM(en_horario) FROM clasificaciones_wa WHERE created_at>=@d AND created_at<@h GROUP BY 2;
SELECT 'REC_DIA', DATE(created_at), COUNT(*) FROM cola_atencion WHERE created_at>=@d AND created_at<@h GROUP BY 2;
SELECT 'REC_TOT', COUNT(*), SUM(origen='tablet'), SUM(TIMESTAMPDIFF(MINUTE,hora_llegada,hora_llamado)<10), SUM(TIMESTAMPDIFF(MINUTE,hora_llegada,hora_llamado)>=30) FROM cola_atencion WHERE created_at>=@d AND created_at<@h;
SELECT 'REC_MOTIVO', motivo, COUNT(*) FROM cola_atencion WHERE created_at>=@d AND created_at<@h GROUP BY 2;
SELECT 'REC_HORA', HOUR(hora_llegada), COUNT(*) FROM cola_atencion WHERE created_at>=@d AND created_at<@h GROUP BY 2;
SELECT 'REC_OS', COALESCE(NULLIF(financiador,''),obra_social,'(sin dato)'), COUNT(*) c FROM cola_atencion WHERE created_at>=@d AND created_at<@h GROUP BY 2 ORDER BY c DESC LIMIT 12;
SELECT 'PV', DATE_FORMAT(fecha,'%Y-%m'), motivo, COUNT(*) FROM primeras_veces WHERE fecha>=DATE_SUB(@d, INTERVAL 3 MONTH) AND fecha<@h GROUP BY 2,3;
SELECT 'PV_MED', medico, COUNT(*) FROM primeras_veces WHERE fecha>=@d AND fecha<@h GROUP BY 2;
SELECT 'DOCS', DATE_FORMAT(created_at,'%Y-%m'), tipo, COUNT(*) FROM documentos_paciente WHERE created_at>=@p AND created_at<@h GROUP BY 2,3;
SELECT 'CHAT', COUNT(*) FROM chat_mensajes WHERE created_at>=@d AND created_at<@h;
SELECT 'TAREAS', estado, COUNT(*) FROM tareas WHERE created_at>=@d AND created_at<@h GROUP BY 2;
"

# Tiempo hasta la primera respuesta (lun-vie 8 a 18). Columnas: consultas, sin respuesta, <15 min, <1 h, <4 h.
q "
SET @d='$DESDE', @h=DATE_ADD('$DESDE', INTERVAL 1 MONTH), @p=DATE_SUB('$DESDE', INTERVAL 1 MONTH);
WITH x AS (SELECT m.conversacion_id cid, c.area, m.direccion d, m.created_at t, LAG(m.direccion) OVER w prevd FROM mensajes_wa m JOIN conversaciones_wa c ON c.id=m.conversacion_id WHERE m.direccion IN ('entrante','saliente') AND m.created_at>=@p AND m.created_at<DATE_ADD(@h, INTERVAL 7 DAY) WINDOW w AS (PARTITION BY m.conversacion_id ORDER BY m.created_at, m.id)),
y AS (SELECT x.*, (SELECT MIN(m2.created_at) FROM mensajes_wa m2 WHERE m2.conversacion_id=x.cid AND m2.direccion='saliente' AND m2.created_at>=x.t) r FROM x WHERE d='entrante' AND (prevd IS NULL OR prevd<>'entrante') AND t<@h AND WEEKDAY(t)<5 AND HOUR(t) BETWEEN 8 AND 17)
SELECT 'RESP', DATE_FORMAT(t,'%Y-%m'), area, COUNT(*), SUM(r IS NULL), SUM(TIMESTAMPDIFF(MINUTE,t,r)<15), SUM(TIMESTAMPDIFF(MINUTE,t,r)<60), SUM(TIMESTAMPDIFF(MINUTE,t,r)<240) FROM y GROUP BY 2,3;
"
