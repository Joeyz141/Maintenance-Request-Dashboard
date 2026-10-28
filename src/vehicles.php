<?php
// src/vehicles.php
// Data access for vehicles
// Returns every vehicle as an array of rows, used to fill the form's dropdown.
// "PDO $pdo" = this function needs an open database connection passed in 
// ": array"  = this function promises to return an array.
function get_all_vehicles(PDO $pdo): array
{
    $sql = "
        SELECT
            v.id,
            v.model_year,
            v.make,
            v.model,
            v.vin
        FROM vehicles AS v
        ORDER BY v.model_year DESC, v.id DESC
    ";

    // query() runs the SQL and returns a "statement" object holding the results.
    $stmt = $pdo->query($sql);

    // fetchAll() turns the results into a PHP array, one item per row,
    // e.g. [ ['id' => 2, 'model_year' => 2023, 'make' => 'Toyota', ...], ... ]
    return $stmt->fetchAll();
}

// Ticket 14: one row per vehicle TYPE (make + model) for the Vehicles page, with request counts.
// e.g. ['make' => 'Ford', 'model' => 'F-150', 'vehicles' => 1, 'open_requests' => 0, 'total_requests' => 2]
// Read-only: vehicles are master data owned by the fleet system, so this app never adds or edits them.
function get_vehicle_types_with_counts(PDO $pdo): array
{
    // LEFT JOIN = keep EVERY vehicle, even one with no requests (its r.* columns are NULL).
    //   A plain JOIN would drop it, and a vehicle type with no problems would vanish from the page.
    // GROUP BY make, model = squash all rows of the same type into ONE result row.
    // COUNT(DISTINCT v.id) = how many different vehicles (VINs) of this type.
    //   DISTINCT is needed because the join repeats a vehicle once per request.
    // COUNT(r.id) = how many requests. COUNT skips NULLs, so "no requests" counts as 0 (COUNT(*) would say 1).
    // COUNT(CASE ... END) = count only the open ones: CASE gives r.id for an open request and NULL otherwise.
    $sql = "
        SELECT
            v.make,
            v.model,
            COUNT(DISTINCT v.id) AS vehicles,
            COUNT(CASE WHEN r.status = 'open' THEN r.id END) AS open_requests,
            COUNT(r.id) AS total_requests
        FROM vehicles AS v
        LEFT JOIN maintenance_requests AS r
            ON r.vehicle_id = v.id
        GROUP BY v.make, v.model
        ORDER BY open_requests DESC, v.make, v.model
    ";

    // No user input in this query, so query() is fine (same as get_status_counts()).
    $stmt = $pdo->query($sql);

    return $stmt->fetchAll();
}
