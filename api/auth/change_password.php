<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['POST']);

$user = get_authenticated_user();
$input = get_json_input();
require_fields($input, ['current_password', 'new_password', 'confirm_password']);

$currentPassword = (string)$input['current_password'];
$newPassword = (string)$input['new_password'];
$confirmPassword = (string)$input['confirm_password'];

if (strlen($newPassword) < 6) {
    json_response(false, 'Mat khau moi phai co it nhat 6 ky tu', [], 422);
}
if ($newPassword !== $confirmPassword) {
    json_response(false, 'Xac nhan mat khau khong khop', [], 422);
}

try {
    $db = (new Database())->getConnection();

    $stmt = $db->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $user['id']]);
    $row = $stmt->fetch();
    if (!$row) {
        json_response(false, 'Khong tim thay nguoi dung', [], 404);
    }

    if (!password_verify($currentPassword, (string)$row['password_hash'])) {
        json_response(false, 'Mat khau hien tai khong dung', [], 422);
    }

    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    $update = $db->prepare('UPDATE users SET password_hash = :hash WHERE id = :id');
    $update->execute([
        ':hash' => $newHash,
        ':id' => $user['id'],
    ]);

    json_response(true, 'Doi mat khau thanh cong');
} catch (Throwable $e) {
    json_response(false, 'Khong the doi mat khau', ['error' => $e->getMessage()], 500);
}
