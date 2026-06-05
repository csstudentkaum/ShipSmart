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

// ── Include database connection and email helper ──
require_once __DIR__ . '/db_config.php';
require_once __DIR__ . '/includes/mailer.php';
require_once __DIR__ . '/includes/db_log.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$feedbackUserId = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

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
$hasUserCol = false;
$colCheck   = $conn->query("SHOW COLUMNS FROM feedback LIKE 'user_id'");
if ($colCheck && $colCheck->num_rows > 0) {
    $hasUserCol = true;
}

if ($hasUserCol) {
    $stmt = $conn->prepare(
        'INSERT INTO feedback (user_id, full_name, email, rating, services, carrier, comments)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Database prepare failed: ' . $conn->error]);
        exit;
    }
    $stmt->bind_param('issssss', $feedbackUserId, $fullName, $email, $rating, $services, $carrier, $comments);
} else {
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
}

if (!$stmt->execute()) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save feedback: ' . $stmt->error]);
    $stmt->close();
    $conn->close();
    exit;
}

$newId = $stmt->insert_id;
$stmt->close();

// ── Send confirmation email to the user ──
$ratingLabel    = ucfirst($rating);   // e.g. "Good", "Average", "Poor"
$carrierLabel   = strtoupper($carrier); // e.g. "ARAMEX", "DHL"
$servicesLabel  = implode(', ', array_map('ucfirst', explode(',', $services)));
$safeComments   = $comments !== '' ? nl2br(htmlspecialchars($comments, ENT_QUOTES, 'UTF-8'))
                                   : '<em style="color:#888;">No comments provided.</em>';
$safeName       = htmlspecialchars($fullName, ENT_QUOTES, 'UTF-8');

$emailBody = <<<HTML
<p style="margin:0 0 16px;color:#444;line-height:1.6;">
  Hi <strong>{$safeName}</strong>,<br />
  Thank you for taking the time to share your feedback with us.
  Here is a summary of what you submitted:
</p>

<table width="100%" cellpadding="0" cellspacing="0"
       style="border-collapse:collapse;font-size:14px;margin-bottom:20px;">
  <tr style="background:#f8f4fb;">
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;width:40%;">Rating</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">{$ratingLabel}</td>
  </tr>
  <tr>
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;">Services Used</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">{$servicesLabel}</td>
  </tr>
  <tr style="background:#f8f4fb;">
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;">Preferred Carrier</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">{$carrierLabel}</td>
  </tr>
  <tr>
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;">Comments</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">{$safeComments}</td>
  </tr>
</table>

<p style="margin:0 0 10px;color:#444;line-height:1.6;">
  We review all feedback carefully to improve ShipSmart.
  If you have any further questions, feel free to contact us.
</p>
<p style="margin:0;color:#444;">— The ShipSmart Team</p>
HTML;

$emailHtml  = buildEmailTemplate("Thank you for your feedback!", $emailBody);
$emailSent  = sendMail($email, $fullName, "Thank you for your feedback, {$fullName}!", $emailHtml);

logEmail(
    $conn,
    $email,
    "Thank you for your feedback, {$fullName}!",
    'feedback_confirmation',
    $emailSent ? 'sent' : 'failed',
    $feedbackUserId,
    'feedback',
    $newId
);

$conn->close();

// ── Return response (include emailSent status so frontend can show a note) ──
echo json_encode([
    'success'    => true,
    'message'    => 'Thank you! Your feedback has been saved.',
    'id'         => $newId,
    'emailSent'  => $emailSent,
    'emailError' => $emailSent ? null : 'Feedback saved, but confirmation email could not be sent.'
]);
?>
