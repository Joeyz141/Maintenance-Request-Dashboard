<?php
// edit.php
// Shows one maintenance request so its status and priority can be changed.
//   GET edit.php?id=8 -> load request #8, or answer 404 if it doesn't exist.
//   POST (Save changes) -> check the CSRF token, validate, update the row, set a flash message, then redirect to index.php.

require __DIR__ . '/src/db.php';        // get_db_connection()
require __DIR__ . '/src/requests.php';  // get_request_by_id(), update_request()
require __DIR__ . '/src/helpers.php';   // e()
require __DIR__ . '/src/validation.php'; // validate_status_update()
require __DIR__ . '/src/csrf.php';       // csrf_token(), csrf_check()
require __DIR__ . '/src/flash.php';      // flash_set() (Ticket 13)

// The id comes from the URL (edit.php?id=8). URL input is untrusted text,
// so (int) turns it into a whole number: "abc", "" or a missing id all become 0.
$id = (int) ($_GET['id'] ?? 0);

// Load the request from the database; if the database itself fails, log it and answer 500.
try {
    $pdo = get_db_connection();
    $request = get_request_by_id($pdo, $id);
} catch (PDOException $e) {
    error_log('Failed to load request: ' . $e->getMessage());
    http_response_code(500);
    exit('Sorry, the maintenance request could not be loaded right now.');
}

// No request has this id (999, or 0 from "abc"/missing): answer 404 Not Found.
if ($request === false) {
    http_response_code(404);
    exit('Request not found');
}

// Starting values for the form: the request's CURRENT status and priority.
// On a POST, the block below replaces them with what the user picked (same idea as $input in create.php).
$input = [
    'status' => $request['status'],
    'priority' => $request['priority'],
];

// No errors on a normal visit; the POST block below may fill this.
$errors = [];

// Only handle the form when it was submitted (POST); a normal visit (GET) skips this block.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check FIRST: a POST without our secret token is stopped here with 403, before we read or save anything.
    csrf_check();

    // Read the two dropdowns with form_text() (validation.php): trimmed text, '' if missing or an array.
    $input = [
        'status' => form_text($_POST, 'status'),
        'priority' => form_text($_POST, 'priority'),
    ];

    // Ask the rules file if the input is OK; an empty array means yes.
    $errors = validate_status_update($input);

    if ($errors === []) {
        // Input is valid: save it. If the database fails, log the real error and show a generic message.
        try {
            update_request($pdo, $id, $input);
            // Ticket 13: one-time message for the toast on index.php.
            flash_set('Request #' . $id . ' updated.');
            // Post/Redirect/Get: send the browser to the list, so a refresh can't re-submit the form.
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            error_log('Failed to update request: ' . $e->getMessage());
            http_response_code(500);
            exit('Sorry, the request could not be saved right now.');
        }
    }
}

// Get this browser's secret token now, BEFORE any HTML is printed (starting a session sends a cookie header).
$csrfToken = csrf_token();

// Where the Streamlit analytics dashboard lives (same setting as index.php), for the header link.
$analyticsUrl = getenv('ANALYTICS_URL') ?: 'http://localhost:8501';

// The closing PHP tag below switches from "PHP mode" to "HTML mode".
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Edit Request #<?= e($request['id']) ?> · Fleet Service</title>

    <!-- Ticket 13: same fonts + stylesheet as index.php and create.php (with the ?v= cache buster). -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
