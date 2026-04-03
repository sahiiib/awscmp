#!/bin/sh
# Stop and remove the container
echo "stoping aws_ec2_monitor pod"
podman stop aws-ec2-monitor 2>/dev/null || true
echo "removin aws_ec2_monitor pod"
podman rm aws-ec2-monitor 2>/dev/null || true
echo "successfuly clean pods ..."
# Delete the old database so setup can run fresh
echo "cleaning old database"
sudo rm -f data/monitor.db
sudo rm -f data/monitor.db-wal
sudo rm -f data/monitor.db-shm
echo "successfuly cleaned database"

# Fix ownership and permissions
echo "fixing permissions"
sudo mkdir -p data/logs
sudo chown -R $USER:$USER data
sudo chmod -R 775 data
echo "successfuly fixed all permissions"

#build Docker Image
echo "building podman image"
podman build -f docker/Dockerfile -t aws-ec2-monitor .
echo "successfuly podman image"

#Run pod 
echo "running pod"
podman run -d --name aws-ec2-monitor  -p 8080:80 -v "$(pwd)/data:/data:z"  --restart unless-stopped  aws-ec2-monitor
echo "successfuly running the pod
#creating new database

#podman exec -it aws-ec2-monitor bash -c "chown -R www-data:www-data /data && chmod -R 775 /data"
