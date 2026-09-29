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

