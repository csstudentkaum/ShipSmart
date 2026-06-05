<?php
/*
 * File: 403.php  (project root)
 * Purpose: Shown when a non-admin tries to access an admin page.
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ShipSmart | Access Denied</title>
  <link rel="stylesheet" href="global/main.css">
</head>
<body>
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="index.html" aria-label="ShipSmart Home">
        <img class="brand-logo" src="images/shipsmart-logo-3.svg" alt="ShipSmart logo">
        <span class="brand-name">ShipSmart</span>
      </a>
    </div>
  </header>

  <main>
    <section class="section" style="text-align:center;padding:80px 0">
      <div class="container" style="max-width:480px">
        <p style="font-size:4rem;margin:0">🚫</p>
        <h1 style="color:var(--primary);margin:16px 0 8px">Access Denied</h1>
        <p class="muted" style="margin-bottom:28px">
          You don't have permission to view this page.<br>
          Admin access is required.
        </p>
        <a class="btn btn-primary btn-inline" href="index.html">Back to Home</a>
        <a class="btn btn-ghost btn-inline" href="login.php" style="margin-left:10px">Sign In</a>
      </div>
    </section>
  </main>

  <footer class="site-footer">
    <div class="container footer-inner">
      <div class="footer-bottom">
        <p>&copy; 2026 ShipSmart &mdash; King Abdulaziz University, Jeddah</p>
        <span class="footer-badge"><span class="footer-badge-dot"></span>CPCS 403 Project</span>
      </div>
    </div>
  </footer>
</body>
</html>