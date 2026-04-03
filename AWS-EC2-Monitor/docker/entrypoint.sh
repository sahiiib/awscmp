#!/bin/bash
set -e

echo "🚀 Starting AWS EC2 Monitor..."

# Create necessary directories
mkdir -p /data/logs /run/php

# Fix permissions for SQLite (very important!)
chown -R www-data:www-data /data
chmod -R 775 /data

# Initialize database on first run
if [ ! -f /data/monitor.db ]; then
    echo "✅ Initializing SQLite database..."
    sqlite3 /data/monitor.db < /var/www/database/schema.sql
    
    # Set proper ownership and permissions after creation
    chown www-data:www-data /data/monitor.db
    chmod 664 /data/monitor.db
fi

# Start PHP-FPM
echo "Starting PHP-FPM..."
php-fpm -D

# Wait a moment for PHP-FPM to start
sleep 2

# Start cron
echo "Starting cron..."
cron

# Start nginx
echo "Starting nginx..."
nginx -g 'daemon off;'