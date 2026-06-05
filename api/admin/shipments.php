<?php
/*
 * Admin shipments API — proxies TrackingMore for the dashboard.
 * GET    → list shipments (trackings/get)
 * POST   → add (trackings/create)
 * PUT    → edit (trackings/update/{id})
 * DELETE → delete (trackings/delete/{id})
 */

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../server/includes/auth.php';
require_once __DIR__ . '/../../server/includes/shipments_service.php';

require_admin(1);

/** @return array<string, mixed> */
function shipments_api_input(): array
{
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if ($method === 'GET' || $method === 'DELETE') {
        $input = $_GET;
        $raw   = file_get_contents('php://input') ?: '';
        if ($raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $input = array_merge($input, $json);
            }
        }
        return $input;
    }

    if (!empty($_POST)) {
        return $_POST;
    }

    $raw = file_get_contents('php://input') ?: '';
    if ($raw === '') {
        return [];
    }

    $json = json_decode($raw, true);
    if (is_array($json)) {
        return $json;
    }

    parse_str($raw, $parsed);
    return is_array($parsed) ? $parsed : [];
}

function shipments_api_respond(array $result): void
{
    $list = shipments_fetch_all();

    http_response_code($result['ok'] ? 200 : 400);
    echo json_encode([
        'ok'         => $result['ok'],
        'message'    => $result['message'],
        'api_code'   => $result['api_code'] ?? null,
        'shipments'  => $list['shipments'],
        'list_ok'    => $list['ok'],
        'list_error' => $list['message'] ?? null,
        'source'     => 'trackingmore',
    ]);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input  = shipments_api_input();

if ($method === 'GET') {
    $result = shipments_fetch_all();
    http_response_code($result['ok'] ? 200 : 502);
    echo json_encode([
        'ok'        => $result['ok'],
        'shipments' => $result['shipments'],
        'message'   => $result['message'] ?? null,
        'api_code'  => $result['api_code'] ?? null,
        'source'    => 'trackingmore',
    ]);
    exit;
}

if ($method === 'PUT') {
    $input['action'] = $input['action'] ?? 'edit';
    if (($input['action'] ?? '') !== 'edit') {
        http_response_code(400);
        echo json_encode(['ok' => false, 'message' => 'PUT only supports action=edit.']);
        exit;
    }
    shipments_api_respond(shipments_edit($input));
    exit;
}

if ($method === 'DELETE') {
    shipments_api_respond(shipments_delete($input));
    exit;
}

if ($method === 'POST') {
    $action = trim((string) ($input['action'] ?? ''));
    if ($action !== 'add') {
        shipments_api_respond([
            'ok'      => false,
            'message' => 'Use DELETE to remove a shipment, PUT to edit, POST only for add.',
        ]);
        exit;
    }
    shipments_api_respond(shipments_add($input));
    exit;
}

http_response_code(405);
echo json_encode(['ok' => false, 'message' => 'Method not allowed. Use GET, POST, PUT, or DELETE.']);
