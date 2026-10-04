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

## Ticket 2: Connect PHP to MySQL

### Goal
Let PHP log into the database safely, and prove it works end to end.

### What I built

| Piece | Where it lives | Job |
|---|---|---|
| `maintenance_app` account | Inside MariaDB | The app's own login, with limited permissions |
| `database/create_app_user.sql` | Repo | Recreates that account in seconds (placeholder password) |
| `.htaccess` | My laptop only (gitignored) | Stores the real password; Apache hands it to PHP |
| `.htaccess.example` | Repo | Template that shows what `.htaccess` should contain |
| `config/config.php` | Repo | The settings sheet: host, port, database, user, and the password read from the environment |
| `src/db.php` | Repo | `get_db_connection()`: builds the DSN, sets safe options, and returns a PDO connection |

```
Browser → Apache → (whichever page you open, db_test.php) → db.php → config.php (+ DB_PASS from .htaccess)
        → MariaDB checks maintenance_app's permissions → rows come back → HTML to the browser
```

The browser never talks to the database. Only PHP has the credentials.

### Key concepts

- **Least privilege:** each account gets only the access its job needs. `maintenance_app` can `SELECT`, `INSERT`, `UPDATE` and `DELETE` rows in `maintenance_dashboard`, and nothing else. If someone ever tricks our PHP, they still can't drop tables or reach other databases. **MariaDB enforces this, not PHP.**
- **DML vs DDL:** DML(Data Manipulation Language) changes rows (`SELECT`, `INSERT`, `UPDATE`, `DELETE`) and is the app's job. DDL(Data Definition Language) changes structure (`CREATE`, `ALTER`, `DROP`) and is done on purpose by an admin (`root`), for example by running `schema.sql`.
- **Environment variable:** a named value that the environment (the computer or server) hands to a program when it runs. `PATH` from Ticket 0 is one. `DB_PASS` is one we made. PHP reads it with `getenv('DB_PASS')`. 
- **Secrets stay out of Git:** `config.php` is committed, so it must never contain the password. `.gitignore` keeps `.htaccess` off GitHub, and Apache refuses to serve `.ht*` files to browsers (403 Forbidden).
- **DSN (Data Source Name):** one string that says what kind of database to talk to and where it is, like a URL for a database: `mysql:host=localhost;port=3306;dbname=maintenance_dashboard;charset=utf8mb4`. It says **where**. The username and password, which say **who**, are passed separately: `new PDO($dsn, $user, $pass, $options)`.
- **PDO options:** `ERRMODE_EXCEPTION` (throw an error when something goes wrong), `FETCH_ASSOC` (rows come back by column name)
- each file has one job, and every page reuses `get_db_connection()` instead of repeating login code.
- **Error handling:** users see a polite message, and developers see the real error in `C:\xampp\apache\logs\error.log`. Real errors can leak details (host, user, database name) to attackers.
- **MariaDB vs MySQL:** MariaDB is a community fork of MySQL. It uses the same SQL, the same client tools and the same PDO driver.
- **Idempotent script:** `create_app_user.sql` starts with `DROP USER IF EXISTS`, so running it once or ten times gives the same result.

### Tests (with a temporary `db_test.php`, deleted before merging)

| # | Test | Result |
|---|---|---|
| 1 | Open `db_test.php` | `Connected! Vehicles: 3, Requests: 4` |
| 2 | View Source | Only the output. No PHP code or credentials |
| 3 | Stop MySQL, refresh | Polite message for users. Log: `[2002]` (nothing listening on port 3306) |
| 4 | `SetEnv DB_NAME "wrong_db"` | Polite message for users. Log: `[1044] Access denied` |
| - | `maintenance_app` tries `CREATE TABLE` / `USE mysql` | `#1142` / `#1044`, so least privilege works |

Why `1044` and not `1049 Unknown database`? MariaDB checks "are you allowed in?" before "does it exist?", so it doesn't reveal which databases exist to accounts that can't use them.

### Problems I debugged

**1. `1045 Access denied` → the app account had disappeared**
- **Symptom:** `db_test.php` showed the polite error, and the Apache log said `[1045] Access denied for user 'maintenance_app'@'localhost' (using password: YES)`.
- **Evidence:** `SELECT User, Host FROM mysql.user WHERE User LIKE '%maint%'` returned 0 rows, but `CREATE USER` failed with `#1396` (already exists).
- **Cause:** MariaDB had never been stopped cleanly (every start in `mysql_error.log` ran crash recovery). The account itself (`mysql.global_priv`) was never saved to disk, but its `GRANT` (`mysql.db`) was. That left an orphaned permission row, which blocked `CREATE USER`.
- **Fix:** `DELETE FROM mysql.db WHERE User = 'maintenance_app' AND Host = 'localhost';` → `FLUSH PRIVILEGES;` → `CREATE USER` + `GRANT`. Then a clean Stop/Start of MySQL to prove the account was saved.
- **Prevention:** `database/create_app_user.sql`, and always **Stop** MySQL before shutting down.

### Habits learned
- Debug in order: **symptom → evidence → cause → fix**. Read the log before changing anything.
- After any cleanup, re-check that what you meant to keep is still there.

## Ticket 3: Show Maintenance Requests

