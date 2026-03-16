<?php
declare(strict_types=1);

function init_api_headers(): void
{
    init_api_session();

    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');

    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
        http_response_code(200);
        exit;
    }
}

function init_api_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function json_response(bool $success, string $message, array $data = [], int $statusCode = 200): void
{
    http_response_code($statusCode);
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

function method_not_allowed(array $allowedMethods): void
{
    if (!in_array($_SERVER['REQUEST_METHOD'], $allowedMethods, true)) {
        json_response(false, 'Phuong thuc khong duoc ho tro', ['allowed_methods' => $allowedMethods], 405);
    }
}

function get_json_input(): array
{
    $raw = file_get_contents('php://input');
    if ($raw === false || $raw === '') {
        return [];
    }

    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        json_response(false, 'Du lieu JSON khong hop le', [], 400);
    }

    return $decoded;
}

function require_fields(array $input, array $fields): void
{
    foreach ($fields as $field) {
        if (!array_key_exists($field, $input) || $input[$field] === '' || $input[$field] === null) {
            json_response(false, "Thieu truong bat buoc: {$field}", [], 422);
        }
    }
}

function validate_role(string $role): string
{
    $allowed = ['landlord', 'tenant'];
    if (!in_array($role, $allowed, true)) {
        json_response(false, 'Vai tro khong hop le. Chi chap nhan landlord, tenant', [], 422);
    }

    return $role;
}

function get_authenticated_user(): array
{
    init_api_session();

    if (!isset($_SESSION['auth_user']) || !is_array($_SESSION['auth_user'])) {
        json_response(false, 'Vui long dang nhap lai', [], 401);
    }

    $user = $_SESSION['auth_user'];
    $userId = isset($user['id']) ? (int)$user['id'] : 0;
    $role = isset($user['role']) ? (string)$user['role'] : '';

    if ($userId <= 0 || !in_array($role, ['landlord', 'tenant'], true)) {
        json_response(false, 'Thong tin dang nhap khong hop le', [], 401);
    }

    return [
        'id' => $userId,
        'role' => $role,
    ];
}

function require_landlord_id(): int
{
    $user = get_authenticated_user();
    if ($user['role'] !== 'landlord') {
        json_response(false, 'Ban khong co quyen landlord', [], 403);
    }

    return $user['id'];
}

function mysqli_prepare_or_fail(mysqli $db, string $sql): mysqli_stmt
{
    $stmt = $db->prepare($sql);
    if (!$stmt) {
        throw new RuntimeException('Prepare failed: ' . $db->error);
    }

    return $stmt;
}

function mysqli_bind_and_execute(mysqli_stmt $stmt, string $types = '', array $params = []): void
{
    if ($types !== '') {
        $bindParams = [$types];
        foreach ($params as $i => $value) {
            $bindParams[] = &$params[$i];
        }
        if (!call_user_func_array([$stmt, 'bind_param'], $bindParams)) {
            throw new RuntimeException('bind_param failed: ' . $stmt->error);
        }
    }

    if (!$stmt->execute()) {
        throw new RuntimeException('Execute failed: ' . $stmt->error);
    }
}

function mysqli_fetch_all_assoc(mysqli_stmt $stmt): array
{
    $result = $stmt->get_result();
    if (!$result) {
        return [];
    }

    return $result->fetch_all(MYSQLI_ASSOC);
}

function mysqli_fetch_one_assoc(mysqli_stmt $stmt): ?array
{
    $rows = mysqli_fetch_all_assoc($stmt);
    return $rows[0] ?? null;
}
