<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['GET']);

function resolve_image_url(string $imageUrl): string
{
    $imageUrl = trim($imageUrl);
    if ($imageUrl === '') {
        return $imageUrl;
    }

    $relative = ltrim($imageUrl, '/');
    $absolute = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relative);
    if (file_exists($absolute)) {
        return $imageUrl;
    }

    $ext = strtolower((string)pathinfo($relative, PATHINFO_EXTENSION));
    if (in_array($ext, ['jpg', 'jpeg', 'png'], true)) {
        $base = preg_replace('/\.[^.]+$/', '', $relative);
        $jfifRelative = $base . '.jfif';
        $jfifAbsolute = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $jfifRelative);
        if (file_exists($jfifAbsolute)) {
            return $jfifRelative;
        }
    }

    return $imageUrl;
}

try {
    $db = (new Database())->getMysqliConnection();

    $roomId = isset($_GET['id']) ? (int)$_GET['id'] : null;
    $buildingId = isset($_GET['building_id']) ? (int)$_GET['building_id'] : null;
    $status = isset($_GET['status']) ? trim((string)$_GET['status']) : null;

    $role = '';
    $userId = 0;
    if (isset($_SESSION['auth_user']) && is_array($_SESSION['auth_user'])) {
        $role = (string)($_SESSION['auth_user']['role'] ?? '');
        $userId = (int)($_SESSION['auth_user']['id'] ?? 0);
    }

    $sql = 'SELECT r.id, r.building_id, r.room_number, r.floor, r.area, r.price, r.status, r.description,
                   b.name AS building_name
            FROM rooms r
            INNER JOIN buildings b ON b.id = r.building_id
            WHERE 1=1';

    $types = '';
    $bindValues = [];

    if ($role === 'landlord' && $userId > 0) {
        // Landlord can only see rooms belonging to their own buildings.
        $sql .= ' AND b.landlord_id = ?';
        $types .= 'i';
        $bindValues[] = $userId;
    }

    if ($roomId) {
        $sql .= ' AND r.id = ?';
        $types .= 'i';
        $bindValues[] = $roomId;
    }

    if ($buildingId) {
        $sql .= ' AND r.building_id = ?';
        $types .= 'i';
        $bindValues[] = $buildingId;
    }

    if ($status !== null && $status !== '') {
        $sql .= ' AND r.status = ?';
        $types .= 's';
        $bindValues[] = $status;
    }

    $sql .= ' ORDER BY r.id DESC';

    $stmt = mysqli_prepare_or_fail($db, $sql);
    mysqli_bind_and_execute($stmt, $types, $bindValues);
    $rooms = mysqli_fetch_all_assoc($stmt);

    $imageStmt = mysqli_prepare_or_fail($db, 'SELECT room_id, image_url FROM room_images WHERE room_id = ? ORDER BY id ASC');
    $featureStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT f.id, f.name
         FROM room_features rf
         INNER JOIN features f ON f.id = rf.feature_id
         WHERE rf.room_id = ?'
    );

    foreach ($rooms as &$room) {
        $roomIdValue = (int)$room['id'];

        $room['id'] = $roomIdValue;
        $room['building_id'] = $room['building_id'] !== null ? (int)$room['building_id'] : null;
        $room['floor'] = $room['floor'] !== null ? (int)$room['floor'] : null;
        $room['price'] = isset($room['price']) ? (float)$room['price'] : null;
        $room['area'] = isset($room['area']) ? (float)$room['area'] : null;

        mysqli_bind_and_execute($imageStmt, 'i', [$roomIdValue]);
        $images = mysqli_fetch_all_assoc($imageStmt);
        $room['images'] = array_map(
            static fn(array $img): string => resolve_image_url((string)$img['image_url']),
            $images
        );

        mysqli_bind_and_execute($featureStmt, 'i', [$roomIdValue]);
        $room['features'] = mysqli_fetch_all_assoc($featureStmt);
    }
    unset($room);

    json_response(true, 'Rooms fetched successfully', ['rooms' => $rooms]);
} catch (Throwable $e) {
    json_response(false, 'Failed to fetch rooms', ['error' => $e->getMessage()], 500);
}
