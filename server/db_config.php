<?php
/*
 * File: server/db_config.php
 * Purpose: MySQL database connection configuration
 */

// ── Database credentials ──
define('DB_HOST', 'localhost');
define('DB_NAME', 'shipsmart_db');
define('DB_USER', 'root');  // MariaDB user
define('DB_PASS', '');      // no password

// ── Create connection using MySQLi (robust against socket vs TCP)
// On macOS/PHP setups 'localhost' may attempt a UNIX socket which is missing;
// if that fails, retry using TCP via 127.0.0.1 which often resolves the
// "No such file or directory" mysqli_sql_exception seen when no socket exists.
$conn = null;
$db_conn_error = null;

// Helper to attempt a connection and return [$conn, $error]
function _try_mysqli_connect(string $host): array {
    try {
        $c = new mysqli($host, DB_USER, DB_PASS, DB_NAME);
        if ($c->connect_error) {
            return [null, $c->connect_error];
        }
        return [$c, null];
    } catch (mysqli_sql_exception $e) {
        return [null, $e->getMessage()];
    }
}

// First attempt with configured host
[$conn, $db_conn_error] = _try_mysqli_connect(DB_HOST);

// If first attempt failed and host was 'localhost', retry via TCP
if ($conn === null && DB_HOST === 'localhost') {
    [$conn, $db_conn_error] = _try_mysqli_connect('127.0.0.1');
}

// If we have a good connection, configure it and ensure schema
if ($conn !== null) {
    $conn->set_charset('utf8mb4');
    require_once __DIR__ . '/includes/db_install.php';
    shipsmart_ensure_schema($conn);
}
?>
