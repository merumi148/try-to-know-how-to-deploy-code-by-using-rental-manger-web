<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['POST']);

$input = get_json_input();

if (!isset($input['full_name']) && isset($input['name'])) {
    $input['full_name'] = $input['name'];
}

require_fields($input, ['full_name', 'email', 'phone', 'password', 'role']);

$fullName = trim((string)$input['full_name']);
$email = strtolower(trim((string)$input['email']));
$phone = trim((string)$input['phone']);
$password = (string)$input['password'];
$role = validate_role((string)$input['role']);

if ($fullName === '') {
    json_response(false, 'Ho va ten la bat buoc', [], 422);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Email khong dung dinh dang', [], 422);
}

if ($phone === '') {
    json_response(false, 'So dien thoai la bat buoc', [], 422);
}

if (strlen($password) < 6) {
    json_response(false, 'Mat khau phai co it nhat 6 ky tu', [], 422);
}

try {
    $db = (new Database())->getConnection();

    $check = $db->prepare('SELECT id FROM users WHERE email = :email LIMIT 1');
    $check->execute([':email' => $email]);
    if ($check->fetch()) {
        json_response(false, 'Email da ton tai', [], 409);
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);

    $stmt = $db->prepare(
        'INSERT INTO users (full_name, email, phone, password_hash, role, created_at)
         VALUES (:full_name, :email, :phone, :password_hash, :role, NOW())'
    );
    $stmt->execute([
        ':full_name' => $fullName,
        ':email' => $email,
        ':phone' => $phone,
        ':password_hash' => $hash,
        ':role' => $role,
    ]);

    json_response(true, 'Dang ky thanh cong', [
        'user_id' => (int)$db->lastInsertId(),
        'full_name' => $fullName,
        'email' => $email,
        'phone' => $phone,
        'role' => $role,
    ], 201);
} catch (Throwable $e) {
    json_response(false, 'Dang ky that bai', ['error' => $e->getMessage()], 500);
}
