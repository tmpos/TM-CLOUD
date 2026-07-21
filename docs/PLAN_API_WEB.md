# Plan maestro: API segura, correo, portal web y facturas PDF

Actualizado: 2026-07-21

Este documento es la fuente de verdad del trabajo. Una tarea solo se marca con `[x]` cuando el código está implementado y se ha realizado la verificación disponible. Las tareas parcialmente implementadas permanecen sin marcar y deben explicar lo que falta.

## 0. Seguimiento

- [x] Crear este plan maestro con fases, dependencias y criterios de aceptación.
- [x] Mantener este documento actualizado después de cada cambio verificable.
- [ ] Crear una batería automatizada de pruebas para API, licencias, correo, portal y PDF.
- [x] Preparar checklist y procedimiento de despliegue a `https://api.tmposystem.com`.
- [x] Resolver el conflicto preexistente del Dockerfile conservando SQLite, Apache y realtime.
- [x] Preparar el contenedor para ejecutar el trabajador de correo cuando esté habilitado.

## 1. Seguridad crítica del API

- [x] Crear un serializador público de licencias que nunca entregue `secret_key`, tokens internos ni datos privados innecesarios.
- [x] Aplicar el serializador a `/api/license/info`, `/licenses/info` y `/api/license/connect`.
- [x] Aplicar respuestas seguras al listado, creación, actualización y detalle de licencias del API.
- [x] Entregar la clave secreta del proyecto solamente durante su creación o rotación autorizada.
- [x] Eliminar copias de `public_key` y `secret_key` dentro de licencias; resolverlas desde el proyecto y limpiar valores históricos durante la migración.
- [x] Proteger `/api/project/create` con límites por IP por minuto y por día.
- [x] Aplicar rate limiting por IP y por licencia a validate, info, connect y use.
- [x] Comprobar `project.status` en todas las rutas API de datos, esquema, almacenamiento, backups y licencias.
- [x] Rechazar en el API cualquier proyecto cuyo estado no sea `active`.
- [x] Verificar que cada licencia operada por la API pertenezca al proyecto autenticado.
- [x] Estandarizar errores del API con `code`, `message` y `request_id`; conservar `error` temporalmente para clientes anteriores y registrar detalle técnico en el log PHP.
- [x] Añadir cabeceras de seguridad y política CORS explícita configurable.
- [x] Aceptar `X-Forwarded-For` únicamente desde una lista exacta y configurable de proxies confiables.
- [x] Añadir rotación independiente de clave pública o secreta, con revocación inmediata de la anterior y auditoría.
- [x] Redactar automáticamente contraseñas, tokens, credenciales y claves antes de escribir cualquier auditoría.
- [ ] Cifrar en reposo las claves recuperables del proyecto sin romper la autenticación Bearer ni la rotación.

### Criterio de aceptación

Una petición anónima o con clave pública no puede obtener ninguna clave secreta, crear recursos ilimitados, acceder a un proyecto suspendido ni recibir rutas/errores internos.

## 2. Licencias y dispositivos

- [x] Añadir en cada registro del panel un botón `License` que abre una modal de solo lectura y permite copiar la licencia.
- [x] Hacer que `/api/license/connect` registre equipos nuevos sin autorizarlos.
- [x] Responder `authorized: false` y `pending_authorization: true` para solicitudes pendientes.
- [x] Permitir registrar un equipo pendiente aunque la licencia haya alcanzado el máximo de autorizados.
- [x] Hacer que únicamente `authorize-device` mueva el equipo a autorizados y actualice el uso.
- [x] Exponer autorizados y pendientes por separado en `GET .../devices`.
- [x] Documentar que `connect` crea una solicitud pendiente.
- [x] Crear tabla `license_devices` con estados `pending`, `authorized`, `blocked`, `revoked`.
- [x] Migrar sin pérdida los JSON `dispositivos` y `equipos_no_autorizados`.
- [x] Añadir índice único por licencia y `device_id`.
- [x] Ejecutar connect/authorize/block/revoke dentro de transacciones para evitar carreras.
- [x] Guardar fechas de solicitud, autorización, revocación y último contacto.
- [x] Guardar versión de aplicación e IP sin exponerlas en la respuesta pública.
- [x] Calcular `current_uses` desde dispositivos autorizados; no mantener contadores divergentes.
- [x] Redefinir `reset-uses` para revocar los dispositivos autorizados explícitamente.
- [x] Convertir `/api/license/use` en validación idempotente sin incrementar usos.
- [x] Añadir endpoints para revocar y bloquear; `connect` permite volver a solicitar después de revocación.
- [x] Registrar auditoría de solicitud, autorización, bloqueo y revocación.

