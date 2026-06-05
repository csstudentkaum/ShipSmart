<?php
/*
 * File: server/db_config.php
 * Purpose: MySQL database connection configuration
 */

// ── Database credentials ──
define('DB_HOST', 'localhost');
define('DB_NAME', 'shipsmart_db');
define('DB_USER', 'root');  // MariaDB user
define('DB_PASS', '');              // no password

// ── Create connection using MySQLi (robust against socket vs TCP)
// Try first using configured host. On macOS/php setups 'localhost' may
// attempt a UNIX socket which is missing; if that fails, retry using
// TCP via 127.0.0.1 which often resolves the "No such file or directory"
// mysqli_sql_exception seen when no socket exists. We catch exceptions
// so including scripts can decide how to behave rather than letting PHP
// throw an uncaught fatal exception.
$db_conn_error = null;
try {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    // If mysqli configured to not throw, check connect_error too
    if ($conn->connect_error && DB_HOST === 'localhost') {
        // retry with TCP
        $conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
    }
} catch (mysqli_sql_exception $e) {
    // First attempt failed with exception. If host was 'localhost'
    // try 127.0.0.1 then capture final error.
    if (DB_HOST === 'localhost') {
        try {
            $conn = new mysqli('127.0.0.1', DB_USER, DB_PASS, DB_NAME);
        } catch (mysqli_sql_exception $e2) {
            $conn = null;
            $db_conn_error = $e2->getMessage();
        }
    } else {
        $conn = null;
        $db_conn_error = $e->getMessage();
    }
}

// If mysqli didn't throw but reported an error via connect_error, capture it
if (isset($conn) && $conn instanceof mysqli && $conn->connect_error) {
    $db_conn_error = $conn->connect_error;
}

// If we have a connection, set charset; otherwise leave $conn null and let
// the caller decide how to handle the missing DB.
if (isset($conn) && $conn instanceof mysqli) {
    $conn->set_charset('utf8mb4');
} else {
    // $db_conn_error may contain the last error message.
    // Note: we intentionally do not exit here so calling scripts can
    // redirect or return a user-friendly response instead of triggering
    // an uncaught fatal error.
}
?>
