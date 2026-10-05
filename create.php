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
    // Read each field from the form; ?? '' avoids warnings if a field is missing,
    // and trim() removes spaces at both ends.
    $input = [
        'title' => trim($_POST['title'] ?? ''),
        'vehicle_id' => trim($_POST['vehicle_id'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'priority' => trim($_POST['priority'] ?? ''),
    ];

    // The ids that really exist, as text so they match what the form sends (see the type trap)
    $vehicleIds = array_map('strval', array_column($vehicles, 'id'));

    // Ask the rules file: is this input OK? An empty array means yes.
    $errors = validate_request($input, $vehicleIds);

    if ($errors === []) {
        // Input is valid: save it. If the database fails, log the real error and show a generic message.
        try {
            $newId = create_request($pdo, $input);
            // Post/Redirect/Get: send the browser to the list, so a refresh can't re-submit the form.
            header('Location: index.php?created=' . $newId);
            exit;
        } catch (PDOException $e) {
            error_log('Failed to create request: ' . $e->getMessage());
            http_response_code(500);
            exit('Sorry, the request could not be saved right now.');
        }
    }
}

// The closing PHP tag below switches from "PHP mode" to "HTML mode".
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Maintenance Request</title>
    <!-- Shows validation error messages in red. -->
    <style> .error { color: #b00020; } </style>
</head>
<body>
    <h1>New Maintenance Request</h1>

    <!-- method="post": this form CHANGES data, so it uses POST (the data travels in the
         request body, not the URL). action="create.php": send it back to this same file -->
    <form method="post" action="create.php">

        <!-- VEHICLE: a dropdown built from the database rows -->
        <p>
            <!-- label for="vehicle_id" + id="vehicle_id" link the label to the field:
                 clicking the label focuses the dropdown, and screen readers read it aloud.
                 id is for the page; name is for PHP ($_POST['vehicle_id']). -->
            <label for="vehicle_id">Vehicle</label><br>
            <select id="vehicle_id" name="vehicle_id" required>
                <!-- Empty first choice: together with "required", the browser won't submit
                     until a real vehicle is picked (a convenience check only; PHP re-checks). -->
                <option value="">-- Choose a vehicle --</option>

                <!-- foreach loops over every row in $vehicles and prints one option each.
                     The browser SENDS the value (the id, e.g. 2) but SHOWS the text
                     (e.g. "2023 Toyota Camry (TESTVIN0000000002)").
                     The short echo tag (less-than, question mark, equals) means "print this",
                     and e() escapes the value first to block XSS. -->
                <!-- "selected" is added to the vehicle the user picked, so it stays chosen after an error. -->
                <?php foreach ($vehicles as $vehicle): ?>
                    <option value="<?= e($vehicle['id']) ?>" <?= (string) $vehicle['id'] === $input['vehicle_id'] ? 'selected' : '' ?>>
                        <?= e($vehicle['model_year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['vin'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <!-- Shows the vehicle error only if validation found one. -->
            <?php if (isset($errors['vehicle_id'])): ?>
                <span class="error"><?= e($errors['vehicle_id']) ?></span>
            <?php endif; ?>
        </p>

        <!-- TITLE: required, and at most 150 characters to match VARCHAR(150) in schema.sql -->
        <p>
            <label for="title">Title</label><br>
            <!-- value="..." refills the title after an error; e() keeps quotes from breaking out of the attribute. -->
            <input type="text" id="title" name="title" required maxlength="150" value="<?= e($input['title']) ?>">
            <!-- Shows the title error only if validation found one. -->
            <?php if (isset($errors['title'])): ?>
                <span class="error"><?= e($errors['title']) ?></span>
            <?php endif; ?>
        </p>

        <!-- DESCRIPTION: optional (the column is TEXT NULL), so no "required".
             textarea = a multi-line box; unlike input it needs a closing tag. -->
        <p>
            <label for="description">Description (optional)</label><br>
            <!-- The text between the textarea tags refills the description after an error (kept on one line on purpose). -->
            <textarea id="description" name="description" rows="4" cols="50"><?= e($input['description']) ?></textarea>
        </p>

        <!-- PRIORITY: the values must exactly match the ENUM('low','medium','high').
             The default (medium) comes from $input at the top of the file.
             A dropdown limits the choices, but DevTools can still edit them, so PHP re-checks. -->
        <p>
            <label for="priority">Priority</label><br>
            <select id="priority" name="priority">
                <!-- Each ternary adds "selected" to the option matching $input['priority'] (medium on a first visit). -->
                <option value="low" <?= $input['priority'] === 'low' ? 'selected' : '' ?>>Low</option>
                <option value="medium" <?= $input['priority'] === 'medium' ? 'selected' : '' ?>>Medium</option>
                <option value="high" <?= $input['priority'] === 'high' ? 'selected' : '' ?>>High</option>
            </select>
            <!-- Shows the priority error only if validation found one. -->
            <?php if (isset($errors['priority'])): ?>
                <span class="error"><?= e($errors['priority']) ?></span>
            <?php endif; ?>
        </p>

        <!-- No Status field on purpose: every new request starts as 'open' (the DB default). -->

        <!-- SUBMIT: packs every field that has a name into the request and sends it
             to the action URL using the method (POST to create.php). -->
        <p>
            <button type="submit">Create request</button>
        </p>
    </form>

    <p><a href="index.php">Back to the list</a></p>
</body>
</html>
