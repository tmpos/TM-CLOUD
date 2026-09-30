# Dominios propios por proyecto

Un administrador abre **Proyecto → Dominios**. El dominio principal sirve la tienda; opcionalmente se añade `sistema.dominio.com` (u otro nombre) para el sistema. El apartado permite guardar, verificar DNS y activar, comprobar HTTPS, desactivar y desvincular.

El propietario crea A/AAAA hacia las IP indicadas y un TXT `_tmpbase.<hostname>` con el valor único mostrado. El TXT debe conservarse para futuras verificaciones. También se admite CNAME si todas sus direcciones resuelven a las IP configuradas. Durante la activación se usa DNS sin proxy. Los registros se crean en el proveedor DNS; guardar el subdominio en TMPBASE no modifica ese proveedor.

Cada hostname es único en la plataforma. Cambiarlo requiere otra prueba de propiedad. La tienda abre directamente en `/`; sus enlaces internos mantienen las rutas `/store/<slug>`. El sistema redirige dentro del mismo dominio a `/sistema/<slug>` para conservar login, PWA y runtime existentes. Los dominios propios no exponen el panel de administración ni rutas de otros proyectos. Bloquear un proyecto deshabilita su acceso inmediatamente.

## Servidor

Configurar en PHP `PROJECT_DOMAIN_IPS` (IP públicas separadas por comas) y `PROJECT_DOMAIN_RESERVED` (hosts de infraestructura que no pueden asignarse). `APP_URL` también está reservado. La tabla central `project_domains` se crea mediante una migración aditiva.

Ejecutar desde el host cada 15 segundos, con un timer de systemd:

```sh
python3 /opt/tmpbase-domain-routing/project-domains-sync.py \
  --database /opt/tmposystem-storage/tmpbase.sqlite \
  --output /etc/dokploy/traefik/dynamic/tmpbase-project-domains.yml \
  --status /opt/tmposystem-storage/domain-routing-status.json \
  --service tmpos-system-api-73trsf-service-12@file
```

El proceso solo lee dominios verificados de proyectos activos, escribe reglas Host de Traefik con HTTPS Let's Encrypt y actualiza el estado del panel. No necesita claves de Dokploy ni acceso al socket Docker desde PHP. El JSON generado es YAML válido. El servicio de destino, los entrypoints `web`/`websecure`, el middleware `redirect-to-https@file` y el resolver `letsencrypt` deben existir en Traefik.

Las unidades están en `deploy/project-domains-sync.service` y `.timer`. Copiar el script a `/opt/tmpbase-domain-routing/` y las unidades a `/etc/systemd/system/`, revisar las rutas y el servicio Traefik, ejecutar `systemctl daemon-reload` y `systemctl enable --now project-domains-sync.timer`. Los hosts reservados del worker y `PROJECT_DOMAIN_RESERVED` deben mantenerse alineados.

Una ruta publicada no significa que el certificado ya fue emitido: el botón **Comprobar HTTPS** valida el certificado contra las IP fijas del VPS y el nombre solicitado. El panel informa cuando el reconciliador no ha actualizado su estado durante 120 segundos. La desactivación retira las reglas en el siguiente ciclo y el backend rechaza inmediatamente ese hostname. No se modifica la configuración DNS, la base de negocio ni el login del proyecto.

Pruebas: `php tests/project-domains-smoke.php` y `python3 tests/project-domains-worker.py`. Las pruebas HTTP usan un contenedor desechable con almacenamiento vacío, `APP_ENV=test`, `APP_URL=http://127.0.0.1:18765` y puerto 18765 enlazado únicamente a loopback: inicializar con `php tests/project-domains-fixture.php` y ejecutar `python3 tests/project-domains-http.py` desde el host. La fixture nunca debe ejecutarse sobre datos reales. Para revertir, detener el timer, retirar únicamente `tmpbase-project-domains.yml` y restaurar la imagen anterior. La tabla aditiva puede conservarse.
