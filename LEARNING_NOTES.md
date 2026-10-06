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

## Ticket 4: Create a New Maintenance Request

### Goal
Users can now create a new maintenance request from a form (`create.php`) instead of only viewing the list. When they submit it, PHP validates the data, saves it to MariaDB with a prepared statement, and redirects them back to the list (`index.php`), where the new request appears at the top.

### What I built

| File | New or changed | Job |
|---|---|---|
| `src/vehicles.php` | new | `get_all_vehicles()`: returns every vehicle as an array of rows (newest year first). Used to build the dropdown and as the list of valid vehicle ids. |
| `src/validation.php` | new | `validate_request()`: checks the submitted data and **returns** an array of error messages, one per field. An empty array means everything is valid. It only checks; `create.php` decides what to show. |
| `src/requests.php` | changed | Added `create_request()`: saves a new request into `maintenance_requests` with a prepared statement (`prepare` + `execute`) and returns the new id. (`get_all_requests()`, the JOIN for the list, is from Ticket 3.) |
| `create.php` | new | **GET:** shows the empty form, with the vehicle dropdown filled from the database. **POST:** reads the form, validates it, then either shows the form again with red error messages next to the fields (keeping what the user typed), or saves the request and redirects to `index.php`. |
| `index.php` | changed | Added a "+ New request" link to the form, and a green "Request #N was created." message after a successful save. |

### How creating a request works
1. The user opens `index.php` (the list) and clicks **+ New request**.
2. The browser sends a **GET** for `create.php`.
3. PHP runs `get_all_vehicles()` and builds the dropdown, then sends the finished form to the browser.
4. The user fills in the form. Nothing runs on the server while they type: PHP only runs when a request arrives.
5. The user clicks **Create request**, and the browser sends a **POST** to `create.php` with the form data.
6. PHP reads `$_POST` into `$input` (`trim()` + `??`).
7. `validate_request()` checks the input.
   - **Errors:** PHP shows the form again with the messages and the user's values. Nothing is saved.
   - **No errors:** go to step 8.
8. `create_request()` runs the prepared **INSERT**, and MariaDB returns the new id.
9. PHP sends a **302 redirect** to `index.php?created=ID`.
10. The browser sends a **GET** for `index.php`, which shows the green message and the new row at the top.

### GET vs POST

| | GET | POST |
|---|---|---|
| Used for | Asking for a page or data (reading) | Sending data that changes something (saving) |
| Where the data travels | In the URL (`?created=12`) | In the request body (not visible in the URL) |
| Safe to refresh? | Yes, it just loads the page again | No, the browser re-sends the data (duplicates) |
| Where we used it | Opening `create.php`, loading `index.php`, `?created=ID` | Submitting the form to `create.php` |

### Two layers of protection
**Interview answer:** Client-side checks are for convenience, server-side validation is for security and correctness, and database constraints are the final safety net.

The dropdown and `required` are not enough, because the browser belongs to the user: with Inspect (DevTools) I could change the priority to `urgent` and a vehicle id to `999`, and the server received them. So PHP has to check every value, for example that the vehicle id is in our list of real vehicle ids.

