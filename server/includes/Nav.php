<?php
/*
 * File: includes/nav.php
 * Purpose: Shared navigation partial — included in every page header.
 *          Shows Login button when logged out, name + Logout when logged in.
 *
 * Usage:
 *   Pass $activeNav = 'home' | 'services' | 'schedule' | 'search' |
 *                     'upload' | 'video' | 'feedback'
 *   Pass $depth = 0 (root pages) or 1 (pages/ folder)
 *
 * Example from index.html equivalent:
 *   <?php $activeNav = 'home'; $depth = 0; require 'includes/nav.php'; ?>
 *
 * Example from pages/feedback.html equivalent:
 *   <?php $activeNav = 'feedback'; $depth = 1; require '../includes/nav.php'; ?>
 */

if (session_status() === PHP_SESSION_NONE) session_start();

$prefix   = str_repeat('../', $depth ?? 0);
$loggedIn = isset($_SESSION['user_id']);
$isAdmin  = ($loggedIn && ($_SESSION['role'] ?? '') === 'admin');
$userName = htmlspecialchars($_SESSION['full_name'] ?? '', ENT_QUOTES, 'UTF-8');

$links = [
    'home'     => ['label' => 'Home',     'href' => $prefix . 'index.html'],
    'services' => ['label' => 'Services', 'href' => $prefix . 'pages/services.html'],
    'schedule' => ['label' => 'Schedule', 'href' => $prefix . 'pages/schedule.html'],
    'search'   => ['label' => 'Search',   'href' => $prefix . 'pages/search.html'],
    'upload'   => ['label' => 'Upload',   'href' => $prefix . 'pages/upload.html'],
    'video'    => ['label' => 'Video',    'href' => $prefix . 'pages/video.html'],
    'feedback' => ['label' => 'Feedback', 'href' => $prefix . 'pages/feedback.html'],
];
?>
<header class="site-header">
  <div class="container header-inner">

    <!-- Brand -->
    <a class="brand" href="<?= $prefix ?>index.html" aria-label="ShipSmart Home">
      <img class="brand-logo" src="<?= $prefix ?>images/shipsmart-logo-3.svg" alt="ShipSmart logo">
      <span class="brand-name">ShipSmart</span>
    </a>

    <!-- Navigation -->
    <nav class="nav" aria-label="Main navigation">
      <ul class="nav-list">
        <?php foreach ($links as $key => $link): ?>
        <li>
          <a class="nav-link <?= ($activeNav ?? '') === $key ? 'is-active' : '' ?>"
             href="<?= $link['href'] ?>">
            <?= $link['label'] ?>
          </a>
        </li>
        <?php endforeach; ?>

        <!-- Admin Dashboard link (admin only) -->
        <?php if ($isAdmin): ?>
        <li>
          <a class="nav-link" href="<?= $prefix ?>admin/dashboard.php"
             style="color:var(--accent);font-weight:900">
            Dashboard
          </a>
        </li>
        <?php endif; ?>
      </ul>

      <!-- Auth button -->
      <div class="nav-auth-wrap">
        <?php if ($loggedIn): ?>
          <span class="nav-auth-user">Hi, <?= $userName ?></span>
          <a class="nav-auth nav-auth-logout"
             href="<?= $prefix ?>api/logout.php">Sign Out</a>
        <?php else: ?>
          <a class="nav-auth nav-auth-login"
             href="<?= $prefix ?>login.php">Login</a>
        <?php endif; ?>
      </div>
    </nav>

    <!-- Mobile toggle -->
    <button class="nav-toggle" type="button"
            aria-label="Open menu" aria-expanded="false">☰</button>
  </div>
</header>