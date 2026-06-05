<?php
/*
 * File: api/upload-document.php
 * Purpose: Handle shipment document uploads — validate type/size, rename,
 *          save to uploads/documents/, and store metadata in the DB.
 *          Returns JSON so the frontend can update without a page reload.
 */

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Only POST requests are allowed.']);
    exit;
}

require_once __DIR__ . '/../server/db_config.php';
require_once __DIR__ . '/../server/includes/mailer.php';
require_once __DIR__ . '/../server/includes/db_log.php';

define('UPLOAD_DIR', __DIR__ . '/../uploads/documents/');

$missingTables = shipsmart_required_tables_ok($conn);
if (!empty($missingTables)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database setup incomplete. Missing table(s): ' . implode(', ', $missingTables)
            . '. Open /server/setup.php once or import server/schema.sql in phpMyAdmin.',
    ]);
    exit;
}

if (!shipsmart_ensure_upload_dir(UPLOAD_DIR)) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Upload folder is missing or not writable: uploads/documents/',
    ]);
    exit;
}

// ── Limits & whitelists ──
define('MAX_SIZE_BYTES', 2 * 1024 * 1024); // 2 MB

$allowedExtensions = ['jpg', 'jpeg', 'png', 'pdf'];
$allowedMimeTypes  = ['image/jpeg', 'image/png', 'application/pdf'];
$allowedCarriers   = ['aramex', 'dhl', 'fedex', 'smsa'];
$allowedDocTypes   = ['invoice', 'receipt', 'proof_of_delivery', 'other'];

// ── Collect & validate form fields ──
$errors = [];

$trackingNumber = trim($_POST['tracking_number']  ?? '');
$carrier        = trim($_POST['carrier']          ?? '');
$docType        = trim($_POST['doc_type']         ?? '');
$uploaderEmail  = trim($_POST['uploader_email']   ?? '');

if ($trackingNumber === '') {
    $errors['tracking_number'] = 'Tracking number is required.';
} elseif (!preg_match('/^[A-Za-z0-9\-]{5,50}$/', $trackingNumber)) {
    $errors['tracking_number'] = 'Tracking number must be 5–50 alphanumeric characters.';
}

if ($carrier === '' || !in_array($carrier, $allowedCarriers, true)) {
    $errors['carrier'] = 'Please select a valid carrier.';
}

if ($docType === '' || !in_array($docType, $allowedDocTypes, true)) {
    $errors['doc_type'] = 'Please select a document type.';
}

// Email — optional, but must be valid format if provided
if ($uploaderEmail !== '' && !filter_var($uploaderEmail, FILTER_VALIDATE_EMAIL)) {
    $errors['uploader_email'] = 'Please enter a valid email address.';
}

// ── Validate uploaded file ──
if (!isset($_FILES['document']) || $_FILES['document']['error'] === UPLOAD_ERR_NO_FILE) {
    $errors['document'] = 'Please select a file to upload.';
} else {
    $file = $_FILES['document'];

    // PHP upload error codes
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors['document'] = 'File upload failed (error code ' . $file['error'] . '). Please try again.';
    } else {
        // 1 — Size check
        if ($file['size'] > MAX_SIZE_BYTES) {
            $errors['document'] = 'File exceeds the 2 MB size limit.';
        }

        // 2 — Extension check (against the original filename)
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, $allowedExtensions, true)) {
            $errors['document'] = 'Only JPG, PNG, and PDF files are allowed.';
        }

        // 3 — Real MIME type check (reads file bytes, not user-supplied header)
        if (empty($errors['document'])) {
            $finfo    = new finfo(FILEINFO_MIME_TYPE);
            $mimeType = $finfo->file($file['tmp_name']);

            if (!in_array($mimeType, $allowedMimeTypes, true)) {
                $errors['document'] = 'File content does not match an allowed type (JPG, PNG, PDF).';
            }
        }
    }
}

// Return all validation errors before touching the filesystem
if (!empty($errors)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'errors' => $errors]);
    exit;
}

// ── Rename file — unique name prevents collisions and hides original extension ──
// Format: doc_<uniqid>_<timestamp>.<ext>
$safeExt     = $ext;                                             // already validated above
$savedName   = 'doc_' . uniqid('', true) . '_' . time() . '.' . $safeExt;
$destination = UPLOAD_DIR . $savedName;

if (!move_uploaded_file($file['tmp_name'], $destination)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Could not save the file. Please try again.']);
    exit;
}

