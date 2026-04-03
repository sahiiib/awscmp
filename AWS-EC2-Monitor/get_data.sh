#!/bin/sh
echo "=== Starting AWS EC2 data collection at $(date) ==="
echo "Fetching data from AWS cloud..."

# Run collector with nice priority and background
nice -n 10 /opt/venv/bin/python /usr/local/bin/collector.py >> /data/logs/collector.log 2>&1 &

echo "Collection triggered in background."
echo "Check logs with: tail -f /data/logs/collector.log"
echo "Refresh your dashboard in 30-90 seconds."