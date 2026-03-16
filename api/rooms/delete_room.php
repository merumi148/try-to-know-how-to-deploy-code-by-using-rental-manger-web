<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['DELETE']);

$landlordId = require_landlord_id();

$input = get_json_input();
require_fields($input, ['id']);

$roomId = (int)$input['id'];

try {
    $db = (new Database())->getMysqliConnection();
    $db->begin_transaction();

    // Ensure delete only affects current landlord data.
    $roomScopeStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT r.id
         FROM rooms r
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE r.id = ? AND b.landlord_id = ?
         LIMIT 1'
    );
    mysqli_bind_and_execute($roomScopeStmt, 'ii', [$roomId, $landlordId]);
    if (!mysqli_fetch_one_assoc($roomScopeStmt)) {
        json_response(false, 'Room not found for current landlord', [], 404);
    }

    $delImageStmt = mysqli_prepare_or_fail($db, 'DELETE FROM room_images WHERE room_id = ?');
    mysqli_bind_and_execute($delImageStmt, 'i', [$roomId]);

    $delFeatureStmt = mysqli_prepare_or_fail($db, 'DELETE FROM room_features WHERE room_id = ?');
    mysqli_bind_and_execute($delFeatureStmt, 'i', [$roomId]);

    $deleteStmt = mysqli_prepare_or_fail(
        $db,
        'DELETE r
         FROM rooms r
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE r.id = ? AND b.landlord_id = ?'
    );
    mysqli_bind_and_execute($deleteStmt, 'ii', [$roomId, $landlordId]);

    $db->commit();
    json_response(true, 'Room deleted successfully', ['room_id' => $roomId]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof mysqli) {
        $db->rollback();
    }
    json_response(false, 'Failed to delete room', ['error' => $e->getMessage()], 500);
}
