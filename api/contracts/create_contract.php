<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['POST']);

$landlordId = require_landlord_id();

$input = get_json_input();
require_fields($input, ['tenant_id', 'room_id', 'start_date', 'end_date', 'deposit_amount', 'status']);

try {
    $db = (new Database())->getMysqliConnection();

    $tenantId = (int)$input['tenant_id'];
    $roomId = (int)$input['room_id'];

    $tenantStmt = mysqli_prepare_or_fail($db, 'SELECT id FROM users WHERE id = ? AND role = ? LIMIT 1');
    mysqli_bind_and_execute($tenantStmt, 'is', [$tenantId, 'tenant']);
    if (!mysqli_fetch_one_assoc($tenantStmt)) {
        json_response(false, 'Tenant not found', [], 404);
    }

    // Room must belong to the current landlord via buildings.
    $roomStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT r.id
         FROM rooms r
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE r.id = ? AND b.landlord_id = ?
         LIMIT 1'
    );
    mysqli_bind_and_execute($roomStmt, 'ii', [$roomId, $landlordId]);
    if (!mysqli_fetch_one_assoc($roomStmt)) {
        json_response(false, 'Room not found for current landlord', [], 404);
    }

    $startDate = trim((string)$input['start_date']);
    $endDate = trim((string)$input['end_date']);
    if (strtotime($startDate) === false || strtotime($endDate) === false) {
        json_response(false, 'Invalid start_date or end_date format', [], 422);
    }
    if ($startDate > $endDate) {
        json_response(false, 'start_date must be before or equal to end_date', [], 422);
    }

    $stmt = mysqli_prepare_or_fail(
        $db,
        'INSERT INTO contracts (tenant_id, room_id, start_date, end_date, deposit_amount, status)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    mysqli_bind_and_execute($stmt, 'iissds', [
        $tenantId,
        $roomId,
        $startDate,
        $endDate,
        (float)$input['deposit_amount'],
        trim((string)$input['status']),
    ]);

    $contractId = (int)$db->insert_id;

    $detailStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT c.id, c.tenant_id, c.room_id, c.start_date, c.end_date, c.deposit_amount, c.status,
                t.full_name AS tenant_name,
                r.room_number, r.floor, r.area, r.price, r.status AS room_status,
                b.id AS building_id, b.name AS building_name, b.address AS building_address, b.landlord_id
         FROM contracts c
         INNER JOIN users t ON t.id = c.tenant_id
         INNER JOIN rooms r ON r.id = c.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE c.id = ? AND b.landlord_id = ?
         LIMIT 1'
    );
    mysqli_bind_and_execute($detailStmt, 'ii', [$contractId, $landlordId]);
    $contract = mysqli_fetch_one_assoc($detailStmt);

    json_response(true, 'Contract created successfully', ['contract' => $contract ?? []], 201);
} catch (Throwable $e) {
    json_response(false, 'Failed to create contract', ['error' => $e->getMessage()], 500);
}
