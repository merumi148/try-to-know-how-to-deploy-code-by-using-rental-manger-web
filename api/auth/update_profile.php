<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['POST']);

$input = get_json_input();
require_fields($input, ['user_id', 'full_name', 'phone']);

$userId = (int)$input['user_id'];
$fullName = trim((string)$input['full_name']);
$phone = trim((string)$input['phone']);

if ($userId <= 0) {
    json_response(false, 'user_id khong hop le', [], 422);
}
if ($fullName === '') {
    json_response(false, 'Ho va ten la bat buoc', [], 422);
}
if ($phone === '') {
    json_response(false, 'So dien thoai la bat buoc', [], 422);
}

try {
    $db = (new Database())->getConnection();

    $check = $db->prepare('SELECT id FROM users WHERE id = :id LIMIT 1');
    $check->execute([':id' => $userId]);
    if (!$check->fetch()) {
        json_response(false, 'Khong tim thay nguoi dung', [], 404);
    }

    $stmt = $db->prepare(
        'UPDATE users
         SET full_name = :full_name,
             phone = :phone
         WHERE id = :id'
    );
    $stmt->execute([
        ':full_name' => $fullName,
        ':phone' => $phone,
        ':id' => $userId,
    ]);

    $getUser = $db->prepare(
        'SELECT id, full_name, email, phone, role, created_at
         FROM users
         WHERE id = :id
         LIMIT 1'
    );
    $getUser->execute([':id' => $userId]);
    $user = $getUser->fetch();

    if (!$user) {
        json_response(false, 'Cap nhat thanh cong nhung khong doc duoc du lieu', [], 500);
    }

    $user['id'] = (int)$user['id'];
    json_response(true, 'Cap nhat thong tin ca nhan thanh cong', ['user' => $user]);
} catch (Throwable $e) {
    json_response(false, 'Khong the cap nhat thong tin ca nhan', ['error' => $e->getMessage()], 500);
}