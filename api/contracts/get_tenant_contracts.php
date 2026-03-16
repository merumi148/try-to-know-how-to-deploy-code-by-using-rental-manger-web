<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['GET']);

$tenantId = isset($_GET['tenant_id']) ? (int)$_GET['tenant_id'] : 0;
if ($tenantId <= 0) {
    json_response(false, 'tenant_id is required', [], 422);
}

try {
    $db = (new Database())->getConnection();

    $tenantStmt = $db->prepare('SELECT id FROM users WHERE id = :tenant_id AND role = :role LIMIT 1');
    $tenantStmt->execute([
        ':tenant_id' => $tenantId,
        ':role' => 'tenant',
    ]);
    if (!$tenantStmt->fetch()) {
        json_response(false, 'Tenant not found', [], 404);
    }

    $sql = 'SELECT c.id, c.tenant_id, c.room_id, c.start_date, c.end_date, c.deposit_amount, c.status,
                   r.room_number, r.floor, r.area, r.price, r.status AS room_status,
                   b.id AS building_id, b.name AS building_name, b.address AS building_address, b.landlord_id
            FROM contracts c
            INNER JOIN rooms r ON r.id = c.room_id
            INNER JOIN buildings b ON b.id = r.building_id
            WHERE c.tenant_id = :tenant_id
            ORDER BY c.id DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute([':tenant_id' => $tenantId]);
    $contracts = $stmt->fetchAll();

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

    json_response(true, 'Tenant contracts fetched successfully', ['contracts' => $contracts]);
} catch (Throwable $e) {
    json_response(false, 'Failed to fetch tenant contracts', ['error' => $e->getMessage()], 500);
}
