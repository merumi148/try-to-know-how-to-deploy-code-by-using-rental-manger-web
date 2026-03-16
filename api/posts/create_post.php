<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['POST']);

$landlordId = require_landlord_id();

$input = get_json_input();
require_fields($input, ['room_id', 'title', 'content', 'status']);

try {
    $db = (new Database())->getMysqliConnection();

    $roomId = (int)$input['room_id'];

    // Post can only be created for room owned by current landlord.
    $roomCheck = mysqli_prepare_or_fail(
        $db,
        'SELECT r.id
         FROM rooms r
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE r.id = ? AND b.landlord_id = ?
         LIMIT 1'
    );
    mysqli_bind_and_execute($roomCheck, 'ii', [$roomId, $landlordId]);
    if (!mysqli_fetch_one_assoc($roomCheck)) {
        json_response(false, 'Room not found for current landlord', [], 404);
    }

    $stmt = mysqli_prepare_or_fail(
        $db,
        'INSERT INTO posts (landlord_id, room_id, title, content, status)
         VALUES (?, ?, ?, ?, ?)'
    );
    mysqli_bind_and_execute($stmt, 'iisss', [
        $landlordId,
        $roomId,
        trim((string)$input['title']),
        trim((string)$input['content']),
        trim((string)$input['status']),
    ]);

    $postId = (int)$db->insert_id;

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

    json_response(true, 'Post created successfully', ['post' => $post ?? []], 201);
} catch (Throwable $e) {
    json_response(false, 'Failed to create post', ['error' => $e->getMessage()], 500);
}
