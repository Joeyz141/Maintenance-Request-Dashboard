<?php
// src/helpers.php
// Small reusable helpers for the HTML pages.

// e() = "escape": makes any value safe to print inside HTML.
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}