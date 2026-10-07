<?php
// index.php
// Dashboard home page: lists all maintenance requests.

require __DIR__ . '/src/db.php';
require __DIR__ . '/src/requests.php';
require __DIR__ . '/src/helpers.php';
require __DIR__ . '/src/validation.php';   

// read the filters from the URL (?q=...&status=...&priority=) and clean them.
// result always has 3 keys; '' = no filter. 
$filters = clean_request_filters($_GET);

try {
    $pdo = get_db_connection();
    // pass the cleaned filters; with all three '' this returns every request
    $requests = get_all_requests($pdo, $filters);
} catch (PDOException $e) {
    error_log('Failed to load requests: ' . $e->getMessage());
    http_response_code(500);
    exit('Sorry, the maintenance requests could not be loaded right now.');
}

// After a successful save, create.php redirects here with ?created=ID in the URL.
// (int) turns anything that isn't a number into 0, so junk in the URL can't do anything.
$createdId = (int) ($_GET['created'] ?? 0);

// After a successful edit, edit.php redirects here with ?updated=ID (same (int) protection).
$updatedId = (int) ($_GET['updated'] ?? 0);

// Where the Streamlit analytics dashboard lives (Ticket 9).
// getenv() reads ANALYTICS_URL (set it in .htaccess, like DB_PASS); ?: uses the local default if it isn't set.
// On AWS we only change the setting, e.g. to /analytics. No code change.
$analyticsUrl = getenv('ANALYTICS_URL') ?: 'http://localhost:8501';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Maintenance Requests</title>
    <style>
        table { border-collapse: collapse; }
        th, td { border: 1px solid #999; padding: 6px 10px; text-align: left; }
        .success { color: #1b7a1b; }
    </style>

    <!-- loads our JavaScript. defer = download it now, but run it only AFTER the whole
         HTML below has been read, so the ids it looks for (filter-form, requests-body, ...) exist -->
    <script src="js/dashboard.js" defer></script>
</head>
<body>
    <h1>Maintenance Requests</h1>

    <!-- Shows a confirmation only after create.php redirected here with ?created=ID. -->
    <?php if ($createdId > 0): ?>
        <p class="success">Request #<?= e($createdId) ?> was created.</p>
    <?php endif; ?>

    <!-- Shows a confirmation only after edit.php redirected here with ?updated=ID. -->
    <?php if ($updatedId > 0): ?>
        <p class="success">Request #<?= e($updatedId) ?> was updated.</p>
    <?php endif; ?>

    <!-- Link to the form for adding a new maintenance request,
         and to the analytics dashboard (a separate Streamlit app, opens in a new tab).
         e() escapes the URL, because it comes from a setting, not from our code.
         rel="noopener" stops the new tab from controlling this page (security habit with target="_blank"). -->
    <p>
        <a href="create.php">+ New request</a>
        |
        <a href="<?= e($analyticsUrl) ?>" target="_blank" rel="noopener">View analytics dashboard</a>
    </p>

    <!-- method="get" puts the choices in the URL, e.g. index.php?q=brake&status=open&priority=
         GET is right here because filtering only READS data -->
    <!-- id="filter-form" is the name tag JS uses to find this form and catch its submit.
         method/action stay, so the form still works with JS turned off. -->
    <form method="get" action="index.php" id="filter-form">
        <!-- Search box: name="q" → ?q=... ; it will search the title OR the VIN. -->
        <label>
            Search
            <!-- value="..." refills the box with what was searched.
                 e() escapes it, because this is user input printed back into the page (XSS). -->
            <input type="text" name="q" placeholder="Title or VIN" value="<?= e($filters['q']) ?>">
        </label>

        <!-- Status filter: name="status" → ?status=...
             value="" (empty) means "no status filter": PHP will treat an empty value as "show all".
             The other values match the ENUM in schema.sql exactly. -->
        <label>
            Status
            <select name="status">
                <!-- When the filter is '' none of them match, so the browser shows the first one ("All"). -->
                <option value="">All statuses</option>
                <option value="open" <?= $filters['status'] === 'open' ? 'selected' : '' ?>>Open</option>
                <option value="in_progress" <?= $filters['status'] === 'in_progress' ? 'selected' : '' ?>>In progress</option>
                <option value="completed" <?= $filters['status'] === 'completed' ? 'selected' : '' ?>>Completed</option>
            </select>
        </label>

        <!-- Priority filter: When the filter is '' none of them match, so the browser shows the first one ("All"). --> 
        <label>
            Priority
            <select name="priority">
                <option value="">All priorities</option>
                <option value="low" <?= $filters['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                <option value="medium" <?= $filters['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="high" <?= $filters['priority'] === 'high' ? 'selected' : '' ?>>High</option>
            </select>
        </label>

        <!-- Submit: the browser builds the query string from the fields above and loads index.php?... -->
        <button type="submit">Filter</button>

        <!-- Clear: a plain link to index.php with NO query string, so every filter is reset. -->
        <a href="index.php">Clear</a>
    </form>

    <!-- empty on purpose. JS writes messages here ("Loading...", "10 requests", or an error).
         aria-live="polite" makes screen readers announce the new text when it changes. -->
    <p id="status-message" aria-live="polite"></p>

    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Title</th>
                <th>Vehicle</th>
                <th>VIN</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Created</th>
                <!-- Extra column for the links that act on each row (Edit for now). -->
                <th>Actions</th>
            </tr>
        </thead>
        <!-- id="requests-body" lets JS find the rows and replace them with new ones. -->
        <tbody id="requests-body">
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td><?= e($request['id']) ?></td>
                    <td><?= e($request['title']) ?></td>
                    <td><?= e($request['model_year'] . ' ' . $request['make'] . ' ' . $request['model']) ?></td>
                    <td><?= e($request['vin']) ?></td>
                    <td><?= e($request['priority']) ?></td>
                    <td><?= e($request['status']) ?></td>
                    <td><?= e($request['created_at']) ?></td>
                    <!-- Edit link for THIS row: the row's id goes into the URL, e.g. edit.php?id=8.
                         edit.php reads it back with $_GET['id'] and loads that one request.
                         e() escapes the id like every other printed value (defense in depth). -->
                    <td><a href="edit.php?id=<?= e($request['id']) ?>">Edit</a></td>
                </tr>
            <?php endforeach; ?>

            <!-- empty state. If no row matched, say so instead of showing an empty table.-->
            <?php if (count($requests) === 0): ?>
                <tr>
                    <td colspan="8">No requests match your filters.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</body>
</html>