### Criterio de aceptación

Un equipo nunca entra autorizado mediante `connect`; dos solicitudes simultáneas no duplican registros y el límite siempre coincide con el número real de dispositivos autorizados.

## 3. Archivos y almacenamiento

- [x] Normalizar `directory` y bloquear `..`, rutas absolutas, bytes nulos y separadores inválidos.
- [x] Verificar con ruta canónica que todo archivo permanezca dentro del proyecto.
- [x] Implementar lista permitida de tipos y extensiones con validación MIME.
- [x] Rechazar PHP, HTML, scripts, SVG no saneado y ejecutables.
- [x] Servir archivos con `nosniff` y nombre saneado.
- [x] Añadir cuota configurable por archivo y proyecto (el almacenamiento pertenece al proyecto, no a usuarios individuales).
- [x] Añadir limpieza autenticada de registros sin archivo, archivos huérfanos con período de seguridad y directorios vacíos.
- [x] Evitar exposición de rutas físicas y nombres internos en respuestas del API de almacenamiento.
- [ ] Añadir pruebas de recorrido de directorio y contenido malicioso.

## 4. Backups y recuperación

- [x] Cerrar o coordinar la conexión SQLite cacheada antes de restaurar.
- [x] Restaurar primero a un archivo temporal.
- [x] Ejecutar `PRAGMA integrity_check` antes y después del reemplazo.
- [x] Reemplazar la base de forma atómica y conservar backup de seguridad.
- [x] Definir retención automática configurable por cantidad y antigüedad, conservando siempre el backup más reciente.
- [x] Aplicar cuota configurable de backups por proyecto conservando siempre el backup más reciente.
- [ ] Cifrar backups que contengan datos sensibles.
- [ ] Programar backups automáticos y pruebas periódicas de restauración.
- [x] Registrar tamaño, checksum y estado; falta asociar usuario responsable en todos los flujos.

## 5. Sincronización confiable

- [ ] Introducir cursor monotónico de cambios por proyecto, no depender solo de fechas.
- [ ] Mantener tombstones de eliminaciones con retención configurable.
- [ ] Añadir `Idempotency-Key` a creación de facturas, pagos y operaciones críticas.
- [x] Hacer bulk/upsert transaccional con modo todo-o-nada opcional mediante `{"rows": [...], "atomic": true}`.
- [x] Definir relaciones remotas principales por UID y resolver claves foráneas al descargar en TM-RESTAURANTE.
- [ ] Crear endpoint de snapshot completo con cursor final y checksums.
- [ ] Validar conteos/checksums después de cambiar una licencia o restaurar empresa.
- [ ] Detectar y resolver conflictos con política documentada.
- [ ] Añadir versión de esquema y migraciones compatibles con clientes antiguos.

## 6. Correo centralizado en el servidor

- [ ] Instalar PHPMailer mediante Composer en el servidor (la dependencia ya está declarada).
- [x] Configurar SMTP únicamente mediante variables de entorno/secretos del servidor.
- [x] Crear `MailService` con TLS, timeout, remitente, reply-to y validación de destinatarios.
- [x] Crear tabla `mail_queue` con estados `pending`, `sending`, `sent`, `failed`.
- [x] Crear trabajador CLI/cron con reintentos y backoff.
- [x] Añadir plantillas HTML y texto controladas para prueba, factura, resumen semanal y licencia.
- [x] Crear endpoint secreto `POST /api/{project}/mail/send`.
- [x] Crear endpoint público controlado por licencia + dispositivo autorizado para clientes instalados.
- [x] Crear endpoint específico para enviar una factura con plantilla controlada y enlace dinámico.
- [ ] Permitir adjuntar el PDF generado por el servidor.
- [x] Guardar message-id, intentos y último error saneado.
- [x] Añadir límite por proyecto/destinatario para la cola de correo.
- [ ] Añadir pantalla administrativa de configuración y prueba de correo.
- [x] Modificar TM-RESTAURANTE para solicitar el envío al API en vez de conectarse a SMTP.
- [x] Eliminar credenciales SMTP almacenadas en el cliente después de migrar.

