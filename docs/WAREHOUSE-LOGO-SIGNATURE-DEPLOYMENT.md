# Logos por almacén y firma del representante — 28/09/2026

Publicado el backend `92cdd8a` y el frontend TMPOS `03327b2`. Imagen desplegada:
`tmpos-system-api-73trsf:warehouse-logo-signature-20260928`; actualización Swarm completada.

La empresa nueva permite elegir logo propio, reutilizar el actual o dejarlo vacío.
Los PDF del servidor seleccionan la empresa por UID del almacén de la factura;
el respaldo por ID requiere coincidencia única. Quitar un logo no elimina el
archivo compartido por otras empresas.

La captura de firma utiliza los canales autenticados de creación, consulta y
cancelación, limitados a Administrador/Soporte. Las rutas licenciadas están en
`/api/license/company-signature/{create|status|cancel}`. El enlace público
`/sign/company/{project}/{token}` vence en 24 horas, exige consentimiento y solo
permite una firma. Se almacena el hash del token. La captura no cambia el diseño:
el operador revisa la firma recibida y guarda el diseño con el interruptor deseado.

Validación: 597 pruebas frontend, TypeScript, compilación, 4 pruebas de herramientas
de publicación, navegador móvil/escritorio y tests PHP de firma, PDF y diseño en
contenedor aislado sin datos de producción. Lint de los archivos de esta entrega
correcto; el lint general frontend señala un error previo de mutación de prop en
`UsuarioFormComp.vue:133`.

La imagen deriva de `signature-upload-ui-20260928` y conserva la inicialización
existente del servidor. No incluye los cambios locales ajenos de pedidos web,
catálogo, OTP ni la carpeta experimental `node-port`.

Reversión del código:
`docker service update --image tmpos-system-api-73trsf:signature-upload-ui-20260928 tmpos-system-api-73trsf`.
Las firmas capturadas se conservan en la base del proyecto.
