FROM php:8.3-fpm

RUN apt-get update && apt-get install -y \
    nginx \
    python3 \
    python3-pip \
    python3-venv \
    cron \
    sqlite3 \
    util-linux \
    && rm -rf /var/lib/apt/lists/* \
    && python3 -m venv /opt/venv \
    && /opt/venv/bin/pip install --upgrade pip \
    && /opt/venv/bin/pip install --no-cache-dir boto3 filelock

# Copy project files
COPY frontend /var/www/html
COPY database /var/www/database          
COPY backend/collector/collector.py /usr/local/bin/collector.py
COPY docker/nginx.conf /etc/nginx/sites-enabled/default
COPY docker/entrypoint.sh /entrypoint.sh

RUN chmod +x /entrypoint.sh /usr/local/bin/collector.py

# Cron every 30 minutes
COPY <<EOF /etc/cron.d/collector
*/30 * * * * root /opt/venv/bin/python /usr/local/bin/collector.py >> /data/logs/collector.log 2>&1
EOF

RUN chmod 0644 /etc/cron.d/collector && crontab /etc/cron.d/collector

EXPOSE 80
VOLUME ["/data"]

ENTRYPOINT ["/entrypoint.sh"]