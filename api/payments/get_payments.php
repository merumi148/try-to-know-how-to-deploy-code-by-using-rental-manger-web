<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['GET']);

$user = get_authenticated_user();

try {
    $db = (new Database())->getMysqliConnection();

    $paymentId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $contractId = isset($_GET['contract_id']) ? (int)$_GET['contract_id'] : null;
    $monthYear = isset($_GET['month_year']) ? trim((string)$_GET['month_year']) : null;
    $paymentStatus = isset($_GET['payment_status']) ? trim((string)$_GET['payment_status']) : null;

    $sql = 'SELECT p.id, p.contract_id, p.month_year, p.total_amount, p.payment_status,
                   c.tenant_id, c.room_id,
                   r.room_number, r.price AS room_price,
                   b.id AS building_id, b.name AS building_name, b.landlord_id
            FROM payments p
            INNER JOIN contracts c ON c.id = p.contract_id
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

    if ($paymentId) {
        $sql .= ' AND p.id = ?';
        $types .= 'i';
        $bindValues[] = $paymentId;
    }

    if ($contractId) {
        $sql .= ' AND p.contract_id = ?';
        $types .= 'i';
        $bindValues[] = $contractId;
    }

    if ($monthYear !== null && $monthYear !== '') {
        $sql .= ' AND p.month_year = ?';
        $types .= 's';
        $bindValues[] = $monthYear;
    }

    if ($paymentStatus !== null && $paymentStatus !== '') {
        $sql .= ' AND p.payment_status = ?';
        $types .= 's';
        $bindValues[] = $paymentStatus;
    }

    $sql .= ' ORDER BY p.id DESC';

    $stmt = mysqli_prepare_or_fail($db, $sql);
    mysqli_bind_and_execute($stmt, $types, $bindValues);
    $payments = mysqli_fetch_all_assoc($stmt);

    foreach ($payments as &$payment) {
        $payment['id'] = (int)$payment['id'];
        $payment['contract_id'] = (int)$payment['contract_id'];
        $payment['tenant_id'] = (int)$payment['tenant_id'];
        $payment['room_id'] = (int)$payment['room_id'];
        $payment['building_id'] = (int)$payment['building_id'];
        $payment['landlord_id'] = (int)$payment['landlord_id'];
        $payment['total_amount'] = (float)$payment['total_amount'];
        $payment['room_price'] = $payment['room_price'] !== null ? (float)$payment['room_price'] : null;
    }
    unset($payment);

    json_response(true, 'Payment history fetched successfully', ['payments' => $payments]);
} catch (Throwable $e) {
    json_response(false, 'Failed to fetch payments', ['error' => $e->getMessage()], 500);
}
