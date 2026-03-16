<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['POST']);

$landlordId = require_landlord_id();

$input = get_json_input();
require_fields($input, ['contract_id', 'month_year', 'total_amount', 'payment_status']);

try {
    $db = (new Database())->getMysqliConnection();

    $contractId = (int)$input['contract_id'];

    // Contract must belong to current landlord.
    $contractStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT c.id
         FROM contracts c
         INNER JOIN rooms r ON r.id = c.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE c.id = ? AND b.landlord_id = ?
         LIMIT 1'
    );
    mysqli_bind_and_execute($contractStmt, 'ii', [$contractId, $landlordId]);
    if (!mysqli_fetch_one_assoc($contractStmt)) {
        json_response(false, 'Contract not found for current landlord', [], 404);
    }

    $monthYear = trim((string)$input['month_year']);
    if (!preg_match('/^\d{4}\-(0[1-9]|1[0-2])$/', $monthYear)) {
        json_response(false, 'month_year must be in YYYY-MM format', [], 422);
    }

    $stmt = mysqli_prepare_or_fail(
        $db,
        'INSERT INTO payments (contract_id, month_year, total_amount, payment_status)
         VALUES (?, ?, ?, ?)'
    );
    mysqli_bind_and_execute($stmt, 'isds', [
        $contractId,
        $monthYear,
        (float)$input['total_amount'],
        trim((string)$input['payment_status']),
    ]);

    $paymentId = (int)$db->insert_id;

    $detailStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT p.id, p.contract_id, p.month_year, p.total_amount, p.payment_status,
                c.tenant_id, c.room_id,
                r.room_number, b.id AS building_id, b.name AS building_name, b.landlord_id
         FROM payments p
         INNER JOIN contracts c ON c.id = p.contract_id
         INNER JOIN rooms r ON r.id = c.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE p.id = ? AND b.landlord_id = ?
         LIMIT 1'
    );
    mysqli_bind_and_execute($detailStmt, 'ii', [$paymentId, $landlordId]);
    $payment = mysqli_fetch_one_assoc($detailStmt);

    json_response(true, 'Payment created successfully', ['payment' => $payment ?? []], 201);
} catch (Throwable $e) {
    json_response(false, 'Failed to create payment', ['error' => $e->getMessage()], 500);
}
