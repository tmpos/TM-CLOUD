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
```

La prueba crea un proyecto temporal, confirma la tienda automática, publica un producto sin exponer su costo y valida que una factura solo sea accesible con la identidad correcta.
