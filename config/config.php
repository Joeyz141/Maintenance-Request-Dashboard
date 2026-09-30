<?php
// config/config.php
// Database settings. Values come from environment variables so that
// no secrets are stored in the code. Defaults are for local XAMPP.

return [
    'db_host' => getenv('DB_HOST') ?: 'localhost',
    'db_port' => getenv('DB_PORT') ?: '3306',
    'db_name' => getenv('DB_NAME') ?: 'maintenance_dashboard',
    'db_user' => getenv('DB_USER') ?: 'maintenance_app',
    'db_pass' => getenv('DB_PASS') ?: '',
];
