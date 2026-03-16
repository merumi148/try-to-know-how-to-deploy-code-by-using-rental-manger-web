<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['PUT']);

$landlordId = require_landlord_id();

$input = get_json_input();
require_fields($input, ['id']);

$paymentId = (int)$input['id'];
$allowed = ['month_year', 'total_amount', 'payment_status'];

$setParts = [];
$types = '';
$values = [];

foreach ($allowed as $field) {
    if (!array_key_exists($field, $input)) {
        continue;
    }

    $setParts[] = "p.{$field} = ?";
    if ($field === 'total_amount') {
        $types .= 'd';
        $values[] = (float)$input[$field];
    } else {
        $types .= 's';
        $values[] = trim((string)$input[$field]);
    }
}

if (!$setParts) {
    json_response(false, 'No fields to update', [], 422);
}

if (array_key_exists('month_year', $input) && !preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', trim((string)$input['month_year']))) {
    json_response(false, 'month_year must be in YYYY-MM format', [], 422);
}

try {
    $db = (new Database())->getMysqliConnection();

    $scopeStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT p.id
         FROM payments p
         INNER JOIN contracts c ON c.id = p.contract_id
         INNER JOIN rooms r ON r.id = c.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE p.id = ? AND b.landlord_id = ?
         LIMIT 1'
    );
    mysqli_bind_and_execute($scopeStmt, 'ii', [$paymentId, $landlordId]);
    if (!mysqli_fetch_one_assoc($scopeStmt)) {
        json_response(false, 'Payment not found for current landlord', [], 404);
    }

    $sql = 'UPDATE payments p
            INNER JOIN contracts c ON c.id = p.contract_id
            INNER JOIN rooms r ON r.id = c.room_id
            INNER JOIN buildings b ON b.id = r.building_id
            SET ' . implode(', ', $setParts) . '
            WHERE p.id = ? AND b.landlord_id = ?';

    $stmt = mysqli_prepare_or_fail($db, $sql);
    $typesWithScope = $types . 'ii';
    $valuesWithScope = array_merge($values, [$paymentId, $landlordId]);
    mysqli_bind_and_execute($stmt, $typesWithScope, $valuesWithScope);

    json_response(true, 'Payment updated successfully', ['payment_id' => $paymentId]);
} catch (Throwable $e) {
    json_response(false, 'Failed to update payment', ['error' => $e->getMessage()], 500);
}
