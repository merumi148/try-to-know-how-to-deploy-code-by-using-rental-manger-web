<?php
declare(strict_types=1);

/**
 * Remove Vietnamese accents for easier keyword matching.
 */
function removeVietnameseAccents(string $text): string
{
    $map = [
        'a' => ['à','á','ạ','ả','ã','â','ầ','ấ','ậ','ẩ','ẫ','ă','ằ','ắ','ặ','ẳ','ẵ'],
        'e' => ['è','é','ẹ','ẻ','ẽ','ê','ề','ế','ệ','ể','ễ'],
        'i' => ['ì','í','ị','ỉ','ĩ'],
        'o' => ['ò','ó','ọ','ỏ','õ','ô','ồ','ố','ộ','ổ','ỗ','ơ','ờ','ớ','ợ','ở','ỡ'],
        'u' => ['ù','ú','ụ','ủ','ũ','ư','ừ','ứ','ự','ử','ữ'],
        'y' => ['ỳ','ý','ỵ','ỷ','ỹ'],
        'd' => ['đ'],
        'A' => ['À','Á','Ạ','Ả','Ã','Â','Ầ','Ấ','Ậ','Ẩ','Ẫ','Ă','Ằ','Ắ','Ặ','Ẳ','Ẵ'],
        'E' => ['È','É','Ẹ','Ẻ','Ẽ','Ê','Ề','Ế','Ệ','Ể','Ễ'],
        'I' => ['Ì','Í','Ị','Ỉ','Ĩ'],
        'O' => ['Ò','Ó','Ọ','Ỏ','Õ','Ô','Ồ','Ố','Ộ','Ổ','Ỗ','Ơ','Ờ','Ớ','Ợ','Ở','Ỡ'],
        'U' => ['Ù','Ú','Ụ','Ủ','Ũ','Ư','Ừ','Ứ','Ự','Ử','Ữ'],
        'Y' => ['Ỳ','Ý','Ỵ','Ỷ','Ỹ'],
        'D' => ['Đ'],
    ];

    foreach ($map as $ascii => $chars) {
        $text = str_replace($chars, $ascii, $text);
    }

    return $text;
}

/**
 * Normalize user text before detecting intent.
 * - lowercase
 * - remove accents
 * - remove special chars
 * - trim spaces
 */
function normalizeText(string $text): string
{
    $text = mb_strtolower($text, 'UTF-8');
    $text = removeVietnameseAccents($text);
    $text = preg_replace('/[^a-z0-9\s]/', ' ', $text) ?? '';
    $text = preg_replace('/\s+/', ' ', $text) ?? '';
    return trim($text);
}

function containsAny(string $text, array $keywords): bool
{
    foreach ($keywords as $kw) {
        if (str_contains($text, $kw)) {
            return true;
        }
    }
    return false;
}

/**
 * Detect chatbot intent by keywords + patterns.
 */
function detectIntent(string $message): string
{
    $text = normalizeText($message);

    if (containsAny($text, ['ban lam duoc gi', 'huong dan su dung', 'giup toi', 'tro giup', 'help'])) {
        return 'help';
    }

    if (preg_match('/\b(phong\s*)?[a-z]\d{2,4}\b/', $text)) {
        if (containsAny($text, ['thong tin', 'chi tiet', 'phong'])) {
            return 'room_detail';
        }
    }

    if (containsAny($text, ['bao nhieu phong', 'tong so phong', 'co bao nhieu phong'])) {
        return 'room_count';
    }

    if (containsAny($text, ['re nhat', 'gia thap nhat'])) {
        return 'cheapest_room';
    }

    if (containsAny($text, ['dat nhat', 'gia cao nhat'])) {
        return 'most_expensive_room';
    }

    if (containsAny($text, ['gia dien', 'gia nuoc', 'tien dien', 'internet', 'phi dich vu'])) {
        return 'utility_price';
    }

    if (containsAny($text, ['phong trong', 'con phong', 'available room', 'phong nao dang trong'])) {
        return 'search_available_room';
    }

    if (containsAny($text, ['tang'])) {
        return 'search_room_by_floor';
    }

    if (containsAny($text, ['m2', 'dien tich', 'phong rong', 'tren']) && containsAny($text, ['phong', 'dien tich', 'm2'])) {
        return 'search_room_by_area';
    }

    if (containsAny($text, ['toa ', 'building ', 'khu ', 'nha '])) {
        return 'search_room_by_building';
    }

    if (containsAny($text, ['duoi', 'toi da', 'gia', 'trieu', 'phong re', 'bao nhieu'])) {
        if (containsAny($text, ['phong'])) {
            return 'search_room_by_price';
        }
    }

    if (containsAny($text, ['hi', 'hello', 'xin chao', 'chao ban', 'chao chatbot'])) {
        return 'greeting';
    }

    return 'unknown';
}

function extractMaxPrice(string $message): ?int
{
    $text = normalizeText($message);
    if (!preg_match('/(\d+(?:[.,]\d+)?)\s*(trieu|tr|k|nghin|ngan)?/', $text, $m)) {
        return null;
    }

    $number = (float)str_replace(',', '.', $m[1]);
    $unit = $m[2] ?? '';

    if (in_array($unit, ['trieu', 'tr'], true)) {
        $number *= 1000000;
    } elseif (in_array($unit, ['k', 'nghin', 'ngan'], true)) {
        $number *= 1000;
    }

    return (int)$number;
}

function extractFloor(string $message): ?int
{
    $text = normalizeText($message);
    if (preg_match('/tang\s*(\d+)/', $text, $m)) {
        return (int)$m[1];
    }
    return null;
}

function extractArea(string $message): ?float
{
    $text = normalizeText($message);
    if (preg_match('/(\d+(?:[.,]\d+)?)\s*(m2|m)?/', $text, $m)) {
        return (float)str_replace(',', '.', $m[1]);
    }
    return null;
}

function extractRoomNumber(string $message): ?string
{
    $text = normalizeText($message);
    if (preg_match('/\b([a-z]\d{2,4})\b/', $text, $m)) {
        return strtoupper($m[1]);
    }
    return null;
}

function extractBuildingKeyword(string $message): ?string
{
    $text = normalizeText($message);
    if (preg_match('/(?:toa|building|khu|nha)\s+([a-z0-9\s]+)/', $text, $m)) {
        $value = trim($m[1]);
        return $value !== '' ? $value : null;
    }
    return null;
}

function formatVnd(float $value): string
{
    return number_format($value, 0, ',', '.') . ' VND';
}