# Reporte de uso de la API — Crecer Reproducción Humana

> Solicitud de acceso al ambiente de **producción** de Omnia Salud.

## 1. Descripción del integrador

- **Nombre**: Plataforma operativa interna de Crecer Reproducción Humana (centro de reproducción y genética humana, Mar del Plata).
- **Naturaleza**: aplicación web propia, on-premise, alojada en un servidor de la clínica. NO se redistribuye, NO es un SaaS multi-tenant.
- **Identificador en Omnia (test)**: centerId **811**, organizationId **672**, usuario `evelina+crecerreproduccion@omniasalud.com`.
- **Acceso a la API**: server-to-server desde el servidor de la clínica. No hay tokens viajando al cliente; el navegador del operador NO ve nunca el JWT de Omnia.

## 2. Endpoints que la app consume

| # | Endpoint | Método | Uso |
|---|---|---|---|
| 1 | `/api/fhir/auth/signin` | POST | Login para obtener `accessToken` (JWT). |
| 2 | `/api/fhir/auth/refreshToken` | POST | Reservado para uso futuro — hoy hacemos re-signin on-demand cuando el token expira. |
| 3 | `/api/v1/external/patients/by-personal-id` | GET | Tablet de recepción: paciente ingresa DNI y obtenemos su `patientId`, nombre y obra social. |
| 4 | `/api/v1/external/patients/{id}/appointments/pending` | GET | Tablet de recepción: turnos del día del paciente que acaba de identificarse. |
| 5 | `/api/v1/external/reports/appointments/ambulatory` | GET | Panel del médico: lee la agenda completa del día del centro, filtra localmente por `NombreDelProfesional` para mostrarle a cada médico SU agenda. **Solo lectura, solo estado `pendiente`.** |

**Nada de escritura.** No creamos turnos, no cancelamos, no modificamos datos en Omnia. La integración es 100 % de lectura.

## 3. Casos de uso operativos

### Caso A — Tablet de auto check-in en recepción

*(en producción interna desde 2026-05-03)*

- Una tablet montada en sala de espera muestra un teclado para que el paciente ingrese su DNI.
- Al ingresar: `GET by-personal-id` → confirma identidad. `GET appointments/pending` → muestra los turnos del día.
- El paciente confirma su turno → solo lectura, no se modifica nada en Omnia (la confirmación queda en la base de datos local).
- Llamadas por paciente: **2** (1 `by-personal-id` + 1 `appointments/pending`).
- Volumen estimado: 60-120 pacientes/día → **120-240 calls/día**.

### Caso B — Panel del médico: agenda del día

*(en desarrollo, se activa al pasar a producción)*

- El médico abre `/medico` y ve su agenda del día (turnos pendientes únicamente).
- Llamada: **1 GET** a `reports/appointments/ambulatory?start=hoy_00:00&end=hoy_23:59`.
- **Cache server-side de 60 segundos** compartida entre médicos del mismo centro y día. El reporte trae todo el centro y filtramos en cliente por `NombreDelProfesional` → una sola llamada cada minuto alimenta a todos los médicos.
- Volumen estimado: 1 call cada 60 s durante la jornada de 10 h = **10 calls/hora × 10 h ≈ 100 calls/día**.

### Volumen total

~**250-350 requests/día** contra el ambiente productivo. Picos en horario de mañana (8-11 h).

## 4. Manejo del token JWT

- El `accessToken` se obtiene con `signin` y se cachea **en la base de datos del servidor de la clínica** (no en filesystem, no en cookies, no en el cliente) con TTL de **1500 s** (margen de 5 min sobre los 1800 s reales).
- Si una request devuelve **401**, se invalida el cache y se hace un nuevo signin automático **una sola vez** antes de reintentar. No hay loops.
- El `refreshToken` queda registrado en memoria pero hoy no se usa (re-signin es más simple y barato).
- Las credenciales (`OMNIA_USER`, `OMNIA_PASSWORD`) viven en `.env` del servidor, fuera del control de versiones.

## 5. Datos personales y compliance

- La app es **on-premise**, los datos no salen del servidor de la clínica.
- Los datos consumidos de Omnia (DNI, nombre, obra social, turnos) se usan **para la operación clínica diaria de la propia institución** que los generó. No se comparten con terceros.
- Logs de la app: el header `Authorization: Bearer ***` queda redactado, no se loggean tokens.
- En caso de error 4xx/5xx, loggeamos el `status` y los primeros 300 caracteres del body para diagnóstico — sin datos sensibles de paciente en logs.

## 6. Confiabilidad y rate-limit

- Timeouts: 8 s por request (auth y data).
- Reintento único en 401 con re-signin automático.
- Cache de 60 s sobre el endpoint pesado (`reports/appointments/ambulatory`).
- Si Omnia está caída, la pantalla del médico muestra la sección vacía con un cartel "agenda no disponible" — la aplicación no rompe. La tablet de check-in sí queda inutilizable mientras la API esté abajo, porque depende de la API en tiempo real.

## 7. Contacto técnico

- **Desarrollador**: Damián Orozco — damianorozco@gmail.com
- **Stack**: Laravel 12 + MySQL 8.0 sobre Linux/Docker, on-premise.
