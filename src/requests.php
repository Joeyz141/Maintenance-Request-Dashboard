<?php
// src/requests.php
// Data access for maintenance requests: SQL only, no HTML in this file.

// TICKET 6: returns the requests (newest first), optionally filtered.
// $filters comes from clean_request_filters(): ['q' => ..., 'status' => ..., 'priority' => ...], '' = no filter.
// The default [] means "no filters", so old calls like get_all_requests($pdo) still work (Ticket 7's API will reuse this).
function get_all_requests(PDO $pdo, array $filters = []): array
{
    // Part 1: the fixed start of the query (no WHERE and no ORDER BY yet; they are added below).
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
    ";

    // $where  = the conditions (SQL text WE wrote, with placeholders)
    // $params = the values for those placeholders (the user's input, sent separately)
    $where = [];
    $params = [];

    // Search: title OR VIN contains the text.
    if (($filters['q'] ?? '') !== '') {
        $where[] = '(r.title LIKE :q_title OR v.vin LIKE :q_vin)';
        $params['q_title'] = '%' . $filters['q'] . '%';
        $params['q_vin'] = '%' . $filters['q'] . '%';
    }

    // Status: exact match (already checked against the ENUM list by clean_request_filters()).
    if (($filters['status'] ?? '') !== '') {
        $where[] = 'r.status = :status';
        $params['status'] = $filters['status'];
    }

    // Priority: exact match, already checked by clean_request_filters()).
    if (($filters['priority'] ?? '') !== '') {
        $where[] = 'r.priority = :priority';
        $params['priority'] = $filters['priority'];
    }

    // Part 2: add WHERE only if at least one filter is active.
    // implode() glues the conditions with ' AND ', e.g. "(r.title LIKE ...) AND r.status = :status".
    //allows us to use multiple filters 
    if (count($where) > 0) {
        $sql .= ' WHERE ' . implode(' AND ', $where);
    }

    // Part 3: ORDER BY always comes last (after WHERE)
    $sql .= ' ORDER BY r.created_at DESC, r.id DESC';

    // prepared statement instead of query()
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

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

// Returns one request (with its vehicle) by id, or false if no request has that id.
// Used by edit.php: false means "not found", so the page can answer with a 404.
function get_request_by_id(PDO $pdo, int $id): array|false
{
    $sql = "
        SELECT
            r.id,
            r.title,
            r.priority,
            r.status,
            r.updated_at,
            v.vin,
            v.make,
            v.model,
            v.model_year
        FROM maintenance_requests AS r
        JOIN vehicles AS v
            ON r.vehicle_id = v.id
        WHERE r.id = :id
    ";

    // Prepared statement: the id travels separately from the SQL, like in create_request().
    $stmt = $pdo->prepare($sql);

    // Only one value to send: $id is already a whole number (the int in the signature guarantees it), so no cast or cleanup is needed.
    $stmt->execute([
        'id' => $id,
    ]);

    // fetch() returns ONE row as an array, or false when no row matched.
    // No ORDER BY needed: r.id is the primary key, so there is at most one row.
    return $stmt->fetch();
}

// Changes the status and priority of ONE request (the one with this id); returns nothing.
function update_request(PDO $pdo, int $id, array $data): void
{
    // WHERE id = :id limits the change to this one request; without it, EVERY row would change.
    $sql = "
        UPDATE maintenance_requests
        SET status = :status, priority = :priority
        WHERE id = :id
    ";

    // Prepared statement: the values travel separately from the SQL, so input can't become SQL.
    $stmt = $pdo->prepare($sql);

    // One value per placeholder; status and priority were already validated by validate_status_update().
    $stmt->execute([
        'status'   => $data['status'],
        'priority' => $data['priority'],
        'id'       => $id,
    ]);

    // No return value (void). If nothing actually changed, MariaDB reports 0 rows changed, and that is fine.
}
