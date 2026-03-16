<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/helpers.php';

init_api_headers();
method_not_allowed(['PUT']);

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
require_fields($input, ['id']);

$roomId = (int)$input['id'];
$allowed = ['building_id', 'room_number', 'floor', 'description', 'price', 'area', 'status'];

$setParts = [];
$types = '';
$values = [];

foreach ($allowed as $field) {
    if (!array_key_exists($field, $input)) {
        continue;
    }

    $setParts[] = "r.{$field} = ?";
    if (in_array($field, ['building_id', 'floor'], true)) {
        $types .= 'i';
        $values[] = (int)$input[$field];
    } elseif (in_array($field, ['price', 'area'], true)) {
        $types .= 'd';
        $values[] = (float)$input[$field];
    } else {
        $types .= 's';
        $values[] = (string)$input[$field];
    }
}

if (!$setParts && !array_key_exists('images', $input) && !array_key_exists('feature_ids', $input)) {
    json_response(false, 'No fields to update', [], 422);
}

try {
    $db = (new Database())->getMysqliConnection();
    $db->begin_transaction();

    // Lock scope: room must belong to a building owned by current landlord.
    $roomScopeStmt = mysqli_prepare_or_fail(
        $db,
        'SELECT r.id
         FROM rooms r
         INNER JOIN buildings b ON b.id = r.building_id
         WHERE r.id = ? AND b.landlord_id = ?
         LIMIT 1'
    );
    mysqli_bind_and_execute($roomScopeStmt, 'ii', [$roomId, $landlordId]);
    if (!mysqli_fetch_one_assoc($roomScopeStmt)) {
        json_response(false, 'Room not found for current landlord', [], 404);
    }

    if (array_key_exists('building_id', $input)) {
        $newBuildingId = (int)$input['building_id'];
        $buildingStmt = mysqli_prepare_or_fail($db, 'SELECT id FROM buildings WHERE id = ? AND landlord_id = ? LIMIT 1');
        mysqli_bind_and_execute($buildingStmt, 'ii', [$newBuildingId, $landlordId]);
        if (!mysqli_fetch_one_assoc($buildingStmt)) {
            json_response(false, 'Target building does not belong to current landlord', [], 422);
        }
    }

    if ($setParts) {
        $sql = 'UPDATE rooms r
                INNER JOIN buildings b ON b.id = r.building_id
                SET ' . implode(', ', $setParts) . '
                WHERE r.id = ? AND b.landlord_id = ?';

        $stmt = mysqli_prepare_or_fail($db, $sql);
        $typesWithScope = $types . 'ii';
        $valuesWithScope = array_merge($values, [$roomId, $landlordId]);
        mysqli_bind_and_execute($stmt, $typesWithScope, $valuesWithScope);
    }

    if (array_key_exists('images', $input) && is_array($input['images'])) {
        $delImage = mysqli_prepare_or_fail($db, 'DELETE FROM room_images WHERE room_id = ?');
        mysqli_bind_and_execute($delImage, 'i', [$roomId]);

        $insImage = mysqli_prepare_or_fail(
            $db,
            'INSERT INTO room_images (room_id, image_url, uploaded_at)
             VALUES (?, ?, NOW())'
        );
        foreach ($input['images'] as $imagePath) {
            if (!is_string($imagePath) || trim($imagePath) === '') {
                continue;
            }
            $imageUrl = normalize_image_url($imagePath);
            if ($imageUrl === '') {
                continue;
            }
            mysqli_bind_and_execute($insImage, 'is', [$roomId, $imageUrl]);
        }
    }

    if (array_key_exists('feature_ids', $input) && is_array($input['feature_ids'])) {
        $delFeature = mysqli_prepare_or_fail($db, 'DELETE FROM room_features WHERE room_id = ?');
        mysqli_bind_and_execute($delFeature, 'i', [$roomId]);

        $insFeature = mysqli_prepare_or_fail($db, 'INSERT INTO room_features (room_id, feature_id) VALUES (?, ?)');
        foreach ($input['feature_ids'] as $featureId) {
            mysqli_bind_and_execute($insFeature, 'ii', [$roomId, (int)$featureId]);
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
    json_response(true, 'Room updated successfully', ['room' => $room]);
} catch (Throwable $e) {
    if (isset($db) && $db instanceof mysqli) {
        $db->rollback();
    }
    json_response(false, 'Failed to update room', ['error' => $e->getMessage()], 500);
}
