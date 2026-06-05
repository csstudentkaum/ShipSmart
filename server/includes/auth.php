<?php
/*
 * File: includes/auth.php
 * Purpose: Session management helpers — start session, check roles,
 *          redirect unauthorized users.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/** True when a user is logged in */
function is_logged_in(): bool {
    return isset($_SESSION['user_id']);
}

/** True when the logged-in user is an admin */
function is_admin(): bool {
    return is_logged_in() && ($_SESSION['role'] ?? '') === 'admin';
}

/**
 * Require login.
 * Redirects to login.php (root) if not signed in.
 * $depth = how many levels deep the calling file is from root.
 *   0 = root (login.php, register.php)
 *   1 = one folder deep (admin/, api/)
 */
function require_login(int $depth = 1): void {
    if (!is_logged_in()) {
        $prefix   = str_repeat('../', $depth);
        $redirect = urlencode($_SERVER['REQUEST_URI'] ?? '');
        header("Location: {$prefix}login.php?redirect={$redirect}");
        exit;
    }
}

/**
 * Require admin role.
 * Redirects to 403.php if not admin.
 */
function require_admin(int $depth = 1): void {
    require_login($depth);
    if (!is_admin()) {
        $prefix = str_repeat('../', $depth);
        header("Location: {$prefix}403.php");
        exit;
    }
}