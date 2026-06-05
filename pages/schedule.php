<?php
/*
 * Name: Wareef Alzubaidi
 * ID: 2207221
 * Section: CPCS403
 * Date: 2026-03-11
 * File: pages/schedule.php
 * Purpose: ShipSmart Schedule Page — live data from TrackingMore API via PHP SDK
 */

// ── Load SDK (manual install) ─────────────────────────────────────────────────
$sdkBase = __DIR__ . '/../trackingmore/trackingmore-sdk-php/src/';
require_once $sdkBase . 'TrackingMoreException.php';
require_once $sdkBase . 'ErrorMessages.php';
require_once $sdkBase . 'Request.php';
require_once $sdkBase . 'Interfaces/CouriersInterface.php';
require_once $sdkBase . 'Couriers.php';
require_once $sdkBase . 'Interfaces/TrackingsInterface.php';
require_once $sdkBase . 'Trackings.php';
require_once $sdkBase . 'Interfaces/AirWaybillsInterface.php';
require_once $sdkBase . 'AirWaybills.php';

require_once __DIR__ . '/../server/config.php';

// ── Fetch all trackings from the API ─────────────────────────────────────────
/*
 * We group every tracking by carrier, then by day-of-week (based on
 * created_at / last checkpoint date), so the table shows real pickup
 * and delivery windows derived from actual shipment timestamps.
 */

$apiError    = null;
$carriers    = [];   // ['DHL' => ['code'=>'dhl', 'shipments'=>[...]], ...]
$totalActive = 0;
$lastUpdated = null;

$carrierNameMap = [
    'aramex'       => 'Aramex',
    'dhl'          => 'DHL',
    'fedex'        => 'FedEx',
    'smsa'         => 'SMSA',
    'smsa-express' => 'SMSA',
    'usps'         => 'USPS',
    'ups'          => 'UPS',
    'china-post'   => 'China Post',
];

$days = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];

$statusLabels = [
    'pending'        => 'Pending',
    'notfound'       => 'Not Found',
    'transit'        => 'In Transit',
    'pickup'         => 'Picked Up',
    'delivered'      => 'Delivered',
    'undelivered'    => 'Undelivered',
    'exception'      => 'Exception',
    'expired'        => 'Expired',
    'inforeceived'   => 'Info Received',
    'outfordelivery' => 'Out for Delivery',
];

try {
    $sdkTrackings = new TrackingMore\Trackings("m8ki266j-uquq-xsv1-88m2-3kvdnjuxgfh9");
    $response     = $sdkTrackings->getTrackingResults([]);

    if (
        isset($response['meta']['code']) && $response['meta']['code'] === 200
        && !empty($response['data'])
    ) {
        foreach ($response['data'] as $t) {
            $code    = strtolower($t['courier_code'] ?? 'unknown');
            $label   = $carrierNameMap[$code] ?? ucfirst($code);
            $status  = $t['delivery_status'] ?? 'pending';
            $updated = $t['update_at'] ?? $t['created_at'] ?? null;
            $created = $t['created_at'] ?? null;

            // Day of week from created_at
            $dayIndex = $created ? (int)date('w', strtotime($created)) : null;
            $dayName  = $dayIndex !== null ? $days[$dayIndex] : '—';

            // Latest checkpoint time (pickup time)
            $checkpoints  = $t['origin_info']['trackinfo'] ?? [];
            $pickupTime   = null;
            $deliveryTime = null;

            foreach ($checkpoints as $cp) {
                $cpDate   = $cp['checkpoint_date'] ?? null;
                $cpDetail = strtolower($cp['tracking_detail'] ?? '');
                if (!$cpDate) continue;

                if (
                    str_contains($cpDetail, 'picked up') ||
                    str_contains($cpDetail, 'pickup') ||
                    str_contains($cpDetail, 'accepted')
                ) {
                    $pickupTime = $pickupTime ?? $cpDate;
                }
                if (
                    str_contains($cpDetail, 'delivered') ||
                    str_contains($cpDetail, 'delivery') ||
                    str_contains($cpDetail, 'out for delivery')
                ) {
                    $deliveryTime = $deliveryTime ?? $cpDate;
                }
            }

            if (!isset($carriers[$label])) {
                $carriers[$label] = [
                    'code'      => $code,
                    'total'     => 0,
                    'shipments' => [],
                ];
            }

            $carriers[$label]['total']++;
            $carriers[$label]['shipments'][] = [
                'tracking_number' => $t['tracking_number'] ?? '',
                'status'          => $status,
                'day'             => $dayName,
                'day_index'       => $dayIndex,
                'created'         => $created,
                'pickup_time'     => $pickupTime,
                'delivery_time'   => $deliveryTime,
                'origin'          => $t['origin_country']      ?? '',
                'destination'     => $t['destination_country'] ?? '',
                'updated'         => $updated,
            ];

            $totalActive++;
            if ($updated && (!$lastUpdated || $updated > $lastUpdated)) {
                $lastUpdated = $updated;
            }
        }

        // Sort carriers alphabetically
        ksort($carriers);
    }
} catch (TrackingMore\TrackingMoreException $e) {
    $apiError = $e->getMessage();
} catch (\Exception $e) {
    $apiError = $e->getMessage();
}

