<?php
// src/db.php
// Opens a connection to the database using the settings in config.php.

function get_db_connection(): PDO
{
    // 1. Look up the contact details
    $config = require __DIR__ . '/../config/config.php';

    // 2. Write the phone number in the format PDO understands
    $dsn = 'mysql:host=' . $config['db_host']
        . ';port=' . $config['db_port']
        . ';dbname=' . $config['db_name']
        . ';charset=utf8mb4';

    // 3. Set the ground rules for the call
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // if something goes wrong, throw an error loudly
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // give rows back by column name
        PDO::ATTR_EMULATE_PREPARES   => false,
        // use real prepared statements
    ];

    // 4. Dial, log in, and hand the open line back
    return new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
    //             where?        who?                proof?      rules
}
