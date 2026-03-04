<?php
/*
 * File: server/process_feedback.php
 * Purpose: Receive feedback form data via POST, validate, and store in MySQL
 */

// ── Allow JSON responses ──
header('Content-Type: application/json');

// ── Only accept POST requests ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

// ── Include database connection ──
require_once __DIR__ . '/db_config.php';

// ── Read & sanitize input ──
$fullName = trim($_POST['fullName']  ?? '');
$email    = trim($_POST['email']     ?? '');
$rating   = trim($_POST['rating']    ?? '');
$carrier  = trim($_POST['preferredCarrier'] ?? '');
$comments = trim($_POST['comments']  ?? '');

// Services comes as an array of checkbox values
$servicesRaw = $_POST['services'] ?? [];
if (!is_array($servicesRaw)) {
    $servicesRaw = [$servicesRaw];
}
// Sanitize each value and join as comma-separated string
$services = implode(',', array_map('trim', array_filter($servicesRaw)));

// ── Server-side validation ──
$errors = [];

// 1. Full name — required, min 2 chars, letters/spaces only
if ($fullName === '' || mb_strlen($fullName) < 2) {
    $errors[] = 'Full name is required (minimum 2 characters).';
} elseif (!preg_match('/^[\p{L}\s]+$/u', $fullName)) {
    $errors[] = 'Full name may only contain letters and spaces.';
}

// 2. Email — required, valid format
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors[] = 'A valid email address is required.';
}

// 3. Rating — must be one of the allowed values
$allowedRatings = ['good', 'average', 'poor'];
if (!in_array($rating, $allowedRatings, true)) {
    $errors[] = 'Please select a valid rating (Good, Average, or Poor).';
}

// 4. Services — at least one must be selected
$allowedServices = ['tracking', 'scheduling', 'notifications', 'multicarrier'];
$serviceValues = explode(',', $services);
$serviceValues = array_filter($serviceValues);
if (count($serviceValues) === 0) {
    $errors[] = 'Please select at least one service.';
} else {
    foreach ($serviceValues as $sv) {
        if (!in_array($sv, $allowedServices, true)) {
            $errors[] = 'Invalid service selection: ' . htmlspecialchars($sv);
        }
    }
}

// 5. Preferred carrier — required
$allowedCarriers = ['aramex', 'dhl', 'fedex', 'smsa', 'other'];
if (!in_array($carrier, $allowedCarriers, true)) {
    $errors[] = 'Please select a valid preferred carrier.';
}

// 6. Comments — optional but max 500 chars
if (mb_strlen($comments) > 500) {
    $errors[] = 'Comments must not exceed 500 characters.';
}

// ── Return validation errors ──
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => implode(' ', $errors), 'errors' => $errors]);
    exit;
}

// ── Insert into database using prepared statement ──
$stmt = $conn->prepare(
    'INSERT INTO feedback (full_name, email, rating, services, carrier, comments)
     VALUES (?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error]);
    exit;
}

$stmt->bind_param('ssssss', $fullName, $email, $rating, $services, $carrier, $comments);

if ($stmt->execute()) {
    $newId = $stmt->insert_id;
    echo json_encode([
        'success' => true,
        'message' => 'Thank you! Your feedback has been saved.',
        'id'      => $newId
    ]);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save feedback: ' . $stmt->error]);
}

$stmt->close();
$conn->close();
?>
