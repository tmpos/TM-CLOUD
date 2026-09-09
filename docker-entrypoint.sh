#!/bin/bash
set -e

# public/sistema/app y public/system-apps se montan sobre un volumen
# persistente (para que subidas desde el panel sobrevivan a rebuilds de la
# imagen). En un volumen nuevo/vacio, se siembra con el contenido por
# defecto que viaja en la imagen; si ya tiene datos de una subida previa,
# no se toca.
seed_if_empty() {
    local target="$1" seed="$2"
    if [ -d "$seed" ] && [ -z "$(ls -A "$target" 2>/dev/null)" ]; then
        echo "[Entrypoint] Sembrando $target con el contenido por defecto..."
        cp -a "$seed/." "$target/"
    fi
}
seed_if_empty /var/www/html/public/sistema/app /var/www/html/.seed/sistema-app
seed_if_empty /var/www/html/public/system-apps /var/www/html/.seed/system-apps
chown -R www-data:www-data /var/www/html/public/sistema/app /var/www/html/public/system-apps

# Start the realtime WebSocket server in the background
echo "[Entrypoint] Starting Realtime WebSocket server..."
php /var/www/html/bin/realtime-server &
REALTIME_PID=$!
echo "[Entrypoint] Realtime server PID: $REALTIME_PID"

if [ "${MAIL_ENABLED:-false}" = "true" ]; then
    echo "[Entrypoint] Starting mail queue worker..."
    (
        while true; do
            php /var/www/html/bin/mail-worker 20 || true
            sleep 30
        done
    ) &
    MAIL_PID=$!
    echo "[Entrypoint] Mail worker PID: $MAIL_PID"
fi

# Start Apache in the foreground
echo "[Entrypoint] Starting Apache..."
exec apache2-foreground
