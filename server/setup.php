<?php
/*
 * File: server/setup.php
 * Purpose: One-click DB check — open in browser after XAMPP install
 * Example: http://localhost/403/server/setup.php
 */

header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . '/db_config.php';

$required = [
    'users',
    'user_sessions',
    'password_reset_tokens',
    'login_attempts',
    'shipments',
    'shipment_status_history',
    'user_shipments',
    'tracking_queries',
    'feedback',
    'shipment_documents',
    'email_log',
    'audit_log',
];

$status = [];
foreach ($required as $table) {
    $safe = $conn->real_escape_string($table);
    $r    = $conn->query(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema = DATABASE() AND table_name = '{$safe}' LIMIT 1"
    );
    $status[$table] = $r && $r->num_rows > 0;
}

$uploadDir = dirname(__DIR__) . '/uploads/documents';
$uploadOk  = shipsmart_ensure_upload_dir($uploadDir);

$shipCount = 0;
$userCount = 0;
$r = $conn->query('SELECT COUNT(*) AS c FROM shipments');
if ($r) {
    $shipCount = (int) $r->fetch_assoc()['c'];
}
$r = $conn->query('SELECT COUNT(*) AS c FROM users');
if ($r) {
    $userCount = (int) $r->fetch_assoc()['c'];
}

$allTables = !in_array(false, $status, true);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ShipSmart — Database Setup</title>
  <style>
    body { font-family: system-ui, sans-serif; max-width: 640px; margin: 40px auto; padding: 0 16px; }
    h1 { color: #7b2b6a; }
    .ok { color: #0b6b2c; }
    .bad { color: #b00020; }
    ul { line-height: 1.8; }
    .box { background: #fbf7ff; border: 1px solid #ececec; border-radius: 12px; padding: 16px; margin-top: 20px; }
  </style>
</head>
<body>
  <h1>ShipSmart database setup</h1>
  <p>Database: <strong><?php echo htmlspecialchars(DB_NAME); ?></strong></p>

  <h2>Tables</h2>
  <ul>
    <?php foreach ($status as $name => $exists): ?>
      <li class="<?php echo $exists ? 'ok' : 'bad'; ?>">
        <?php echo $exists ? '✓' : '✗'; ?>
        <?php echo htmlspecialchars($name); ?>
      </li>
    <?php endforeach; ?>
  </ul>

  <h2>Data</h2>
  <ul>
    <li>Shipments: <strong><?php echo $shipCount; ?></strong> rows</li>
    <li>Users: <strong><?php echo $userCount; ?></strong> rows</li>
    <li>Upload folder: <span class="<?php echo $uploadOk ? 'ok' : 'bad'; ?>">
      <?php echo $uploadOk ? 'OK' : 'Missing or not writable'; ?>
    </span></li>
  </ul>

  <div class="box">
    <?php if ($allTables && $uploadOk && $shipCount > 0): ?>
      <p class="ok"><strong>Ready.</strong> Open your site home page and test Search + Upload.</p>
    <?php else: ?>
      <p class="bad"><strong>Not ready yet.</strong></p>
      <p>Refresh this page — PHP auto-creates missing core tables on each request.</p>
      <p>If tables are still missing, import <code>server/schema.sql</code> in phpMyAdmin.</p>
    <?php endif; ?>
  </div>

  <p><a href="../index.html">← Back to ShipSmart Home</a></p>
</body>
</html>
