<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['PUT']);

$landlordId = require_landlord_id();

$input = get_json_input();
require_fields($input, ['id']);

$postId = (int)$input['id'];
$allowed = ['room_id', 'title', 'content', 'status'];

$setParts = [];
$types = '';
$values = [];

foreach ($allowed as $field) {
    if (!array_key_exists($field, $input)) {
        continue;
    }

    $setParts[] = "p.{$field} = ?";
    if ($field === 'room_id') {
        $types .= 'i';
        $values[] = (int)$input[$field];
    } else {
        $types .= 's';
        $values[] = trim((string)$input[$field]);
    }
}

if (!$setParts) {
    json_response(false, 'No fields to update', [], 422);
}

try {
    $db = (new Database())->getMysqliConnection();

    $postCheck = mysqli_prepare_or_fail($db, 'SELECT id FROM posts WHERE id = ? AND landlord_id = ? LIMIT 1');
    mysqli_bind_and_execute($postCheck, 'ii', [$postId, $landlordId]);
    if (!mysqli_fetch_one_assoc($postCheck)) {
        json_response(false, 'Post not found for current landlord', [], 404);
    }

    if (array_key_exists('room_id', $input)) {
        $roomCheck = mysqli_prepare_or_fail(
            $db,
            'SELECT r.id
             FROM rooms r
             INNER JOIN buildings b ON b.id = r.building_id
             WHERE r.id = ? AND b.landlord_id = ?
             LIMIT 1'
        );
        mysqli_bind_and_execute($roomCheck, 'ii', [(int)$input['room_id'], $landlordId]);
        if (!mysqli_fetch_one_assoc($roomCheck)) {
            json_response(false, 'Room not found for current landlord', [], 404);
        }
    }

    $sql = 'UPDATE posts p
            INNER JOIN rooms r ON r.id = p.room_id
            INNER JOIN buildings b ON b.id = r.building_id
            SET ' . implode(', ', $setParts) . '
            WHERE p.id = ? AND p.landlord_id = ? AND b.landlord_id = ?';

    $stmt = mysqli_prepare_or_fail($db, $sql);
    $typesWithScope = $types . 'iii';
    $valuesWithScope = array_merge($values, [$postId, $landlordId, $landlordId]);
    mysqli_bind_and_execute($stmt, $typesWithScope, $valuesWithScope);

    $detailStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT p.id, p.landlord_id, p.room_id, p.title, p.content, p.status,
                r.room_number, r.floor, r.area, r.price, r.status AS room_status, r.description AS room_description,
                b.id AS building_id, b.name AS building_name, b.address AS building_address
         FROM posts p
         INNER JOIN rooms r ON r.id = p.room_id
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE p.id = ? AND p.landlord_id = ? AND b.landlord_id = ?
         LIMIT 1'
    );
    mysqli_bind_and_execute($detailStmt, 'iii', [$postId, $landlordId, $landlordId]);
    $post = mysqli_fetch_one_assoc($detailStmt);

    json_response(true, 'Post updated successfully', ['post' => $post ?? []]);
} catch (Throwable $e) {
    json_response(false, 'Failed to update post', ['error' => $e->getMessage()], 500);
}
