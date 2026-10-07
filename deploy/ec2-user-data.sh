#!/bin/bash
# deploy/ec2-user-data.sh
# Paste this into "Advanced details -> User data" when launching the EC2 instance
# (Ubuntu Server 24.04). EC2 runs it ONCE, as root, on the very first boot.
# Progress log on the server: /var/log/maintenance-setup.log
set -euxo pipefail
exec > >(tee -a /var/log/maintenance-setup.log) 2>&1

# 1. 2 GB swap file: extra breathing room for a small (2 GB RAM) server.
if [ ! -f /swapfile ]; then
    fallocate -l 2G /swapfile
    chmod 600 /swapfile
    mkswap /swapfile
    swapon /swapfile
    echo '/swapfile none swap sw 0 0' >> /etc/fstab
fi

# 2. Docker + Docker Compose + git.
apt-get update
DEBIAN_FRONTEND=noninteractive apt-get install -y docker.io docker-compose-v2 git curl openssl
systemctl enable --now docker

# 3. The code (public GitHub repo).
if [ ! -d /opt/maintenance-dashboard ]; then
    git clone https://github.com/Joeyz141/Maintenance-Request-Dashboard.git /opt/maintenance-dashboard
fi

# 4. Register start.sh to run at every boot, and run it now.
cp /opt/maintenance-dashboard/deploy/maintenance-dashboard.service /etc/systemd/system/
systemctl daemon-reload
systemctl enable --now maintenance-dashboard.service
