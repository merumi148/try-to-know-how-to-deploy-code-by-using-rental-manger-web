<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['POST']);

$input = get_json_input();
require_fields($input, ['email', 'password']);

$email = strtolower(trim((string)$input['email']));
$password = (string)$input['password'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(false, 'Email khong dung dinh dang', [], 422);
}

try {
    $db = (new Database())->getMysqliConnection();

    $stmt = mysqli_prepare_or_fail(
        $db,
        'SELECT id, full_name, email, phone, password_hash, role, created_at
         FROM users
         WHERE email = ?
         LIMIT 1'
    );
    mysqli_bind_and_execute($stmt, 's', [$email]);
    $user = mysqli_fetch_one_assoc($stmt);

    if (!$user || !password_verify($password, (string)$user['password_hash'])) {
        json_response(false, 'Email hoac mat khau khong dung', [], 401);
    }

    unset($user['password_hash']);
    $user['id'] = (int)$user['id'];

    // Save authenticated user in server session so APIs can scope data by landlord/tenant.
    $_SESSION['auth_user'] = [
        'id' => $user['id'],
        'role' => (string)$user['role'],
    ];

    json_response(true, 'Dang nhap thanh cong', ['user' => $user]);
} catch (Throwable $e) {
    json_response(false, 'Dang nhap that bai', ['error' => $e->getMessage()], 500);
}
