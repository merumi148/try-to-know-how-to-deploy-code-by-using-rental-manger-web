<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['PUT']);

$landlordId = require_landlord_id();

$input = get_json_input();
require_fields($input, ['id', 'status']);

$requestId = (int)$input['id'];
$status = trim((string)$input['status']);

$allowed = ['pending', 'confirmed', 'cancelled'];
if (!in_array($status, $allowed, true)) {
    json_response(false, 'Trang thai khong hop le', [], 422);
}

error_log('[viewing_request] update request_id=' . $requestId . ' input_status=' . $status . ' landlord_id=' . $landlordId);

try {
    $db = (new Database())->getMysqliConnection();

    $scopeStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT vr.id
         FROM viewing_requests vr
         INNER JOIN rooms r ON r.id = vr.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE vr.id = ? AND b.landlord_id = ?
         LIMIT 1'
    );
    mysqli_bind_and_execute($scopeStmt, 'ii', [$requestId, $landlordId]);
    if (!mysqli_fetch_one_assoc($scopeStmt)) {
        json_response(false, 'Yeu cau khong ton tai', [], 404);
    }

    $stmt = mysqli_prepare_or_fail(
        $db,
        'UPDATE viewing_requests vr
         INNER JOIN rooms r ON r.id = vr.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         SET vr.status = ?
         WHERE vr.id = ? AND b.landlord_id = ?'
    );
    mysqli_bind_and_execute($stmt, 'sii', [$status, $requestId, $landlordId]);

    error_log('[viewing_request] update affected_rows=' . $stmt->affected_rows);

    json_response(true, 'Cap nhat yeu cau thanh cong', ['request_id' => $requestId, 'status' => $status]);
} catch (Throwable $e) {
    error_log('[viewing_request] update error: ' . $e->getMessage());
    json_response(false, 'Cap nhat yeu cau that bai', ['error' => $e->getMessage()], 500);
}
