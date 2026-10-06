<?php
// src/csrf.php
// CSRF protection: proves a POST came from OUR form, not from another website.
//   1. csrf_token() makes a secret, keeps it in the session (on the server), and returns it for a hidden form field.
//   2. csrf_check() compares the secret the form sent back with the session's copy, and stops with 403 if they differ.

// Starts the session if it isn't running yet (session = the server remembering this browser between requests).
function start_session_once(): void
{
    // session_status() tells us if the session is already active, so we never start it twice.
    if (session_status() !== PHP_SESSION_ACTIVE) {
        // Gives the browser a cookie with a random session ID (the "coat-check ticket") and opens $_SESSION.
        session_start();
    }
}

// Returns this browser's secret token, creating it the first time.
// Call it BEFORE any HTML is printed: starting a session sends a cookie header, and headers must come first.
function csrf_token(): string
{
    start_session_once();

    // Only make a new token if this session doesn't have one yet (one token per session).
    if (empty($_SESSION['csrf_token'])) {
        // random_bytes(32) = 32 unpredictable bytes; bin2hex() turns them into 64 readable characters.
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Checks the token sent with a POST; if it's missing or wrong, answers 403 Forbidden and stops the script.
function csrf_check(): void
{
    start_session_once();

    // The secret we gave out earlier (stored on the server), and the one the form sent back.
    $expected = $_SESSION['csrf_token'] ?? '';
    $sent = $_POST['csrf_token'] ?? '';

    // is_string(): an attacker could send an array instead of text, so reject anything that isn't text.
    // hash_equals(): compares two secrets safely (same speed whether they match early or late).
    if ($expected === '' || !is_string($sent) || !hash_equals($expected, $sent)) {
        // 403 Forbidden = "I understood the request, but I refuse it."
        http_response_code(403);
        exit('Sorry, this form has expired or did not come from this site. Please go back, reload the page and try again.');
    }
}