### Goal
Use the `db.php` connection from Ticket 2 to show every maintenance request in an HTML table in the browser. This is the first time data makes the full trip: MariaDB → PHP → browser. A user can now open the dashboard and see all requests, with the vehicle each one belongs to.

### What I built

| File | Job |
|---|---|
| `src/requests.php` | `get_all_requests()`: runs the JOIN query and returns every request with its vehicle. SQL only, no HTML. |
| `src/helpers.php` | `e()`: escapes any value so it is safe to print in HTML (stops XSS). |
| `index.php` | The page: connects, calls `get_all_requests()`, loops over the rows and builds the HTML table, passing every value through `e()`. |


### How a page load works
1. The browser sends an HTTP request for `localhost/maintenance-dashboard/`.
2. Apache receives it and hands `index.php` to PHP.
3. PHP opens a connection with `get_db_connection()` (from `db.php`).
4. PHP calls `get_all_requests()`, which sends the JOIN query to MariaDB.
5. MariaDB sends the rows back, and PHP stores them in the `$requests` array.
6. PHP loops over `$requests`, escapes every value with `e()`, and builds the HTML table.
7. Apache sends the finished HTML back, and the browser displays it.


### Key concepts
- **JOIN:** combines two tables using the foreign key (`r.vehicle_id = v.id`), so each request row comes back with its vehicle's make, model and VIN.  
- **Aliases:** `maintenance_requests AS r` and `vehicles AS v` are short nicknames. The `r.` and `v.` prefixes say which table a column comes from. Both tables have `id` and `created_at`, so without prefixes MySQL errors with "ambiguous column."
- **fetchAll and the array shape:** `fetchAll()` returns a list of rows, and each row maps column names to values. `$requests[0]['title']` means "first row, title column." Counting starts at 0, so the last of 4 rows is `$requests[3]`. The data is already in the variable, so no new query is needed to read it.
- **foreach:** `foreach ($requests as $request)` goes through the list one row at a time, calling the current row `$request`, and outputs one `<tr>` per row. 4 rows or 400 rows, the same code works.
- **XSS and `e()`:** if a value contains `<script>` and PHP prints it raw, the browser **runs it as code**. An attacker could steal a logged-in user's session. `e()` turns `<` into `&lt;`, so the browser shows it as text. **Every value gets `e()`**, because any database value could have come from a user, and a rule with no exceptions can't be forgotten.
- **ORDER BY with a tie-breaker:** `ORDER BY r.created_at DESC, r.id DESC` shows the newest requests first. When two requests have the same `created_at`, MySQL may return them in any order, so `r.id DESC` breaks the tie and makes the order the same every time (deterministic).
- **Failing safely:** if the database is down, `try/catch` catches the error. The user sees only a generic "Sorry…" message with HTTP status 500, and the real error goes to `C:\xampp\apache\logs\error.log` for me. No database details leak to the browser.

### Tests

| # | Test | Expected | Result |
|---|---|---|---|
| 1 | Open the dashboard (happy path) | 4 rows, correct vehicles, newest first |  Order 4, 3, 2, 1. Ford F-150 shows on two requests. |
| 2 | XSS: insert a request titled `<script>alert("XSS")</script>` | Title shows as text, no popup | Text shown. View Source shows `&lt;script&gt;…` |
| 2b | Same row, `e()` temporarily removed from the title | The attack fires | ✅ "localhost says XSS" popup. Put `e()` back and confirmed the popup was gone. |
| 3 | Stop MySQL and refresh | Generic message, status 500, real error logged | ✅ Only "Sorry…" on the page, Network tab showed 500, `error.log`: `Failed to load requests: SQLSTATE[HY000] [2002]` |
| 4 | Cleanup: `DELETE … WHERE title LIKE '<script>%'` | Back to 4 rows | ✅ |



### Problems I debugged

**1. Rows in random order (4, 1, 2, 3)**
- **Symptom:** sorting by `created_at` didn't give a stable order.
- **Cause:** all seed rows have the same `created_at`, so MySQL could return the ties in any order.
- **Fix:** added a tie-breaker: `ORDER BY r.created_at DESC, r.id DESC`.

**2. MySQL ran crash recovery on every start**
- **Symptom:** every start in `mysql_error.log` said "Starting crash recovery", even after pressing Stop in the XAMPP panel.
- **Evidence:** stopping with the panel → the next start ran crash recovery. Stopping with `mysqladmin` → the next start had none.
- **Cause:** the XAMPP panel's Stop button kills MySQL instead of shutting it down. This is probably what corrupted `maintenance_app` in Ticket 2.
- **Fix:** stop MySQL with `C:\xampp\mysql\bin\mysqladmin.exe -u root shutdown`. Start it with the panel, clicking Start once.

### Habits learned

- **Stop MySQL with `mysqladmin … shutdown`, not the panel.** (This replaces the "always Stop MySQL" habit from Ticket 2.) A clean stop shows up as **no** "crash recovery" on the next start.
- Test security on purpose: prove the attack works without the defense and fails with it.

### Objective
My dashboard page calls a data function that runs one SQL query, joining maintenance requests with vehicles so each request comes back with its vehicle details. PHP loops over the rows and builds an HTML table, escaping every value with `htmlspecialchars()` to prevent XSS. I tested that by inserting a script tag as a title and confirming it shows as text, and fires only when escaping is removed. If the database is unavailable, the page catches the exception, logs the real error for me, and shows users a generic message with a 500 status.
