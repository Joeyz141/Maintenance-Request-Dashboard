// js/dashboard.js
// makes the filter form on index.php work WITHOUT reloading the page.
// If this file fails or JS is turned off, the form still works the old way (PHP reload).

// --- 1. Find the elements by their id (the "name tags" we added in index.php) ---------
const form          = document.getElementById('filter-form');     // the filter form
const statusMessage = document.getElementById('status-message');  // the empty <p> for messages
const tbody         = document.getElementById('requests-body');   // the table rows
const searchBox     = form.querySelector('input[name="q"]');     // the Search box inside the form (step 8)
const chipsNav      = document.getElementById('status-chips');    // Ticket 12: the row of status chips

// --- 2. Ask the API for the requests that match the filters ---------------------------
// async = this function is allowed to use await 
// params = the "order slip" built from the form, e.g. q=&status=completed&priority=
// Counts the trips to the kitchen. Used to ignore OLD answers that arrive late (see below).
let latestRequestNumber = 0;

async function loadRequests(params) {
    // Give this trip a number: 1, 2, 3, ... (let = a variable that CAN change, unlike const).
    // ++ adds 1 first, then the new value is used.
    const myRequestNumber = ++latestRequestNumber;

    // Ticket 12: make the chips match these filters (orange chip + up-to-date links).
    // Every way of filtering (chip, Filter button, live search, Back/Forward) comes through here,
    // so calling it once here keeps the chips right in all four cases.
    syncChips(params);

    // Tell the user something is happening (the trip to the server takes a moment).
    statusMessage.textContent = 'Loading...';

    // try/catch = safety net #2: catches problems that stop us from getting an answer at all
    // (Apache off, no network), plus the error we throw ourselves below.
    try {
        // await = pause THIS function until the answer arrives.
        // ex: api/requests.php?q=&status=completed&priority=
        const response = await fetch('api/requests.php?' + params.toString());

        // Safety net #1: fetch does NOT fail on 404/500, it just comes back with ok = false.
        // So we check it ourselves. "throw" jumps straight to catch below.
        // ! means "not": if the response is NOT ok...
        if (!response.ok) {
            throw new Error('The API answered with status ' + response.status);
        }

        // turn the JSON text into a JS object { data, count, filters }.
        const result = await response.json();

        // Race guard: while we waited, a NEWER trip may have started (e.g. the user kept typing).
        // If so, this answer is out of date: ignore it, so an old result can't overwrite a newer one.
        if (myRequestNumber !== latestRequestNumber) {
            return;
        }

        // Draw the new rows from the array of requests (step 6, function below).
        renderRows(result.data);

        // Tell the user how many matched, with correct grammar: "1 request" but "3 requests".
        // condition ? A : B  = a short if/else (ternary): if count is 1 use A, otherwise use B.
        const word = result.count === 1 ? 'request' : 'requests';
        statusMessage.textContent = result.count + ' ' + word + ' found.'; // ex: 3 requests found.
    } catch (error) {
        // Same race guard: an out-of-date trip should not show an error over newer results.
        if (myRequestNumber !== latestRequestNumber) {
            return;
        }

        // The details are for us (developers) in the Console; the user gets a short, friendly message.
        console.error('Loading requests failed:', error);

        // Clear the old rows, so the table never shows results that don't match the error message
        // (ex: rows from the previous filter staying on screen under "Sorry...").
        tbody.textContent = '';

        statusMessage.textContent = 'Sorry, the requests could not be loaded right now.';
    }
}

