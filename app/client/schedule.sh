#!/bin/sh

APP_DIR=$(dirname "$0")

CURRENT_MINUTE=$(date +%M)

# Every 2 minutes
if [ $((CURRENT_MINUTE % 2)) -eq 0 ]; then
    echo "Running $APP_DIR/client.sh"

    if [ -z "$1" ]; then
        SERVER_URL='http://localhost/server-tools/app/server/server.php'
    else
        SERVER_URL="$1"
    fi

    "$APP_DIR/client.sh" "$SERVER_URL" &
fi