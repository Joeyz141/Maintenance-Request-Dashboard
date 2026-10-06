<?php
// api/requests.php
// It answers with DATA (JSON), never with an HTML page, so other programs can use it
// (JavaScript in Ticket 8, Python in Ticket 9, curl.exe, or the browser).

// How to call it (always with GET):
//   api/requests.php                                -> every request
//   api/requests.php?q=brake&status=in_progress     -> filtered list (same filters as index.php)
//   api/requests.php?id=1                           -> one request
//
// Status codes it can answer with:
//   200 OK                  -> here is the data
//   400 Bad Request         -> ?id is not a positive whole number (e.g. ?id=abc)
//   404 Not Found           -> no request has that id
//   405 Method Not Allowed  -> anything other than GET (e.g. POST)
//   500 Server Error        -> the database failed (details go to the log, not to the client)

require __DIR__ . '/../src/db.php';         // get_db_connection()
require __DIR__ . '/../src/requests.php';   // get_all_requests(), get_request_by_id()
require __DIR__ . '/../src/validation.php'; // clean_request_filters()
// No helpers.php: e() escapes text for HTML, and this file never prints HTML.

// Every response gets the same three things: a status code, the JSON label, and a JSON body.
//   $status = the HTTP status code (200, 400, 404, 405 or 500)
//   $body   = a PHP array; json_encode() turns it into JSON text
function send_json(int $status, array $body): void
{
    // 1. The status code: tells the client how it went before it reads anything else.
    http_response_code($status);

    // 2. The label on the box: "this is JSON text in UTF-8", so clients don't treat it as HTML.
    header('Content-Type: application/json; charset=utf-8');

    // Security: tells the browser to trust that label and never guess ("sniff") that this is HTML.
    // So a title like <script>...</script> stays plain text inside the JSON and never runs.
    header('X-Content-Type-Options: nosniff');

    // 3. The body. The flags (joined with |) change how the JSON is written:
    //   JSON_PRETTY_PRINT            -> one value per line with indentation, easy to read while learning
    //   JSON_UNESCAPED_UNICODE       -> keeps characters like é as they are instead of \u00e9
    //   JSON_INVALID_UTF8_SUBSTITUTE -> if a value has broken characters, replace them instead of failing
    echo json_encode($body, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

    // 4. Stop here: nothing else may be printed after the JSON, or the client couldn't read it.
    exit;
}

// ex: ?id=999, Yes it's a GET, PASSES
// --- 1. Only GET is allowed -------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    send_json(405, ['error' => 'Method not allowed. Use GET.']);
}

// --- 2. Read and check the input from the URL --------------------------------
// Bad values are ignored, not errors.
// ex: ?id=999, Yes it's a positive number, so it's written correctly 
$filters = clean_request_filters($_GET);

// id: null means "no ?id in the URL, so send the whole (filtered) list".
$id = null;

if (isset($_GET['id'])) {
    // filter_var(..., FILTER_VALIDATE_INT) returns the number, or false if the text isn't a whole number.
    // "5" -> 5      "abc" -> false      "" -> false      "-3" -> false      ?id[]=5 (an array) -> false
    $id = filter_var($_GET['id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

    // 400 = "your input is invalid", 404 = "your input is fine, but that request doesn't exist".
    if ($id === false) {
        send_json(400, ['error' => 'id must be a positive whole number.']);
    }
}

// --- 3. Ask the database -----------------------------------------------------
// ex: ?id=999 does not equal NULL, so answer is false('nothing found') and passes to next step
try {
    $pdo = get_db_connection();

    if ($id !== null) {
        // One request: an array with its columns, or false if no request has this id.
        $request = get_request_by_id($pdo, $id);
    } else {
        // The list: an array of rows (maybe empty), filtered by the cleaned filters.
        $requests = get_all_requests($pdo, $filters);
    }
} catch (PDOException $e) {
    // The real error goes to the Apache error log for us; the client gets a short, safe message.
    // Never send $e->getMessage() to the client: it can reveal table names, the DB user, etc.
    error_log('API failed to load requests: ' . $e->getMessage());
    send_json(500, ['error' => 'The maintenance requests could not be loaded right now.']);
}

// --- 4. Answer ---------------------------------------------------------------
// ex: ?id=999, false means 404 "Request not found" 
if ($id !== null) {
    if ($request === false) {
        send_json(404, ['error' => 'Request not found.']);
    }

    // One request: "data" is a single object { ... }.
    send_json(200, ['data' => $request]);
}

// The list: an "envelope" with the rows in "data" plus extra information next to them.
// "filters" shows which filters were actually used, so a client can see when a bad value was ignored.
send_json(200, [
    'data'    => $requests,
    'count'   => count($requests),
    'filters' => $filters,
]);