// --- 2b. Draw the table rows from the API data ---------------------------------------
// Adds ONE cell (<td>) with plain text to a row (<tr>).
// Written once, used for every column .
// Ticket 11: optional className = the CSS class for the cell (same classes as index.php), e.g. 'cell-vin'.
// "= ''" is a default value: if we don't pass one, it's '' (no class).
// It also RETURNS the cell, so we can add more inside it when needed (the vehicle's year).
// Ticket 12: optional label = the column name for the phone cards, e.g. 'VIN' → data-label="VIN"
// (same as index.php). dataset.label is how JS writes a data-label attribute.
function addCell(row, text, className = '', label = '') {
    const cell = document.createElement('td'); // make a new, empty <td> (not on the page yet)
    cell.textContent = text;                   // put the text inside AS PLAIN TEXT (never as HTML)
    cell.className = className;                // ex: class="cell-vin"
    if (label) {
        cell.dataset.label = label;            // ex: data-label="VIN"
    }
    row.appendChild(cell);                     // attach the cell to the end of the row
    return cell;
}

// --- Ticket 11: pills and meters, the JS twin of the PHP in index.php -----------------
// The API sends raw values ('in_progress', 'high'). These two lists turn them into
// what people see. Same labels as STATUS_OPTIONS / PRIORITY_OPTIONS in validation.php.
// const + { key: value } = a JS object used as a lookup list (like a PHP array with keys).
const STATUS_LABELS = {
    open: 'Open',
    in_progress: 'In progress',
    completed: 'Completed',
};

// For the priority meter: the label AND how many of the 3 bars to light.
const PRIORITY_INFO = {
    low:    { label: 'Low',    level: 1 },
    medium: { label: 'Medium', level: 2 },
    high:   { label: 'High',   level: 3 },
};

// Adds the Status cell: <td><span class="pill pill--in_progress">In progress</span></td>
function addStatusPill(row, status) {
    const cell = document.createElement('td');
    cell.dataset.label = 'Status';                        // Ticket 12: label for the phone card
    const pill = document.createElement('span');
    pill.className = 'pill pill--' + status;              // ex: "pill pill--in_progress" → amber in style.css
    pill.textContent = STATUS_LABELS[status] ?? status;   // ex: "In progress" (?? = fall back to the raw value)
    cell.appendChild(pill);
    row.appendChild(cell);
}

// Adds the Priority cell: 3 bars (1/2/3 lit) + the label, same HTML as index.php:
// <td class="cell-priority"><span class="meter"><b class="on"></b><b class="on"></b><b></b></span>Medium</td>
function addPriorityMeter(row, priority) {
    const info = PRIORITY_INFO[priority] ?? { label: priority, level: 0 }; // unknown value → 0 bars lit

    const cell = document.createElement('td');
    cell.className = 'cell-priority';
    cell.dataset.label = 'Priority';                       // Ticket 12: label for the phone card

    const meter = document.createElement('span');
    meter.className = 'meter';

    // Same loop as the PHP for(): count 1, 2, 3 and light the bars up to the level.
    for (let i = 1; i <= 3; i++) {
        const bar = document.createElement('b');
        if (i <= info.level) {
            bar.className = 'on';
        }
        meter.appendChild(bar);
    }

    cell.appendChild(meter);
    cell.appendChild(document.createTextNode(info.label)); // the label as plain text (never HTML)
    row.appendChild(cell);
}

