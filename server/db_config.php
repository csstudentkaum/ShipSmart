<?php
/*
 * File: server/db_config.php
 * Purpose: MySQL database connection configuration
 */

// ── Database credentials ──
define('DB_HOST', 'localhost');
define('DB_NAME', 'shipsmart_db');
define('DB_USER', 'cpsc403');  // MariaDB user
define('DB_PASS', '');              // no password

// ── Create connection using MySQLi ──
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// ── Check connection ──
if ($conn->connect_error) {
    // Return a JSON error so the front-end can handle it
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]);
    exit;
}

// Set charset to utf8mb4 for full Unicode support
$conn->set_charset('utf8mb4');
?>
