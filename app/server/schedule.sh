#!/bin/sh

APP_DIR=$(dirname "$0")

CURRENT_MINUTE=$(date +%M)

# Every 2 minutes
if [ $((CURRENT_MINUTE % 2)) -eq 0 ]; then
    echo "Running $APP_DIR/report.php"
    php "$APP_DIR/report.php" &
fi
