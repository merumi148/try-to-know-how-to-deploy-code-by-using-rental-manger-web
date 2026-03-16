<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['GET']);

$user = get_authenticated_user();

try {
    $db = (new Database())->getMysqliConnection();

    $contractId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;
    $status = isset($_GET['status']) ? trim((string)$_GET['status']) : null;

    $sql = 'SELECT c.id, c.tenant_id, c.room_id, c.start_date, c.end_date, c.deposit_amount, c.status,
                   t.full_name AS tenant_name, t.phone AS tenant_phone,
                   r.room_number, r.floor, r.area, r.price, r.status AS room_status,
                   b.id AS building_id, b.name AS building_name, b.address AS building_address, b.landlord_id
            FROM contracts c
            INNER JOIN users t ON t.id = c.tenant_id
            INNER JOIN rooms r ON r.id = c.room_id
            INNER JOIN buildings b ON b.id = r.building_id
            WHERE 1=1';

    $types = '';
    $bindValues = [];

    if ($user['role'] === 'landlord') {
        $sql .= ' AND b.landlord_id = ?';
        $types .= 'i';
        $bindValues[] = $user['id'];
    } else {
        $sql .= ' AND c.tenant_id = ?';
        $types .= 'i';
        $bindValues[] = $user['id'];
    }

    if ($contractId) {
        $sql .= ' AND c.id = ?';
        $types .= 'i';
        $bindValues[] = $contractId;
    }

    if ($roomId) {
        $sql .= ' AND c.room_id = ?';
        $types .= 'i';
        $bindValues[] = $roomId;
    }

    if ($status !== null && $status !== '') {
        $sql .= ' AND c.status = ?';
        $types .= 's';
        $bindValues[] = $status;
    }

    $sql .= ' ORDER BY c.id DESC';

    $stmt = mysqli_prepare_or_fail($db, $sql);
    mysqli_bind_and_execute($stmt, $types, $bindValues);
    $contracts = mysqli_fetch_all_assoc($stmt);

    foreach ($contracts as &$contract) {
        $contract['id'] = (int)$contract['id'];
        $contract['tenant_id'] = (int)$contract['tenant_id'];
        $contract['room_id'] = (int)$contract['room_id'];
        $contract['deposit_amount'] = (float)$contract['deposit_amount'];
        $contract['building_id'] = (int)$contract['building_id'];
        $contract['landlord_id'] = (int)$contract['landlord_id'];
        $contract['floor'] = $contract['floor'] !== null ? (int)$contract['floor'] : null;
        $contract['area'] = $contract['area'] !== null ? (float)$contract['area'] : null;
        $contract['price'] = $contract['price'] !== null ? (float)$contract['price'] : null;
    }
    unset($contract);

    json_response(true, 'Contracts fetched successfully', ['contracts' => $contracts]);
} catch (Throwable $e) {
    json_response(false, 'Failed to fetch contracts', ['error' => $e->getMessage()], 500);
}
