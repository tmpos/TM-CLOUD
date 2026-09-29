# Tiendas web multiempresa

## Creación automática

`Database::migrate()` crea la tabla central `storefronts` y genera una tienda para cualquier proyecto anterior que todavía no tenga una. `ProjectService::create()` crea la tienda de los proyectos nuevos en la misma solicitud.

La URL predeterminada utiliza el slug único del proyecto:

```text
/store/{slug}
```

## Catálogo

La detección automática busca `productos`, `accesorios`, `inventario`, `articulos` o `items`. El administrador puede elegir otra tabla desde la configuración.

El servicio crea una respuesta pública limitada. Los registros inactivos u ocultos no se publican y nunca se entrega el registro original completo.

Las tarjetas del catálogo enlazan a `/store/{slug}/products/{uid}`. La ficha reconoce imágenes individuales (`imagen`, `imagen2`, `foto`, etc.) y galerías JSON (`imagenes`, `images`, `galeria` o `gallery`). También expone una lista controlada de especificaciones como marca, modelo, color, capacidad, memoria, condición y garantía.

El carrito se encuentra en `/store/{slug}/cart` y el checkout en `/store/{slug}/checkout`. El carrito se conserva en el navegador por tienda, pero el servidor vuelve a validar precios, publicación y existencias antes de crear cada pedido.

La página `/store/{slug}/categories` presenta todas las categorías con sus cantidades y permite combinar búsqueda, categoría, rango de precio, disponibilidad y ordenamiento por fecha, precio o nombre.

## Precios y almacén de la tienda

En `/{slug}/admin/web`, los usuarios con rol `owner` o `manager` pueden guardar **Mostrar precios** y el **Almacén de la tienda**. El guardado requiere sesión y protección CSRF. Estos ajustes también están disponibles en la configuración de la tienda del proyecto.

`show_prices` está habilitado por defecto, incluso para tiendas existentes. Al desactivarlo, la tienda funciona como catálogo: muestra «Consultar precio», oculta precios de venta y comparación en las páginas y respuestas públicas, ignora filtros y ordenamientos por precio y bloquea compras web. El POS administrativo conserva sus precios. Volver a activarlo recupera la compra normal sin modificar los precios del inventario.

`warehouse_uid` identifica un almacén de la tabla `empresa` del mismo proyecto. Si está vacío, se utiliza el primero por `id` ascendente. El selector permite elegir otro; si el almacén seleccionado se elimina, no se publica inventario de un almacén diferente como sustituto.

Los productos se filtran por `almacen_uid` y, cuando falta ese valor, por el `almacen_id` heredado. El UID tiene prioridad si ambos campos discrepan. Los registros sin asignación se consideran del primer almacén. Las tablas sin campos de almacén mantienen sus modelos compartidos; las existencias y precios derivados de IMEI o seriales se calculan con el inventario del almacén seleccionado. Los pedidos conservan el almacén utilizado al crearse para que un cambio posterior de configuración no desvíe su descuento de existencias.

## Checkout, diseño y pagos

La administración de la tienda permite controlar colores, tipografía, bordes, portada, tarjetas, anuncio, redes, datos de contacto, retiro, entrega, envío nacional, tarifa y envío gratis desde un monto.

Los métodos disponibles son efectivo, transferencia, tarjeta al recibir, Azul mediante página de pago alojada, Stripe Checkout y PayPal Orders. Stripe y PayPal crean la orden en el servidor y redirigen al entorno seguro del proveedor. Las credenciales privadas se cifran con `CredentialCipher`, nunca se incluyen en HTML ni en las API públicas.

Las órdenes y sus productos se guardan en `storefront_orders` y `storefront_order_items`. La confirmación pública requiere la sesión que creó el pedido.

Endpoints:

```text
GET /api/storefront/{slug}
GET /api/storefront/{slug}/products
GET /api/storefront/{slug}/products/{uid}
POST /api/storefront/{slug}/checkout
```

## Consulta de facturas

El formulario acepta el número de factura, NCF, código o UID y un correo, teléfono, celular, cédula, RNC, documento o identificación del titular.

La verificación se realiza primero contra la factura y luego contra `clientes`/`customers` cuando existe una relación mediante UID o ID. Una coincidencia crea autorización de sesión por 15 minutos. No se coloca ninguna clave API en el navegador.

## Seguridad

- La clave secreta del proyecto nunca se entrega a la tienda.
- El catálogo utiliza una lista explícita de campos públicos.
- La consulta de facturas tiene CSRF, rate limiting y mensajes que no permiten enumerar documentos.
- Las vistas de facturas usan `no-store` y `noindex`.
- Los colores, URLs y datos configurables se validan antes de persistirlos.

## Verificación

```bash
php tests/storefront-smoke.php
php tests/storefront-catalog-settings.php
```

La prueba crea un proyecto temporal, confirma la tienda automática, publica un producto sin exponer su costo y valida que una factura solo sea accesible con la identidad correcta.

La prueba de configuración verifica la ocultación pública y los precios internos, el bloqueo de checkout, la selección y el cambio de almacén, la compatibilidad con IDs y UIDs, el filtrado de IMEI/seriales, el descuento y restauración de existencias y que repetir las migraciones conserve los ajustes guardados.
