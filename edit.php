<?php
// edit.php
// Shows one maintenance request so its status and priority can be changed.
//   GET edit.php?id=8 -> load request #8, or answer 404 if it doesn't exist.
//   POST (Save changes) -> check the CSRF token, validate, update the row, then redirect to index.php?updated=8.

require __DIR__ . '/src/db.php';        // get_db_connection()
require __DIR__ . '/src/requests.php';  // get_request_by_id(), update_request()
require __DIR__ . '/src/helpers.php';   // e()
require __DIR__ . '/src/validation.php'; // validate_status_update()
require __DIR__ . '/src/csrf.php';       // csrf_token(), csrf_check()

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
            // Post/Redirect/Get: send the browser to the list, so a refresh can't re-submit the form.
            header('Location: index.php?updated=' . $id);
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

// The closing PHP tag below switches from "PHP mode" to "HTML mode".
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Maintenance Request</title>
    <!-- Red text for validation errors. -->
    <style> .error { color: #b00020; } </style>
</head>
<body>
    <!-- The heading shows WHICH request is being edited. e() escapes it like every printed value. -->
    <h1>Edit request #<?= e($request['id']) ?></h1>

    <!-- READ-ONLY INFO: shown for context, but it is plain text, not a form field,
         so it is never sent back and can't be changed from this page. -->
    <p>
        <strong><?= e($request['title']) ?></strong><br>
        <?= e($request['model_year'] . ' ' . $request['make'] . ' ' . $request['model']) ?>
        (VIN <?= e($request['vin']) ?>)<br>
        Last updated: <?= e($request['updated_at']) ?>
    </p>

    <!-- method="post": this form CHANGES data, so it uses POST.
         action="edit.php?id=8": the id rides along in the URL, so when the form is
         submitted, $_GET['id'] still tells edit.php WHICH request this is. -->
    <form method="post" action="edit.php?id=<?= e($request['id']) ?>">
        <!-- Hidden CSRF token: invisible to the user, but sent with the form so csrf_check() can prove
             the POST came from THIS page. Another website can't read this value, so it can't fake it. -->
        <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">

        <!-- STATUS: the values must exactly match ENUM('open','in_progress','completed') in schema.sql.
             The browser SENDS the value (in_progress) but SHOWS the text (In progress). -->
        <p>
            <label for="status">Status</label><br>
            <select id="status" name="status">
                <!-- One option per entry in STATUS_OPTIONS (validation.php). The ternary adds "selected"
                     to the option matching $input['status'], so the dropdown starts on the current status. -->
                <?php foreach (STATUS_OPTIONS as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $input['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <!-- Shows the status error only if validation found one. -->
            <?php if (isset($errors['status'])): ?>
                <span class="error"><?= e($errors['status']) ?></span>
            <?php endif; ?>
        </p>

        <!-- PRIORITY: values must match ENUM('low','medium','high'). Same pattern as create.php. -->
        <p>
            <label for="priority">Priority</label><br>
            <select id="priority" name="priority">
                <!-- Same loop with PRIORITY_OPTIONS: the current priority starts selected. -->
                <?php foreach (PRIORITY_OPTIONS as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $input['priority'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
            <!-- Shows the priority error only if validation found one. -->
            <?php if (isset($errors['priority'])): ?>
                <span class="error"><?= e($errors['priority']) ?></span>
            <?php endif; ?>
        </p>

        <!-- SUBMIT: sends status + priority as a POST to the action URL (edit.php?id=N). -->
        <p>
            <button type="submit">Save changes</button>
        </p>
    </form>

    <p><a href="index.php">Back to the list</a></p>
</body>
</html>
