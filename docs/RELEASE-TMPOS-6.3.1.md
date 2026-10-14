# TMPOS 6.3.1 — 14 de octubre de 2026

Resultado: imagen activa en el VPS, HTTP 200 y SHA-256 coincidente en los **325 archivos web** publicados. Pruebas PHP de ventas en espera, 10 escenarios de stock, rollback, replay y venta atómica aprobadas. Frontend: **1141 pruebas + 4 de publicación**, tipos y lint aprobados; ajustes del entorno de pruebas en [da8724f](https://github.com/tmpos/tmpos-online/commit/da8724f), sin cambios al código ejecutable publicado.

- Frontend: [d2535c4](https://github.com/tmpos/tmpos-online/commit/d2535c4), con cambios funcionales en [ce244a4](https://github.com/tmpos/tmpos-online/commit/ce244a4) y versión en [a95030f](https://github.com/tmpos/tmpos-online/commit/a95030f).
- Backend: runtime sincronizado en `6a335cd`, normalizado en `8b9d843`; esta entrega incorpora la distribución web y `app:getVersion = 6.3.1`.
- Imagen de publicación: `tmpos-system-api-73trsf:release-6.3.1`, basada en la imagen activa `holds-manager-20261014`, con los directorios `app`, `tests` y la distribución web de este repositorio.
- Web: `https://api.tmposystem.com/sistema/app/`, directorio persistente `/opt/tmposystem-ui/sistema/app`.
- Instalador y notas: [release oficial v6.3.1](https://github.com/tmpos/tmpos-online/releases/tag/v6.3.1).

La publicación conserva las bases de datos y archivos de clientes. La actualización de la web copia primero los assets y sustituye el índice al final; conserva los assets anteriores para sesiones abiertas. Se respalda el índice previo en `/opt/tmposystem-ui/backups/release-6.3.1` y se conserva la imagen anterior para reversión del código.

La verificación de publicación compara SHA-256 de los archivos servidos con la distribución local y comprueba el estado del servicio. Las pruebas de ventas en espera, stock y atomicidad usan SQLite aislado; no se realizan ventas ni eliminaciones sobre datos reales. La aceptación operativa con usuarios e impresoras permanece separada de estas verificaciones técnicas.
