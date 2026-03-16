<?php
declare(strict_types=1);

require_once __DIR__ . '/chatbot_logic.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['response' => 'Phuong thuc khong duoc ho tro.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput ?: '', true);

if (!is_array($input) || empty($input['message'])) {
    http_response_code(422);
    echo json_encode(['response' => 'Thieu noi dung cau hoi.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$message = trim((string)$input['message']);
$intent = detectIntent($message);
$responseText = 'Hien chua co phong phu hop.';

$host = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'quanlyphongtro_db';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';

$mysqli = new mysqli($host, $user, $pass, $dbName);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['response' => 'Khong the ket noi co so du lieu.'], JSON_UNESCAPED_UNICODE);
    exit;
}
$mysqli->set_charset('utf8mb4');

$noDataMessage = 'Hiện chưa có phòng phù hợp.';

try {
    switch ($intent) {
        case 'greeting':
            $responseText = 'Xin chao! Toi co the ho tro: phong trong, phong theo gia, tang, dien tich, toa nha, thong tin phong va gia dich vu.';
            break;

        case 'help':
            $responseText = "Ban co the hoi:\n"
                . "- con phong trong khong\n"
                . "- phong duoi 3 trieu\n"
                . "- phong tang 2\n"
                . "- phong tren 20m2\n"
                . "- phong toa A\n"
                . "- thong tin phong A101\n"
                . "- gia dien nuoc\n"
                . "- phong re nhat / phong dat nhat\n"
                . "- co bao nhieu phong";
            break;

        case 'search_available_room':
            $result = $mysqli->query("SELECT room_number, price, area FROM rooms WHERE status='available'");
            if ($result && $result->num_rows > 0) {
                $lines = ['Cac phong dang trong:'];
                while ($row = $result->fetch_assoc()) {
                    $lines[] = 'Phong ' . $row['room_number'] . ' - ' . formatVnd((float)$row['price']) . ' - ' . $row['area'] . 'm2';
                }
                $responseText = implode("\n", $lines);
            } else {
                $responseText = $noDataMessage;
            }
            break;

        case 'search_room_by_price':
            $maxPrice = extractMaxPrice($message);
            if ($maxPrice === null || $maxPrice <= 0) {
                $responseText = 'Vui long nhap muc gia hop le. Vi du: phong duoi 3 trieu.';
                break;
            }

            $stmt = $mysqli->prepare("SELECT room_number, price FROM rooms WHERE price <= ? AND status='available'");
            $stmt->bind_param('i', $maxPrice);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $lines = ['Cac phong theo muc gia yeu cau:'];
                while ($row = $result->fetch_assoc()) {
                    $lines[] = 'Phong ' . $row['room_number'] . ' - ' . formatVnd((float)$row['price']);
                }
                $responseText = implode("\n", $lines);
            } else {
                $responseText = $noDataMessage;
            }
            $stmt->close();
            break;

        case 'search_room_by_floor':
            $floor = extractFloor($message);
            if ($floor === null) {
                $responseText = 'Vui long cung cap so tang. Vi du: phong tang 2.';
                break;
            }

            $stmt = $mysqli->prepare("SELECT room_number, price, area, floor FROM rooms WHERE floor = ? AND status='available'");
            $stmt->bind_param('i', $floor);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $lines = ['Cac phong o tang ' . $floor . ':'];
                while ($row = $result->fetch_assoc()) {
                    $lines[] = 'Phong ' . $row['room_number'] . ' - ' . formatVnd((float)$row['price']) . ' - ' . $row['area'] . 'm2';
                }
                $responseText = implode("\n", $lines);
            } else {
                $responseText = $noDataMessage;
            }
            $stmt->close();
            break;

        case 'search_room_by_area':
            $area = extractArea($message);
            if ($area === null || $area <= 0) {
                $responseText = 'Vui long cung cap dien tich. Vi du: phong tren 20m2.';
                break;
            }

            $stmt = $mysqli->prepare("SELECT room_number, price, area FROM rooms WHERE area >= ? AND status='available' ORDER BY area ASC");
            $stmt->bind_param('d', $area);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $lines = ['Cac phong co dien tich tu ' . $area . 'm2:'];
                while ($row = $result->fetch_assoc()) {
                    $lines[] = 'Phong ' . $row['room_number'] . ' - ' . formatVnd((float)$row['price']) . ' - ' . $row['area'] . 'm2';
                }
                $responseText = implode("\n", $lines);
            } else {
                $responseText = $noDataMessage;
            }
            $stmt->close();
            break;

        case 'search_room_by_building':
            $keyword = extractBuildingKeyword($message);
            if ($keyword === null) {
                $responseText = 'Vui long nhap ten toa nha. Vi du: phong toa A.';
                break;
            }

            $like = '%' . $keyword . '%';
            $stmt = $mysqli->prepare(
                "SELECT r.room_number, r.price, r.area, b.name AS building_name
                 FROM rooms r
                 JOIN buildings b ON b.id = r.building_id
                 WHERE (LOWER(b.name) LIKE LOWER(?) OR LOWER(b.address) LIKE LOWER(?))
                 AND r.status='available'"
            );
            $stmt->bind_param('ss', $like, $like);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && $result->num_rows > 0) {
                $lines = ['Cac phong tai toa nha phu hop:'];
                while ($row = $result->fetch_assoc()) {
                    $lines[] = 'Phong ' . $row['room_number'] . ' (' . $row['building_name'] . ') - ' . formatVnd((float)$row['price']) . ' - ' . $row['area'] . 'm2';
                }
                $responseText = implode("\n", $lines);
            } else {
                $responseText = $noDataMessage;
            }
            $stmt->close();
            break;

        case 'room_detail':
            $roomNumber = extractRoomNumber($message);
            if ($roomNumber === null) {
                $responseText = 'Vui long nhap ma phong. Vi du: phong A101.';
                break;
            }

            $stmt = $mysqli->prepare('SELECT room_number, floor, area, price, status, description FROM rooms WHERE room_number = ? LIMIT 1');
            $stmt->bind_param('s', $roomNumber);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($result && ($row = $result->fetch_assoc())) {
                $responseText = 'Thong tin phong ' . $row['room_number'] . ': '
                    . formatVnd((float)$row['price']) . ', '
                    . $row['area'] . 'm2, tang ' . $row['floor'] . ', trang thai ' . $row['status'];
                if (!empty($row['description'])) {
                    $responseText .= '. Mo ta: ' . $row['description'];
                }
            } else {
                $responseText = $noDataMessage;
            }
            $stmt->close();
            break;

        case 'utility_price':
            $result = $mysqli->query(
                "SELECT electricity_price, water_price, internet_price, effective_from
                 FROM utility_rates
                 ORDER BY effective_from DESC
                 LIMIT 1"
            );
            if ($result && ($row = $result->fetch_assoc())) {
                $responseText = 'Gia dich vu (tu ' . $row['effective_from'] . '): '
                    . 'Dien ' . formatVnd((float)$row['electricity_price']) . '/kWh, '
                    . 'Nuoc ' . formatVnd((float)$row['water_price']) . '/m3, '
                    . 'Internet ' . formatVnd((float)$row['internet_price']) . '/thang.';
            } else {
                $responseText = $noDataMessage;
            }
            break;

        case 'cheapest_room':
            $result = $mysqli->query('SELECT room_number, price FROM rooms ORDER BY price ASC LIMIT 1');
            if ($result && ($row = $result->fetch_assoc())) {
                $responseText = 'Phong re nhat: ' . $row['room_number'] . ' - ' . formatVnd((float)$row['price']);
            } else {
                $responseText = $noDataMessage;
            }
            break;

        case 'most_expensive_room':
            $result = $mysqli->query('SELECT room_number, price FROM rooms ORDER BY price DESC LIMIT 1');
            if ($result && ($row = $result->fetch_assoc())) {
                $responseText = 'Phong dat nhat: ' . $row['room_number'] . ' - ' . formatVnd((float)$row['price']);
            } else {
                $responseText = $noDataMessage;
            }
            break;

        case 'room_count':
            $result = $mysqli->query('SELECT COUNT(*) AS total_rooms FROM rooms');
            if ($result && ($row = $result->fetch_assoc())) {
                $responseText = 'Tong so phong hien co: ' . (int)$row['total_rooms'];
            } else {
                $responseText = $noDataMessage;
            }
            break;

        default:
            $responseText = 'Xin lỗi tôi chưa hiểu câu hỏi. Bạn có thể hỏi về phòng trống, giá phòng hoặc giá điện nước.';
            break;
    }

    // Save chat history
    $logStmt = $mysqli->prepare(
        'INSERT INTO chat_logs (user_message, bot_response, detected_intent, created_at)
         VALUES (?, ?, ?, NOW())'
    );
    if ($logStmt) {
        $logStmt->bind_param('sss', $message, $responseText, $intent);
        $logStmt->execute();
        $logStmt->close();
    }

    echo json_encode(['response' => $responseText], JSON_UNESCAPED_UNICODE);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['response' => 'Co loi xay ra khi xu ly chatbot.'], JSON_UNESCAPED_UNICODE);
} finally {
    $mysqli->close();
}