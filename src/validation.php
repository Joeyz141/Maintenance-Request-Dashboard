<?php
// src/validation.php
// Validation rules for a new maintenance request

// The allowed values for status and priority, in ONE place (Ticket 10).
// Keys   = the ENUM values in database/schema.sql (what gets saved and sent in URLs).
// Values = the text people see in the dropdowns.
// The validation below, and the dropdowns in index.php, create.php and edit.php, all read these lists,
// so adding a value means changing schema.sql + this list (tests/php/ValidationTest.php checks they match).
const STATUS_OPTIONS = [
    'open'        => 'Open',
    'in_progress' => 'In progress',
    'completed'   => 'Completed',
];

const PRIORITY_OPTIONS = [
    'low'    => 'Low',
    'medium' => 'Medium',
    'high'   => 'High',
];

// Description limit. The column is TEXT (up to 65,535 bytes); without a limit, a huge description
// would make MariaDB's strict mode refuse the INSERT and the user would get a 500 instead of a clear message.
const DESCRIPTION_MAX_LENGTH = 2000;

// Reads ONE text field from a form ($_POST) and trims it.
// A normal form always sends text, but a crafted request can send an ARRAY (title[]=x),
// and trim() on an array crashes PHP with a fatal error. Anything that isn't text becomes ''.
function form_text(array $source, string $key): string
{
    $value = $source[$key] ?? '';

    return is_string($value) ? trim($value) : '';
}

// Checks the submitted input and returns an array of error messages, keyed by field name.
// An EMPTY array means everything is valid.
// $vehicleIds = the ids of the vehicles that really exist, e.g. ['2', '1', '3'].
function validate_request(array $input, array $vehicleIds): array
{
    $errors = [];

    // Title: required, at most 150 characters (matches VARCHAR(150) in schema.sql)
    if ($input['title'] === '') {
        $errors['title'] = 'Title is required.';
    } elseif (mb_strlen($input['title']) > 150) {
        $errors['title'] = 'Title must be 150 characters or fewer.';
    }

    // Vehicle: must be one of the real vehicle ids.
    // in_array() answers "is it in the list?" (true/false); ! flips it to "is it NOT in the list?"
    // This also catches an empty value and fake ids like 999.
    if (!in_array($input['vehicle_id'], $vehicleIds, true)) {
        $errors['vehicle_id'] = 'Please choose a vehicle.';
    }

    // Description: optional, but at most DESCRIPTION_MAX_LENGTH characters.
    if (mb_strlen($input['description']) > DESCRIPTION_MAX_LENGTH) {
        $errors['description'] = 'Description must be ' . DESCRIPTION_MAX_LENGTH . ' characters or fewer.';
    }

    // Priority: must exactly match one of the ENUM values in schema.sql.
    // array_keys(PRIORITY_OPTIONS) = ['low', 'medium', 'high']; true = strict match, so 'HIGH' or 'urgent' are rejected.
    if (!in_array($input['priority'], array_keys(PRIORITY_OPTIONS), true)) {
        $errors['priority'] = 'Please choose a valid priority.';
    }

    return $errors;
}

// Checks the edit form's status and priority; an empty array means everything is valid.
function validate_status_update(array $input): array
{
    $errors = [];

    // Status: must exactly match one of the ENUM values in schema.sql.
    if (!in_array($input['status'], array_keys(STATUS_OPTIONS), true)) {
        $errors['status'] = 'Please choose a valid status.';
    }

    // Priority: must exactly match one of the ENUM values in schema.sql.
    if (!in_array($input['priority'], array_keys(PRIORITY_OPTIONS), true)) {
        $errors['priority'] = 'Please choose a valid priority.';
    }

    return $errors;
}

// turns the raw URL values ($_GET) into safe filters for the request list.
// Always returns all three keys; '' (empty) means "no filter" (show everything).
// Forgiving on purpose: a bad value is ignored instead of showing an error, because a search can't damage data.
function clean_request_filters(array $get): array
{
    // Start with "no filters at all"; each block below may fill one in.
    $filters = ['q' => '', 'status' => '', 'priority' => ''];

    // Search text: accept it only if the key exists AND is a string (?q[]=x would make it an array),
    // then remove spaces at both ends and keep at most 100 characters.
    if (isset($get['q']) && is_string($get['q'])) {
        $filters['q'] = mb_substr(trim($get['q']), 0, 100);
    }

    // Status: same exists + string check, then keep it ONLY if it exactly matches an ENUM value in schema.sql.
    // No else needed: a bad value (e.g. 'urgent' or 'OPEN') just leaves $filters['status'] as ''.
    if (isset($get['status']) && is_string($get['status'])
        && in_array($get['status'], array_keys(STATUS_OPTIONS), true)) {
        $filters['status'] = $get['status'];
    }

    // Priority: same rule with the priority ENUM values.
    if (isset($get['priority']) && is_string($get['priority'])
        && in_array($get['priority'], array_keys(PRIORITY_OPTIONS), true)) {
        $filters['priority'] = $get['priority'];
    }

    return $filters;
}
