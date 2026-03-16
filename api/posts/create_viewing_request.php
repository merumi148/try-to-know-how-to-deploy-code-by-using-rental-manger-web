<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['POST']);

$input = get_json_input();

try {
    $db = (new Database())->getConnection();

    $roomId = 0;
    if (isset($input['room_id'])) {
        $roomId = (int)$input['room_id'];
    } elseif (isset($input['post_id'])) {
        // Backward compatibility for old frontend payload.
        $roomId = (int)$input['post_id'];
    }
    if ($roomId <= 0) {
        json_response(false, 'Thieu room_id hop le', [], 422);
    }

    $customerName = isset($input['customer_name']) ? trim((string)$input['customer_name']) : '';
    $phone = isset($input['phone']) ? trim((string)$input['phone']) : '';

    if (($customerName === '' || $phone === '') && isset($input['tenant_id'])) {
        $userStmt = $db->prepare('SELECT full_name, phone FROM users WHERE id = :id LIMIT 1');
        $userStmt->execute([':id' => (int)$input['tenant_id']]);
        $user = $userStmt->fetch();
        if ($user) {
            if ($customerName === '') {
                $customerName = (string)$user['full_name'];
            }
            if ($phone === '') {
                $phone = (string)$user['phone'];
            }
        }
    }

    if ($customerName === '' || $phone === '') {
        json_response(false, 'Thieu customer_name hoac phone', [], 422);
    }

    $preferredDate = isset($input['preferred_date']) ? trim((string)$input['preferred_date']) : date('Y-m-d');
    if (strtotime($preferredDate) === false) {
        json_response(false, 'preferred_date khong hop le', [], 422);
    }

    $status = isset($input['status']) ? trim((string)$input['status']) : 'pending';

    $roomCheck = $db->prepare('SELECT id FROM rooms WHERE id = :room_id LIMIT 1');
    $roomCheck->execute([':room_id' => $roomId]);
    if (!$roomCheck->fetch()) {
        json_response(false, 'Khong tim thay phong', [], 404);
    }

    $stmt = $db->prepare(
        'INSERT INTO viewing_requests (customer_name, phone, room_id, preferred_date, status)
         VALUES (:customer_name, :phone, :room_id, :preferred_date, :status)'
    );
    $stmt->execute([
        ':customer_name' => $customerName,
        ':phone' => $phone,
        ':room_id' => $roomId,
        ':preferred_date' => $preferredDate,
        ':status' => $status,
    ]);

    json_response(true, 'Tao yeu cau xem phong thanh cong', [
        'request_id' => (int)$db->lastInsertId(),
        'room_id' => $roomId,
    ], 201);
} catch (Throwable $e) {
    json_response(false, 'Tao yeu cau xem phong that bai', ['error' => $e->getMessage()], 500);
}
