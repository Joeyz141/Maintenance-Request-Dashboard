<?php
// src/requests.php
// Data access for maintenance requests: SQL only, no HTML in this file.

function get_all_requests(PDO $pdo): array
{
    $sql = "
        SELECT
            r.id,
            r.title,
            r.priority,
            r.status,
            r.created_at,
            v.vin,
            v.make,
            v.model,
            v.model_year
        FROM maintenance_requests AS r
        JOIN vehicles AS v
            ON r.vehicle_id = v.id
        ORDER BY r.created_at DESC, r.id DESC 
    ";
    $stmt = $pdo->query($sql);

    return $stmt->fetchAll();
}

// Saves a new maintenance request and returns its new id.
// Uses a prepared statement: the SQL and the user's values travel separately, so input can't become SQL.
function create_request(PDO $pdo, array $data): int
{
    $sql = "
        INSERT INTO maintenance_requests (vehicle_id, title, description, priority)
        VALUES (:vehicle_id, :title, :description, :priority)
    ";

    // Step 1: send the SQL with blank placeholders; MariaDB plans the command before seeing any user text.
    $stmt = $pdo->prepare($sql);

    // Step 2: send the values; each one fills its placeholder as plain data.
    $stmt->execute([
        'vehicle_id'  => (int) $data['vehicle_id'],
        'title'       => $data['title'],
        'description' => $data['description'] === '' ? null : $data['description'],
        'priority'    => $data['priority'],
    ]);

    // The id MariaDB just gave the new row (AUTO_INCREMENT).
    return (int) $pdo->lastInsertId();
}