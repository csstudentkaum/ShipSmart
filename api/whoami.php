<?php
// Simple whoami endpoint used by client-side scripts to show/hide admin UI.
header('Content-Type: application/json');
// include session helpers but do not require DB
require_once __DIR__ . '/../server/includes/auth.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$role = $_SESSION['role'] ?? null;
echo json_encode(['role' => $role]);
