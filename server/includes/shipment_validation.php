<?php
/**
 * Input validation for TrackingMore create tracking (POST /v4/trackings/create).
 * Maps rules to API meta codes documented in trackingmore-sdk-php README.
 */

function shipment_allowed_carriers(): array
{
    return ['aramex', 'dhl', 'fedex', 'smsa', 'smsa-express', 'ups', 'usps'];
}

/**
 * TrackingMore v4 create only accepts a subset of fields.
 * weight and scheduled_delivery_date must be sent via update after create.
 *
 * @return array{
 *   ok: bool,
 *   errors: array<string, string>,
 *   params: array<string, mixed>,
 *   update_params: array<string, mixed>
 * }
 */
function validate_add_shipment(array $input): array
{
    $errors = [];
    $allowed = shipment_allowed_carriers();

    $tracking = trim((string) ($input['tracking_number'] ?? ''));
    $carrier  = strtolower(trim((string) ($input['carrier'] ?? $input['courier_code'] ?? '')));

    // 4111 — Tracking_number is required
    if ($tracking === '') {
        $errors['tracking_number'] = 'Tracking number is required.';
    }
    // 4110 — invalid tracking_number
    elseif (!preg_match('/^[A-Za-z0-9\-]{5,50}$/', $tracking)) {
        $errors['tracking_number'] = 'Invalid tracking number (use 5–50 letters, numbers, or hyphens).';
    }

    // 4120 — invalid courier_code (SDK: ErrMissingCourierCode when empty)
    if ($carrier === '') {
        $errors['carrier'] = 'Carrier is required.';
    } elseif (!in_array($carrier, $allowed, true)) {
        $errors['carrier'] = 'Invalid carrier. Please select a supported courier.';
    }

    $origin = trim((string) ($input['origin_city'] ?? ''));
    $dest   = trim((string) ($input['destination_city'] ?? ''));
    $weight = trim((string) ($input['weight_kg'] ?? ''));
    $eta    = trim((string) ($input['estimated_delivery'] ?? ''));
    $note   = trim((string) ($input['note'] ?? ''));

    if (strlen($origin) > 80 || strlen($dest) > 80) {
        $errors['route'] = 'Origin and destination must be 80 characters or fewer.';
    }

    if ($weight !== '') {
        if (!is_numeric($weight) || (float) $weight <= 0 || (float) $weight > 99999) {
            $errors['weight_kg'] = 'Weight must be a positive number up to 99999 kg.';
        }
    }

    if ($eta !== '') {
        $dt = \DateTime::createFromFormat('Y-m-d', $eta);
        if (!$dt || $dt->format('Y-m-d') !== $eta) {
            $errors['estimated_delivery'] = 'Estimated delivery must be a valid date (YYYY-MM-DD).';
        }
    }

    if (strlen($note) > 500) {
        $errors['note'] = 'Note must be 500 characters or fewer.';
    }

    $params = [
        'tracking_number' => $tracking,
        'courier_code'    => $carrier,
    ];

    if ($origin !== '' || $dest !== '') {
        $params['title'] = $origin . ($dest !== '' ? ' → ' . $dest : '');
    }
    if ($note !== '') {
        $params['note'] = $note;
    }

    $updateParams = [];
    if ($weight !== '') {
        $updateParams['weight'] = $weight;
    }
    if ($eta !== '') {
        $updateParams['scheduled_delivery_date'] = $eta;
    }

    return [
        'ok'            => $errors === [],
        'errors'        => $errors,
        'params'        => $params,
        'update_params' => $updateParams,
    ];
}

/** User-facing message for TrackingMore create API meta codes. */
function trackingmore_create_error_message(int $code, string $fallback = 'Unknown error'): string
{
    $map = [
        4110 => 'The tracking number format is invalid.',
        4111 => 'Tracking number is required.',
        4120 => 'The selected carrier code is invalid.',
        4121 => 'Could not detect a carrier for this tracking number. Try another carrier.',
        4122 => 'This carrier requires additional fields not provided.',
        4130 => 'Invalid field sent to TrackingMore (weight and ETA are applied after create).',
        4101 => 'This tracking number already exists in TrackingMore.',
        4190 => 'API quota exceeded. Please try again later or upgrade your plan.',
        401  => 'API authentication failed. Check your API key.',
        429  => 'Too many API requests. Please try again later.',
    ];

    return $map[$code] ?? ($fallback !== '' ? $fallback : 'Unknown error');
}

/** Flatten field errors into one flash string. */
function shipment_validation_flash(array $errors): string
{
    return implode(' ', array_values($errors));
}