// Logged-in uploader (set when login/session is implemented)
$userId = null;
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!empty($_SESSION['user_id'])) {
    $userId = (int) $_SESSION['user_id'];
}

$shipmentId = findShipmentId($conn, $trackingNumber, $carrier);

// ── Store metadata in the database ──
$stmt = $conn->prepare(
    'INSERT INTO shipment_documents
       (user_id, shipment_id, uploader_email, tracking_number, carrier, doc_type, original_name, saved_name, file_size, mime_type)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
);

if (!$stmt) {
    unlink($destination);
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $conn->error,
    ]);
    exit;
}

$originalName = $file['name'];
$fileSize     = $file['size'];

$uploaderEmailDb = $uploaderEmail !== '' ? $uploaderEmail : null;

$stmt->bind_param('iissssssis',
    $userId,
    $shipmentId,
    $uploaderEmailDb,
    $trackingNumber,
    $carrier,
    $docType,
    $originalName,
    $savedName,
    $fileSize,
    $mimeType
);

if (!$stmt->execute()) {
    unlink($destination);
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to save document record.']);
    exit;
}

$docId = $conn->insert_id;
$stmt->close();

// ── Send upload confirmation email if address was provided ──
$emailSent  = false;
$emailError = null;

if ($uploaderEmail !== '') {
    $safeTracking  = htmlspecialchars($trackingNumber, ENT_QUOTES, 'UTF-8');
    $safeCarrier   = htmlspecialchars(strtoupper($carrier), ENT_QUOTES, 'UTF-8');
    $safeDocType   = htmlspecialchars(ucwords(str_replace('_', ' ', $docType)), ENT_QUOTES, 'UTF-8');
    $safeFileName  = htmlspecialchars($originalName, ENT_QUOTES, 'UTF-8');
    $fileSizeKb    = round($fileSize / 1024, 1);
    $uploadedAt    = date('d M Y, H:i');

    $emailBody = <<<HTML
<p style="margin:0 0 16px;color:#444;line-height:1.6;">
  Your shipment document has been uploaded and saved successfully.
  Here are the details:
</p>

<table width="100%" cellpadding="0" cellspacing="0"
       style="border-collapse:collapse;font-size:14px;margin-bottom:20px;">
  <tr style="background:#f8f4fb;">
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;width:40%;">Tracking Number</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">{$safeTracking}</td>
  </tr>
  <tr>
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;">Carrier</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">{$safeCarrier}</td>
  </tr>
  <tr style="background:#f8f4fb;">
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;">Document Type</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">{$safeDocType}</td>
  </tr>
  <tr>
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;">File Name</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">{$safeFileName}</td>
  </tr>
  <tr style="background:#f8f4fb;">
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;">File Size</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">{$fileSizeKb} KB</td>
  </tr>
  <tr>
    <td style="padding:10px 14px;border:1px solid #ece6f0;
               font-weight:600;color:#7b2b6a;">Uploaded At</td>
    <td style="padding:10px 14px;border:1px solid #ece6f0;color:#333;">{$uploadedAt}</td>
  </tr>
</table>

<p style="margin:0;color:#444;line-height:1.6;">
  Keep this email as your upload confirmation. If you did not upload this document,
  please contact us immediately.
</p>
HTML;

    $emailHtml = buildEmailTemplate('Document Upload Confirmation', $emailBody);
    $emailSent = sendMail(
        $uploaderEmail,
        '',
        "Upload Confirmation — Tracking #{$trackingNumber}",
        $emailHtml
    );

    if (!$emailSent) {
        $emailError = 'Document saved, but confirmation email could not be sent.';
    }

    logEmail(
        $conn,
        $uploaderEmail,
        "Upload Confirmation — Tracking #{$trackingNumber}",
        'upload_confirmation',
        $emailSent ? 'sent' : 'failed',
        $userId,
        'shipment_documents',
        $docId
    );
}

logAudit($conn, 'document_upload', $userId, 'shipment_documents', $docId, $trackingNumber, $_SERVER['REMOTE_ADDR'] ?? null);

$conn->close();

// ── Success response ──
echo json_encode([
    'success'       => true,
    'message'       => 'Document uploaded successfully.',
    'original_name' => $originalName,
    'saved_name'    => $savedName,
    'file_size_kb'  => round($fileSize / 1024, 1),
    'emailSent'     => $emailSent,
    'emailError'    => $emailError
]);
