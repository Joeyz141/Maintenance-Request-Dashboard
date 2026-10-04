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