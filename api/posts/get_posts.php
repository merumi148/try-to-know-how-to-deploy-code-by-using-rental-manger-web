<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['GET']);

try {
    $db = (new Database())->getMysqliConnection();

    $postId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $roomId = isset($_GET['room_id']) ? (int)$_GET['room_id'] : null;
    $status = isset($_GET['status']) ? trim((string)$_GET['status']) : null;

    $role = '';
    $userId = 0;
    if (isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])) {
        $role = (string)($_SESSION['auth_user']['role'] ?? '');
        $userId = (int)($_SESSION['auth_user']['id'] ?? 0);
    }

    $sql = 'SELECT p.id, p.landlord_id, p.room_id, p.title, p.content, p.status,
                   r.room_number, r.floor, r.area, r.price, r.status AS room_status, r.description AS room_description,
                   b.id AS building_id, b.name AS building_name, b.address AS building_address
            FROM posts p
            INNER JOIN rooms r ON r.id = p.room_id
            INNER JOIN buildings b ON b.id = r.building_id
            WHERE 1=1';

    $types = '';
    $bindValues = [];

    if ($role === 'landlord' && $userId > 0) {
        // Landlord only sees own posts.
        $sql .= ' AND p.landlord_id = ?';
        $types .= 'i';
        $bindValues[] = $userId;
    }

    if ($postId) {
        $sql .= ' AND p.id = ?';
        $types .= 'i';
        $bindValues[] = $postId;
    }

    if ($roomId) {
        $sql .= ' AND p.room_id = ?';
        $types .= 'i';
        $bindValues[] = $roomId;
    }

    if ($status !== null && $status !== '') {
        $sql .= ' AND p.status = ?';
        $types .= 's';
        $bindValues[] = $status;
    }

    $sql .= ' ORDER BY p.id DESC';

    $stmt = mysqli_prepare_or_fail($db, $sql);
    mysqli_bind_and_execute($stmt, $types, $bindValues);
    $posts = mysqli_fetch_all_assoc($stmt);

    $imageStmt = mysqli_prepare_or_fail($db, 'SELECT image_url FROM room_images WHERE room_id = ? ORDER BY id ASC');

    foreach ($posts as &$post) {
        $post['id'] = (int)$post['id'];
        $post['landlord_id'] = (int)$post['landlord_id'];
        $post['room_id'] = (int)$post['room_id'];
        $post['building_id'] = $post['building_id'] !== null ? (int)$post['building_id'] : null;
        $post['floor'] = $post['floor'] !== null ? (int)$post['floor'] : null;
        $post['area'] = $post['area'] !== null ? (float)$post['area'] : null;
        $post['price'] = $post['price'] !== null ? (float)$post['price'] : null;

        mysqli_bind_and_execute($imageStmt, 'i', [$post['room_id']]);
        $images = mysqli_fetch_all_assoc($imageStmt);
        $post['images'] = array_map(
            static fn(array $img): string => (string)$img['image_url'],
            $images
        );
    }
    unset($post);

    json_response(true, 'Posts fetched successfully', ['posts' => $posts]);
} catch (Throwable $e) {
    json_response(false, 'Failed to fetch posts', ['error' => $e->getMessage()], 500);
}
