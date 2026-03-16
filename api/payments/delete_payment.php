<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['DELETE']);

$landlordId = require_landlord_id();

$input = get_json_input();
require_fields($input, ['id']);

$paymentId = (int)$input['id'];

try {
    $db = (new Database())->getMysqliConnection();

    $deleteStmt = mysqli_prepare_or_fail(
        $db,
        'DELETE p
         FROM payments p
         INNER JOIN contracts c ON c.id = p.contract_id
         INNER JOIN rooms r ON r.id = c.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE p.id = ? AND b.landlord_id = ?'
    );
    mysqli_bind_and_execute($deleteStmt, 'ii', [$paymentId, $landlordId]);

    if ($deleteStmt->affected_rows === 0) {
        json_response(false, 'Payment not found for current landlord', [], 404);
    }

    json_response(true, 'Payment deleted successfully', ['payment_id' => $paymentId]);
} catch (Throwable $e) {
    json_response(false, 'Failed to delete payment', ['error' => $e->getMessage()], 500);
}
