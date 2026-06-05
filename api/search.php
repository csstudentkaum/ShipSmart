<?php
/*
 * File: api/search.php
 * Purpose: Search and filter shipments via GET parameters, return JSON results
 */

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(400);
    echo json_encode(['error' => 'Only GET requests are allowed.']);
    exit;
}

require_once __DIR__ . '/../server/includes/db.php';

// ── Helper: save a TrackingMore result into local shipments table ─────────────
function search_save_to_local_db(mysqli $conn, array $item): void
{
    $tracking = trim((string) ($item['tracking_number'] ?? ''));
    $carrier  = strtolower(trim((string) ($item['carrier'] ?? '')));
    $origin   = trim((string) ($item['origin_city'] ?? ''));
    $dest     = trim((string) ($item['destination_city'] ?? ''));
    $status   = trim((string) ($item['status'] ?? 'created'));
    $category = trim((string) ($item['category'] ?? 'standard'));
    $weight   = trim((string) ($item['weight_kg'] ?? '0'));
    $eta      = trim((string) ($item['estimated_delivery'] ?? ''));
    $now      = date('Y-m-d H:i:s');

    if ($tracking === '') return;

    // Normalize carrier to local ENUM values
    $carrierMap = ['smsa-express' => 'smsa', 'ups' => 'aramex', 'usps' => 'aramex',
                   'fedex-international' => 'fedex', 'dhl-express' => 'dhl'];
    if (isset($carrierMap[$carrier])) $carrier = $carrierMap[$carrier];
    if (!in_array($carrier, ['aramex','dhl','fedex','smsa'], true)) $carrier = 'aramex';

    // Normalize status to local ENUM values
    $statusMap = ['delivered' => 'delivered', 'transit' => 'in_transit',
                  'in_transit' => 'in_transit', 'pickup' => 'picked_up',
                  'picked_up' => 'picked_up', 'undelivered' => 'out_for_delivery',
                  'out_for_delivery' => 'out_for_delivery', 'pending' => 'created',
                  'created' => 'created', 'notfound' => 'created'];
    $statusNorm = $statusMap[strtolower($status)] ?? 'created';

    if (!in_array($category, ['standard','express','freight'], true)) $category = 'standard';
    if ($origin  === '') $origin  = 'Unknown';
    if ($dest    === '') $dest    = 'Unknown';
    if (!is_numeric($weight) || (float)$weight <= 0) $weight = '0.00';
    if ($eta === '' || strtotime($eta) === false) $eta = date('Y-m-d', strtotime('+7 days'));

    $stmt = $conn->prepare(
        'INSERT INTO shipments
            (tracking_number, carrier, origin_city, destination_city, status, category, weight_kg, estimated_delivery, last_updated)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE
            status            = VALUES(status),
            origin_city       = VALUES(origin_city),
            destination_city  = VALUES(destination_city),
            weight_kg         = VALUES(weight_kg),
            estimated_delivery= VALUES(estimated_delivery),
            last_updated      = VALUES(last_updated)'
    );
    if (!$stmt) return;
    $stmt->bind_param('sssssssss',
        $tracking, $carrier, $origin, $dest,
        $statusNorm, $category, $weight, $eta, $now
    );
    $stmt->execute();
    $stmt->close();
}


// If TRACKINGMORE_API_KEY is defined in environment or in server/config, use the SDK
$tmApiKey = getenv('TRACKINGMORE_API_KEY') ?: (file_exists(__DIR__ . '/../server/config.php') ? include __DIR__ . '/../server/config.php' : '');

$q          = trim($_GET['q'] ?? '');
$carrier    = trim($_GET['carrier'] ?? '');
$status     = trim($_GET['status'] ?? '');
$category   = trim($_GET['category'] ?? '');
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');

$allowedCarriers   = ['aramex', 'dhl', 'fedex', 'smsa-express'];
$allowedStatuses   = ['created', 'picked_up', 'in_transit', 'out_for_delivery', 'delivered'];
$allowedCategories = ['standard', 'express', 'freight'];

$sql    = 'SELECT id, tracking_number, carrier, origin_city, destination_city,
                  status, category, weight_kg, estimated_delivery, last_updated, created_at
           FROM shipments WHERE 1=1';
$params = [];
$types  = '';

if ($q !== '') {
    $sql .= ' AND (tracking_number LIKE ? OR origin_city LIKE ? OR destination_city LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $types .= 'sss';
}

if ($carrier !== '' && in_array($carrier, $allowedCarriers, true)) {
    $sql .= ' AND carrier = ?';
    $params[] = $carrier;
    $types .= 's';
}

if ($status !== '' && in_array($status, $allowedStatuses, true)) {
    $sql .= ' AND status = ?';
    $params[] = $status;
    $types .= 's';
}

if ($category !== '' && in_array($category, $allowedCategories, true)) {
    $sql .= ' AND category = ?';
    $params[] = $category;
    $types .= 's';
}

