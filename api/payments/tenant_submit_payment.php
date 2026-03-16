<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['POST']);

$user = get_authenticated_user();
if ($user['role'] !== 'tenant') {
    json_response(false, 'Ban khong co quyen tenant', [], 403);
}

$input = get_json_input();
require_fields($input, ['id']);

$paymentId = (int)$input['id'];

try {
    $db = (new Database())->getMysqliConnection();

    $scopeStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT p.id, p.payment_status
         FROM payments p
         INNER JOIN contracts c ON c.id = p.contract_id
         WHERE p.id = ? AND c.tenant_id = ?
         LIMIT 1'
    );
    mysqli_bind_and_execute($scopeStmt, 'ii', [$paymentId, $user['id']]);
    $payment = mysqli_fetch_one_assoc($scopeStmt);
    if (!$payment) {
        json_response(false, 'Payment not found for current tenant', [], 404);
    }

    $currentStatus = (string)($payment['payment_status'] ?? '');
    if ($currentStatus === 'paid') {
        json_response(false, 'Payment already marked as paid', [], 409);
    }

    $updateStmt = mysqli_prepare_or_fail(
        $db,
        'UPDATE payments p
         INNER JOIN contracts c ON c.id = p.contract_id
         SET p.payment_status = ?
         WHERE p.id = ? AND c.tenant_id = ?'
    );
    mysqli_bind_and_execute($updateStmt, 'sii', ['pending', $paymentId, $user['id']]);

    json_response(true, 'Payment submitted successfully', ['payment_id' => $paymentId]);
} catch (Throwable $e) {
    json_response(false, 'Failed to submit payment', ['error' => $e->getMessage()], 500);
}
