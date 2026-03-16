<?php
declare(strict_types=1);

require_once __DIR__ . '/chatbot_logic.php';

header('Content-Type: application/json; charset=utf-8');

$cases = [
    ['message' => 'xin chào', 'expected' => 'greeting'],
    ['message' => 'hello bạn', 'expected' => 'greeting'],
    ['message' => 'hi chatbot', 'expected' => 'greeting'],

    ['message' => 'còn phòng trống không', 'expected' => 'search_available_room'],
    ['message' => 'hiện tại còn phòng không', 'expected' => 'search_available_room'],
    ['message' => 'available room không', 'expected' => 'search_available_room'],

    ['message' => 'phòng dưới 3 triệu', 'expected' => 'search_room_by_price'],
    ['message' => 'phòng giá 2tr', 'expected' => 'search_room_by_price'],
    ['message' => 'phòng bao nhiêu tiền', 'expected' => 'search_room_by_price'],

    ['message' => 'giá điện nước bao nhiêu', 'expected' => 'utility_price'],
    ['message' => 'tiền điện tháng này', 'expected' => 'utility_price'],
    ['message' => 'internet bao nhiêu', 'expected' => 'utility_price'],

    ['message' => 'phòng A101 còn không', 'expected' => 'room_detail'],
    ['message' => 'cho xem phòng B203', 'expected' => 'room_detail'],

    ['message' => 'tôi muốn hỏi thông tin khác', 'expected' => 'unknown'],
];

$results = [];
$passCount = 0;

foreach ($cases as $case) {
    $actual = detectIntent($case['message']);
    $pass = ($actual === $case['expected']);
    if ($pass) {
        $passCount++;
    }

    $results[] = [
        'message' => $case['message'],
        'expected' => $case['expected'],
        'actual' => $actual,
        'pass' => $pass,
    ];
}

echo json_encode([
    'summary' => [
        'total' => count($cases),
        'pass' => $passCount,
        'fail' => count($cases) - $passCount,
    ],
    'results' => $results,
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);