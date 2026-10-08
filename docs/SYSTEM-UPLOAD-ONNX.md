# Sistemas web con modelos ONNX

El cargador `/system-apps/upload` acepta modelos `.onnx` junto con los recursos
web y WASM. Se mantienen el límite de subida, el límite de 1 GB descomprimido,
la validación de rutas y el rechazo de ejecutables PHP/EXE. No se extraen ZIP
anidados ni se cambia la selección de sistema de ningún proyecto.

Verificación del extractor y de un ZIP compilado:

```sh
php tests/system-app-onnx.php /ruta/TM-GYM-web.zip
```

## Persistencia en Docker

Conservar estos montajes del servicio `tmpos-system-api-73trsf` también en
la configuración del gestor de despliegues:

| Origen del host | Destino del contenedor |
| --- | --- |
| `/opt/tmposystem-storage` | `/var/www/html/storage` |
| `/opt/tmposystem-ui/sistema` | `/var/www/html/public/sistema` |
| `/opt/tmposystem-ui/system-apps` | `/var/www/html/public/system-apps` |

El montaje del sistema predeterminado se hace en **sistema**, no en
**sistema/app**: el reemplazo del ZIP necesita renombrar `app` para guardar la
versión anterior y publicar de forma atómica. Renombrar un punto de montaje
directo fallaría.

Antes de añadir los montajes, copiar las carpetas completas del contenedor
activo al host y verificar sus SHA-256. Nunca montar carpetas vacías sobre los
sistemas ya publicados. El entrypoint solo siembra destinos vacíos.

La actualización ONNX del 08/10/2026 parte de la imagen activa y cambia solo
`SystemAppService.php` y su prueba. El respaldo y el manifiesto de los sistemas
anteriores quedan en `/opt/tmposystem-releases/upload-onnx-20261008/`.
Para revertir el código, usar la imagen de `previous-image.txt` conservando los
montajes persistentes; no restaurar ni reemplazar las bases de datos.

## Resultado de publicación

- GitHub: corrección `65170aa` en `main`.
- VPS: imagen `tmpos-system-api-73trsf:upload-onnx-20261008`, actualización Swarm completada.
- Los 6598 archivos existentes coinciden con el manifiesto SHA-256 anterior.
- Los dos montajes de interfaces también se registraron en Dokploy.
- TM-GYM se agregó a `public/system-apps/tm-gym`, a partir del ZIP de 291
  archivos, sin cambiar las asignaciones de proyectos.
- La prueba HTTP aislada aceptó ese ZIP y rechazó sobrescribir el mismo slug;
  conservó tanto la interfaz predeterminada como otra aplicación existente.
- El acceso directo a `system-apps/<slug>/index.html` sigue protegido por
  `.htaccess`; la entrada habitual es `/sistema/<slug-del-proyecto>` después
  de seleccionar la interfaz del proyecto en el panel.
