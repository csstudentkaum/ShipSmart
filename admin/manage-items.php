<?php
/*
 * File: admin/manage-items.php
 * Purpose: Legacy form POST handler — redirects to dashboard with flash.
 *          Prefer api/admin/shipments.php (fetch) from the dashboard UI.
 */

require_once __DIR__ . '/../server/includes/auth.php';
require_once __DIR__ . '/../server/includes/shipments_service.php';

require_admin(1);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: dashboard.php');
    exit;
}

$action   = trim($_POST['action'] ?? '');
$redirect = 'dashboard.php';

function redirectWith(string $type, string $msg, string $to): void
{
    $param = $type === 'ok' ? 'msg' : 'err';
    header('Location: ' . $to . '?' . $param . '=' . urlencode($msg));
    exit;
}

$result = match ($action) {
    'add'    => shipments_add($_POST),
    'edit'   => shipments_edit($_POST),
    'delete' => shipments_delete($_POST),
    default  => ['ok' => false, 'message' => 'Unknown action.'],
};

redirectWith($result['ok'] ? 'ok' : 'err', $result['message'], $redirect);
