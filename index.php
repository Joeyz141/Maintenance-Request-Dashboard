<?php
// index.php
// Dashboard home page: lists all maintenance requests.

require __DIR__ . '/src/db.php';
require __DIR__ . '/src/requests.php';
require __DIR__ . '/src/helpers.php';
require __DIR__ . '/src/validation.php';   
require __DIR__ . '/src/flash.php';   // flash_take(): the one-time toast message (Ticket 13)

// read the filters from the URL (?q=...&status=...&priority=) and clean them.
// result always has 3 keys; '' = no filter. 
$filters = clean_request_filters($_GET);

try {
    $pdo = get_db_connection();
    // pass the cleaned filters; with all three '' this returns every request
    $requests = get_all_requests($pdo, $filters);

    // Ticket 11: how many requests per status, for the KPI cards, e.g. ['open' => 1, 'in_progress' => 8, 'completed' => 1].
    // No filters on purpose: the cards always show the whole fleet, not just the filtered rows.
    $statusCounts = get_status_counts($pdo);
} catch (PDOException $e) {
    error_log('Failed to load requests: ' . $e->getMessage());
    http_response_code(500);
    exit('Sorry, the maintenance requests could not be loaded right now.');
}

// Ticket 13: the one-time message left by create.php / edit.php (e.g. "Request #8 updated."), or null.
// flash_take() reads it AND deletes it, so a refresh won't show it again.
// Called BEFORE any HTML: it may start the session, which sends a cookie header.
$flash = flash_take();

// Where the Streamlit analytics dashboard lives (Ticket 9).
// getenv() reads ANALYTICS_URL (set it in .htaccess, like DB_PASS); ?: uses the local default if it isn't set.
// On AWS we only change the setting, e.g. to /analytics. No code change.
$analyticsUrl = getenv('ANALYTICS_URL') ?: 'http://localhost:8501';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <!-- viewport = use the phone's real width instead of pretending to be a desktop -->
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Fleet Service · Maintenance Requests</title>

    <!-- Ticket 11: fonts from Google Fonts + our stylesheet (all the looks live in css/style.css) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Barlow+Condensed:wght@600;700;800&family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&display=swap">
    <!-- Ticket 13: ?v=<last-saved time> = "cache buster". When style.css changes, the URL changes,
         so browsers download the new file instead of reusing their old saved copy. -->
    <link rel="stylesheet" href="css/style.css?v=<?= filemtime(__DIR__ . '/css/style.css') ?>">

    <!-- loads our JavaScript. defer = download it now, but run it only AFTER the whole
         HTML below has been read, so the ids it looks for (filter-form, requests-body, ...) exist -->
    <!-- Ticket 14: same cache buster as style.css, because dashboard.js changed -->
    <script src="js/dashboard.js?v=<?= filemtime(__DIR__ . '/js/dashboard.js') ?>" defer></script>
