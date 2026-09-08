# TMPOS dentro de TMPBASE

La aplicacion completa se publica en `/sistema`. Igual que la tienda `/store/{slug}`, cada proyecto abre su propia instancia en `/sistema/{slug}` y usa exclusivamente `/api/system/{slug}/*`. Ejemplo: `/store/tmpos` corresponde a `/sistema/tmpos`. El navegador nunca recibe claves privadas ni rutas de base de datos.

## Acceso por proyecto

1. Abra `/sistema/{slug}` o use **Abrir sistema** dentro del proyecto.
2. Ingrese el PIN de cuatro digitos, igual que en el sistema cliente.
3. El PIN se valida contra usuarios activos de `usuarios` en la SQLite de ese proyecto.
4. La sesion conserva el usuario real, rol y permisos. Cada solicitud abre unicamente esa SQLite.

El acceso web no solicita usuario ni contrasena. Las pantallas respetan el usuario real, su rol y sus permisos de TMPOS. Ventas, cobros, turnos, ajustes y transferencias usan transacciones del servidor.

## Publicar varias interfaces

La carpeta historica `public/sistema/app/` continua disponible como **Sistema TMPOS (predeterminado)**.

Para agregar otras interfaces:

1. Compile el proyecto web, por ejemplo con `npm run build`, configurando rutas relativas (`base: './'` en Vite).
2. Comprima el contenido de `dist/` en un ZIP. Debe existir un unico `index.html`.
3. En TMPBase abra **Sistemas web**, indique un nombre y una carpeta (por ejemplo `gimnasio`, `restaurante` o `tienda`) y suba el ZIP.
4. Al crear un proyecto seleccione la interfaz deseada. En proyectos existentes puede cambiarla desde **Configuracion > Ubicacion del sistema**.

Cada interfaz se publica en su propia carpeta dentro de `public/system-apps/`. Los archivos ejecutables del servidor no se aceptan y una carpeta en uso no puede eliminarse.
La carga y extraccion requieren la extension PHP `zip` habilitada en el servidor.

Use HTTPS y cookies seguras.

La impresion usa el dialogo del navegador. Bluetooth, actualizacion del ejecutable y acceso directo a archivos locales siguen siendo capacidades de la aplicacion instalada.
