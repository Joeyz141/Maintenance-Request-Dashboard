<?php
// src/db.php
// Opens a connection to the database using the settings in config.php.

function get_db_connection(): PDO
{
    $config = require __DIR__ . '/../config/config.php';

    $dsn = 'mysql:host=' . $config['db_host']
        . ';port=' . $config['db_port']
        . ';dbname=' . $config['db_name']
        . ';charset=utf8mb4';

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    // Create the PDO connection and return it
    return new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
}
