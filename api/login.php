<?php
/*
 * File: api/login.php
 * Purpose: Verify credentials, start session, redirect by role.
 */

header('Content-Type: application/json');

// Start session so session_regenerate_id() and $_SESSION work without warnings
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// FIX #1: Corrected paths — api/ is one level below root, server/ is a sibling of api/
$dbPath   = __DIR__ . '/../server/includes/db.php';
$authPath = __DIR__ . '/../server/includes/auth.php';

if (file_exists($dbPath)) {
    require_once $dbPath;
} else {
    $conn = null;
}
if (file_exists($authPath)) {
    require_once $authPath;
}

$email    = trim($_POST['email']    ?? '');
$password =       $_POST['password'] ?? '';

$errors = [];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}
if ($password === '') {
    $errors['password'] = 'Password is required.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// ── TEST ACCOUNTS (no DB needed) ─────────────────────────────
// FIX #2: Hashes replaced — both accounts use password: "password"
// (same hash used in schema.sql and db_install.php seeder)
// Remove this block once you no longer need a DB-less fallback.
$testAccounts = [
    'admin@shipsmart.com' => [
        'id' => 1, 'full_name' => 'ShipSmart Admin', 'role' => 'admin',
        'hash' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    ],
    'user@shipsmart.com' => [
        'id' => 2, 'full_name' => 'Demo User', 'role' => 'user',
        'hash' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi',
    ],
];

if (isset($testAccounts[strtolower($email)])) {
    $t = $testAccounts[strtolower($email)];
    if (!password_verify($password, $t['hash'])) {
        http_response_code(401);
        echo json_encode(['success' => false, 'errors' => [
            'general' => 'Incorrect email or password.'
        ]]);
        exit;
    }
    session_regenerate_id(true);
    $_SESSION['user_id']   = $t['id'];
    $_SESSION['full_name'] = $t['full_name'];
    $_SESSION['role']      = $t['role'];
    $redirect = $t['role'] === 'admin' ? 'admin/dashboard.php' : 'index.html';
    echo json_encode(['success' => true, 'role' => $t['role'], 'redirect' => $redirect]);
    exit;
}
// ── END TEST ACCOUNTS ─────────────────────────────────────────

// No DB connection available and email not in test accounts
if (!$conn) {
    http_response_code(503);
    echo json_encode(['success' => false, 'errors' => [
        'general' => 'Database unavailable. Please try again later.'
    ]]);
    exit;
}

// Fetch user from DB
$stmt = $conn->prepare(
    'SELECT id, full_name, password_hash, role FROM users WHERE email = ?'
);
$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user   = $result->fetch_assoc();
$stmt->close();
$conn->close();

// Verify password — generic error prevents user enumeration
if (!$user || !password_verify($password, $user['password_hash'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'errors' => [
        'general' => 'Incorrect email or password.'
    ]]);
    exit;
}

// Regenerate session ID to prevent session fixation
session_regenerate_id(true);

$_SESSION['user_id']   = $user['id'];
$_SESSION['full_name'] = $user['full_name'];
$_SESSION['role']      = $user['role'];

// Redirect based on role
$redirect = $user['role'] === 'admin'
    ? 'admin/dashboard.php'
    : 'index.html';

echo json_encode([
    'success'  => true,
    'role'     => $user['role'],
    'redirect' => $redirect,
]);
