<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['PUT']);

$landlordId = require_landlord_id();

$input = get_json_input();
require_fields($input, ['id']);

$contractId = (int)$input['id'];
$allowed = ['room_id', 'tenant_id', 'start_date', 'end_date', 'deposit_amount', 'status'];

$setParts = [];
$types = '';
$values = [];

foreach ($allowed as $field) {
    if (!array_key_exists($field, $input)) {
        continue;
    }

    $setParts[] = "c.{$field} = ?";

    if (in_array($field, ['room_id', 'tenant_id'], true)) {
        $types .= 'i';
        $values[] = (int)$input[$field];
    } elseif ($field === 'deposit_amount') {
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

try {
    $db = (new Database())->getMysqliConnection();

    // Contract must belong to current landlord.
    $scopeStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT c.id
         FROM contracts c
         INNER JOIN rooms r ON r.id = c.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE c.id = ? AND b.landlord_id = ?
         LIMIT 1'
    );
    mysqli_bind_and_execute($scopeStmt, 'ii', [$contractId, $landlordId]);
    if (!mysqli_fetch_one_assoc($scopeStmt)) {
        json_response(false, 'Contract not found for current landlord', [], 404);
    }

    if (array_key_exists('room_id', $input)) {
        $newRoomId = (int)$input['room_id'];
        $roomStmt = mysqli_prepare_or_fail(
            $db,
            'SELECT r.id
             FROM rooms r
             INNER JOIN buildings b ON b.id = r.building_id
             WHERE r.id = ? AND b.landlord_id = ?
             LIMIT 1'
        );
        mysqli_bind_and_execute($roomStmt, 'ii', [$newRoomId, $landlordId]);
        if (!mysqli_fetch_one_assoc($roomStmt)) {
            json_response(false, 'Target room does not belong to current landlord', [], 422);
        }
    }

    if (array_key_exists('tenant_id', $input)) {
        $tenantStmt = mysqli_prepare_or_fail($db, 'SELECT id FROM users WHERE id = ? AND role = ? LIMIT 1');
        mysqli_bind_and_execute($tenantStmt, 'is', [(int)$input['tenant_id'], 'tenant']);
        if (!mysqli_fetch_one_assoc($tenantStmt)) {
            json_response(false, 'Tenant not found', [], 404);
        }
    }

    if (array_key_exists('start_date', $input) || array_key_exists('end_date', $input)) {
        $dateStmt = mysqli_prepare_or_fail($db, 'SELECT start_date, end_date FROM contracts WHERE id = ? LIMIT 1');
        mysqli_bind_and_execute($dateStmt, 'i', [$contractId]);
        $current = mysqli_fetch_one_assoc($dateStmt);
        if (!$current) {
            json_response(false, 'Contract not found', [], 404);
        }

        $startDate = array_key_exists('start_date', $input) ? trim((string)$input['start_date']) : (string)$current['start_date'];
        $endDate = array_key_exists('end_date', $input) ? trim((string)$input['end_date']) : (string)$current['end_date'];
        if (strtotime($startDate) === false || strtotime($endDate) === false || $startDate > $endDate) {
            json_response(false, 'Invalid start_date/end_date', [], 422);
        }
    }

    $sql = 'UPDATE contracts c
            INNER JOIN rooms r ON r.id = c.room_id
            INNER JOIN buildings b ON b.id = r.building_id
            SET ' . implode(', ', $setParts) . '
            WHERE c.id = ? AND b.landlord_id = ?';

    $stmt = mysqli_prepare_or_fail($db, $sql);
    $typesWithScope = $types . 'ii';
    $valuesWithScope = array_merge($values, [$contractId, $landlordId]);
    mysqli_bind_and_execute($stmt, $typesWithScope, $valuesWithScope);

    json_response(true, 'Contract updated successfully', ['contract_id' => $contractId]);
} catch (Throwable $e) {
    json_response(false, 'Failed to update contract', ['error' => $e->getMessage()], 500);
}
