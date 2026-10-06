<?php
// src/validation.php
// Validation rules for a new maintenance request

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

    // Priority: must exactly match one of the ENUM values in schema.sql.
    // true = strict match, so 'HIGH' or 'urgent' are rejected.
    if (!in_array($input['priority'], ['low', 'medium', 'high'], true)) {
        $errors['priority'] = 'Please choose a valid priority.';
    }

    return $errors;
}

// Checks the edit form's status and priority; an empty array means everything is valid.
function validate_status_update(array $input): array
{
    $errors = [];

    // Status: must exactly match one of the ENUM values in schema.sql.
    if (!in_array($input['status'], ['open', 'in_progress', 'completed'], true)) {
        $errors['status'] = 'Please choose a valid status.';
    }

    // Priority: must exactly match one of the ENUM values in schema.sql.
    if (!in_array($input['priority'], ['low', 'medium', 'high'], true)) {
        $errors['priority'] = 'Please choose a valid priority.';
    }

    return $errors;
}