</head>
<body>
    <!-- Same dark header as index.php. -->
    <header class="site-header">
        <div class="site-header__inner">
            <a class="brand" href="index.php">
                <span class="brand__logo">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0C0E10" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/></svg>
                </span>
                <span class="brand__name">Fleet Service</span>
            </a>
            <nav class="nav" aria-label="Main">
                <a class="is-active" href="index.php">Requests</a>
                <a href="vehicles.php">Vehicles</a>
                <a href="<?= e($analyticsUrl) ?>" target="_blank" rel="noopener">Analytics ↗</a>
            </nav>
            <a class="btn-accent btn-new" href="create.php"><span aria-hidden="true">+</span><span class="btn-new__text">New request</span></a>
        </div>
    </header>

    <main class="page">
        <!-- The heading shows WHICH request is being edited. e() escapes it like every printed value. -->
        <section class="rise">
            <div class="eyebrow">Request #<?= e($request['id']) ?></div>
            <h1 class="page-title">Edit request</h1>
            <p class="lede">Change the status or priority. The other details are read-only.</p>
        </section>

        <!-- READ-ONLY INFO: plain text, not form fields, so it is never sent back and can't be changed here.
             dl = "description list": dt is the name (VIN), dd is the value (TESTVIN...). -->
        <dl class="panel info-card rise">
            <div class="info-card__title">
                <dt>Title</dt>
                <dd><?= e($request['title']) ?></dd>
            </div>
            <div>
                <dt>Vehicle</dt>
                <dd><?= e($request['make'] . ' ' . $request['model']) ?> <span class="info-card__sub"><?= e($request['model_year']) ?></span></dd>
            </div>
            <div>
                <dt>VIN</dt>
                <dd class="mono"><?= e($request['vin']) ?></dd>
            </div>
            <div>
                <dt>Last updated</dt>
                <dd class="mono"><?= e($request['updated_at']) ?></dd>
            </div>
        </dl>

        <!-- method="post": this form CHANGES data. action="edit.php?id=8": the id rides along in the URL,
             so after submitting, $_GET['id'] still tells edit.php WHICH request this is. -->
        <form class="panel form-card rise" method="post" action="edit.php?id=<?= e($request['id']) ?>">
            <!-- Hidden CSRF token: proves the POST came from THIS page. -->
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <!-- STATUS (Ticket 13): radio segments, like Priority on create.php.
                 "segments--status" = an extra class so CSS can color the picked one by status (blue / amber / green).
                 The values must match ENUM('open','in_progress','completed'); PHP re-checks them. -->
            <fieldset class="field">
                <legend>Status</legend>
                <div class="segments segments--status">
                    <?php foreach (STATUS_OPTIONS as $value => $label): ?>
                        <input type="radio" id="status-<?= e($value) ?>" name="status" value="<?= e($value) ?>" <?= $input['status'] === $value ? 'checked' : '' ?>>
                        <label for="status-<?= e($value) ?>"><?= e($label) ?></label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['status'])): ?>
                    <p class="field__error"><?= e($errors['status']) ?></p>
                <?php endif; ?>
            </fieldset>

            <!-- PRIORITY: same pattern as create.php; the current priority starts checked. -->
            <fieldset class="field">
                <legend>Priority</legend>
                <div class="segments">
                    <?php foreach (PRIORITY_OPTIONS as $value => $label): ?>
                        <input type="radio" id="priority-<?= e($value) ?>" name="priority" value="<?= e($value) ?>" <?= $input['priority'] === $value ? 'checked' : '' ?>>
                        <label for="priority-<?= e($value) ?>"><?= e($label) ?></label>
                    <?php endforeach; ?>
                </div>
                <?php if (isset($errors['priority'])): ?>
                    <p class="field__error"><?= e($errors['priority']) ?></p>
                <?php endif; ?>
            </fieldset>

            <div class="form-actions">
                <button class="btn-accent" type="submit">Save changes</button>
                <a class="form-actions__cancel" href="index.php">Cancel</a>
            </div>
        </form>
    </main>

    <!-- Same phone tab bar as index.php (Ticket 12). -->
    <nav class="tabbar" aria-label="Main">
        <a class="tabbar__item is-active" href="index.php">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
            <span>Requests</span>
        </a>
        <!-- Ticket 14: Vehicles tab (truck icon) -->
        <a class="tabbar__item" href="vehicles.php">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 6h13v10H1zM14 10h4l3 3v3h-7"/><circle cx="5.5" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/></svg>
            <span>Vehicles</span>
        </a>
        <a class="tabbar__item" href="<?= e($analyticsUrl) ?>" target="_blank" rel="noopener">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
            <span>Analytics ↗</span>
        </a>
    </nav>
</body>
</html>
