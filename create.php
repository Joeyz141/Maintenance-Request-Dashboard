<?php
// create.php
// Shows the form for creating a new maintenance request.
//   1. The browser asks for this page (a GET request: "show me the form").
//   2. PHP fetches the vehicles from the database for the dropdown.
//   3. PHP builds the HTML form and sends it back to the browser.


// require = "load this other file now; stop with an error if it's missing".
// __DIR__ is the folder this file lives in, so the paths work from anywhere.
require __DIR__ . '/src/db.php';        // get_db_connection(): opens the PDO connection
require __DIR__ . '/src/vehicles.php';  // get_all_vehicles(): SELECTs the vehicles
require __DIR__ . '/src/helpers.php';   // e(): escapes values before printing them in HTML

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

// The closing PHP tag below switches from "PHP mode" to "HTML mode".
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>New Maintenance Request</title>
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
                <?php foreach ($vehicles as $vehicle): ?>
                    <option value="<?= e($vehicle['id']) ?>">
                        <?= e($vehicle['model_year'] . ' ' . $vehicle['make'] . ' ' . $vehicle['model'] . ' (' . $vehicle['vin'] . ')') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>

        <!-- TITLE: required, and at most 150 characters to match VARCHAR(150) in schema.sql -->
        <p>
            <label for="title">Title</label><br>
            <input type="text" id="title" name="title" required maxlength="150">
        </p>

        <!-- DESCRIPTION: optional (the column is TEXT NULL), so no "required".
             textarea = a multi-line box; unlike input it needs a closing tag. -->
        <p>
            <label for="description">Description (optional)</label><br>
            <textarea id="description" name="description" rows="4" cols="50"></textarea>
        </p>

        <!-- PRIORITY: the values must exactly match the ENUM('low','medium','high').
             "selected" pre-picks medium, the same as the database default.
             A dropdown limits the choices, but DevTools can still edit them, so PHP re-checks. -->
        <p>
            <label for="priority">Priority</label><br>
            <select id="priority" name="priority">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
            </select>
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
