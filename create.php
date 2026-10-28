<?php
// create.php
// Shows the form for creating a new maintenance request.
//   1. The browser asks for this page (a GET request: "show me the form").
//   2. PHP fetches the vehicles from the database for the dropdown.
//   3. PHP builds the HTML form and sends it back to the browser.
//   4. On a POST, PHP validates the input and shows any errors next to the fields.

// require = "load this other file now; stop with an error if it's missing".
// __DIR__ is the folder this file lives in, so the paths work from anywhere.
require __DIR__ . '/src/db.php';        // get_db_connection(): opens the PDO connection
require __DIR__ . '/src/vehicles.php';  // get_all_vehicles(): SELECTs the vehicles
require __DIR__ . '/src/helpers.php';   // e(): escapes values before printing them in HTML
require __DIR__ . '/src/validation.php'; // validation rules for a new maintenance request
require __DIR__ . '/src/requests.php';  // create_request(): INSERTs a new request
require __DIR__ . '/src/csrf.php';      // csrf_token(), csrf_check(): proves the POST came from this form
require __DIR__ . '/src/flash.php';     // flash_set(): one-time "Request #N created." message (Ticket 13)

// try/catch: "try this risky code; if it throws a PDOException, run the catch block".
// Database code is risky: MySQL could be stopped, the password could be wrong, etc.
try {
    $pdo = get_db_connection();           // $pdo = our open line to the database
    $vehicles = get_all_vehicles($pdo);   // $vehicles = an array of rows, one per vehicle
} catch (PDOException $e) {
    error_log('Failed to load vehicles: ' . $e->getMessage());
    // Tell the browser "something went wrong on the server" (HTTP status 500).
    http_response_code(500);
    // The user sees only a generic message, never the technical details. exit() stops the script.
    exit('Sorry, the vehicles could not be loaded right now.');
}

// Start with no errors so the form can safely check $errors on a normal GET visit.
$errors = [];

// Starting values for the form: empty on a normal visit, replaced by what the user typed after a POST.
$input = [
    'vehicle_id' => '',
    'title' => '',
    'description' => '',
    'priority' => 'medium',
];

// Only handle form data when the form was submitted (POST).
// A normal page visit is a GET, so this block is skipped and only the empty form is shown.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check FIRST: a POST without our secret token is stopped here with 403, before we read or save anything.
    csrf_check();

    // Read each field from the form with form_text() (validation.php): it trims the text,
    // gives '' for a missing field, and '' for an array (title[]=x) instead of crashing trim().
    $input = [
        'title' => form_text($_POST, 'title'),
        'vehicle_id' => form_text($_POST, 'vehicle_id'),
        'description' => form_text($_POST, 'description'),
        'priority' => form_text($_POST, 'priority'),
    ];

    // The ids that really exist, as text so they match what the form sends (see the type trap)
    $vehicleIds = array_map('strval', array_column($vehicles, 'id'));

    // Ask the rules file: is this input OK? An empty array means yes.
    $errors = validate_request($input, $vehicleIds);

    if ($errors === []) {
        // Input is valid: save it. If the database fails, log the real error and show a generic message.
        try {
            $newId = create_request($pdo, $input);
            // Ticket 13: leave a one-time message in the session for index.php to show as a toast.
            flash_set('Request #' . $newId . ' created.');
            // Post/Redirect/Get: send the browser to the list, so a refresh can't re-submit the form.
            // No ?created=N any more: the message travels in the session, not the URL.
            header('Location: index.php');
            exit;
        } catch (PDOException $e) {
            error_log('Failed to create request: ' . $e->getMessage());
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
    <!-- viewport = use the phone's real width (this page didn't have it before Ticket 13) -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>New Request · Fleet Service</title>

    <!-- Ticket 13: same fonts + stylesheet as index.php (with the ?v= cache buster), so the form matches the dashboard. -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
</head>
<body>
    <!-- Same dark header as index.php (copied; no New request button, because we're already on that page). -->
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
        </div>
    </header>

    <main class="page">
        <section class="rise">
            <div class="eyebrow">Maintenance requests</div>
            <h1 class="page-title">New request</h1>
            <p class="lede">Pick the vehicle, describe the problem and set the priority. Every new request starts as Open.</p>
        </section>

        <!-- method="post": this form CHANGES data. action="create.php": send it back to this same file.
             class="panel form-card": the dark box (panel, from Ticket 11) + the form layout (style.css section 13). -->
        <form class="panel form-card rise" method="post" action="create.php">
            <!-- Hidden CSRF token: sent with the form so csrf_check() can prove the POST came from THIS page. -->
            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

            <!-- VEHICLE: a dropdown built from the database rows. label for= + id= link the label to the field. -->
            <div class="field">
                <label for="vehicle_id">Vehicle</label>
                <select id="vehicle_id" name="vehicle_id" required>
                    <!-- Empty first choice + "required" = the browser won't submit until a vehicle is picked (PHP re-checks). -->
                    <option value="">Choose a vehicle</option>
                    <!-- "selected" keeps the picked vehicle chosen after an error. -->
                    <?php foreach ($vehicles as $vehicle): ?>
                        <option value="<?= e($vehicle['id']) ?>" <?= (string) $vehicle['id'] === $input['vehicle_id'] ? 'selected' : '' ?>>
                            <?= e($vehicle['model_year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['vin'] . ')') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <?php if (isset($errors['vehicle_id'])): ?>
                    <p class="field__error"><?= e($errors['vehicle_id']) ?></p>
                <?php endif; ?>
            </div>

            <!-- TITLE: required, at most 150 characters (VARCHAR(150)). value= refills it after an error. -->
            <div class="field">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" required maxlength="150" value="<?= e($input['title']) ?>">
                <?php if (isset($errors['title'])): ?>
                    <p class="field__error"><?= e($errors['title']) ?></p>
                <?php endif; ?>
            </div>

            <!-- DESCRIPTION: optional (TEXT NULL). The text between the tags refills it after an error (kept on one line on purpose). -->
            <div class="field">
                <label for="description">Description <span class="field__optional">(optional)</span></label>
                <textarea id="description" name="description" rows="4" maxlength="<?= DESCRIPTION_MAX_LENGTH ?>"><?= e($input['description']) ?></textarea>
                <?php if (isset($errors['description'])): ?>
                    <p class="field__error"><?= e($errors['description']) ?></p>
                <?php endif; ?>
            </div>

            <!-- PRIORITY (Ticket 13): radio buttons styled as one bar of segments, instead of a dropdown.
                 fieldset + legend = "these radios belong together, and this is their question" (screen readers read it).
                 All 3 radios share name="priority", so only ONE can be picked, and PHP still gets $_POST['priority'].
                 Each radio has its own id, and its label's for= points at it: clicking the label picks the radio.
                 "checked" = the picked one (medium on a first visit, the user's pick after an error).
                 The radios only offer valid values, but DevTools can still change them, so PHP re-checks. -->
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

            <!-- No Status field on purpose: every new request starts as 'open' (the DB default). -->

            <div class="form-actions">
                <button class="btn-accent" type="submit">Create request</button>
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
