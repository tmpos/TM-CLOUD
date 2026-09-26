# Formulario configurable de clientes

Configuración → Registro de clientes permite elegir visibilidad y obligatoriedad
de los campos del enlace público y del QR. Incluye título, mensaje, color,
logo de Empresa y vista previa. Nombre siempre obligatorio; documento y teléfono
obligatorios por defecto, pero configurables. Los campos CRM adicionales son
configurables; el consentimiento de contacto permanece obligatorio.

## API y persistencia

- `POST /api/license/customer-registration-settings/get` y `/save`: licencia y
  dispositivo autorizado, proyecto derivado de la licencia.
- Runtime: `clientes:obtenerFormulario` y `clientes:guardarFormulario`. El
  guardado web requiere una sesión Administrador/Soporte. Electron usa los
  endpoints licenciados y verifica el permiso de configuración.
- Respuesta: `{data: {settings, branding: {name, logo_url}}}`.
- `settings`: `version: 1`, `show_logo`, `title`, `description`, `primary_color`,
  `fields`. Cada campo tiene `visible` y `required` booleanos.
- Campos: `nombre`, `telefono`, `documento`, `email`, `direccion`, `tipo_cliente`,
  `producto_interes`, `necesidad`. Los últimos dos solo se muestran en CRM.
- Se guarda un documento JSON validado en `_customer_registration_settings`,
  dentro de la base del proyecto. La tabla se crea al guardar. No requiere
  migración de registros existentes ni altera otros proyectos.
- Las reglas se consultan al abrir y enviar cada formulario, incluso para
  enlaces existentes. Campos ocultos se ignoran en POST; campos visibles
  opcionales conservan validación de formato si se completan. Fallos de
  validación no crean clientes ni consumen el enlace.
- Logo desde Empresa, seleccionada por almacén del enlace. Solo referencias
  de almacenamiento, HTTPS o imágenes raster base64; no HTML ni scripts.

## Verificación

`php tests/customer-registration-smoke.php`

`php tests/crm-registration-smoke.php`

`php tests/customer-registration-settings.php`

Pruebas en contenedor aislado sin datos de producción: obligatoriedad,
persistencia, aislamiento, campos ocultos, enlaces existentes, CRM, logo,
escape de contenido y reintentos. Editor verificado en navegador contra un
backend aislado y formulario público revisado en móvil.

## Publicación

Imagen base: `tmpos-system-api-73trsf:web601-20260925`.
Imagen de esta entrega: `tmpos-system-api-73trsf:customer-form-20260926`.
Incluye los servicios/controlador/vista de registro y el bundle web actualizado.
Preserva los demás cambios de la imagen base y el volumen de datos existente.

Reversión de código:
`docker service update --image tmpos-system-api-73trsf:web601-20260925 tmpos-system-api-73trsf`.
La configuración guardada permanece, pero el código anterior no la aplica.
El escritorio debe usar una compilación actualizada y reiniciarse para cargar
los nuevos canales; el editor web queda incluido en la publicación.
