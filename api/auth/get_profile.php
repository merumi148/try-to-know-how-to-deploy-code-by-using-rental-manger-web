<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['GET']);

$userId = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;
if ($userId <= 0) {
    json_response(false, 'Thieu user_id hop le', [], 422);
}

try {
    $db = (new Database())->getConnection();

    $stmt = $db->prepare(
        'SELECT id, full_name, email, phone, role, created_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $stmt->execute([':id' => $userId]);
    $user = $stmt->fetch();

    if (!$user) {
        json_response(false, 'Khong tim thay nguoi dung', [], 404);
    }

    $user['id'] = (int)$user['id'];
    json_response(true, 'Lay thong tin ca nhan thanh cong', ['user' => $user]);
} catch (Throwable $e) {
    json_response(false, 'Khong the lay thong tin ca nhan', ['error' => $e->getMessage()], 500);
}