<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['GET']);

$user = get_authenticated_user();

try {
    $db = (new Database())->getMysqliConnection();

    $status = isset($_GET['status']) ? trim((string)$_GET['status']) : null;

    $sql = 'SELECT vr.id, vr.customer_name, vr.phone, vr.room_id, vr.preferred_date, vr.status,
                   r.room_number, b.id AS building_id, b.name AS building_name, b.landlord_id
            FROM viewing_requests vr
            INNER JOIN rooms r ON r.id = vr.room_id
            INNER JOIN buildings b ON b.id = r.building_id
            WHERE 1=1';

    $types = '';
    $bindValues = [];

    if ($user['role'] === 'landlord') {
        // Landlord only sees requests for rooms in their buildings.
        $sql .= ' AND b.landlord_id = ?';
        $types .= 'i';
        $bindValues[] = $user['id'];
    } else {
        $userStmt = mysqli_prepare_or_fail($db, 'SELECT phone FROM users WHERE id = ? LIMIT 1');
        mysqli_bind_and_execute($userStmt, 'i', [$user['id']]);
        $profile = mysqli_fetch_one_assoc($userStmt);
        if (!$profile || trim((string)$profile['phone']) === '') {
            json_response(true, 'Khong co yeu cau xem phong', ['requests' => []]);
        }

        $sql .= ' AND vr.phone = ?';
        $types .= 's';
        $bindValues[] = (string)$profile['phone'];
    }

    if ($status !== null && $status !== '') {
        $sql .= ' AND vr.status = ?';
        $types .= 's';
        $bindValues[] = $status;
    }

    $sql .= ' ORDER BY vr.id DESC';

    $stmt = mysqli_prepare_or_fail($db, $sql);
    mysqli_bind_and_execute($stmt, $types, $bindValues);
    $requests = mysqli_fetch_all_assoc($stmt);

    foreach ($requests as &$request) {
        $request['id'] = (int)$request['id'];
        $request['room_id'] = (int)$request['room_id'];
        $request['building_id'] = (int)$request['building_id'];
        $request['landlord_id'] = (int)$request['landlord_id'];
    }
    unset($request);

    json_response(true, 'Lay danh sach yeu cau xem phong thanh cong', ['requests' => $requests]);
} catch (Throwable $e) {
    json_response(false, 'Lay danh sach yeu cau xem phong that bai', ['error' => $e->getMessage()], 500);
}
