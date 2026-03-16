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

    $paymentStatus = isset($_GET['payment_status']) ? trim((string)$_GET['payment_status']) : null;
    $monthYear = isset($_GET['month_year']) ? trim((string)$_GET['month_year']) : null;

    $sql = 'SELECT p.id, p.contract_id, p.month_year, p.total_amount, p.payment_status,
                   c.tenant_id, c.room_id, c.start_date, c.end_date, c.status AS contract_status,
                   r.room_number, r.floor, r.area, r.price AS room_price,
                   b.id AS building_id, b.name AS building_name, b.address AS building_address, b.landlord_id
            FROM payments p
            INNER JOIN contracts c ON c.id = p.contract_id
            INNER JOIN rooms r ON r.id = c.room_id
            INNER JOIN buildings b ON b.id = r.building_id
            WHERE c.tenant_id = :tenant_id';
    $params = [':tenant_id' => $tenantId];

    if ($paymentStatus !== null && $paymentStatus !== '') {
        $sql .= ' AND p.payment_status = :payment_status';
        $params[':payment_status'] = $paymentStatus;
    }
    if ($monthYear !== null && $monthYear !== '') {
        $sql .= ' AND p.month_year = :month_year';
        $params[':month_year'] = $monthYear;
    }

    $sql .= ' ORDER BY p.id DESC';
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $payments = $stmt->fetchAll();

    foreach ($payments as &$payment) {
        $payment['id'] = (int)$payment['id'];
        $payment['contract_id'] = (int)$payment['contract_id'];
        $payment['tenant_id'] = (int)$payment['tenant_id'];
        $payment['room_id'] = (int)$payment['room_id'];
        $payment['building_id'] = (int)$payment['building_id'];
        $payment['landlord_id'] = (int)$payment['landlord_id'];
        $payment['floor'] = $payment['floor'] !== null ? (int)$payment['floor'] : null;
        $payment['area'] = $payment['area'] !== null ? (float)$payment['area'] : null;
        $payment['room_price'] = $payment['room_price'] !== null ? (float)$payment['room_price'] : null;
        $payment['total_amount'] = (float)$payment['total_amount'];
    }
    unset($payment);

    json_response(true, 'Tenant payment history fetched successfully', ['payments' => $payments]);
} catch (Throwable $e) {
    json_response(false, 'Failed to fetch tenant payment history', ['error' => $e->getMessage()], 500);
}
