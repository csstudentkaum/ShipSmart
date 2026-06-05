<?php
/*
 * File: profile.php  (project root)
 * Purpose: Profile page — shows name, role, access level, and sign out.
 *          Accessible by both admin and regular user.
 *          Redirects to login if not signed in.
 */

require_once __DIR__ . '/server/includes/auth.php';
require_login(0);

$name    = htmlspecialchars($_SESSION['full_name'] ?? '', ENT_QUOTES, 'UTF-8');
$role    = $_SESSION['role'] ?? 'user';
$isAdmin = $role === 'admin';
$initial = mb_strtoupper(mb_substr($_SESSION['full_name'] ?? 'U', 0, 1, 'UTF-8'), 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ShipSmart | My Profile</title>
  <link rel="stylesheet" href="global/main.css">
  <link rel="stylesheet" href="global/print.css" media="print">
</head>
<body>

  <!-- ===================== HEADER ===================== -->
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="index.html" aria-label="ShipSmart Home">
        <img class="brand-logo" src="images/shipsmart-logo-3.svg" alt="ShipSmart logo">
        <span class="brand-name">ShipSmart</span>
      </a>

      <nav class="nav" aria-label="Main navigation">
        <ul class="nav-list">
          <li><a class="nav-link" href="index.html">Home</a></li>
          <li><a class="nav-link" href="pages/services.html">About</a></li>
          <li><a class="nav-link" href="pages/schedule.html">Schedule</a></li>
          <li><a class="nav-link" href="pages/search.html">Search</a></li>
          <li><a class="nav-link" href="pages/upload.html">Upload</a></li>
          <li><a class="nav-link" href="pages/video.html">Video</a></li>
          <li><a class="nav-link" href="pages/feedback.html">Feedback</a></li>
          <li><a class="nav-link is-active" href="profile.php">Profile</a></li>
          <?php if ($isAdmin): ?>
          <li>
            <a class="nav-link" href="admin/dashboard.php"
               style="color:var(--accent);font-weight:900">Dashboard</a>
          </li>
          <?php endif; ?>
        </ul>
      </nav>
      <button class="nav-toggle" type="button"
              aria-label="Open menu" aria-expanded="false">☰</button>
    </div>
  </header>

  <!-- ===================== MAIN ===================== -->
  <main>
    <section class="section page-hero">
      <div class="container">
        <h1>My Profile</h1>
        <p class="muted">Your account details and sign out.</p>
      </div>
    </section>

    <section class="section" style="padding-top:0">
      <div class="container profile-wrap">
        <div class="card profile-card">

          <!-- Dark gradient banner -->
          <div class="profile-banner"></div>

          <!-- Avatar overlapping banner -->
          <div class="profile-avatar-wrap">
            <div class="profile-avatar"><?= $initial ?></div>
            <h2 class="profile-name"><?= $name ?></h2>
            <p class="profile-meta">
              <?= $isAdmin ? 'Administrator · ShipSmart' : 'User · ShipSmart' ?>
            </p>
          </div>

          <!-- Card body -->
          <div class="profile-body">

            <!-- Info rows -->
            <div class="profile-info">
              <div class="profile-info-row">
                <span class="profile-info-label">Full Name</span>
                <span class="profile-info-value"><?= $name ?></span>
              </div>
              <div class="profile-info-row">
                <span class="profile-info-label">Role</span>
                <span class="profile-info-value">
                  <?= $isAdmin ? 'Administrator' : 'Regular User' ?>
                </span>
              </div>
              <div class="profile-info-row">
                <span class="profile-info-label">Access Level</span>
                <span class="profile-info-value">
                  <?= $isAdmin ? 'Full dashboard access' : 'Browse & track shipments' ?>
                </span>
              </div>
            </div>

            <!-- Action buttons -->
            <div class="profile-actions">
              <?php if ($isAdmin): ?>
                <a class="btn-dashboard" href="admin/dashboard.php">Go to Dashboard</a>
              <?php else: ?>
                <a class="btn-dashboard" href="index.html">Back to Home</a>
              <?php endif; ?>
              <a class="btn-signout" href="api/logout.php">Sign Out</a>
            </div>

          </div>
        </div>
      </div>
    </section>
  </main>

  <!-- ===================== FOOTER ===================== -->
  <footer class="site-footer">
    <div class="container footer-inner">
      <div class="footer-grid">
        <div>
          <div class="footer-brand">
            <img class="footer-logo" src="images/shipsmart-logo-3.svg" alt="ShipSmart logo">
            <strong>ShipSmart</strong>
          </div>
          <p class="muted">Universal Shipment Tracker &mdash; a course project for CPCS 403.</p>
          <p class="small">Tracking smarter, one parcel at a time.</p>
        </div>
        <div>
          <p class="footer-title">Pages</p>
          <ul class="footer-links">
            <li><a href="index.html">Home</a></li>
            <li><a href="pages/services.html">Services</a></li>
            <li><a href="pages/schedule.html">Schedule</a></li>
            <li><a href="pages/search.html">Search</a></li>
            <li><a href="pages/upload.html">Upload</a></li>
            <li><a href="pages/video.html">Video</a></li>
            <li><a href="pages/feedback.html">Feedback</a></li>
          </ul>
        </div>
      </div>
      <div class="footer-bottom">
        <p>&copy; 2026 ShipSmart &mdash; King Abdulaziz University, Jeddah</p>
        <span class="footer-badge">
          <span class="footer-badge-dot"></span>CPCS 403 Project
        </span>
      </div>
    </div>
  </footer>

  <script src="scripts/main.js"></script>
</body>
</html>