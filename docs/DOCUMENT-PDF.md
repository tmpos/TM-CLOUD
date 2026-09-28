# Facturas y cotizaciones PDF

El enlace compartido usa `PdfService` y `app/Views/document-pdf.php`. La
plantilla sigue el diseño del escritorio: datos de empresa a la izquierda,
identificación del documento a la derecha, ficha de cliente, productos y
resumen. Usa tablas para ser compatible con mPDF. El pie PDF se repite por
página. No depende de recursos externos para fuentes o estilos.

Los datos del cliente se resuelven por `cliente_uid`, `cliente_id` o
`cod_cliente`, dentro de la empresa autorizada. La información incluida en
la factura tiene prioridad; la ficha del cliente completa los datos que
faltan. Los fallbacks por documento, teléfono o nombre deben ser únicos y
no vacíos. El código interno del cliente nunca sustituye al RNC/cédula.

`DocumentSettingsService` almacena el diseño en `_document_settings` dentro
de la base de cada empresa. Los endpoints licenciados son
`POST /api/license/document-settings/get` y `/save`. Los canales del runtime
son `documentos:obtenerDiseno` y `documentos:guardarDiseno`; guardar exige
Administrador o Soporte. No se acepta un proyecto elegido por el cliente.

Se configuran dos colores hexadecimales, visibilidad y tamaño del logo,
y días de validez. Facturas y cotizaciones comparten estos ajustes. Los
enlaces existentes los aplican al abrirse; no se cambia el token compartido.
Las cotizaciones muestran COT y omiten información fiscal, pagos y firmas
de entrega. Sus archivos se descargan como `Cotizacion_COT….pdf`.

La firma del representante se guarda por empresa en la misma configuración:
`show_company_signature`, `representative_name` y `representative_signature`.
Solo admite PNG/JPEG embebido validado (máximo 700000 caracteres, 2000 píxeles
por lado y 2 millones de píxeles). El interruptor está desactivado inicialmente;
activarlo exige nombre e imagen. Desactivarlo conserva la imagen y oculta la firma
en ambos documentos. Guardar desde clientes anteriores conserva los campos
omitidos. La firma del cliente no se reemplaza. Los enlaces existentes aplican
el interruptor al volver a generarse; los PDF ya descargados no se modifican.

La descripción guardada del servicio se imprime debajo del nombre, conservando
saltos de línea y escapando HTML. Se admite tanto detalle en tabla como productos
embebidos en la factura, sin duplicar una descripción usada como nombre.

Validación: `php tests/invoice-pdf-smoke.php` genera facturas y cotizaciones
reales con mPDF; `php tests/document-settings.php` verifica persistencia,
permisos, validación de estilos y aislamiento entre empresas.