$lastUpdatedFormatted = $lastUpdated
    ? date('D d M Y, H:i', strtotime($lastUpdated)) . ' AST'
    : 'N/A';

// ── Build per-carrier per-day summary ─────────────────────────────────────────
/*
 * For each carrier, for each day of the week, collect:
 *  - count of shipments created on that day
 *  - earliest pickup time seen
 *  - latest delivery time seen
 *  - most common status
 */
function buildDaySummary(array $shipments): array {
    global $days;
    $summary = [];
    foreach ($days as $d) {
        $summary[$d] = ['count'=>0,'statuses'=>[],'pickup'=>null,'delivery'=>null];
    }
    foreach ($shipments as $s) {
        $d = $s['day'];
        if (!isset($summary[$d])) continue;
        $summary[$d]['count']++;
        $st = $s['status'];
        $summary[$d]['statuses'][$st] = ($summary[$d]['statuses'][$st] ?? 0) + 1;

        if ($s['pickup_time']) {
            $t = date('H:i', strtotime($s['pickup_time']));
            if (!$summary[$d]['pickup'] || $t < $summary[$d]['pickup'])
                $summary[$d]['pickup'] = $t;
        }
        if ($s['delivery_time']) {
            $t = date('H:i', strtotime($s['delivery_time']));
            if (!$summary[$d]['delivery'] || $t > $summary[$d]['delivery'])
                $summary[$d]['delivery'] = $t;
        }
    }
    return $summary;
}

function makeGroups(array $workDays, array $summary, string $type): array {
    $groups = [];
    $i = 0;
    while ($i < count($workDays)) {
        $day = $workDays[$i];
        $d   = $summary[$day];

        if ($type === 'pickup') {
            $key = $d['count'] > 0
                ? (array_key_first(array_reverse(arsort($d['statuses']) ? $d['statuses'] : $d['statuses'], true)) ?? '__empty__')
                : '__empty__';
            // simpler: just pick top status
            if ($d['count'] > 0) {
                $tmp = $d['statuses'];
                arsort($tmp);
                $key = array_key_first($tmp) ?? '__empty__';
            } else {
                $key = '__empty__';
            }
        } else {
            $key = $d['count'] > 0 && $d['delivery']
                ? $d['delivery']
                : ($d['count'] > 0 ? '__notime__' : '__empty__');
        }

        $span = 1;
        while ($i + $span < count($workDays)) {
            $nd = $summary[$workDays[$i + $span]];
            if ($type === 'pickup') {
                if ($nd['count'] > 0) {
                    $tmp2 = $nd['statuses'];
                    arsort($tmp2);
                    $nextKey = array_key_first($tmp2) ?? '__empty__';
                } else {
                    $nextKey = '__empty__';
                }
            } else {
                $nextKey = $nd['count'] > 0 && $nd['delivery']
                    ? $nd['delivery']
                    : ($nd['count'] > 0 ? '__notime__' : '__empty__');
            }
            if ($nextKey === $key) { $span++; } else { break; }
        }

        $groups[] = ['day'=>$day, 'key'=>$key, 'span'=>$span, 'data'=>$d];
        $i += $span;
    }
    return $groups;
}