</head>
<body>
    <!-- Ticket 11: dark header. Logo + name, nav (Requests / Analytics), New request button. -->
    <header class="site-header">
        <div class="site-header__inner">
            <a class="brand" href="index.php">
                <!-- the wrench icon is a small inline SVG drawing; aria-hidden = screen readers skip it -->
                <span class="brand__logo">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#0C0E10" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14.7 6.3a4 4 0 0 0-5.4 5.4L3 18l3 3 6.3-6.3a4 4 0 0 0 5.4-5.4l-2.5 2.5-2.4-.6-.6-2.4z"/></svg>
                </span>
                <span class="brand__name">Fleet Service</span>
            </a>

            <!-- Link to the analytics dashboard (a separate Streamlit app, opens in a new tab).
                 e() escapes the URL, because it comes from a setting, not from our code.
                 rel="noopener" stops the new tab from controlling this page (security habit with target="_blank"). -->
            <nav class="nav" aria-label="Main">
                <a class="is-active" href="index.php" aria-current="page">Requests</a>
                <a href="vehicles.php">Vehicles</a>
                <a href="<?= e($analyticsUrl) ?>" target="_blank" rel="noopener">Analytics ↗</a>
            </nav>

            <!-- Link to the form for adding a new maintenance request -->
            <!-- Ticket 12: the words are in their own <span> so phones can hide them and show just a square "+".
                 Screen readers still read "New request" (the CSS hides it only visually). -->
            <a class="btn-accent btn-new" href="create.php"><span aria-hidden="true">+</span><span class="btn-new__text">New request</span></a>
        </div>
    </header>

    <main class="page">
    <!-- Big condensed heading -->
    <section class="rise">
        <div class="eyebrow">Maintenance requests</div>
        <h1 class="page-title">Service operations</h1>
        <p class="lede">Every job across the fleet, in one place.</p>
    </section>

    <!-- Ticket 13: toast, only when there is a flash message. role="status" = screen readers announce it politely.
         e() escapes it like every printed value. Styled in style.css (Step 2). -->
    <?php if ($flash !== null): ?>
        <div class="toast" role="status"><?= e($flash) ?></div>
    <?php endif; ?>

    <!-- Ticket 11: the 4 KPI cards (Total, Open, In progress, Completed). -->
    <?php
    // array_sum() adds up all the values: 1 + 8 + 1 = 10.
    $totalRequests = array_sum($statusCounts);
    ?>
    <section class="kpis">
        <!-- Card 1: Total. Its bar is always full (100%). -->
        <div class="kpi rise">
            <div class="kpi__label">Total requests</div>
            <div class="kpi__number"><?= e($totalRequests) ?></div>
            <div class="kpi__track"><span class="kpi__bar" style="width: 100%"></span></div>
        </div>

        <!-- Cards 2-4: one per status, from the same STATUS_OPTIONS list as the dropdown.
             $value = 'open' / 'in_progress' / 'completed' (matches the CSS class kpi--open etc.)
             $label = 'Open' / 'In progress' / 'Completed' (the text people see) -->
        <?php foreach (STATUS_OPTIONS as $value => $label): ?>
            <?php
            // This status's share of all requests, as a percent: 8 of 10 = 80.
            // If there are no requests at all, use 0 (dividing by 0 would crash).
            $percent = $totalRequests > 0 ? round($statusCounts[$value] / $totalRequests * 100) : 0;
            ?>
            <div class="kpi kpi--<?= e($value) ?> rise">
                <div class="kpi__label"><?= e($label) ?></div>
                <div class="kpi__number"><?= e($statusCounts[$value]) ?></div>
                <div class="kpi__track"><span class="kpi__bar" style="width: <?= e($percent) ?>%"></span></div>
            </div>
        <?php endforeach; ?>
    </section>

    <!-- method="get" puts the choices in the URL, e.g. index.php?q=brake&status=open&priority=
         GET is right here because filtering only READS data -->
    <!-- id="filter-form" is the name tag JS uses to find this form and catch its submit.
         method/action stay, so the form still works with JS turned off. -->
    <!-- Ticket 11: one box (panel) holds the filters and the table -->
    <section class="panel rise">

    <!-- Ticket 14: when the list is filtered to one vehicle type (from the Vehicles page), say so,
         with a way back to everything. Only shown when make or model is in the URL. -->
    <?php if ($filters['make'] !== '' || $filters['model'] !== ''): ?>
        <p class="vehicle-filter">
            Vehicle: <strong><?= e(trim($filters['make'] . ' ' . $filters['model'])) ?></strong>
            <a href="index.php">Show all requests</a>
        </p>
    <?php endif; ?>

    <!-- Ticket 12: status chips (All · Open · In progress · Completed), each with its count.
         They are plain LINKS, so they work with JS off: clicking one loads index.php?status=... and PHP filters (Ticket 6).
         JS will catch the click in Step 2 and filter without a reload. -->
    <?php
    // The chip list: "All" first ('' = no status filter), then the 3 statuses from STATUS_OPTIONS.
    // + joins two arrays: ['' => 'All'] + ['open' => 'Open', ...] = ['' => 'All', 'open' => 'Open', ...]
    $chips = ['' => 'All'] + STATUS_OPTIONS;
    ?>
    <nav class="chips" id="status-chips" aria-label="Filter by status">
        <?php foreach ($chips as $value => $label): ?>
            <?php
            // The number on the chip: the whole fleet for "All", otherwise this status's count (same numbers as the KPI cards).
            $count = $value === '' ? $totalRequests : $statusCounts[$value];

            // The chip's link KEEPS the current search and priority and only changes the status.
            // http_build_query() turns the array into a URL query string: q=brake&status=open&priority=
            // Ticket 14: make + model are kept too, so a chip stays inside the chosen vehicle type.
            $url = 'index.php?' . http_build_query([
                'q'        => $filters['q'],
                'status'   => $value,
                'priority' => $filters['priority'],
                'make'     => $filters['make'],
                'model'    => $filters['model'],
            ]);

            // Is this the chip that's selected right now? ('' = All is selected when there's no status filter.)
            $isActive = $filters['status'] === $value;
            ?>
            <!-- data-status = a name tag JS reads in Step 2 to know which status this chip is.
                 aria-current = tells screen readers which chip is selected. -->
            <a class="chip<?= $isActive ? ' is-active' : '' ?>"
               href="<?= e($url) ?>"
               data-status="<?= e($value) ?>"
               <?= $isActive ? 'aria-current="true"' : '' ?>>
                <?= e($label) ?>
                <span class="chip__count"><?= e($count) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <form method="get" action="index.php" id="filter-form" class="toolbar">
        <!-- Search box: name="q" → ?q=... ; it will search the title OR the VIN. -->
        <label>
            Search
            <!-- value="..." refills the box with what was searched.
                 e() escapes it, because this is user input printed back into the page (XSS). -->
            <input type="text" name="q" placeholder="Title or VIN" value="<?= e($filters['q']) ?>">
        </label>

        <!-- Ticket 12: the Status dropdown is gone (the chips above replaced it).
             This hidden field still carries the current status, so pressing Filter
             keeps the chosen chip instead of resetting it. type="hidden" = sent with the form, not shown. -->
        <input type="hidden" name="status" value="<?= e($filters['status']) ?>">

        <!-- Ticket 14: the vehicle type travels the same way (hidden = sent, not shown).
             Because JS builds every API call from this form (new FormData(form)), the Filter button,
             live search and chips all stay inside the chosen vehicle type with NO extra JS. -->
        <input type="hidden" name="make" value="<?= e($filters['make']) ?>">
        <input type="hidden" name="model" value="<?= e($filters['model']) ?>">

        <!-- Priority filter: When the filter is '' none of them match, so the browser shows the first one ("All"). --> 
        <label>
            Priority
            <select name="priority">
                <option value="">All priorities</option>
                <?php foreach (PRIORITY_OPTIONS as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $filters['priority'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <!-- Submit: the browser builds the query string from the fields above and loads index.php?... -->
        <button type="submit" class="btn-accent">Filter</button>

        <!-- Clear: a plain link to index.php with NO query string, so every filter is reset. -->
        <a href="index.php" class="toolbar__clear">Clear</a>
    </form>

    <!-- empty on purpose. JS writes messages here ("Loading...", "10 requests", or an error).
         aria-live="polite" makes screen readers announce the new text when it changes. -->
    <p id="status-message" class="status-message" aria-live="polite"></p>

    <!-- table-scroll = on a narrow screen the table scrolls sideways instead of breaking the page -->
    <div class="table-scroll">
    <table class="requests">
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
                <!-- Ticket 12: data-label="..." = the column name, stored on each cell.
                     Desktop ignores it. On a phone (under 700 px) the CSS shows it as a small label
                     inside the card ("VIN  TESTVIN..."), because the table headers are hidden there.
                     ID, Title and Edit have no label: they're the card's top line and its button. -->
                <tr>
                    <td class="cell-id">#<?= e($request['id']) ?></td>
                    <td class="cell-title"><?= e($request['title']) ?></td>
                    <!-- Vehicle: make + model on top, year underneath (no "Unit 104" numbers: they aren't in the database) -->
                    <td data-label="Vehicle">
                        <?= e($request['make'] . ' ' . $request['model']) ?>
                        <div class="sub"><?= e($request['model_year']) ?></div>
                    </td>
                    <td class="cell-vin" data-label="VIN"><?= e($request['vin']) ?></td>
                    <!-- Ticket 11: priority meter = 3 small bars, 1/2/3 of them lit (orange) for low/medium/high,
                         then the readable label (Low / Medium / High) from PRIORITY_OPTIONS. -->
                    <?php
                    // Turn the priority word into a number of lit bars: 'high' → 3.
                    $level = ['low' => 1, 'medium' => 2, 'high' => 3][$request['priority']];
                    ?>
                    <td class="cell-priority" data-label="Priority">
                        <span class="meter">
                            <?php for ($i = 1; $i <= 3; $i++): ?>
                                <!-- bar number $i is lit ("on") if it's within the level: for medium (2), bars 1 and 2 -->
                                <b class="<?= $i <= $level ? 'on' : '' ?>"></b>
                            <?php endfor; ?>
                        </span>
                        <?= e(PRIORITY_OPTIONS[$request['priority']]) ?>
                    </td>

                    <!-- Ticket 11: status pill. The class uses the ENUM value (pill--in_progress → amber in style.css),
                         the text uses the readable label from STATUS_OPTIONS ('In progress'). -->
                    <td data-label="Status">
                        <span class="pill pill--<?= e($request['status']) ?>"><?= e(STATUS_OPTIONS[$request['status']]) ?></span>
                    </td>
                    <td class="cell-date" data-label="Created"><?= e($request['created_at']) ?></td>
                    <!-- Edit link for THIS row: the row's id goes into the URL, e.g. edit.php?id=8.
                         edit.php reads it back with $_GET['id'] and loads that one request.
                         e() escapes the id like every other printed value (defense in depth). -->
                    <td class="cell-actions"><a class="edit-link" href="edit.php?id=<?= e($request['id']) ?>">Edit</a></td>
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
    </div>
    </section>
    </main>

    <!-- Ticket 12: bottom tab bar, for PHONES ONLY.
         It's always in the HTML, but style.css hides it on wide screens (display: none)
         and shows it under 700 px, where it replaces the header's Requests / Analytics links.
         Why the bottom? On a phone your thumb reaches the bottom of the screen, not the top.
         Same 2 links as the header nav; Vehicles is added in Ticket 14. -->
    <nav class="tabbar" aria-label="Main">
        <a class="tabbar__item is-active" href="index.php" aria-current="page">
            <!-- list icon (3 lines), drawn with SVG like the wrench logo -->
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01"/></svg>
            <span>Requests</span>
        </a>
        <!-- Ticket 14: Vehicles tab (truck icon) -->
        <a class="tabbar__item" href="vehicles.php">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M1 6h13v10H1zM14 10h4l3 3v3h-7"/><circle cx="5.5" cy="18" r="1.8"/><circle cx="17.5" cy="18" r="1.8"/></svg>
            <span>Vehicles</span>
        </a>
        <a class="tabbar__item" href="<?= e($analyticsUrl) ?>" target="_blank" rel="noopener">
            <!-- bar chart icon -->
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/></svg>
            <span>Analytics ↗</span>
        </a>
    </nav>
</body>
</html>