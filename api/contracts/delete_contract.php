<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['DELETE']);

$landlordId = require_landlord_id();

$input = get_json_input();
require_fields($input, ['id']);

$contractId = (int)$input['id'];

try {
    $db = (new Database())->getMysqliConnection();
    $db->begin_transaction();

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

    $delPaymentStmt = mysqli_prepare_or_fail($db, 'DELETE FROM payments WHERE contract_id = ?');
    mysqli_bind_and_execute($delPaymentStmt, 'i', [$contractId]);

    $deleteStmt = mysqli_prepare_or_fail(
        $db,
        'DELETE c
         FROM contracts c
         INNER JOIN rooms r ON r.id = c.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE c.id = ? AND b.landlord_id = ?'
    );
    mysqli_bind_and_execute($deleteStmt, 'ii', [$contractId, $landlordId]);

    $db->commit();
    json_response(true, 'Contract deleted successfully', ['contract_id' => $contractId]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof mysqli) {
        $db->rollback();
    }
    json_response(false, 'Failed to delete contract', ['error' => $e->getMessage()], 500);
}
