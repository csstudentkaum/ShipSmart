<?php
/*
 * File: api/register.php
 * Purpose: Handle user registration — validate, hash password, insert to DB.
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

require_once __DIR__ . '/../server/db_config.php';

$fullName = trim($_POST['full_name'] ?? '');
$email    = trim($_POST['email']     ?? '');
$password =       $_POST['password'] ?? '';
$confirm  =       $_POST['confirm']  ?? '';

$errors = [];

if ($fullName === '' || mb_strlen($fullName) < 2) {
    $errors['full_name'] = 'Full name must be at least 2 characters.';
} elseif (!preg_match('/^[\p{L}\s]+$/u', $fullName)) {
    $errors['full_name'] = 'Full name may only contain letters and spaces.';
}

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Please enter a valid email address.';
}

if (strlen($password) < 8) {
    $errors['password'] = 'Password must be at least 8 characters.';
} elseif (!preg_match('/[A-Z]/', $password) || !preg_match('/[0-9]/', $password)) {
    $errors['password'] = 'Password must contain at least one uppercase letter and one number.';
}

if ($password !== $confirm) {
    $errors['confirm'] = 'Passwords do not match.';
}

if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// Check email not already taken
$check = $conn->prepare('SELECT id FROM users WHERE email = ?');
$check->bind_param('s', $email);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => [
        'email' => 'An account with this email already exists.'
    ]]);
    $check->close();
    $conn->close();
    exit;
}
$check->close();

// Hash with bcrypt — never store plain text
$hash = password_hash($password, PASSWORD_BCRYPT);
$role = 'user'; // new registrations are always regular users

$stmt = $conn->prepare(
    'INSERT INTO users (full_name, email, password_hash, role) VALUES (?, ?, ?, ?)'
);
$stmt->bind_param('ssss', $fullName, $email, $hash, $role);

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
    $stmt->close();
    $conn->close();
    exit;
}

$stmt->close();
$conn->close();

echo json_encode(['success' => true, 'message' => 'Account created! You can now log in.']);