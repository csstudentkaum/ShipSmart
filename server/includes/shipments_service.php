<?php
/**
 * TrackingMore shipment list + CRUD helpers for admin dashboard.
 */

require_once __DIR__ . '/trackingmore_sdk.php';
require_once __DIR__ . '/shipment_validation.php';
require_once __DIR__ . '/shipment_meta_store.php';

/** Split TrackingMore title into origin / destination (API may return HTML entities). */
function shipments_parse_route_title(string $title): array
{
    $title = html_entity_decode(trim($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    if ($title === '') {
        return ['', ''];
    }

    foreach (['→', '->', '—', '–', ' to '] as $sep) {
        if (str_contains($title, $sep)) {
            return array_map('trim', explode($sep, $title, 2));
        }
    }

    return [$title, ''];
}

/** @return array<int, array<string, mixed>> */
function shipments_normalize_list(array $data): array
{
    $shipments = [];
    foreach ($data as $t) {
        $title  = (string) ($t['title'] ?? '');
        [$origin, $dest] = shipments_parse_route_title($title);
        if ($origin === '' && $dest === '') {
            $origin = (string) ($t['origin_country'] ?? '');
            $dest   = (string) ($t['destination_country'] ?? '');
        }
        $apiWeight = $t['weight'] ?? $t['weight_kg'] ?? null;
        $apiEta    = $t['scheduled_delivery_date'] ?? null;

        $shipments[] = [
            'id'                 => $t['id']               ?? '',
            'tracking_number'    => $t['tracking_number']  ?? '',
            'carrier'            => $t['courier_code']      ?? '',
            'origin_city'        => $origin,
            'destination_city'   => $dest,
            'status'             => $t['delivery_status']   ?? 'pending',
            'category'           => $t['product_type']      ?? '—',
            'weight_kg'          => ($apiWeight !== null && $apiWeight !== '') ? (string) $apiWeight : '—',
            'estimated_delivery' => ($apiEta !== null && $apiEta !== '') ? (string) $apiEta : '—',
            'note'               => $t['note']              ?? '',
            'last_updated'       => $t['update_at']         ?? $t['created_at'] ?? '',
            'latest_event'       => $t['latest_event']      ?? '',
            'created_at'         => $t['created_at']        ?? '',
        ];
    }

    return shipment_meta_merge_list($shipments);
}

/**
 * @return array{ok: bool, shipments: array, message?: string, api_code?: int}
 */
function shipments_fetch_all(): array
{
    try {
        $sdk      = trackingmore_trackings();
        $response = $sdk->getTrackingResults([]);

        $code = (int) ($response['meta']['code'] ?? 0);
        if ($code === 200) {
            return [
                'ok'         => true,
                'shipments'  => shipments_normalize_list($response['data'] ?? []),
                'api_code'   => $code,
            ];
        }

        return [
            'ok'        => false,
            'shipments' => [],
            'message'   => $response['meta']['message'] ?? 'Unknown API error',
            'api_code'  => $code,
        ];
    } catch (\TrackingMore\TrackingMoreException $e) {
        return ['ok' => false, 'shipments' => [], 'message' => $e->getMessage()];
    }
}

/** Resolve TrackingMore tracking id after create or duplicate (4101). */
function shipments_resolve_tracking_id(
    \TrackingMore\Trackings $sdk,
    array $createParams,
    ?array $createResponse
): ?string {
    if (!empty($createResponse['data']['id'])) {
        return (string) $createResponse['data']['id'];
    }

    $tn = $createParams['tracking_number'] ?? '';
    $cc = $createParams['courier_code'] ?? '';
    if ($tn === '') {
        return null;
    }

    $query = ['tracking_numbers' => $tn];
    if ($cc !== '') {
        $query['courier_code'] = $cc;
    }

    $resp = $sdk->getTrackingResults($query);
    if ((int) ($resp['meta']['code'] ?? 0) !== 200) {
        return null;
    }

    foreach ($resp['data'] ?? [] as $row) {
        if (($row['tracking_number'] ?? '') === $tn) {
            return (string) ($row['id'] ?? '');
        }
    }

    return null;
}

/**
 * Apply weight / ETA via update API (not allowed on create in v4).
 *
 * @return array{ok: bool, message: string}
 */
function shipments_apply_create_metadata(
    \TrackingMore\Trackings $sdk,
    string $id,
    array $updateParams
): array {
    if ($updateParams === []) {
        return ['ok' => true, 'message' => ''];
    }

    $res  = $sdk->updateTrackingByID($id, $updateParams);
    $code = (int) ($res['meta']['code'] ?? 0);

    if ($code === 200) {
        return ['ok' => true, 'message' => ''];
    }

    return [
        'ok'      => false,
        'message' => 'Shipment was created, but weight/ETA could not be saved on TrackingMore: '
            . ($res['meta']['message'] ?? 'Unknown error'),
    ];
}

/** Persist weight / ETA locally (TrackingMore list API does not return them). */
function shipments_save_local_meta(
    string $trackingmoreId,
    string $trackingNumber,
    string $courierCode,
    array $updateParams
): void {
    $fields = [];
    if (array_key_exists('weight', $updateParams)) {
        $fields['weight_kg'] = (string) $updateParams['weight'];
    }
    if (array_key_exists('scheduled_delivery_date', $updateParams)) {
        $fields['estimated_delivery'] = (string) $updateParams['scheduled_delivery_date'];
    }
    if ($fields !== []) {
        shipment_meta_upsert($trackingmoreId, $trackingNumber, $courierCode, $fields);
    }
}

/**
 * @return array{ok: bool, message: string, api_code?: int}
 */
function shipments_add(array $post): array
{
    $validated = validate_add_shipment($post);
    if (!$validated['ok']) {
        return ['ok' => false, 'message' => shipment_validation_flash($validated['errors'])];
    }

    try {
        $sdk          = trackingmore_trackings();
        $res          = $sdk->createTracking($validated['params']);
        $code         = (int) ($res['meta']['code'] ?? 0);
        $updateParams = $validated['update_params'] ?? [];

        if ($code === 200) {
            $message = 'Shipment added and registered with TrackingMore.';
            if ($updateParams !== []) {
                $id = shipments_resolve_tracking_id($sdk, $validated['params'], $res);
                if ($id) {
                    $meta = shipments_apply_create_metadata($sdk, $id, $updateParams);
                    shipments_save_local_meta(
                        $id,
                        $validated['params']['tracking_number'],
                        $validated['params']['courier_code'],
                        $updateParams
                    );
                    if (!$meta['ok']) {
                        return ['ok' => true, 'message' => $message . ' ' . $meta['message'], 'api_code' => $code];
                    }
                    $message .= ' Weight and delivery date saved.';
                }
            }
            return ['ok' => true, 'message' => $message, 'api_code' => $code];
        }

        if ($code === 4101) {
            $message = 'Tracking number already exists in your TrackingMore account.';
            if ($updateParams !== []) {
                $id = shipments_resolve_tracking_id($sdk, $validated['params'], $res);
                if ($id) {
                    $meta = shipments_apply_create_metadata($sdk, $id, $updateParams);
                    shipments_save_local_meta(
                        $id,
                        $validated['params']['tracking_number'],
                        $validated['params']['courier_code'],
                        $updateParams
                    );
                    if (!$meta['ok']) {
                        return ['ok' => true, 'message' => $message . ' ' . $meta['message'], 'api_code' => $code];
                    }
                    $message .= ' Weight and delivery date updated.';
                }
            }
            return ['ok' => true, 'message' => $message, 'api_code' => $code];
        }

        return [
            'ok'      => false,
            'message' => 'TrackingMore: ' . trackingmore_create_error_message(
                $code,
                (string) ($res['meta']['message'] ?? 'Unknown error')
            ),
            'api_code' => $code,
        ];
    } catch (\TrackingMore\TrackingMoreException $e) {
        return ['ok' => false, 'message' => 'SDK error: ' . $e->getMessage()];
    }
}

/**
 * @return array{ok: bool, message: string, api_code?: int}
 */
function shipments_edit(array $post): array
{
    $id = trim((string) ($post['id'] ?? ''));
    if ($id === '') {
        return ['ok' => false, 'message' => 'Missing tracking ID.'];
    }

    $params = [];
    $origin = trim((string) ($post['origin_city'] ?? ''));
    $dest   = trim((string) ($post['destination_city'] ?? ''));
    $weight = trim((string) ($post['weight_kg'] ?? ''));
    $eta    = trim((string) ($post['estimated_delivery'] ?? ''));
    $note   = trim((string) ($post['note'] ?? ''));

    $hasRoute = array_key_exists('origin_city', $post) || array_key_exists('destination_city', $post);
    if ($hasRoute) {
        $params['title'] = $origin . ($dest !== '' ? ' → ' . $dest : '');
    }
    if ($weight !== '') {
        if (!is_numeric($weight) || (float) $weight <= 0 || (float) $weight > 99999) {
            return ['ok' => false, 'message' => 'Weight must be a positive number up to 99999 kg.'];
        }
        $params['weight'] = $weight;
    }
    if ($eta !== '') {
        $dt = \DateTime::createFromFormat('Y-m-d', $eta);
        if (!$dt || $dt->format('Y-m-d') !== $eta) {
            return ['ok' => false, 'message' => 'Estimated delivery must be a valid date (YYYY-MM-DD).'];
        }
        $params['scheduled_delivery_date'] = $eta;
    }
    if (array_key_exists('note', $post)) {
        $params['note'] = $note;
    }

    if ($params === []) {
        return ['ok' => false, 'message' => 'Nothing to update — please change at least one field.'];
    }

    try {
        $sdk  = trackingmore_trackings();
        $res  = $sdk->updateTrackingByID($id, $params);
        $code = (int) ($res['meta']['code'] ?? 0);

        if ($code === 200) {
            $tracking = trim((string) ($post['tracking_number'] ?? ''));
            $carrier  = strtolower(trim((string) ($post['carrier'] ?? $post['courier_code'] ?? '')));

            $localMeta = [];
            if (array_key_exists('weight_kg', $post)) {
                $localMeta['weight_kg'] = $weight !== '' ? $weight : null;
            }
            if (array_key_exists('estimated_delivery', $post)) {
                $localMeta['estimated_delivery'] = $eta !== '' ? $eta : null;
            }
            if ($localMeta !== [] && $tracking !== '') {
                shipment_meta_upsert($id, $tracking, $carrier, $localMeta);
            }

            return ['ok' => true, 'message' => 'Shipment updated successfully.', 'api_code' => $code];
        }

        return [
            'ok'      => false,
            'message' => 'TrackingMore: ' . ($res['meta']['message'] ?? 'Unknown error'),
            'api_code' => $code,
        ];
    } catch (\TrackingMore\TrackingMoreException $e) {
        return ['ok' => false, 'message' => 'SDK error: ' . $e->getMessage()];
    }
}

/**
 * @return array{ok: bool, message: string, api_code?: int}
 */
function shipments_delete(array $post): array
{
    $id = trim((string) ($post['id'] ?? ''));
    if ($id === '') {
        return ['ok' => false, 'message' => 'Missing tracking ID.'];
    }

    try {
        $sdk  = trackingmore_trackings();
        $res  = $sdk->deleteTrackingByID($id);
        $code = (int) ($res['meta']['code'] ?? 0);

        if ($code === 200) {
            shipment_meta_remove($id);
            return ['ok' => true, 'message' => 'Shipment deleted from TrackingMore.', 'api_code' => $code];
        }

        return [
            'ok'      => false,
            'message' => 'TrackingMore: ' . ($res['meta']['message'] ?? 'Unknown error'),
            'api_code' => $code,
        ];
    } catch (\TrackingMore\TrackingMoreException $e) {
        return ['ok' => false, 'message' => 'SDK error: ' . $e->getMessage()];
    }
}
