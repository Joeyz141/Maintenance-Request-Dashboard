<?php
// vehicles.php
// Vehicles page (Ticket 14): one row per vehicle TYPE (make + model) with its request counts.
// READ-ONLY on purpose: vehicles are master data owned by the fleet system, so there is no add/edit here.
// Clicking a type opens the request list filtered to it: index.php?make=Ford&model=F-150

require __DIR__ . '/src/db.php';
require __DIR__ . '/src/vehicles.php';   // get_vehicle_types_with_counts()
require __DIR__ . '/src/helpers.php';    // e()

try {
    $pdo = get_db_connection();
    // e.g. [ ['make' => 'Toyota', 'model' => 'Camry', 'vehicles' => 1, 'open_requests' => 1, 'total_requests' => 5], ... ]
    $vehicleTypes = get_vehicle_types_with_counts($pdo);
} catch (PDOException $e) {
    // Same pattern as index.php: real error to the log, short safe message to the user.
    error_log('Failed to load vehicles: ' . $e->getMessage());
    http_response_code(500);
    exit('Sorry, the vehicles could not be loaded right now.');
}

// Same setting as index.php (Ticket 9): where the Streamlit analytics dashboard lives.
$analyticsUrl = getenv('ANALYTICS_URL') ?: 'http://localhost:8501';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fleet Service · Vehicles</title>

    <!-- Same fonts + stylesheet (with the Ticket 13 cache buster) as the other pages -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">
    <!-- No JavaScript on this page: it only shows data, and every link is a normal link. -->
</head>
<body>
    <!-- Header: this page's own copy (each page keeps its own). Vehicles is the active link. -->
    <header class="site-header">
        <div class="site-header__inner">
            <a class="brand" href="index.php">
                <span class="brand__logo">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0C0E10" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/></svg>
                </span>
                <span class="brand__name">Fleet Service</span>
            </a>

            <nav class="nav" aria-label="Main">
                <a href="index.php">Requests</a>
                <a class="is-active" href="vehicles.php" aria-current="page">Vehicles</a>
                <a href="<?= e($analyticsUrl) ?>" target="_blank" rel="noopener">Analytics ↗</a>
            </nav>

            <a class="btn-accent btn-new" href="create.php"><span aria-hidden="true">+</span><span class="btn-new__text">New request</span></a>
        </div>
    </header>

    <main class="page">
    <section class="rise">
        <div class="eyebrow">Fleet</div>
        <h1 class="page-title">Vehicles</h1>
        <p class="lede">Requests per vehicle type. Pick one to see its requests.</p>
        <!-- Tells people WHY there is no "Add vehicle" button. -->
        <p class="readonly-note">Read-only · managed by the fleet system</p>
    </section>

    <section class="panel rise">
    <div class="table-scroll">
    <!-- class="requests" on purpose: reuses the table look AND the phone cards from Ticket 12.
         data-label = the small label each line shows on a phone card. -->
    <table class="requests vehicles">
        <thead>
            <tr>
                <th>Vehicle type</th>
                <th>Vehicles</th>
                <th>Open</th>
                <th>Total requests</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($vehicleTypes as $type): ?>
                <?php
                // The link to the request list for THIS type, e.g. index.php?make=Ford&model=F-150
                // http_build_query() builds the query string AND encodes spaces/symbols (Model 3 → Model+3).
                $url = 'index.php?' . http_build_query([
                    'make'  => $type['make'],
                    'model' => $type['model'],
                ]);
                ?>
                <tr>
                    <td class="cell-title"><a class="row-link" href="<?= e($url) ?>"><?= e($type['make'] . ' ' . $type['model']) ?></a></td>
                    <td class="cell-num" data-label="Vehicles"><?= e($type['vehicles']) ?></td>
                    <!-- Open count as a pill, like the Status pills on the dashboard:
                         blue (pill--open) when the type has open requests, white (pill--clear) when it has 0.
                         pill--count = no dot, just the number. -->
                    <td data-label="Open">
                        <span class="pill pill--count <?= $type['open_requests'] > 0 ? 'pill--open' : 'pill--clear' ?>"><?= e($type['open_requests']) ?> open</span>
                    </td>
                    <td class="cell-num" data-label="Total"><?= e($type['total_requests']) ?></td>
                    <td class="cell-actions"><a class="edit-link" href="<?= e($url) ?>">View requests</a></td>
                </tr>
            <?php endforeach; ?>

            <?php if (count($vehicleTypes) === 0): ?>
                <tr>
                    <td colspan="5">No vehicles yet.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    </div>
    </section>
    </main>

    <!-- Phone tab bar: this page's own copy, Vehicles active. -->
    <nav class="tabbar" aria-label="Main">
        <a class="tabbar__item" href="index.php">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
            <span>Requests</span>
        </a>
        <a class="tabbar__item is-active" href="vehicles.php" aria-current="page">
            <!-- truck icon -->
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
