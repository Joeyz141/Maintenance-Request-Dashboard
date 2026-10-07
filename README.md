# Maintenance Request Dashboard

A portfolio project focused on learning AI-assisted software development
through the development of a maintenance request management feature.

**Live demo:** https://32-190-224-231.sslip.io
(analytics dashboard: https://32-190-224-231.sslip.io/analytics/)

The demo runs on an AWS EC2 server that is switched on for demos, so the link may be offline at other times.
It uses fictional data and has no login, so please don't enter real information.

## Objective

Develop a focused internal maintenance request management feature that
allows users to create, view, search, filter, and update maintenance requests.

## Features

- **List** every maintenance request with its vehicle (newest first)
- **Create** a request (validated, CSRF-protected, Post/Redirect/Get)
- **Update** a request's status and priority
- **Search** by title or VIN and **filter** by status and priority, without a page reload (JavaScript + `fetch()`), and still working with JavaScript turned off
- **JSON API** (`api/requests.php`) used by the JavaScript front end and the Python dashboard
- **Analytics dashboard** (Python, Streamlit + Plotly): summary cards, charts, table, CSV download
- **Automated tests**: PHPUnit and pytest (see [TESTING.md](TESTING.md))
- **Deployed** on AWS EC2 with Docker Compose and HTTPS (see [DEPLOY.md](DEPLOY.md))

## Architecture

```
Browser ──HTTP──> Apache + PHP ──PDO──> MySQL (MariaDB)
  │  index.php, create.php, edit.php        maintenance_dashboard
  │  js/dashboard.js ──fetch──> api/requests.php (JSON)
  │                                  ▲
Streamlit dashboard (Python, :8501) ─┘ requests.get()
```

| Folder / file | Responsibility |
|---|---|
| `index.php`, `create.php`, `edit.php` | Pages: read input, call the functions below, print HTML |
| `api/requests.php` | JSON API (GET only): list, filters, one request by `?id=` |
| `src/` | Shared PHP: `db.php` (connection), `requests.php` / `vehicles.php` (SQL), `validation.php` (rules + allowed values), `csrf.php`, `helpers.php` (`e()` escaping) |
| `js/dashboard.js` | Live search and filtering with `fetch()` |
| `reports/` | Python: `maintenance_data.py` (API call + pandas analysis + charts), `dashboard.py` (Streamlit page) |
| `database/` | `schema.sql` (tables), `seed.sql` (fictional sample data), `create_app_user.sql` (least-privilege DB user) |
| `tests/` | `php/` (PHPUnit) and `python/` (pytest) |

## Running it locally (Windows + XAMPP)

**Requirements:** XAMPP with PHP 8.2+ and MariaDB/MySQL, Git, Python 3.12+, Composer (for the PHP tests).

1. Clone the repository into `C:\xampp\htdocs\maintenance-dashboard`.
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Create the database in phpMyAdmin (SQL tab) by running, in order:
   `database/schema.sql`, `database/seed.sql`, then `database/create_app_user.sql`
   (in that last file, replace `CHANGE_ME` with a password of your choice, but don't commit it).
4. Copy `.htaccess.example` to `.htaccess` and put the same password in `SetEnv DB_PASS`.
   `.htaccess` is gitignored, so the password never reaches GitHub.
5. Open http://localhost/maintenance-dashboard/

### Settings (environment variables)

| Variable | Used by | Default |
|---|---|---|
| `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` | PHP (`config/config.php`) | `localhost`, `3306`, `maintenance_dashboard`, `maintenance_app` |
| `DB_PASS` | PHP | *(none; set it in `.htaccess`)* |
| `ANALYTICS_URL` | `index.php` link | `http://localhost:8501` |
| `API_URL` | Python dashboard | `http://localhost/maintenance-dashboard/api/requests.php` |

## Running the analytics dashboard

From the project folder, in PowerShell (Apache and MySQL running):

```powershell
python -m venv .venv
.venv\Scripts\Activate.ps1
python -m pip install -r reports\requirements.txt
python -m streamlit run reports\dashboard.py
```

Then open http://localhost:8501 (or use **View analytics dashboard** on the home page).
The port is fixed in `.streamlit/config.toml`; if 8501 is taken, Streamlit stops with an error instead of moving to another port.

## Running the tests

```powershell
# PHP (once: composer install)
vendor\bin\phpunit

# Python (venv on; once: python -m pip install -r reports\requirements-dev.txt)
python -m pytest
```

What they cover, and the manual checklist for everything else: [TESTING.md](TESTING.md).

## Deployment

The live demo runs as four Docker containers on one AWS EC2 server:
Caddy (HTTPS + routing), PHP + Apache, Streamlit, and MariaDB.
Setup is automated: an EC2 user-data script installs Docker and clones this repository,
and `deploy/start.sh` runs at every boot (`git pull` + `docker compose up`), so a reboot deploys the latest `main`.
Details, costs and design choices: [DEPLOY.md](DEPLOY.md).

## Security notes

- Every database query uses **prepared statements** (no user input inside SQL text).
- Every value printed into HTML goes through `e()` (`htmlspecialchars`) against **XSS**; the JavaScript uses `textContent`, never `innerHTML`.
- Forms that change data are protected with a **CSRF token**; the session cookie is `HttpOnly` and `SameSite=Lax`.
- The app connects as a **least-privilege** database user (SELECT, INSERT, UPDATE, DELETE only).
- Secrets come from **environment variables**, never from committed files.
- The live deployment hides PHP errors from visitors (`display_errors` off), serves everything over **HTTPS** with a `Secure` session cookie, blocks `src/` and `config/` in the browser, and keeps the database off the internet. Database passwords are generated on the server and never committed.
- Not done yet: authentication (the app has no login), so the demo uses fictional data only.

## Technologies

- PHP
- MySQL (MariaDB)
- HTML
- CSS
- JavaScript
- Python (pandas, Plotly, Streamlit)
- PHPUnit, pytest
- Docker & Docker Compose, Caddy
- AWS (EC2, Elastic IP)
- Git & GitHub
- Claude / Claude Code

## Learning Goals

- Learn backend development with PHP
- Learn relational database development with MySQL
- Strengthen Python skills for data analysis
- Understand frontend/backend communication
- Learn API fundamentals
- Practice input validation and error handling
- Practice testing and debugging
- Improve Git/GitHub workflows
- Learn effective AI-assisted software development

## Development Philosophy

Claude will be used as an AI development assistant rather than simply
generating the entire application.

The goal is to understand the architecture, code, development decisions,
testing, and debugging process.

## Disclaimer

This is an independent portfolio project using fictional data. It is not
affiliated with or representative of any internal system used by Flyer
Defense or any other company.