// requests = result.data from the API: an array of objects like
// { id: 8, title: "Weird Sound", make: "Toyota", model: "Camry", model_year: 2023, vin: "...", ... }
function renderRows(requests) {
    // 1. Clear the old rows (the ones PHP drew, or the previous filter's rows).
    tbody.textContent = '';

    // 2. Empty state: nothing matched. Same message as the PHP version (Ticket 6).
    if (requests.length === 0) {
        const row = document.createElement('tr');
        const cell = document.createElement('td');
        cell.colSpan = 8;                                // stretch across all 8 columns
        cell.textContent = 'No requests match your filters.';
        row.appendChild(cell);
        tbody.appendChild(row);
        return;                                          // stop here: there are no rows to draw
    }

    // 3. One <tr> per request. "for...of" = foreach in PHP: request is one object at a time.
    for (const request of requests) {
        const row = document.createElement('tr');

        // Column 1: ID. request.id reads the "id" field of this object (ex: 8).
        addCell(row, '#' + request.id, 'cell-id');      // ex: #8

        // Columns 2 to 7, in the same order as the <th> headers in index.php.
        addCell(row, request.title, 'cell-title');      // Title (ex: Weird Sound)

        // Vehicle (Ticket 11): make + model on top, the year in a small grey line under it,
        // same as the PHP version. + joins text in JS (like . in PHP).
        const vehicleCell = addCell(row, request.make + ' ' + request.model, '', 'Vehicle'); // ex: Toyota Camry
        const year = document.createElement('div');
        year.className = 'sub';
        year.textContent = request.model_year;          // ex: 2023
        vehicleCell.appendChild(year);

        addCell(row, request.vin, 'cell-vin', 'VIN');   // VIN (ex: TESTVIN0000000002)
        addPriorityMeter(row, request.priority);        // Priority: bars + Low / Medium / High (Ticket 11)
        addStatusPill(row, request.status);             // Status: colored pill (Ticket 11)
        addCell(row, request.created_at, 'cell-date', 'Created');  // Created (ex: 2026-10-05 10:24:35)

        // Column 8: the Edit link, e.g. <a href="edit.php?id=8">Edit</a>.
        const actionCell = document.createElement('td');
        actionCell.className = 'cell-actions';          // Ticket 12: full-width button on the phone card
        const link = document.createElement('a');
        link.href = 'edit.php?id=' + encodeURIComponent(request.id); // encodeURIComponent = make it URL-safe
        link.textContent = 'Edit';
        link.className = 'edit-link';
        actionCell.appendChild(link);
        row.appendChild(actionCell);

        // Put the finished row into the table (this is the moment it appears on screen).
        tbody.appendChild(row);
    }
}

// --- 3. Catch the Filter click -------------------------------------------------------
// The Filter button has type="submit", so clicking it (or pressing Enter in the search box)
// makes the browser fire a "submit" event on the form. We listen for that event.
form.addEventListener('submit', function (event) {
    // Stop the browser's normal behavior (loading index.php?... as a new page).
    event.preventDefault();

    // If a live-search timer is still waiting (step 8), cancel it: Filter does the work now.
    clearTimeout(searchTimer);

    // Read the form's fields (by their name="...") and format them for a URL.
    // ex: "q=&status=completed&priority="
    const params = new URLSearchParams(new FormData(form));

    // Update the address bar WITHOUT reloading, e.g. index.php?q=&status=completed&priority=
    // So refresh, bookmarks and shared links show the same filtered view (PHP reads this URL, Ticket 6).
    // pushState(state, title, url): we only need the url, so the first two are null and ''.
    history.pushState(null, '', 'index.php?' + params.toString());

    // Hand the order slip to the function above.
    loadRequests(params);
});

// --- 4. Back / Forward buttons -----------------------------------------------
// Copies the filters from a URL into the form fields, so the form matches the table.
// params.get('status') reads one value from the query string; ?? '' = "use '' if it's missing"
// (same as ?? in PHP).
function fillForm(params) {
    form.elements.q.value        = params.get('q') ?? '';
    form.elements.status.value   = params.get('status') ?? '';
    form.elements.priority.value = params.get('priority') ?? '';
    // Ticket 14: the vehicle type (hidden inputs), so Back/Forward keeps it right too.
    form.elements.make.value     = params.get('make') ?? '';
    form.elements.model.value    = params.get('model') ?? '';
}

// pushState() added entries to the browser history, but it doesn't redraw anything when you go back.
// "popstate" fires when the user presses Back or Forward between those entries.
window.addEventListener('popstate', function () {
    // location.search = the query string of the URL we just went back/forward to,
    // ex: "?q=&status=completed&priority=" (or "" for plain index.php = no filters).
    const params = new URLSearchParams(location.search);

    fillForm(params);      // 1. put those filters back into the form fields
    loadRequests(params);  // 2. redraw the table for them
    // No pushState here: the browser already moved to this URL. Adding one would break Back.
});

