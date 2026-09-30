# Development Learning Notes

## Background

My previous application development experience comes primarily from
CapyWise, a senior project developed using Flutter/Dart, Firebase
Authentication, and Cloud Firestore.

This project is intended to expand that foundation into traditional
web and backend development.

## Technologies I Want to Learn

### PHP
Backend application development.

### MySQL
Relational database design and SQL.

### JavaScript
Frontend interaction and communication with the backend.

### Python
Data analysis and reporting using application data.

### Claude
AI-assisted software development, debugging, testing, and code review.

## Questions I Want to Answer

- What happens when a frontend communicates with a backend?
- What exactly does PHP do?
- How does PHP communicate with MySQL?
- How is MySQL different from Firestore?
- What is an API?
- What are HTTP requests?
- What are GET and POST requests?
- What is JSON?
- How should user input be validated?
- How should errors be handled?
- How should backend code be tested?
- How can Python analyze data created by a PHP application?

## Ticket 0: Environment Setup

### What I set up
Installed XAMPP 8.2.12 with winget. XAMPP bundles everything needed to run a web app locally:
- **X**: cross-platform (runs on Windows, Mac, and Linux)
- **A**: Apache, the web server that receives HTTP requests
- **M**: MariaDB, a MySQL-compatible database server
- **P**: PHP, the server-side language Apache hands `.php` files to
- **P**: Perl, another language (not used in this project)

The project lives in `C:\xampp\htdocs\maintenance-dashboard` because Apache only serves files from `htdocs`, and OneDrive can interfere with Git. I cloned it fresh from GitHub, which also confirmed GitHub had a complete copy.

### Problems I debugged

**1. `winget` not recognized**
- **Symptom:** Running `winget` in PowerShell gave `CommandNotFoundException`, but running it by its full path worked.
- **Cause:** My user PATH was missing `C:\Users\Slain\AppData\Local\Microsoft\WindowsApps`, where winget lives. PATH had a look-alike entry for the SYSTEM account's folder instead.
- **Fix:** Backed up my PATH, added `%USERPROFILE%\AppData\Local\Microsoft\WindowsApps` to my user PATH, and restarted my terminals.
- **Note:** winget is the Windows Package Manager, a command-line tool that installs applications.

**2. `fatal: not a git repository`** (two separate bugs)
- **Wrong location:** I ran `git status` from `C:\xampp\htdocs`. Git looks for a `.git` folder in the current directory and its parents, but `.git` is inside the project folder, one level down. **Fix:** `cd` into the project folder.
- **Typo:** The clone created `maintenace-dashboard` (missing an "n"), so `code ...\maintenance-dashboard` opened a folder that didn't exist. **Fix:** `Rename-Item`.

**3. `404 Not Found` for hello.php**
- **Symptom:** The browser showed `404 Not Found` for `http://localhost/maintenance-dashboard/hello.php`.
- **Cause:** I created `hello.php` in the old OneDrive folder because VS Code still had that folder open. Apache only looks in `htdocs`.
- **Fix:** Moved the file with `Move-Item`. Lesson: check the VS Code Explorer root and the terminal prompt before working.

### Key concepts
- **PATH:** an environment variable holding the list of folders Windows searches when I type a program's name, like `winget` or `git`. Different from a file path, which is the address of one file or folder.
- **Current directory:** the folder my terminal is in, shown in the prompt. `cd` changes it.
- **localhost:** the name for my own computer. Apache is the server running on it. HTTP defaults to port 80 (Apache); MySQL uses port 3306.
- **The browser never sees PHP code:** Apache hands `.php` files to PHP, which runs them and sends back only the output (HTML or JSON). Verified with View Page Source.

## Ticket 1: Data Model & Schema

### What I built
- `database/schema.sql` creates the `maintenance_dashboard` database with two tables: `vehicles` and `maintenance_requests`.
- They have a **one-to-many relationship**: one vehicle can have many maintenance requests, and each request belongs to exactly one vehicle. The link is the `vehicle_id` column in `maintenance_requests`.
- `database/seed.sql` loads fictional sample data (3 vehicles, 4 requests) so there is something to test with. It is for local development only and would never run in production.

```
vehicles (1) ──────< maintenance_requests (many)
   id  ◄──────────────  vehicle_id   (foreign key)
```

### Key concepts
- **Primary key (PK):** the column that uniquely identifies each row (`id`)
- **Foreign key (FK):** a column that points to another table's primary key (`vehicle_id` → `vehicles.id`). The database checks that every FK points to a row that really exists (**referential integrity**).
- **UNIQUE:** no two rows can have the same value in that column (no two vehicles share a VIN).
- **Index:** like a book's index, it lets MySQL jump straight to matching rows instead of scanning the whole table. It speeds up reads but slightly slows down writes. PK and UNIQUE columns get one automatically, and InnoDB requires one on FK columns (`idx_requests_vehicle_id`).
- **ENUM:** a column that only accepts values from a fixed list (`low`, `medium`, `high`).
- **ON DELETE RESTRICT:** you can't delete a parent row (vehicle) while any child rows (requests) still point to it, whatever their status. It is the default, so `SHOW CREATE TABLE` doesn't print it.
- **ON UPDATE CASCADE:** if a parent's `id` changes, the change flows down to its children automatically.
- **Database rules** database rules are simple structural facts the database enforces (this ID must exist, this VIN must be unique).
- **MySQL:**  MySQL has a strict schema and enforces the rules itself.

### Constraint tests (negative tests)
I deliberately tried to break each rule to prove the schema rejects bad data.

| Test | What I tried | Result |
|---|---|---|
| 1 | Insert a request for vehicle `99` (doesn't exist) | `#1452` Cannot add or update a child row: a foreign key constraint fails |
| 2 | Delete vehicle `1`, which has requests | `#1451` Cannot delete or update a parent row: a foreign key constraint fails |
| 3 | Insert a second vehicle with an existing VIN | `#1062` Duplicate entry for key `uq_vehicles_vin` |
| 4 | Insert a request with priority `'urgent'` | `#1265` Data truncated for column `priority` (only after enabling strict mode, see below) |

Each failed statement saved nothing: a single SQL statement is **atomic** (it fully succeeds or fully fails).

### Problems I debugged

**1. SQL comments without a space**
- **Symptom:** My notes like `--character set` would have broken the script when run as a whole file.
- **Cause:** MySQL only treats `--` as a comment when it is followed by a space: `-- like this`.
- **Fix:** Added a space after every `--`.

**2. Invalid ENUM value was accepted**
- **Symptom:** Inserting priority `'urgent'` succeeded, and a blank value (`''`, not NULL) was stored.
- **Cause:** XAMPP's MariaDB runs without strict mode (`STRICT_TRANS_TABLES` missing from `@@sql_mode`), so it "fixes" bad values and only gives a warning.
- **Fix:** Backed up `C:\xampp\mysql\bin\my.ini`, then set `sql_mode=STRICT_TRANS_TABLES,NO_ZERO_IN_DATE,NO_ZERO_DATE,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION` and restarted MySQL.
- **Why it matters:** AWS RDS runs in strict mode, so local and production would have behaved differently ("works on my machine"). It also showed why input should be validated in PHP too (**defense in depth**).

### Habits learned
- Read the **newest** log lines (`Get-Content C:\xampp\mysql\data\mysql_error.log -Tail 30`) and the **first** `[ERROR]`, not the last.
- Stop MySQL in the XAMPP Control Panel before shutting down the laptop, and click Start only once.
- Commit small working steps, so there is always a known-good state to return to.
- Test the rules, not just the happy path.