function topStatus(array $statuses): string {
    if (empty($statuses)) return '—';
    arsort($statuses);
    global $statusLabels;
    $k = array_key_first($statuses);
    return $statusLabels[$k] ?? ucfirst($k);
}

function statusClass(string $s): string {
    return match(strtolower($s)) {
        'delivered'                    => 'badge-status-delivered',
        'transit', 'outfordelivery'    => 'badge-status-in_transit',
        'pickup'                       => 'badge-status-picked_up',
        'exception','undelivered'      => 'badge-status-out_for_delivery',
        default                        => 'badge-status-created',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>ShipSmart | Shipping Schedule</title>
  <link rel="stylesheet" href="../global/main.css">
  <link rel="stylesheet" href="../global/print.css" media="print">
  <style>
    .api-stats-bar{
      background:var(--soft);
      border:1px solid var(--border);
      border-radius:var(--radius);
      padding:18px 24px;
      margin-bottom:24px;
      display:flex;
      flex-wrap:wrap;
      gap:18px;
      align-items:center;
      justify-content:space-between;
    }
    .api-live-dot{
      width:8px;height:8px;border-radius:50%;
      background:#0b6b2c;
      animation:livepulse 2s ease-in-out infinite;
      flex-shrink:0;
    }
    @keyframes livepulse{0%,100%{opacity:1;transform:scale(1)}50%{opacity:0.4;transform:scale(0.7)}}
    .api-stats-label{ font-weight:800; color:var(--primary); font-size:0.92rem; margin:0; }
    .api-stats-updated{ font-size:0.8rem; color:var(--muted); margin:0; }
    .api-carrier-chip{
      display:flex; align-items:center; gap:8px;
      padding:8px 14px; border-radius:12px;
      border:1px solid var(--border); background:#fff; font-size:0.85rem;
    }
    .api-carrier-chip strong{ color:var(--primary); font-weight:900; }
    .chip-count{
      background:var(--primary); color:#fff; border-radius:999px;
      font-size:0.72rem; font-weight:900; padding:2px 8px;
    }
    .chip-status{ font-size:0.75rem; color:var(--muted); }

    .api-error-banner{
      padding:14px 18px; background:#fff4f4;
      border:1px solid #f7c1c1; border-radius:12px;
      color:#a32d2d; font-size:0.88rem; margin-bottom:20px;
      display:flex; align-items:center; gap:10px;
    }
    .no-data-card{
      padding:52px 20px; text-align:center;
    }
    .no-data-card h3{ margin:0 0 8px; color:var(--primary); }
    .no-data-card p{ margin:0; color:var(--muted); }

    /* Table day cell — show count badge */
    .day-cell{ position:relative; vertical-align:top; }
    .day-count{
      display:inline-block;
      background:var(--primary); color:#fff;
      border-radius:999px; font-size:0.68rem;
      font-weight:900; padding:1px 6px;
      margin-left:4px; vertical-align:middle;
    }
    .day-status-pill{
      display:block; margin-top:4px;
      font-size:0.72rem; font-weight:800;
      border-radius:999px; padding:2px 8px;
      white-space:nowrap;
    }
    .day-time{
      display:block; margin-top:3px;
      font-size:0.75rem; color:var(--muted);
    }
    .cell-empty{ color:var(--muted); font-size:0.82rem; }

    @media(max-width:600px){
      .api-stats-bar{ flex-direction:column; align-items:flex-start; }
    }
  </style>
</head>

<body>

  <!-- ===================== HEADER ===================== -->
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="../index.html" aria-label="ShipSmart Home">
        <img class="brand-logo" src="../images/shipsmart-logo-3.svg" alt="ShipSmart logo">
        <span class="brand-name">ShipSmart</span>
      </a>
      <nav class="nav" aria-label="Main navigation">
        <ul class="nav-list">
          <li><a class="nav-link" href="../index.html">Home</a></li>
          <li><a class="nav-link" href="services.html">About</a></li>
          <li><a class="nav-link is-active" href="schedule.php">Schedule</a></li>
          <li><a class="nav-link" href="search.html">Search</a></li>
          <li><a class="nav-link" href="upload.html">Upload</a></li>
          <li><a class="nav-link" href="video.html">Video</a></li>
          <li><a class="nav-link" href="feedback.html">Feedback</a></li>
          <li><a class="nav-link" href="../profile.php">Profile</a></li>
          <li id="nav-dashboard" style="display:none">
            <a class="nav-link" href="../admin/dashboard.php"
               style="color:var(--accent);font-weight:900">Dashboard</a>
          </li>
        </ul>
      </nav>
      <button class="nav-toggle" type="button" aria-label="Open menu" aria-expanded="false">☰</button>
    </div>
  </header>

  <!-- ===================== MAIN ===================== -->
  <main>

    <section class="section page-hero">
      <div class="container">
        <h1>Shipping Schedule</h1>
        <p class="muted">
          Live shipment activity per carrier — pulled directly from the TrackingMore API.
          Each cell shows active shipments, current status, and recorded times.
        </p>
      </div>
    </section>

    <section class="section" id="scheduleTable">
      <div class="container">

        <?php if ($apiError): ?>
        <div class="api-error-banner">
          <span>⚠</span>
          <span>API error: <?= htmlspecialchars($apiError) ?></span>
        </div>
        <?php endif; ?>

        <?php if (!empty($carriers)): ?>

        <!-- Live stats bar -->
        <div class="api-stats-bar">
          <div style="display:flex;align-items:center;gap:10px;">
            <span class="api-live-dot"></span>
            <div>
              <p class="api-stats-label">
                <?= $totalActive ?> active shipment<?= $totalActive !== 1 ? 's' : '' ?> tracked live
              </p>
              <p class="api-stats-updated">Last update: <?= htmlspecialchars($lastUpdatedFormatted) ?></p>
            </div>
          </div>
          <div style="display:flex;flex-wrap:wrap;gap:10px;">
            <?php foreach ($carriers as $label => $data): ?>
            <div class="api-carrier-chip">
              <strong><?= htmlspecialchars($label) ?></strong>
              <span class="chip-count"><?= $data['total'] ?></span>
              <span class="chip-status">
                <?= htmlspecialchars(topStatus(array_column($data['shipments'], 'status', 'status') ?: [])) ?>
              </span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Schedule table — built entirely from API data -->
        <h2>Weekly Pickup &amp; Delivery Activity</h2>
        <p>
          Each row shows a carrier's live shipment activity by day of the week.
          Counts reflect shipments created on that day. Times are from real checkpoint data.
        </p>

        <div class="table-wrap">
          <table class="schedule-table" id="mainSchedule">
            <caption>ShipSmart Live Shipping Schedule — from TrackingMore API</caption>
            <thead>
              <tr>
                <th>Carrier</th>
                <th>Service</th>
                <th>Sunday</th>
                <th>Monday</th>
                <th>Tuesday</th>
                <th>Wednesday</th>
                <th>Thursday</th>
                <th>Friday</th>
                <th>Saturday</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($carriers as $label => $data):
                $summary        = buildDaySummary($data['shipments']);
                $workDays       = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
                $pickupGroups   = makeGroups($workDays, $summary, 'pickup');
                $deliveryGroups = makeGroups($workDays, $summary, 'delivery');
            ?>

              <!-- ── <?= htmlspecialchars($label) ?> ── -->
              <tr>
                <td rowspan="2" class="carrier-cell">
                  <?= htmlspecialchars($label) ?>
                  <br>
                  <small style="font-weight:700;color:#fff;opacity:0.75;font-size:0.7rem;">
                    <?= $data['total'] ?> shipment<?= $data['total'] !== 1 ? 's' : '' ?>
                  </small>
                </td>
                <td>Pickup</td>

                <?php foreach ($pickupGroups as $g):
                    $d      = $g['data'];
                    $span   = $g['span'];
                    $isMerged = $span > 1 && $g['key'] !== '__empty__';
                    $colspan  = $span > 1 ? ' colspan="' . $span . '"' : '';
                    $mergedCls = $isMerged ? ' merged-cell' : '';
                ?>
                <td class="day-cell<?= $mergedCls ?>"<?= $colspan ?>>
                  <?php if ($d['count'] > 0): ?>
                    <?php
                      arsort($d['statuses']);
                      $topSt  = array_key_first($d['statuses']);
                      $topLbl = $GLOBALS['statusLabels'][$topSt] ?? ucfirst($topSt);
                    ?>
                    <span>
                      <?= $d['count'] * $span ?> shipment<?= ($d['count'] * $span) !== 1 ? 's' : '' ?>
                      <?php if ($isMerged): ?>(<?= $span ?> days)<?php endif; ?>
                    </span>
                    <span class="day-status-pill badge-status-<?= statusClass($topSt) ?>">
                      <?= htmlspecialchars($topLbl) ?>
                    </span>
                    <?php if ($d['pickup']): ?>
                    <span class="day-time">⏱ <?= htmlspecialchars($d['pickup']) ?></span>
                    <?php endif; ?>
                  <?php else: ?>
                    <span class="cell-empty">—</span>
                  <?php endif; ?>
                </td>
                <?php endforeach; ?>
              </tr>

              <!-- Delivery row — rowspan already set on carrier cell above -->
              <tr>
                <td>Delivery</td>

                <?php foreach ($deliveryGroups as $g):
                    $d        = $g['data'];
                    $span     = $g['span'];
                    $isMerged = $span > 1 && $g['key'] !== '__empty__';
                    $colspan  = $span > 1 ? ' colspan="' . $span . '"' : '';
                    $mergedCls = $isMerged ? ' merged-cell' : '';
                ?>
                <td class="day-cell<?= $mergedCls ?>"<?= $colspan ?>>
                  <?php if ($d['count'] > 0 && $d['delivery']): ?>
                    <span class="day-time">
                      ⏱ <?= htmlspecialchars($d['delivery']) ?>
                      <?php if ($isMerged): ?>(<?= $span ?> days)<?php endif; ?>
                    </span>
                  <?php elseif ($d['count'] > 0): ?>
                    <span class="cell-empty" style="font-size:0.8rem;">No delivery time</span>
                  <?php else: ?>
                    <span class="cell-empty">—</span>
                  <?php endif; ?>
                </td>
                <?php endforeach; ?>
              </tr>

            <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="schedule-info">
          <h3>Important Notes</h3>
          <ul>
            <li>All times are in <strong>Arabia Standard Time (AST)</strong>.</li>
            <li>Shipment counts reflect the day the tracking was created.</li>
            <li>Times are extracted from real carrier checkpoint events.</li>
            <li>Data is pulled live from the <strong>TrackingMore API</strong> on every page load.</li>
          </ul>
        </div>

        <?php else: ?>

        <!-- No data state -->
        <div class="card no-data-card">
          <div style="font-size:2.5rem;margin-bottom:12px;">📦</div>
          <h3>No shipments found</h3>
          <p>
            No tracking data is available yet from the API.<br>
            Register shipments via the TrackingMore dashboard or the Search page to see live schedule data here.
          </p>
        </div>

        <?php endif; ?>

      </div>
    </section>
  </main>

  <!-- ===================== FOOTER ===================== -->
  <footer class="site-footer">
    <div class="container footer-inner">
      <div class="footer-grid">
        <div>
          <div class="footer-brand">
            <img class="footer-logo" src="../images/shipsmart-logo-3.svg" alt="ShipSmart logo">
            <strong>ShipSmart</strong>
          </div>
          <p class="muted">Universal Shipment Tracker &mdash; a course project for CPCS 403.</p>
          <p class="small">Tracking smarter, one parcel at a time.</p>
        </div>
        <div>
          <p class="footer-title">Pages</p>
          <ul class="footer-links">
            <li><a href="../index.html">Home</a></li>
            <li><a href="services.html">About</a></li>
            <li><a href="schedule.php">Schedule</a></li>
            <li><a href="search.html">Search</a></li>
            <li><a href="upload.html">Upload</a></li>
            <li><a href="video.html">Video</a></li>
            <li><a href="feedback.html">Feedback</a></li>
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

  <script src="../scripts/main.js"></script>
</body>
</html>