| Bad input | Caught by PHP validation? | Caught by the database? | What the user sees |
|---|---|---|---|
| Title of only spaces | Yes (`trim()` makes it `''`) | **No** (`NOT NULL` only blocks NULL, not an empty string) | "Title is required." |
| Priority `urgent` | Yes | Yes, ENUM + strict mode (#1265) | "Please choose a valid priority." |
| Vehicle `999` | Yes | Yes, the foreign key (#1452) | "Please choose a vehicle." |
| Title over 150 characters | Yes | Yes, `VARCHAR(150)` + strict mode | "Title must be 150 characters or fewer." |

MariaDB is the final destination, so the data that reaches it should already be correct. PHP validation also gives the user a clear message next to the right field and keeps what they typed. If only the database caught it, the user would get a generic 500 page and lose everything. And some bad input, like a blank title, the database doesn't catch at all.

### Key concepts
- **`value` vs the text shown in a dropdown:** the user sees the text ("2023 Toyota Camry …"), but the browser sends the `value` (the vehicle id, e.g. `2`).
- **`$_SERVER['REQUEST_METHOD']`:** tells PHP whether this request is a `GET` (show the form) or a `POST` (handle the submitted form).
- **`trim()` and `??`:** `trim()` removes spaces at both ends, so a title of only spaces becomes `''`. `$_POST['title'] ?? ''` uses `''` if the field wasn't sent, instead of an "Undefined array key" warning.
- **`in_array(..., true)` and `!`:** `in_array()` answers "is this value in the list?" with true/false, and `true` makes it an exact (strict) match. `!` flips the answer, so `!in_array(...)` means "not in the list", which is when we add an error.
- **The type trap (`'2'` vs `2`):** the form sends text (`'2'`), but PDO returns ids as numbers (`2`), and a strict comparison says they're different. Validation: convert the ids to text with `array_map('strval', ...)`. INSERT: convert the input to a number with `(int)`.
- **Keeping the user's values after an error:** PHP prints the submitted values back into the form (`value="..."`, textarea content, `selected`). `e()` matters inside `value="..."`: without it, a `"` in the input could close the attribute and inject HTML.
- **SQL injection:** user input that becomes part of the SQL command. If the title were glued into the SQL string, `x'); DROP TABLE vehicles; --` could run as commands.
- **Prepared statements (`prepare` + `execute`):** `prepare()` sends the SQL with placeholders (`:title`), and `execute()` sends the values separately. The text is **not** changed; it's safe because MariaDB already finished reading the command before the values arrive, so they can only ever be data.
- **"Prepared statements on the way in, escaping on the way out":** prepared statements protect the database when data goes **in** (SQL injection) by keeping values separate. `e()` protects the browser when data comes **out** (XSS) by converting characters like `<` into `&lt;`.
- **Empty description → NULL:** NULL means "no value", which says "no description" more clearly than an empty string, and matches the schema (`TEXT NULL`).
- **PHP is stateless:** PHP forgets everything after each request. `$input` only exists while one page is being built, so keeping the values after an error is not saving them.
- **Post/Redirect/Get:** after saving, PHP replies with a **302** redirect (`header('Location: ...')`), and the browser loads `index.php` with a GET. Pressing F5 then repeats only the harmless GET, so no duplicate is created. `exit` after `header()` stops the script so nothing else runs. Headers must be sent before any output.
- **`$_GET` and `(int)`:** `$_GET` reads values from the URL. The URL is user input too, so `(int)` turns anything that isn't a number (like `abc` or `<script>`) into `0`.
- **Separation of concerns:** `src/vehicles.php` and `src/requests.php` = SQL only, `src/validation.php` = rules only, `create.php` and `index.php` = the pages (read input, call the functions, show the result).

### Tests

| # | Test | Expected | Result |
|---|---|---|---|
| 1 | Open the form (GET) | Empty form, 3 vehicles with VINs (newest first), Medium selected, no errors | 
| 2 | Submit with a title of only spaces | "Title is required." under Title, nothing saved | 
| 3 | DevTools: change priority to `urgent` | "Please choose a valid priority." | The value `urgent` reached the server, proving the browser can't be trusted |
| 4 | DevTools: change a vehicle value to `999` | "Please choose a vehicle." | 
| 5 | Error keeps values; title `"><b>hi</b>` | Vehicle, description and priority stay filled; the title shows as plain text, nothing turns bold | 
| 6 | Save a valid request | Saved (#8), shows on `index.php` | 
| 7 | Empty description | Description saved as NULL | #9 shows NULL in phpMyAdmin |
| 8 | SQL injection: title `x'); DROP TABLE vehicles; --` | Saved as a normal title, `vehicles` table still exists | 
| 9 | F5 after saving, before PRG | Browser asks to resubmit, creates a duplicate | #11 was a duplicate of #10 (the problem PRG fixes) |
| 10 | F5 after saving, with PRG | No resubmit prompt, no duplicate | 
| 11 | Network tab after submitting | `create.php` 302, then `index.php` 200 | 
| 12 | `index.php?created=abc` | List loads with no message | `(int)` turned `abc` into 0 |

### Problems I debugged


**1. Missing `?>` → "unexpected token `<`"**
- **Symptom:** parse error on the `<!DOCTYPE html>` line.
- **Cause:** PHP was still in PHP mode when the HTML started.
- **Fix:** added `?>` before the HTML to switch to HTML mode.

### Habits learned
- Test attacks on purpose (DevTools edits, SQL injection, XSS) to prove the defenses work.

### Check yourself
1. What would happen if `create.php` built the INSERT by gluing `$input['title']` into the SQL string?
   - A title like `x'); DROP TABLE vehicles; --` would become part of the SQL and could run as commands (SQL injection). The prepared statement prevents this by sending the values separately. SQL is read before the data arrives. 
2. Why does the form still need PHP validation if the vehicle is a dropdown?
   - Because the browser can be edited: with DevTools a user can send any value (like `999`), so the server has to check that the id is a real vehicle.
3. After an error, the form shows the user's values again. Is anything saved at that point? Why or why not?
   - No. We only save when there are no errors. PHP just prints the submitted values back into the form for that one page, and then forgets them (PHP is stateless).
4. Why does pressing F5 on `index.php?created=12` not create a duplicate?
   - There's no "don't save duplicates" code. After saving, PHP redirects, so the last request is a GET of `index.php`, which only reads data. F5 repeats that GET, not the POST.

### Objective
When a user clicks "New request", `create.php` shows a form whose vehicle dropdown is built from the database. On submit, the browser POSTs the data, and PHP trims it and validates it on the server, because the browser can be edited. If anything is wrong, the form comes back with an error next to each field and the user's values kept. If it's valid, `create_request()` saves it with a prepared statement, so user input can never become SQL, and MariaDB's constraints act as a final safety net. Finally, PHP redirects to the list (Post/Redirect/Get), so refreshing can't create a duplicate, and the list shows a confirmation with the new row at the top.

## Ticket 5: Update a Request's Status and Priority

### Goal
A user can now change the **status** and **priority** of an existing maintenance request from an edit page (`edit.php`). The title, vehicle and VIN are shown but can't be changed. Both forms that change data (create and edit) are now protected against CSRF with a secret token, on top of validation, prepared statements and `e()`.

### What I built

| File | New or changed | Job |
|---|---|---|
| `src/requests.php` | changed | Added `get_request_by_id()`: returns **one** request (with its vehicle) as an array, or `false` if no request has that id. Added `update_request()`: prepares and executes an `UPDATE ... WHERE id = :id` that changes only that request's status and priority |
| `src/validation.php` | changed | Added `validate_status_update()`: checks that status and priority exactly match the ENUM values. It returns an array of errors; an empty array means there are no errors. |
| `src/csrf.php` | new | `csrf_token()` creates a secret (once per session) and returns it for a hidden form field. `csrf_check()` compares the token the form sent back with the session's copy, and stops with **403** if it's missing or different. |
| `edit.php` | new | **GET:** loads one request by its id (`edit.php?id=8`) and shows its details with the status and priority dropdowns pre-selected. Wrong id → 404, database down → 500. **POST:** checks the CSRF token, reads and validates the two dropdowns, saves with `update_request()`, then redirects to `index.php?updated=8`. |
| `index.php` | changed | Added an **Actions** column with an **Edit** link per row (`edit.php?id=N`), and a green "Request #N was updated." message after a save. (No CSRF here: the list only reads data.) |
| `create.php` | changed | Added the same CSRF protection (`csrf_check()` + the hidden token), because creating a request is also a data-changing POST. |

### How updating a request works
1. On the list (`index.php`), the user clicks **Edit** on a row. The link is `edit.php?id=8`.
2. The browser sends a **GET** for `edit.php?id=8`.
3. PHP reads the id with `(int) ($_GET['id'] ?? 0)`. Junk like `abc` becomes `0`.
4. `get_request_by_id()` loads that one request. No row → **404** "Request not found". Database error → **500**.
5. PHP gets the CSRF token (`csrf_token()`) **before** printing any HTML, then shows the form: the request's details as plain text, the dropdowns pre-selected with the current values, and the hidden token.
6. The user changes status and/or priority and clicks **Save changes**. The browser sends a **POST** to `edit.php?id=8` (the id stays in the URL through the form's `action`).
7. `csrf_check()` runs first. A missing or wrong token → **403**, and nothing else runs.
8. PHP reads `$_POST` into `$input` (`trim()` + `??`), and `validate_status_update()` checks it.
   - **Errors:** the form comes back with a red message next to the field. Nothing is saved.
   - **No errors:** go to step 9.
9. `update_request()` runs the prepared `UPDATE ... WHERE id = 8`. MariaDB updates `updated_at` by itself.
10. PHP sends a **302 redirect** to `index.php?updated=8`.
11. The browser sends a **GET** for `index.php`, which shows the green message and the new values in the table.

### UPDATE and WHERE
```sql
UPDATE maintenance_requests
SET status = 'in_progress', priority = 'high'
WHERE id = 8;
```
- Without the `WHERE` line, **every row** in the table would be changed to in_progress/high, and MariaDB would **not** show an error, just "N rows affected".
- **SELECT-first habit:** before running an UPDATE by hand, run a SELECT with the same WHERE. The rows it returns are exactly the rows the UPDATE will change.
- Columns not listed in `SET` stay the same, and `updated_at` changes by itself (`ON UPDATE CURRENT_TIMESTAMP` from Ticket 1).

### 404, 500 and 403

| Code | Meaning | When edit.php sends it |
|---|---|---|
| 404 Not Found | "What you asked for doesn't exist." The user's request is wrong. | `get_request_by_id()` returns `false`: `?id=999`, `?id=abc` (becomes 0) or no id |
| 500 Server Error | "We broke." Our side failed. | A `PDOException`, e.g. MySQL is stopped |
| 403 Forbidden | "I understood the request, but I refuse it." | `csrf_check()` finds a missing or wrong token |

`?id=abc` gives a 404 and not a PHP error because `(int)` turns `abc` into `0` **before** the database sees it. No request has id 0, so `get_request_by_id()` returns `false` and our own `if` answers 404.

### CSRF
1. **The attack (Cross-Site Request Forgery):** another website (e.g. `funny-cats.com`) contains a hidden form that points at **our** `edit.php` or `create.php` and submits itself. The victim's browser sends it to our site, along with our session cookie. The data is valid, so validation alone would let it through, and a request gets changed or created without the user meaning to.
2. **How the token stops it:** our form contains a secret token that our server created and stored in the session. On a POST, `csrf_check()` compares the token that came back with the session's copy. The attacker's page **can't read** our pages (the browser's same-origin policy), so it never learns the token, and its forged POST gets a 403.
3. **What a session is:** the way PHP remembers a browser between requests, even though PHP is stateless. `session_start()` gives the browser a cookie with a random ID (like a coat-check ticket), and `$_SESSION` is the shelf on the server where we keep things for that ID, like the token.
4. **CSRF vs XSS:** CSRF = another site makes **your browser submit our form**; the defense is the token. XSS = text in **our** page runs as code (like `<script>`); the defense is `e()` when printing. Different attacks, different defenses: `e()` doesn't stop CSRF.

**Rule:** every POST that changes data gets a CSRF token, and every value printed into HTML goes through `e()`.

### Key concepts
- **Each table has its own primary key named `id`:** `r.id` is the request's own number, `v.id` the vehicle's, and `r.vehicle_id` is the foreign key that points at `v.id`. The aliases tell them apart.
- **`fetch()` vs `fetchAll()`:** `fetchAll()` returns a list of rows; `fetch()` returns one row, or `false` if there's none. That's why the return type is `array|false`.
- **Placeholder wiring:** the name in the SQL (`:id`) matches the key in `execute()` without the colon (`'id' => $id`).
- **`int` in a signature vs `(int)`:** `int $id` in a function signature means PHP only lets a whole number in. `(int)` is a cast for **untrusted input** (like `$_GET`). It isn't needed on values that are already numbers or on query results.
- **The form's `action` keeps the id:** `action="edit.php?id=8"` keeps the id in the URL, so the POST can still read `$_GET['id']` and the same load/404 code protects it.
- **Only fields with a `name` are sent:** the title and VIN are plain text, not form fields, so they're never sent and can't be changed from this page, even with DevTools.
- **`$input` starts from the database row:** on a GET, the dropdowns start on the current values; on a POST, `$input` is replaced with what the user picked.
- **`void`:** a return type that means "returns nothing".
- **Post/Redirect/Get again:** after saving, a 302 sends the browser to the list, so F5 repeats only the harmless GET.
- **Headers before HTML:** `csrf_token()` starts the session, which sends a cookie header, so it has to run before any HTML is printed, like `header('Location: ...')`.
- **Separation of concerns:** `requests.php` = SQL, `validation.php` = rules, `csrf.php` = the token, `edit.php` = the page.

### Tests

| # | Test | Expected | Result |
|---|---|---|---|
| 1 | Query A and B in phpMyAdmin on #8 | B changes only #8; `updated_at` changes by itself | low/open → high/in_progress, `updated_at` 10:24:35 → 14:22:14 |
| 2 | `edit.php?id=8` | Form with the current status and priority selected | 
| 3 | `?id=999`, `?id=abc`, no id | "Request not found", Network 404 | 404 for all three |
| 4 | Valid save | Green "Request #8 was updated.", Network 302 → 200 | 
| 5 | F5 on the list after saving | No resubmit prompt | 
| 6 | Save without changing anything | Still redirects, no error | 
| 7 | DevTools: status `urgent` / priority = a link | Red error message, 200, nothing saved | "Please choose a valid status/priority." |
| 8 | Hidden token changed by one character | 403, nothing saved | 
| 9 | Hidden token deleted | 403, nothing saved | 
| 10 | Create form without a token | 403, no new row | 
| 11 | Create form with an empty title | "Title is required.", typed values kept | 

### Problems I debugged

**1. VS Code saved old copies over new files**
- **Symptom:** changes written to a file disappeared seconds later (four times).
- **Cause:** VS Code had the files open with old content and saved them back over the new versions.
- **Fix:** close all tabs (Ctrl+K W, Don't Save) before files are changed on disk, then reopen them.

### Habits learned
- Run a SELECT with the same WHERE before an UPDATE.
- Ctrl+F a variable name to make sure it's spelled the same everywhere.
- Close tabs before files are changed outside VS Code.
- Network tab: tick **Preserve log**, and check the **Payload** tab to see exactly what the browser sent.

### Check yourself
1. What happens if the UPDATE has no WHERE?
   - It doesn't fail: **every row** gets the new status and priority, and MariaDB shows no error, only "N rows affected".
2. Why is a wrong id a 404 and not a 500?
   - 404 means the thing asked for doesn't exist (the user's request is wrong); 500 means our server failed. A missing request isn't our failure, so our own `if` answers 404.
3. Why can't the title or VIN be changed from the edit page, even with DevTools?
   - They're plain text, not form fields, so they're never sent. And `update_request()` only ever updates status and priority.
4. The attacker's page sends our session cookie along with its forged POST. Why does it still fail?
   - The POST also needs the secret token in the form, and the attacker's page can't read our pages to learn it, so `csrf_check()` answers 403.
5. Why does `create.php` need a CSRF token if `e()` already protects the list?
   - `e()` only makes stored text safe to **display**. It doesn't decide whether a POST is **allowed**. A forged form with valid data would pass validation and create a fake request; only the token checks that the POST came from our form.

### Objective
When a user clicks "Edit" on the list, `edit.php` reads the id from the URL, turns it into a number, and loads that one request, answering 404 if it doesn't exist. It shows the request's details and two dropdowns pre-selected with the current status and priority, plus a hidden CSRF token. On submit, PHP first checks the token, so another website can't forge the form; then it validates both values against the ENUM lists and saves them with a prepared `UPDATE ... WHERE id = :id`, so only that one row changes and input can never become SQL. Finally, it redirects to the list (Post/Redirect/Get), which shows a confirmation, and every value is printed through `e()` to stop XSS.

## Ticket 6: Search and Filter Requests

### Goal
The user can now filter the existing requests on the list page (`index.php`) by **status**, **priority** and a **search box** that matches the request's **title or the vehicle's VIN**. The filters are validated, turned into a safe database query, and only the matching requests come back from MariaDB.

### What I built

| File | New or changed | Job |
|---|---|---|
| `index.php` | changed | Added a **GET** form with a search box and two dropdowns (status, priority), a **Filter** button and a **Clear** link. Clicking Filter makes the browser write the choices into the URL (`index.php?q=brake&status=open&priority=`). The page now requires `src/validation.php`, cleans the URL values with `clean_request_filters($_GET)`, passes them to `get_all_requests()`, keeps the chosen values in the form, and shows "No requests match your filters." when nothing matches. |
| `src/validation.php` | changed | Added `clean_request_filters()`: always returns the three keys `q`, `status` and `priority`. `''` means "no filter" (show everything). The search text is trimmed and cut to 100 characters; status and priority must be an **exact** ENUM match, otherwise they're ignored. |
| `src/requests.php` | changed | `get_all_requests()` now takes optional `$filters`. Each active filter adds a condition to `$where` and a value to `$params`. If there is **at least one** condition, **one** `WHERE` is added and the conditions are joined with `AND`; then `ORDER BY` goes last. It runs as a prepared statement. |

### How filtering works
1. The user opens `index.php`. The browser sends a **GET**, and with no filters the table shows every request.
2. The user types in the search box and/or picks a status and priority, then clicks **Filter**.
3. Because the form uses `method="get"`, the browser puts the choices in the URL and sends a new GET: `index.php?q=brake&status=in_progress&priority=`.
4. PHP reads them from `$_GET`, and `clean_request_filters()` keeps only safe values (e.g. `status=urgent` becomes `''`).
5. `get_all_requests($pdo, $filters)` builds the query: a condition for each active filter, joined with `AND` after one `WHERE`. The user's values travel separately as bound parameters.
6. MariaDB returns only the matching rows.
7. The page shows those rows, refills the form with the chosen values (through `e()`), or shows "No requests match your filters." **Clear** goes back to plain `index.php`.

### GET for search
- We're only **viewing** data, not changing it, so GET is the right method. Refreshing or repeating it can't hurt anything.
- Because the filters live in the URL, a filtered view can be **bookmarked, refreshed or shared** with a coworker.
- **No CSRF token is needed:** CSRF protects requests that change data. A forged search can only show someone a list. (`create.php` and `edit.php` change data, so they use POST + a token.)

### LIKE and the dynamic WHERE
- **`WHERE`** is where the conditions go; a row is returned only if they're true.
- **`=`** means an exact match (used for status and priority). **`LIKE '%brake%'`** means "contains brake": `%` stands for any characters or none. It's case-insensitive here, so `BRAKE` also matches.
- **`$where`** holds the conditions (SQL text **we** wrote, with placeholders). **`$params`** holds the user's values. They're kept separate, so typed text can never become SQL.
- `implode(' AND ', $where)` glues the conditions with `AND` **between** them, and `WHERE` is only added if there's at least one condition. No filters = the original query.

### Key concepts
- **Forgiving vs strict validation:** a bad filter value is ignored (a search can't damage anything); create/edit stay strict and show errors.
- **`''` means "no filter":** the "All" options have `value=""`, so "All" and "nothing chosen" are the same thing.
- **`?? ''`:** "use `''` if the key is missing", so `get_all_requests($pdo)` without filters still works 
- **`is_string()` first:** `?status[]=x` makes the value an array; checking it first stops `trim()`/`in_array()` from crashing.
- **`:q_title` and `:q_vin`:** two placeholder names for the same text

### Tests

| # | Test | Expected | Result |
|---|---|---|---|
| 1 | No filters | All requests | all 10 rows |
| 2 | Search `brake` / `BRAKE` | Only "Brake noise when stopping" | #1 for both |
| 3 | Search `0001` (part of a VIN) | Requests for that vehicle | #2 and #1 (the F-150) |
| 4 | In progress + High | Only rows with both | 8 rows |
| 5 | `brake` + Completed | "No requests match your filters." | message shown |
| 6 | Search `' OR 1=1 -- ` | Treated as text, no error | no match, no error |
| 7 | `?status=urgent` in the URL | Ignored, all rows | all 10 rows |
| 8 | Search `"><script>alert(1)</script>` | Shown as text in the box, no script runs | escaped by `e()` |
| 9 | Values after clicking Filter, then Clear | Choices stay selected; Clear resets | as expected |

### Objective
On the list page, a GET form lets the user search by title or VIN and filter by status and priority; the browser puts the choices in the URL, so a filtered view can be refreshed, bookmarked or shared, and no CSRF token is needed because nothing changes. PHP first cleans the URL values with `clean_request_filters()`, ignoring anything that isn't a valid status, priority or text. `get_all_requests()` then builds the query from only the active filters: each one adds a condition to `$where` and a value to `$params`, the conditions are joined with `AND` after a single `WHERE`, and the values are sent separately in a prepared statement, so input can never become SQL. The page shows the matching rows, keeps the chosen values in the form through `e()`, and says "No requests match your filters." when nothing is found.
