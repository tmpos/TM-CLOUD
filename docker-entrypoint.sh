#!/bin/bash
set -e

# Start the realtime WebSocket server in the background
echo "[Entrypoint] Starting Realtime WebSocket server..."
php /var/www/html/bin/realtime-server &
REALTIME_PID=$!
echo "[Entrypoint] Realtime server PID: $REALTIME_PID"

# Start Apache in the foreground
echo "[Entrypoint] Starting Apache..."
exec apache2-foreground
