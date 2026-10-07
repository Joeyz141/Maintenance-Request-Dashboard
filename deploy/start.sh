#!/bin/bash
# deploy/start.sh
# Runs on the EC2 server at EVERY boot (systemd: maintenance-dashboard.service).
# So "Start instance" in the AWS console = get the latest code from GitHub + start the site,
# and "Reboot" = redeploy after you push new code.
set -euo pipefail

APP_DIR=/opt/maintenance-dashboard
ENV_FILE="$APP_DIR/deploy/.env"
cd "$APP_DIR"

# 1. Latest code from GitHub's main branch (if GitHub is unreachable, keep the current code).
git pull --ff-only || echo "git pull failed; starting with the code already on the server"

# 2. Secrets: generated ON the server the first time, never in git (like your gitignored .htaccess).
if [ ! -f "$ENV_FILE" ]; then
    umask 077
    {
        echo "DB_ROOT_PASSWORD=$(openssl rand -hex 24)"
        echo "DB_APP_PASSWORD=$(openssl rand -hex 24)"
        echo "HTTPS=on"
    } > "$ENV_FILE"
fi

# 3. The site address, from this server's current public IP (asked from the EC2 metadata service).
#    54.12.34.56 -> 54-12-34-56.sslip.io (a free DNS name that points to that IP, so HTTPS works).
TOKEN=$(curl -sf -X PUT "http://169.254.169.254/latest/api/token" -H "X-aws-ec2-metadata-token-ttl-seconds: 60")
IP=$(curl -sf -H "X-aws-ec2-metadata-token: $TOKEN" "http://169.254.169.254/latest/meta-data/public-ipv4")

if grep -q '^HTTPS=off' "$ENV_FILE"; then
    SITE_ADDRESS=":80"            # plain HTTP fallback
    SECURE=0
else
    SITE_ADDRESS="${IP//./-}.sslip.io"
    SECURE=1
fi
sed -i '/^SITE_ADDRESS=/d; /^SESSION_COOKIE_SECURE=/d' "$ENV_FILE"
echo "SITE_ADDRESS=$SITE_ADDRESS" >> "$ENV_FILE"
echo "SESSION_COOKIE_SECURE=$SECURE" >> "$ENV_FILE"

# 4. Build the images (only changed parts are rebuilt) and start all four containers.
docker compose -f deploy/compose.yaml up -d --build --remove-orphans

echo "Site: https://$SITE_ADDRESS  (analytics: https://$SITE_ADDRESS/analytics/)"
