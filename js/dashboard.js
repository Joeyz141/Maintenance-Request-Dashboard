// js/dashboard.js
// makes the filter form on index.php work WITHOUT reloading the page.
// If this file fails or JS is turned off, the form still works the old way (PHP reload).

// --- 1. Find the elements by their id (the "name tags" we added in index.php) ---------
const form          = document.getElementById('filter-form');     // the filter form
const statusMessage = document.getElementById('status-message');  // the empty <p> for messages
const tbody         = document.getElementById('requests-body');   // the table rows
const searchBox     = form.querySelector('input[name="q"]');     // the Search box inside the form (step 8)

// --- 2. Ask the API for the requests that match the filters ---------------------------
// async = this function is allowed to use await 
// params = the "order slip" built from the form, e.g. q=&status=completed&priority=
// Counts the trips to the kitchen. Used to ignore OLD answers that arrive late (see below).
let latestRequestNumber = 0;

async function loadRequests(params) {
    // Give this trip a number: 1, 2, 3, ... (let = a variable that CAN change, unlike const).
    // ++ adds 1 first, then the new value is used.
    const myRequestNumber = ++latestRequestNumber;

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
function addCell(row, text) {
    const cell = document.createElement('td'); // make a new, empty <td> (not on the page yet)
    cell.textContent = text;                   // put the text inside AS PLAIN TEXT (never as HTML)
    row.appendChild(cell);                     // attach the cell to the end of the row
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
        addCell(row, request.id);

        // Columns 2 to 7, in the same order as the <th> headers in index.php.
        addCell(row, request.title);                    // Title (ex: Weird Sound)

        // Vehicle: 3 fields joined with spaces, same as the PHP version (Ticket 3).
        // + joins text in JS (like . in PHP). model_year is a number (2023), but number + text = text.
        addCell(row, request.model_year + ' ' + request.make + ' ' + request.model); // ex: 2023 Toyota Camry

        addCell(row, request.vin);                      // VIN (ex: TESTVIN0000000002)
        addCell(row, request.priority);                 // Priority (low / medium / high)
        addCell(row, request.status);                   // Status (open / in_progress / completed)
        addCell(row, request.created_at);               // Created (ex: 2026-10-05 10:24:35)

        // Column 8: the Edit link, e.g. <a href="edit.php?id=8">Edit</a>.
        const actionCell = document.createElement('td');
        const link = document.createElement('a');
        link.href = 'edit.php?id=' + encodeURIComponent(request.id); // encodeURIComponent = make it URL-safe
        link.textContent = 'Edit';
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
