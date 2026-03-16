<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['POST']);

function normalize_image_url(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    if (str_starts_with($value, 'uploads/rooms/')) {
        return $value;
    }
    return 'uploads/rooms/' . ltrim($value, '/');
}

$landlordId = require_landlord_id();

$input = get_json_input();
require_fields($input, [
    'building_id',
    'room_number',
    'floor',
    'area',
    'price',
    'status',
    'description',
    'images',
    'feature_ids',
]);

$buildingId = (int)$input['building_id'];
$roomNumber = trim((string)$input['room_number']);
$floor = (int)$input['floor'];
$description = trim((string)$input['description']);
$price = (float)$input['price'];
$area = (float)$input['area'];
$status = trim((string)$input['status']);
$images = is_array($input['images']) ? $input['images'] : null;
$featureIds = is_array($input['feature_ids']) ? $input['feature_ids'] : null;

if ($images === null || $featureIds === null) {
    json_response(false, 'images and feature_ids must be arrays', [], 422);
}

try {
    $db = (new Database())->getMysqliConnection();
    $db->begin_transaction();

    // Ensure landlord only creates rooms inside their own building.
    $buildingStmt = mysqli_prepare_or_fail($db, 'SELECT id FROM buildings WHERE id = ? AND landlord_id = ? LIMIT 1');
    mysqli_bind_and_execute($buildingStmt, 'ii', [$buildingId, $landlordId]);
    if (!mysqli_fetch_one_assoc($buildingStmt)) {
        json_response(false, 'Building not found for current landlord', [], 404);
    }

    $stmt = mysqli_prepare_or_fail(
        $db,
        'INSERT INTO rooms (building_id, room_number, floor, area, price, status, description)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    mysqli_bind_and_execute($stmt, 'isiddss', [
        $buildingId,
        $roomNumber,
        $floor,
        $area,
        $price,
        $status,
        $description,
    ]);

    $roomId = (int)$db->insert_id;

    if ($images) {
        $imageStmt = mysqli_prepare_or_fail(
            $db,
            'INSERT INTO room_images (room_id, image_url, uploaded_at)
             VALUES (?, ?, NOW())'
        );
        foreach ($images as $imagePath) {
            if (!is_string($imagePath) || $imagePath === '') {
                continue;
            }
            $imageUrl = normalize_image_url($imagePath);
            if ($imageUrl === '') {
                continue;
            }
            mysqli_bind_and_execute($imageStmt, 'is', [$roomId, $imageUrl]);
        }
    }

    if ($featureIds) {
        $featureStmt = mysqli_prepare_or_fail($db, 'INSERT INTO room_features (room_id, feature_id) VALUES (?, ?)');
        foreach ($featureIds as $featureId) {
            mysqli_bind_and_execute($featureStmt, 'ii', [$roomId, (int)$featureId]);
        }
    }

    $detailStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT r.id, r.building_id, r.room_number, r.floor, r.area, r.price, r.status, r.description,
                b.name AS building_name
         FROM rooms r
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE r.id = ? AND b.landlord_id = ?
         LIMIT 1'
    );
    mysqli_bind_and_execute($detailStmt, 'ii', [$roomId, $landlordId]);
    $room = mysqli_fetch_one_assoc($detailStmt) ?: ['id' => $roomId];

    $imagesStmt = mysqli_prepare_or_fail($db, 'SELECT image_url FROM room_images WHERE room_id = ? ORDER BY id ASC');
    mysqli_bind_and_execute($imagesStmt, 'i', [$roomId]);
    $roomImages = mysqli_fetch_all_assoc($imagesStmt);
    $room['images'] = array_map(
        static fn(array $img): string => (string)$img['image_url'],
        $roomImages
    );

    $featuresStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT f.id, f.name
         FROM room_features rf
         INNER JOIN features f ON f.id = rf.feature_id
         WHERE rf.room_id = ?
         ORDER BY f.id ASC'
    );
    mysqli_bind_and_execute($featuresStmt, 'i', [$roomId]);
    $room['features'] = mysqli_fetch_all_assoc($featuresStmt);

    $db->commit();
    json_response(true, 'Room created successfully', ['room' => $room], 201);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof mysqli) {
        $db->rollback();
    }
    json_response(false, 'Failed to create room', ['error' => $e->getMessage()], 500);
}
