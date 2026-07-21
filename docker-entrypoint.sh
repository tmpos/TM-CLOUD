#!/bin/bash
set -e

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
