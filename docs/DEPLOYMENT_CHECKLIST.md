# Despliegue seguro de TMPBase

Este procedimiento evita publicar cambios de API, correo, portal o PDF sin validar. Debe ejecutarse primero en staging y después en `https://api.tmposystem.com`.

## 1. Preparación

- [ ] Crear un backup completo de `.env`, `storage/tmpbase.sqlite`, `storage/projects`, `storage/backups` y `storage/uploads`.
- [ ] Confirmar PHP 8.2 o 8.3 y extensiones `pdo_sqlite`, `fileinfo`, `mbstring`, `gd` y `zip`.
- [ ] Ejecutar `composer install --no-dev --optimize-autoloader`; PHPMailer y mPDF deben quedar instalados.
- [ ] Ejecutar `composer validate --strict` y `php -l` sobre todos los archivos PHP modificados.
- [ ] Confirmar que PHP tiene habilitadas `pdo_sqlite`, `fileinfo`, `mbstring` y `gd`; verificar que `Mpdf\\Mpdf` carga desde `vendor/autoload.php`.
- [ ] Ejecutar `php tests/mpdf-smoke.php` y confirmar `PDF_SMOKE=OK` y `PHPMAILER=OK` antes de publicar.
- [ ] Ejecutar `php tests/invoice-pdf-smoke.php` y confirmar que la factura profesional con logo y QR DGII se genera correctamente.
- [ ] Mantener `APP_DEBUG=false`, `SESSION_SECURE=true` y una URL HTTPS sin barra final.
- [ ] Configurar `CORS_ALLOWED_ORIGINS` con orígenes concretos, nunca `*` en producción.
- [ ] Configurar `TRUSTED_PROXIES` solo si existe un proxy inverso conocido; no confiar globalmente en `X-Forwarded-For`.
- [ ] Revisar `PROJECT_STORAGE_MAX_MB`, `BACKUP_RETENTION_COUNT`, `BACKUP_RETENTION_DAYS` y `BACKUP_MAX_MB_PER_PROJECT` según el espacio disponible.
- [ ] Configurar los secretos `MAIL_*` en el servidor y verificar que `.env` no sea accesible por HTTP.

## 2. Staging

- [ ] Desplegar una copia de código y datos anonimizados en staging.
- [ ] Abrir el panel para ejecutar las migraciones automáticas y revisar que no existan errores PHP.
- [ ] Crear un proyecto temporal y conservar sus claves solo durante la prueba.
- [ ] Crear licencia, conectar un equipo y confirmar que queda `pending`.
- [ ] Autorizar el equipo y confirmar que solo entonces puede entrar y enviar correo.
- [ ] Ejecutar dos veces `connect` y comprobar que no duplica dispositivo ni uso.
- [ ] Probar carga rechazada de `../archivo.php`, HTML, SVG y ejecutables.
- [ ] Crear backup, descargarlo, restaurarlo y comprobar `integrity_check`.
- [ ] Sincronizar una factura con detalle, cliente y pagos.
- [ ] Crear enlace web/PDF, modificar la factura, sincronizar y verificar que el mismo enlace cambió.
- [ ] Enviar correo de prueba y factura; comprobar transición `pending -> sending -> sent` en `mail_queue`.
- [ ] Forzar un fallo SMTP y confirmar reintento, backoff y error saneado.
- [ ] Crear dos usuarios de portal en proyectos distintos e intentar intercambio de UID en la URL.
- [ ] Verificar que los errores JSON incluyen `code`, `message`, `request_id` y cabecera `X-Request-Id`.

## 3. Producción

- [ ] Activar modo mantenimiento o detener temporalmente procesos de escritura.
- [ ] Subir código sin reemplazar `.env` ni `storage/`.
- [ ] Ejecutar Composer y validar permisos de escritura del usuario PHP sobre `storage/`.
- [ ] Iniciar un worker persistente con `php bin/mail-worker` mediante supervisor/cron o usar el contenedor preparado.
- [ ] Confirmar HTTPS, HSTS, CORS, cookies seguras y bloqueo HTTP de `.env`, `composer.json`, `app/` y `storage/`.
- [ ] Ejecutar smoke tests de health, licencia, sync, portal, enlace PDF y correo.
- [ ] Revisar logs por `request_id`, crecimiento de cola, respuestas 4xx/5xx y espacio libre.
- [ ] Retirar modo mantenimiento y conservar el backup previo hasta completar el período de observación.

## 4. Reversión

- [ ] Detener escrituras y worker de correo.
- [ ] Restaurar el código anterior y su `composer.lock`/`vendor` compatible.
- [ ] Restaurar primero la base central y después las bases de proyectos necesarias.
- [ ] Ejecutar `PRAGMA integrity_check` en cada base restaurada.
- [ ] Documentar el `request_id`, hora, causa y alcance del incidente antes de reintentar.
