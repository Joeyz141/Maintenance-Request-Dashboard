# Deployment (AWS EC2 + Docker Compose)

The live demo runs on **one small AWS EC2 server** with four Docker containers.
It's built to be cheap and simple: switch the server on for a demo, off afterwards.

```
visitor --HTTPS--> caddy (ports 80/443, free Let's Encrypt certificate)
                     |-- /            --> web       (PHP 8.2 + Apache: index/create/edit + api/requests.php)
                     |-- /analytics/  --> analytics (Python + Streamlit, calls web's JSON API)
                                          web --> db (MariaDB 10.11, not reachable from the internet)
```

| File | What it does |
|---|---|
| `deploy/compose.yaml` | The four containers, their settings and how they connect |
| `deploy/web/Dockerfile` | PHP + Apache image with only the website files, `pdo_mysql`, production `php.ini` |
| `deploy/web/php-production.ini` | Errors hidden from visitors (logged instead), `Secure` session cookie |
| `deploy/web/apache-security.conf` | Hides versions, blocks `src/` and `config/` in the browser |
| `deploy/analytics/Dockerfile` | Streamlit image, served under `/analytics/`, runs as a non-root user |
| `deploy/caddy/Caddyfile` | Front door: HTTPS + routing + security headers |
| `deploy/db/` | Demo rows + the least-privilege `maintenance_app` user (runs once on an empty database) |
| `deploy/start.sh` | Runs at every boot: `git pull`, creates secrets once, works out the site address, `docker compose up` |
| `deploy/maintenance-dashboard.service` | systemd unit that runs `start.sh` at boot |
| `deploy/ec2-user-data.sh` | Pasted into EC2 "User data": installs Docker, clones the repo, installs the service |

## Everyday use
- **Turn on:** AWS console → EC2 → Instances → select → *Instance state* → **Start**. Wait ~2 minutes.
- **Turn off:** *Instance state* → **Stop**. The data stays (Docker volume on the disk).
- **Deploy new code:** push to `main` on GitHub, then **Reboot** the instance (`start.sh` pulls and rebuilds).
- **Address:** `https://<elastic-ip-with-dashes>.sslip.io`, e.g. `https://54-12-34-56.sslip.io`.
  sslip.io is a free DNS service: that name always points to the IP written in it, which lets Caddy get an HTTPS certificate without buying a domain.

## Secrets
`deploy/.env` is created **on the server** by `start.sh` with random passwords (`openssl rand`). It is never in git
(`.env` is in `.gitignore`). Same idea as the local `.htaccess` with `SetEnv DB_PASS`.

## Cost (approx., us-west-2)
t3.small ~$0.02/h while running · 20 GB disk ~$1.60/month · Elastic IP ~$3.60/month (charged even when stopped).
Covered by the AWS free-plan credits. A $10 Budget alert emails if anything grows.

## Why not ECS / RDS / a load balancer?
For a demo, one server keeps the cost near zero and has few moving parts. In production I would move the
database to **RDS** (managed backups), run the containers on **ECS Fargate** behind an **ALB** (more than one copy,
health checks; sessions would then need a shared store like Redis), and add **GitHub Actions** to run pytest +
PHPUnit and deploy on every merge. The Docker images would be reused unchanged.

## Known limits
- One server: if it's stopped or broken, the site is down.
- No login: anyone with the link can create and edit requests (demo data only).
- `HTTPS=off` in `deploy/.env` switches to plain HTTP (fallback if the certificate can't be issued).
