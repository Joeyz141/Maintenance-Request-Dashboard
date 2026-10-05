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
