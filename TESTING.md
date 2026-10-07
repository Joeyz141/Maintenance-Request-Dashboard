# Testing

How this project is tested: automated unit tests for the logic, and a manual checklist for
the parts that need a browser, Apache and MySQL.

## 1. Automated tests (run after every change)

| Suite | What it tests | Command (from the project folder) |
|---|---|---|
| **PHPUnit** (`tests/php/`) | `src/validation.php`: new-request rules, edit rules, filter cleaning, `form_text()`, and that the status/priority lists match `database/schema.sql` | `vendor\bin\phpunit` |
| **pytest** (`tests/python/`) | `reports/maintenance_data.py`: DataFrame building, counts, status × priority table, summary cards, every `load_requests()` error path (with a fake HTTP call), and that the Python lists match `schema.sql` | `python -m pytest` (venv on) |

Neither suite needs Apache, MySQL or the internet. They test **pure functions**: input in, result out.

First-time setup:
- PHP: install [Composer](https://getcomposer.org/), then run `composer install` (creates `vendor/`, which is gitignored).
- Python: `python -m pip install -r reports\requirements-dev.txt` with the venv on.

### Contract tests
`schema.sql` is the single source of truth for the allowed status and priority values. Both suites read
the `ENUM(...)` lists from it and compare them with `STATUS_OPTIONS` / `PRIORITY_OPTIONS` (PHP) and
`STATUSES` / `PRIORITIES` (Python). Add a value in one place but not the others, and a test fails.

## 2. Manual checklist (before a pull request)

Start Apache and MySQL in XAMPP. Open http://localhost/maintenance-dashboard/.

| # | Feature | Steps | Expected |
|---|---|---|---|
| 1 | List | Open the home page | Every request, newest first, with vehicle, VIN, priority, status, created date and an Edit link |
| 2 | XSS | Create a request titled `<b>bold</b>` | Shows the tags as plain text, not bold |
| 3 | Create | **+ New request** → fill in → Create | Back on the list with "Request #N was created."; refresh does not create it twice (PRG) |
| 4 | Create validation | Submit with an empty title / no vehicle | Red message next to the field, typed values kept |
| 5 | Edit | Edit a request → change status and priority → Save | "Request #N was updated." and the new values in the list |
| 6 | Edit 404 | Open `edit.php?id=999` and `edit.php?id=abc` | "Request not found" (status 404) |
| 7 | CSRF | Submit a form after deleting its hidden `csrf_token` (DevTools) | 403 "this form has expired…" |
| 8 | Search & filter | Type in Search; pick a status and a priority | The table updates without a page reload; the URL updates too |
| 9 | Filter without JS | Disable JavaScript → Filter | Same results, with a full page reload |
| 10 | Back / Forward | Filter twice, press Back | Form and table go back to the previous filter |
| 11 | No results | Search `zzzz` | "No requests match your filters." |
| 12 | API | Open `api/requests.php`, `?id=1`, `?id=999`, `?id=abc` | 200 JSON list, 200 one object, 404, 400 |
| 13 | API errors | Stop MySQL → reload the API | 500 with `{"error": "..."}` and no technical details |
| 14 | Analytics | `python -m streamlit run reports\dashboard.py` → open http://localhost:8501 | Cards, 2 charts, table, Download CSV; filters change everything |
| 15 | Analytics errors | Stop Apache → **Refresh data** | Red "Could not reach the API … Is Apache running?" message |

## 3. What is NOT covered (yet) and why

| Not tested automatically | Why | How it's checked today |
|---|---|---|
| SQL in `src/requests.php` | Needs a real database; would be an *integration* test with a separate test database | Manual checks 1, 3, 5, 8 |
| `js/dashboard.js` | Needs a browser (or a JS test tool such as Jest + jsdom) | Manual checks 8–11 |
| The Streamlit page itself | UI layout; the logic it uses is covered by pytest | Manual check 14 |
| CI (tests on every push) | Planned for the AWS phase (GitHub Actions) | Run both suites before every PR |
