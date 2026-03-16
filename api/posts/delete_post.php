<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['DELETE']);

$landlordId = require_landlord_id();

$input = get_json_input();
require_fields($input, ['id']);

$postId = (int)$input['id'];

try {
    $db = (new Database())->getMysqliConnection();

    $stmt = mysqli_prepare_or_fail($db, 'DELETE FROM posts WHERE id = ? AND landlord_id = ?');
    mysqli_bind_and_execute($stmt, 'ii', [$postId, $landlordId]);

    if ($stmt->affected_rows === 0) {
        json_response(false, 'Post not found for current landlord', [], 404);
    }

    json_response(true, 'Post deleted successfully', ['post_id' => $postId]);
} catch (Throwable $e) {
    json_response(false, 'Failed to delete post', ['error' => $e->getMessage()], 500);
}
