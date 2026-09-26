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

Validación: `php tests/invoice-pdf-smoke.php` genera facturas y cotizaciones
reales con mPDF; `php tests/document-settings.php` verifica persistencia,
permisos, validación de estilos y aislamiento entre empresas.
