<?php
/*
 * File: admin/dashboard.php
 * Purpose: Admin dashboard — shipments via api/admin/shipments.php (TrackingMore proxy)
 */

require_once __DIR__ . '/../server/includes/auth.php';
require_admin(1);

$adminName = htmlspecialchars($_SESSION['full_name'] ?? 'Admin', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ShipSmart | Admin Dashboard</title>
  <link rel="stylesheet" href="../global/main.css">
  <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
  <style>
    .kpi-grid{
      display:grid;
      grid-template-columns:repeat(4,1fr);
      gap:16px;
      margin-bottom:28px;
    }
    .kpi-card{
      background:#fff;
      border:1px solid var(--border);
      border-radius:16px;
      padding:20px 22px;
      position:relative;
      overflow:hidden;
    }
    .kpi-card::before{
      content:"";
      position:absolute;
      top:0; left:0; right:0;
      height:3px;
      background:var(--kpi-color, var(--primary));
    }
    .kpi-label{
      font-size:0.75rem;
      font-weight:800;
      text-transform:uppercase;
      letter-spacing:0.07em;
      color:var(--muted);
      margin:0 0 8px;
    }
    .kpi-value{
      font-size:2rem;
      font-weight:900;
      color:var(--text);
      line-height:1;
      margin:0 0 6px;
    }
    .kpi-sub{
      font-size:0.78rem;
      color:var(--muted);
      margin:0;
    }
    .kpi-icon{
      position:absolute;
      top:16px; right:18px;
      font-size:1.6rem;
      opacity:0.15;
    }
    .charts-grid{
      display:grid;
      grid-template-columns:1fr 1fr 1fr;
      gap:16px;
      margin-bottom:28px;
    }
    .chart-card{
      background:#fff;
      border:1px solid var(--border);
      border-radius:16px;
      padding:20px 22px;
    }
    .chart-card h3{
      margin:0 0 16px;
      font-size:0.88rem;
      font-weight:800;
      text-transform:uppercase;
      letter-spacing:0.06em;
      color:var(--muted);
    }
    .chart-card canvas{ display:block; }
    .progress-wrap{ margin-top:8px; }
    .progress-bar{
      height:8px;
      background:var(--border);
      border-radius:999px;
      overflow:hidden;
      margin-top:6px;
    }
    .progress-fill{
      height:100%;
      background:linear-gradient(90deg,var(--primary),#b84ca0);
      border-radius:999px;
      transition:width 1s ease;
    }
    .tbl-wrap{
      overflow-x:auto; border-radius:14px; border:1px solid var(--border);
    }
    .admin-table{ width:100%; border-collapse:collapse; font-size:0.87rem; }
    .admin-table th{
      background:var(--primary); color:#fff;
      padding:11px 14px; text-align:left;
      white-space:nowrap; font-weight:800;
    }
    .admin-table td{
      padding:10px 14px; border-bottom:1px solid var(--border);
      vertical-align:middle;
    }
    .admin-table tr:last-child td{ border-bottom:none; }
    .admin-table tr:hover td{ background:var(--soft); }
    .btn-sm{
      padding:5px 11px; font-size:0.77rem; border-radius:8px;
      border:1px solid; cursor:pointer; font-weight:700;
      display:inline-flex; align-items:center; background:#fff;
      font-family:inherit;
    }
    .btn-edit{ border-color:rgba(123,43,106,0.3); color:var(--primary); }
    .btn-edit:hover{ background:var(--soft); }
    .btn-del { border-color:rgba(176,0,32,0.3); color:#b00020; }
    .btn-del:hover{ background:#fff0f0; }
    .modal-bg{
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,0.45); z-index:1000;
      align-items:center; justify-content:center; padding:20px;
    }
    .modal-bg.open{ display:flex; }
    .modal-card{
      background:#fff; border-radius:20px; padding:32px;
      width:100%; max-width:560px; max-height:90vh; overflow-y:auto;
      box-shadow:0 32px 80px rgba(0,0,0,0.2);
    }
    .modal-card h3{ margin:0 0 18px; color:var(--primary); }
    .flash{
      padding:11px 16px; border-radius:10px;
      margin-bottom:18px; font-weight:700; font-size:0.9rem;
    }
    .flash-ok { background:#e9fff0; color:#0b6b2c; border:1px solid rgba(11,107,44,0.2); }
    .flash-err{ background:#fff0f0; color:#b00020; border:1px solid rgba(176,0,32,0.2); }
    .field-row{ display:grid; grid-template-columns:1fr 1fr; gap:14px; }
    .field-row .form-group{ margin-bottom:14px; }
    .field-row label{ display:block; font-weight:800; margin-bottom:6px; font-size:0.88rem; }
    .field-row input,
    .field-row select{
      width:100%; padding:10px 12px; border-radius:10px;
      border:1px solid var(--border); font-size:0.9rem; font-family:inherit;
    }
    .field-row input:focus,
    .field-row select:focus{
      outline:none;
      border-color:rgba(123,43,106,0.6);
      box-shadow:0 0 0 3px rgba(123,43,106,0.10);
    }
    .field-error{
      color:#b00020;
      font-size:0.78rem;
      font-weight:700;
      margin:4px 0 0;
      min-height:1.1em;
    }
    .field-row input.is-invalid,
    .field-row select.is-invalid{
      border-color:#b00020;
    }
    .latest-event{
      font-size:0.75rem; color:var(--muted);
      max-width:180px; white-space:nowrap;
      overflow:hidden; text-overflow:ellipsis;
    }
    .section-head{
      display:flex; align-items:center; justify-content:space-between;
      gap:12px; margin-bottom:18px; flex-wrap:wrap;
    }
    .section-head h2{ margin:0; color:var(--primary); }
    @media(max-width:900px){
      .kpi-grid{ grid-template-columns:1fr 1fr; }
      .charts-grid{ grid-template-columns:1fr; }
    }
    @media(max-width:560px){
      .kpi-grid{ grid-template-columns:1fr; }
      .field-row{ grid-template-columns:1fr; }
    }
  </style>
</head>
<body>

  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="../index.html" aria-label="ShipSmart Home">
        <img class="brand-logo" src="../images/shipsmart-logo-3.svg" alt="ShipSmart logo">
        <span class="brand-name">ShipSmart</span>
      </a>
      <nav class="nav" aria-label="Main navigation">
        <ul class="nav-list">
          <li><a class="nav-link" href="../index.html">Home</a></li>
          <li><a class="nav-link" href="../pages/services.html">About</a></li>
          <li><a class="nav-link" href="../pages/schedule.php">Schedule</a></li>
          <li><a class="nav-link" href="../pages/search.html">Search</a></li>
          <li><a class="nav-link" href="../pages/upload.html">Upload</a></li>
          <li><a class="nav-link" href="../pages/video.html">Video</a></li>
          <li><a class="nav-link" href="../pages/feedback.html">Feedback</a></li>
          <li><a class="nav-link" href="../profile.php">Profile</a></li>
          <li><a class="nav-link is-active" href="dashboard.php" style="color:var(--accent)">Dashboard</a></li>
        </ul>
      </nav>
      <button class="nav-toggle" type="button" aria-label="Open menu" aria-expanded="false">☰</button>
    </div>
  </header>

  <div style="background:var(--soft);border-bottom:1px solid var(--border);padding:10px 0">
    <div class="container" style="display:flex;align-items:center;justify-content:space-between">
      <p style="margin:0;font-size:0.85rem;color:var(--muted)">
        Admin Dashboard &mdash; <strong style="color:var(--primary)">GET / POST / PUT / DELETE</strong> api/admin/shipments.php
      </p>
      <a href="../index.html" style="font-size:0.82rem;color:var(--primary);font-weight:700;text-decoration:none">← View Site</a>
    </div>
  </div>

  <main>
    <section class="section">
      <div class="container">

        <div id="dashFlash" class="flash" hidden role="status"></div>

        <div class="kpi-grid">
          <div class="kpi-card" style="--kpi-color:#7b2b6a">
            <p class="kpi-label">Total Shipments</p>
            <p class="kpi-value" id="kpiTotal">—</p>
            <p class="kpi-sub">Across all carriers</p>
            <span class="kpi-icon">📦</span>
          </div>
          <div class="kpi-card" style="--kpi-color:#0b6b2c">
            <p class="kpi-label">Delivered</p>
            <p class="kpi-value" id="kpiDelivered">—</p>
            <p class="kpi-sub" id="kpiDeliveredSub">Loading…</p>
            <span class="kpi-icon">✅</span>
            <div class="progress-wrap">
              <div class="progress-bar">
                <div class="progress-fill" id="kpiProgress" style="width:0%"></div>
              </div>
            </div>
          </div>
          <div class="kpi-card" style="--kpi-color:#c2410c">
            <p class="kpi-label">In Transit</p>
            <p class="kpi-value" id="kpiTransit">—</p>
            <p class="kpi-sub">Currently moving</p>
            <span class="kpi-icon">🚚</span>
          </div>
          <div class="kpi-card" style="--kpi-color:#b00020">
            <p class="kpi-label">Exceptions</p>
            <p class="kpi-value" id="kpiExceptions">—</p>
            <p class="kpi-sub">Require attention</p>
            <span class="kpi-icon">⚠️</span>
          </div>
        </div>

        <div class="charts-grid" id="chartsGrid" hidden>
          <div class="chart-card">
            <h3>Status Breakdown</h3>
            <canvas id="chartStatus" height="200"></canvas>
          </div>
          <div class="chart-card">
            <h3>Shipments by Carrier</h3>
            <canvas id="chartCarrier" height="200"></canvas>
          </div>
          <div class="chart-card">
            <h3>Activity by Day</h3>
            <canvas id="chartDay" height="200"></canvas>
          </div>
        </div>

        <div class="section-head">
          <h2>Shipments (<span id="shipmentsCount">…</span>)</h2>
          <div style="display:flex;gap:8px;flex-wrap:wrap">
            <button class="btn btn-ghost btn-inline" id="refreshShipmentsBtn"
                    type="button" style="margin-top:0;font-size:0.88rem" title="Reload from TrackingMore">
              ↻ Refresh
            </button>
            <button class="btn btn-primary btn-inline" id="openAddBtn"
                    type="button" style="margin-top:0;font-size:0.88rem">
              + Add Shipment
            </button>
          </div>
        </div>

        <div class="tbl-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>#</th>
                <th>Tracking Number</th>
                <th>Carrier</th>
                <th>Route</th>
                <th>Status</th>
                <th>Last Event</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody id="shipmentsBody">
              <tr>
                <td colspan="7" style="text-align:center;color:var(--muted);padding:32px">
                  Loading shipments from TrackingMore…
                </td>
              </tr>
            </tbody>
          </table>
        </div>

      </div>
    </section>
  </main>

  <div class="modal-bg" id="addModal">
    <div class="modal-card">
      <h3>Add New Shipment</h3>
      <p style="margin:0 0 18px;font-size:0.85rem;color:var(--muted)">Registers the tracking number with TrackingMore.</p>
      <form id="addShipmentForm" novalidate>
        <input type="hidden" name="action" value="add">
        <div class="field-row">
          <div class="form-group">
            <label for="addTracking">Tracking Number *</label>
            <input type="text" id="addTracking" name="tracking_number"
                   placeholder="e.g., ARX1002345678" required
                   minlength="5" maxlength="50"
                   pattern="[A-Za-z0-9\-]{5,50}"
                   autocomplete="off"
                   title="5–50 letters, numbers, or hyphens">
            <p class="field-error" id="addTrackingError" role="alert"></p>
          </div>
          <div class="form-group">
            <label for="addCarrier">Carrier *</label>
            <select name="carrier" id="addCarrier" required>
              <option value="">Select</option>
              <option value="aramex">Aramex</option>
              <option value="dhl">DHL</option>
              <option value="fedex">FedEx</option>
              <option value="smsa">SMSA</option>
              <option value="smsa-express">SMSA Express</option>
              <option value="ups">UPS</option>
              <option value="usps">USPS</option>
            </select>
            <p class="field-error" id="addCarrierError" role="alert"></p>
          </div>
        </div>
        <div class="field-row">
          <div class="form-group">
            <label for="addOrigin">Route — Origin City</label>
            <input type="text" id="addOrigin" name="origin_city" maxlength="80"
                   placeholder="e.g., Jeddah">
          </div>
          <div class="form-group">
            <label for="addDest">Route — Destination City</label>
            <input type="text" id="addDest" name="destination_city" maxlength="80"
                   placeholder="e.g., Riyadh">
          </div>
        </div>
        <p class="field-error" id="addRouteError" role="alert"></p>
        <div style="display:flex;gap:10px;margin-top:8px">
          <button class="btn btn-primary" type="submit" style="flex:1;margin-top:0">Save Shipment</button>
          <button class="btn btn-ghost" type="button" data-close-modal="addModal">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <div class="modal-bg" id="editModal">
    <div class="modal-card">
      <h3>Edit Shipment</h3>
      <p style="margin:0 0 6px;font-size:0.78rem;color:var(--muted)">Tracking Number · Carrier (read-only)</p>
      <p style="margin:0 0 18px;font-size:0.88rem;font-weight:800;color:var(--primary)" id="eTrackingDisplay"></p>
      <form id="editShipmentForm">
        <input type="hidden" name="action" value="edit">
        <input type="hidden" name="id" id="editId">
        <input type="hidden" name="tracking_number" id="editTracking">
        <input type="hidden" name="carrier" id="editCarrier">
        <div class="field-row">
          <div class="form-group">
            <label for="eOrigin">Route — Origin City</label>
            <input type="text" name="origin_city" id="eOrigin" maxlength="80"
                   placeholder="e.g., Jeddah">
          </div>
          <div class="form-group">
            <label for="eDest">Route — Destination City</label>
            <input type="text" name="destination_city" id="eDest" maxlength="80"
                   placeholder="e.g., Riyadh">
          </div>
        </div>
        <div style="display:flex;gap:10px;margin-top:8px">
          <button class="btn btn-primary" type="submit" style="flex:1;margin-top:0">Update Shipment</button>
          <button class="btn btn-ghost" type="button" data-close-modal="editModal">Cancel</button>
        </div>
      </form>
    </div>
  </div>

  <script src="../scripts/main.js"></script>
  <script src="../scripts/admin-dashboard.js"></script>
</body>
</html>
