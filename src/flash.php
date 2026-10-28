<?php
// src/flash.php
// Flash message = a one-time note kept in the session (on the server), not in the URL (Ticket 13).
//   1. create.php / edit.php call flash_set('Request #8 updated.') right before the redirect.
//   2. index.php calls flash_take(): it reads the note AND deletes it, so a refresh can't show it again.

// require_once = load csrf.php only if it isn't loaded yet (create.php and edit.php already load it).
// We need it for start_session_once().
require_once __DIR__ . '/csrf.php';

// Saves a one-time message in the session for the next page to show.
// Call it BEFORE header('Location: ...'): the session must be open to write to it.
function flash_set(string $message): void
{
    start_session_once();
    $_SESSION['flash'] = $message;
}

// Returns the saved message and deletes it, or null if there isn't one.
// ?string = "a string OR null". Call it BEFORE any HTML is printed (starting a session sends a cookie header).
function flash_take(): ?string
{
    start_session_once();

    // ?? null = "use null if there is no 'flash' key" (a normal visit with no message).
    $message = $_SESSION['flash'] ?? null;

    // unset() removes the key from the session: this is what makes the message "one time".
    unset($_SESSION['flash']);

    return $message;
}