### Criterio de aceptación

El cliente envía solo el tipo de mensaje, destinatario y UID del recurso. TMPBASE compone, genera adjuntos, envía, reintenta y registra el resultado sin revelar credenciales SMTP.

## 7. Portal web de los sistemas

- [x] Separar usuarios administradores de TMPBASE de usuarios del portal empresarial.
- [x] Crear tablas `portal_users`, `project_memberships` y sesión independiente.
- [ ] Añadir recuperación de contraseña y verificación de correo.
- [x] Implementar roles por proyecto: propietario, administrador, contabilidad y consulta (control base de membresía; faltan permisos finos por acción).
- [x] Crear inicio de sesión del portal con protección CSRF y rate limiting.
- [x] Crear selector/dashboard de empresa según membresías.
- [x] Crear dashboard inicial con ventas y cantidad de facturas.
- [x] Crear listado paginado y detalle visual/PDF de facturas.
- [x] Crear vistas iniciales de clientes, productos y categorías.
- [x] Crear diseño responsive inicial para computadora, tableta y móvil.
- [x] Aislar cada consulta del portal por membresía y proyecto activo.
- [ ] Registrar auditoría de accesos, descargas y acciones.
- [ ] Añadir enlaces desde el panel TMPBASE para gestionar miembros del portal.
- [ ] Preparar una API versionada para futuras aplicaciones web completas de cada sistema.

### Criterio de aceptación

Un usuario solo puede ver proyectos donde tenga membresía y nunca puede cambiar el UID de la URL para acceder a otra empresa.

## 8. Facturas PDF en línea y enlaces compartibles

- [x] Rediseñar `PdfService` con plantilla profesional y responsive de factura.
- [x] Obtener empresa, factura, cliente, detalle, impuestos y pagos directamente del proyecto.
- [x] Escapar todo contenido antes de producir HTML/PDF.
- [x] Crear tabla `shared_documents` con token aleatorio hasheado, proyecto, recurso, expiración y revocación.
- [x] Crear enlace estable `GET /share/invoice/{token}` para vista web.
- [x] Crear enlace estable `GET /share/invoice/{token}.pdf` para PDF.
- [x] Generar el documento desde los datos actuales en cada solicitud.
- [x] Añadir ETag para evitar transferencias innecesarias sin servir versiones obsoletas.
- [x] Permitir crear, revocar y definir vencimiento del enlace.
- [x] Añadir botones para enviar por correo, WhatsApp y copiar el enlace dinámico desde la factura del cliente.
- [x] Evitar que el token revele UID, licencia o clave del proyecto; solo se guarda su hash.
- [x] Registrar conteo y fecha de aperturas/descargas sin almacenar IP pública.
- [x] Añadir logo de empresa y código QR fiscal DGII en la factura cuando existe NCF/e-NCF, usando RNC, total y código de seguridad.

### Criterio de aceptación

Después de modificar y sincronizar una factura desde TM-RESTAURANTE, el mismo enlace muestra y descarga el PDF actualizado sin volver a crear ni compartir otra URL.

## 9. Calidad, observabilidad y despliegue

- [ ] Añadir pruebas unitarias de servicios críticos.
- [ ] Añadir pruebas de integración con SQLite temporal.
- [ ] Añadir pruebas HTTP de permisos público/secreto y aislamiento multiempresa.
- [ ] Añadir pruebas de correo con transporte falso.
- [ ] Añadir pruebas de PDF y enlaces revocados/vencidos.
- [x] Añadir logs de error estructurados en JSON con `request_id`, estado, clase y ubicación.
- [ ] Añadir métricas de errores, latencia, cola de correo, sincronización y almacenamiento.
- [ ] Añadir endpoint de health profundo sin revelar secretos.
- [ ] Añadir migraciones versionadas y rollback documentado.
- [x] Actualizar API Docs, README, checklist de despliegue y `.env.example`.
- [ ] Ejecutar revisión de seguridad previa al despliegue.
- [ ] Desplegar primero en staging, ejecutar smoke tests y luego producción.

## Orden de ejecución acordado

1. Seguridad crítica y almacenamiento.
2. Modelo transaccional de dispositivos/licencias.
3. Backups, errores y sincronización.
4. Correo centralizado.
5. Usuarios y portal web.
6. PDF dinámico y enlaces compartibles.
7. Integración final con TM-RESTAURANTE.
8. Pruebas, staging y producción.
