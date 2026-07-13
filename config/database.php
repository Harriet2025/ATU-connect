<?php
// =========================================================
// DATABASE CONFIGURATION
// Copy this file's values from config/.env.php (not committed)
// =========================================================

return [
    'driver'   => 'mysql',
    'host'     => getenv('DB_HOST') ?: '127.0.0.1',
    'port'     => getenv('DB_PORT') ?: '3306',
    // named atu_connect_platform (not atu_connect) to avoid colliding with
    // an unrelated pre-existing "atu_connect" database on shared dev machines
    'database' => getenv('DB_NAME') ?: 'atu_connect_platform',
    'username' => getenv('DB_USER') ?: 'root',
    'password' => getenv('DB_PASS') ?: '',
    'charset'  => 'utf8mb4',
    'collation'=> 'utf8mb4_unicode_ci',
];