if ($dateFrom !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $sql .= ' AND estimated_delivery >= ?';
    $params[] = $dateFrom;
    $types .= 's';
}

if ($dateTo !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $sql .= ' AND estimated_delivery <= ?';
    $params[] = $dateTo;
    $types .= 's';
}

$sql .= ' ORDER BY last_updated DESC';

// Helper: detect if the query is likely a tracking number (alphanumeric, length 8-40)
$looksLikeTracking = $q !== '' && preg_match('/^[A-Z0-9\-]{8,40}$/i', str_replace(' ', '', $q));

// If client explicitly requests TrackingMore or the query looks like a tracking number,
// prefer the TrackingMore API (requires server/config.php or env var to provide API key).
// Local DB is always the default; TrackingMore only when explicitly requested
$useTrackingMore = !empty($_GET['source']) && $_GET['source'] === 'trackingmore';

if ($useTrackingMore && !empty($tmApiKey)) {
    // Load the bundled TrackingMore SDK in dependency order:
    // 1) interfaces, 2) Request trait, 3) remaining classes
    $sdkSrc = __DIR__ . '/../trackingmore/trackingmore-sdk-php/src';
    // Load interfaces first
    foreach (glob($sdkSrc . '/Interfaces/*.php') as $f) {
        require_once $f;
    }
    // Then the Request trait
    $requestFile = $sdkSrc . '/Request.php';
    if (file_exists($requestFile)) {
        require_once $requestFile;
    }
    // Finally load the rest of the files (skip Interfaces and Request)
    foreach (glob($sdkSrc . '/*.php') as $f) {
        $base = basename($f);
        if ($base === 'Request.php') continue;
        if (strpos($base, 'Interfaces') !== false) continue;
        require_once $f;
    }

    try {
        // ensure SDK classes are loaded
        $couriers = new \TrackingMore\Couriers($tmApiKey);
        $trackings = new \TrackingMore\Trackings($tmApiKey);

        // tracking-number-only flow required
        if (!$looksLikeTracking) {
            http_response_code(400);
            echo json_encode(['error' => 'TrackingMore endpoint requires a tracking-number-like query.']);
            exit;
        }

        // Step 1: Detect courier if not provided
        $chosenCourier = $carrier ?: '';
        if ($chosenCourier === '') {
            try {
                $det = $couriers->detect(['tracking_number' => $q]);
                if (is_array($det) && isset($det['data']) && is_array($det['data']) && count($det['data'])>0) {
                    $chosenCourier = $det['data'][0]['courier_code'] ?? '';
                }
            } catch (Exception $e) {
                error_log('TrackingMore detect error: ' . $e->getMessage());
            }
        }

        // Prepare params for get
        $params = ['tracking_numbers' => $q];
        if ($chosenCourier !== '') $params['courier_code'] = $chosenCourier;

        // Try to fetch tracking results
        $resp = $trackings->getTrackingResults($params);

        // If no data or API says "no exists" (meta code 4102), try to create then re-fetch
        $needCreate = false;
        if (!is_array($resp) || !isset($resp['meta']) || ($resp['meta']['code'] ?? 0) !== 200) {
            $code = $resp['meta']['code'] ?? null;
            if ($code === 4102) {
                $needCreate = true;
            }
        } else {
            // meta 200 but empty data
            if (empty($resp['data'])) {
                $needCreate = true;
            }
        }

        if ($needCreate) {
            try {
                // create tracking record (api requires tracking_number + courier_code)
                $createParams = ['tracking_number' => $q, 'courier_code' => $chosenCourier ?: null];
                $trackings->createTracking($createParams);
                // re-fetch
                $resp = $trackings->getTrackingResults($params);
            } catch (Exception $e) {
                error_log('TrackingMore create error: ' . $e->getMessage());
            }
        }

    $out = [];
        if (is_array($resp) && isset($resp['data']) && is_array($resp['data'])) {
            foreach ($resp['data'] as $item) {
                $originCity = $item['origin_city'] ?? ($item['origin_info']['origin_city'] ?? ($item['origin_info']['weblink'] ?? ''));
                $destCity = $item['destination_city'] ?? ($item['destination_info']['destination_city'] ?? ($item['destination_info']['weblink'] ?? ''));
                // extract tracking link and checkpoints when available
                $trackingLink = '';
                $checkpoints = [];
                if (!empty($item['origin_info']) && is_array($item['origin_info'])) {
                    $trackingLink = $item['origin_info']['tracking_link'] ?? $item['origin_info']['weblink'] ?? '';
                    if (!empty($item['origin_info']['trackinfo']) && is_array($item['origin_info']['trackinfo'])) {
                        foreach ($item['origin_info']['trackinfo'] as $t) {
                            $checkpoints[] = [
                                'time' => $t['time'] ?? $t['checkpoint_time'] ?? null,
                                'status' => $t['status_description'] ?? $t['status'] ?? null,
                                'location' => $t['location'] ?? $t['area'] ?? null,
                            ];
                        }
                    }
                }
                if (empty($checkpoints) && !empty($item['destination_info']) && is_array($item['destination_info'])) {
                    $trackingLink = $trackingLink ?: ($item['destination_info']['tracking_link'] ?? $item['destination_info']['weblink'] ?? '');
                    if (!empty($item['destination_info']['trackinfo']) && is_array($item['destination_info']['trackinfo'])) {
                        foreach ($item['destination_info']['trackinfo'] as $t) {
                            $checkpoints[] = [
                                'time' => $t['time'] ?? $t['checkpoint_time'] ?? null,
                                'status' => $t['status_description'] ?? $t['status'] ?? null,
                                'location' => $t['location'] ?? $t['area'] ?? null,
                            ];
                        }
                    }
                }

                $out[] = [
                    'tracking_number' => $item['tracking_number'] ?? $q,
                    'carrier' => $item['courier_code'] ?? $item['carrier_code'] ?? '',
                    'origin_city' => $originCity,
                    'destination_city' => $destCity,
                    'status' => $item['delivery_status'] ?? $item['substatus'] ?? ($item['tag'] ?? 'unknown'),
                    'category' => $item['product_type'] ?? $item['type'] ?? '',
                    'weight_kg' => $item['weight_kg'] ?? $item['weight'] ?? '',
                    'estimated_delivery' => $item['scheduled_delivery_date'] ?? $item['scheduled_delivery'] ?? '',
                    'last_updated' => $item['update_at'] ?? $item['latest_checkpoint_time'] ?? $item['created_at'] ?? '',
                    'tracking_link' => $trackingLink,
                    'checkpoints' => $checkpoints,
                ];
            }
        }
        // Apply server-side filters (status, category, date range) when using TrackingMore
        $filtered = $out;
        if (!empty($status) || !empty($category) || !empty($dateFrom) || !empty($dateTo)) {
            $filtered = array_filter($out, function($it) use ($status, $category, $dateFrom, $dateTo) {
                // status filter: substring match (case-insensitive)
                if (!empty($status)) {
                    $s = strtolower($it['status'] ?? '');
                    $req = strtolower($status);
                    if (strpos($s, $req) === false) return false;
                }
                // category filter: exact-ish match
                if (!empty($category)) {
                    $c = strtolower($it['category'] ?? '');
                    if ($c === '') return false;
                    if (strpos($c, strtolower($category)) === false) return false;
                }
                // date range: compare estimated_delivery if present
                if (!empty($dateFrom) || !empty($dateTo)) {
                    $ed = $it['estimated_delivery'] ?? '';
                    if (empty($ed)) return false;
                    $ts = strtotime($ed);
                    if ($ts === false) return false;
                    if (!empty($dateFrom)) {
                        $fromTs = strtotime($dateFrom . ' 00:00:00');
                        if ($ts < $fromTs) return false;
                    }
                    if (!empty($dateTo)) {
                        $toTs = strtotime($dateTo . ' 23:59:59');
                        if ($ts > $toTs) return false;
                    }
                }
                return true;
            });
            // reindex
            $filtered = array_values($filtered);
        }

        // Save each TrackingMore result to local DB
        if ($conn && !empty($out)) {
            foreach ($out as $tmItem) {
                search_save_to_local_db($conn, $tmItem);
            }
        }

        echo json_encode($filtered);
        exit;
    } catch (\TrackingMore\TrackingMoreException $e) {
        error_log('TrackingMore error: ' . $e->getMessage());
        http_response_code(502);
        echo json_encode(['error' => 'TrackingMore API error']);
        exit;
    } catch (Exception $e) {
        error_log('TrackingMore error: ' . $e->getMessage());
        // Fall back to DB search below
    }
}

$stmt = $conn->prepare($sql);

if (!$stmt) {
    http_response_code(500);
    echo json_encode(['error' => 'Query preparation failed.']);
    exit;
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

$shipments = [];
while ($row = $result->fetch_assoc()) {
    $shipments[] = $row;
}

echo json_encode($shipments);

$stmt->close();

// ── Log this search to tracking_queries ──────────────────────
if ($q !== '' && $looksLikeTracking) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $logUserId  = !empty($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    $logCarrier = $carrier !== '' ? $carrier : 'aramex'; // default if not filtered
    $foundInDb  = !empty($shipments) ? 1 : 0;
    $logIp      = $_SERVER['REMOTE_ADDR'] ?? null;
    $logStmt    = $conn->prepare(
        'INSERT INTO tracking_queries (user_id, tracking_number, carrier, found_in_db, ip_address)
         VALUES (?, ?, ?, ?, ?)'
    );
    if ($logStmt) {
        $logStmt->bind_param('issis', $logUserId, $q, $logCarrier, $foundInDb, $logIp);
        $logStmt->execute();
        $logStmt->close();
    }
}

$conn->close();
