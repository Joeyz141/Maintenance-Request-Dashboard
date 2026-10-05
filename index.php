<?php
// index.php
// Dashboard home page: lists all maintenance requests.

require __DIR__ . '/src/db.php';
require __DIR__ . '/src/requests.php';
require __DIR__ . '/src/helpers.php';

try {
    $pdo = get_db_connection();
    $requests = get_all_requests($pdo);
} catch (PDOException $e) {
    error_log('Failed to load requests: ' . $e->getMessage());
    http_response_code(500);
    exit('Sorry, the maintenance requests could not be loaded right now.');
}

// After a successful save, create.php redirects here with ?created=ID in the URL.
// (int) turns anything that isn't a number into 0, so junk in the URL can't do anything.
$createdId = (int) ($_GET['created'] ?? 0);
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
</head>
<body>
    <h1>Maintenance Requests</h1>

    <!-- Shows a confirmation only after create.php redirected here with ?created=ID. -->
    <?php if ($createdId > 0): ?>
        <p class="success">Request #<?= e($createdId) ?> was created.</p>
    <?php endif; ?>

    <!-- Link to the form for adding a new maintenance request. -->
    <p><a href="create.php">+ New request</a></p>

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
            </tr>
        </thead>
        <tbody>
            <?php foreach ($requests as $request): ?>
                <tr>
                    <td><?= e($request['id']) ?></td>
                    <td><?= e($request['title']) ?></td>
                    <td><?= e($request['model_year'] . ' ' . $request['make'] . ' ' . $request['model']) ?></td>
                    <td><?= e($request['vin']) ?></td>
                    <td><?= e($request['priority']) ?></td>
                    <td><?= e($request['status']) ?></td>
                    <td><?= e($request['created_at']) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>