// --- 5. Live search: filter while typing (step 8) --------------------------------------
// Without a pause we'd call the API on EVERY key: typing "brake" = 5 trips.
// "Debounce" = wait until the user stops typing for 300 ms, then make ONE trip.
let searchTimer = null; // the id of the waiting timer (null = nothing waiting)

// "input" fires on every change to the box: typing, deleting, pasting.
searchBox.addEventListener('input', function () {
    // Cancel the previous countdown: the user is still typing.
    clearTimeout(searchTimer);

    // Start a new 300 ms countdown. If no new key arrives in time, the function runs.
    searchTimer = setTimeout(function () {
        const params = new URLSearchParams(new FormData(form));

        // replaceState (not pushState): update the URL WITHOUT adding a history entry per keystroke,
        // so Back doesn't have to step through every half-typed search (one entry per pause).
        history.replaceState(null, '', 'index.php?' + params.toString());

        loadRequests(params);
    }, 300);
});

// --- 6. Status chips (Ticket 12) -------------------------------------------------------
// The chips are links that PHP wrote (index.php?q=...&status=open&priority=...).
// With JS off, clicking one reloads the page. With JS on, we catch the click and use the
// SAME no-reload path as the Filter button: fill the form, pushState, loadRequests().

// Makes the chips match the current filters:
//  1. the chip for the current status gets "is-active" (orange) and aria-current
//  2. every chip's link is rebuilt with the current search + priority, so the links never go stale
//     (ex: after a live search for "oil", the Open chip's link includes q=oil, for Ctrl+click / new tab).
function syncChips(params) {
    const currentStatus = params.get('status') ?? '';   // '' = All

    // querySelectorAll = find ALL elements matching the CSS selector (here: the 4 chips).
    for (const chip of chipsNav.querySelectorAll('.chip')) {
        const chipStatus = chip.dataset.status;           // reads data-status="open" from the HTML ('' for All)
        const isActive = chipStatus === currentStatus;

        // classList.toggle(name, true/false) = add the class if true, remove it if false.
        chip.classList.toggle('is-active', isActive);

        if (isActive) {
            chip.setAttribute('aria-current', 'true');
        } else {
            chip.removeAttribute('aria-current');
        }

        // The JS twin of http_build_query() in index.php: same 5 keys, URL-safe.
        const link = new URLSearchParams({
            q: params.get('q') ?? '',
            status: chipStatus,
            priority: params.get('priority') ?? '',
            make: params.get('make') ?? '',      // Ticket 14
            model: params.get('model') ?? '',    // Ticket 14
        });
        chip.href = 'index.php?' + link.toString();
    }
}

// ONE listener on the whole chip row instead of one per chip ("event delegation").
// A click on any chip "bubbles up" to the row, and we work out which chip it was.
chipsNav.addEventListener('click', function (event) {
    // closest('.chip') = the chip that was clicked, even if the click landed on the number <span> inside it.
    // null if the click was in the gap between chips.
    const chip = event.target.closest('.chip');
    if (!chip) {
        return;
    }

    // Ctrl/Cmd/Shift + click = the user wants a new tab or window: let the browser follow the link normally.
    if (event.ctrlKey || event.metaKey || event.shiftKey) {
        return;
    }

    // Stop the normal link behavior (loading a new page). Same idea as preventDefault() on the form.
    event.preventDefault();

    // A live search may still be waiting (step 8): cancel it, the chip does the work now.
    clearTimeout(searchTimer);

    // Put the chip's status into the hidden input (the "invisible dropdown"),
    // so the form now holds: the search box + this status + the priority.
    form.elements.status.value = chip.dataset.status;

    // From here it's exactly the Filter button's path (section 3).
    const params = new URLSearchParams(new FormData(form));
    history.pushState(null, '', 'index.php?' + params.toString());
    loadRequests(params);   // loadRequests also calls syncChips(), so the orange chip moves
});
