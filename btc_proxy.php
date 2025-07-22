<?php
/*
 * btc_proxy.php – агрегирует свечи BTC/USDT с Binance и отдаёт единый JSON
 *
 * © 2025, поставить на собственный сервер (PHP ≥7.4, cURL включён).
 */

header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('UTC');      // чтобы в JSON были ISO-8601 в UTC

// --- 1. Настройка -----------------------------------------------------------
$symbol   = 'BTCUSDT';
$intervals = [
    '1m',  // 1-минута
    '5m',  // 5-минут
    '15m', // 15-минут
    '1h',  // 1-час
    '4h'   // 4-часа
];
$limit    = 1000;                      // максимум свечей за запрос
$baseUrl  = 'https://api.binance.com/api/v3/klines';

// --- 2. Функция запроса одной серии свечей ----------------------------------
function fetchKlines(string $url): array|null {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
    ]);
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    curl_close($ch);

    if ($raw === false || $err) {
        return null;
    }
    return json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
}

// --- 3. Сбор данных ---------------------------------------------------------
$response = [
    'symbol'   => $symbol,
    'generated'=> gmdate('c'),
    'data'     => []
];

foreach ($intervals as $int) {
    $url = sprintf('%s?symbol=%s&interval=%s&limit=%d',
                   $baseUrl, $symbol, $int, $limit);
    $klines = fetchKlines($url);
    if ($klines === null) {
        http_response_code(502);
        echo json_encode(['error' => "Failed fetching $int"], JSON_PRETTY_PRINT);
        exit;
    }
    // укорачиваем до нужных полей: [openTime, open, high, low, close, volume]
    $cleaned = array_map(fn($k) => [
        't' => $k[0],          // ms
        'o' => $k[1],
        'h' => $k[2],
        'l' => $k[3],
        'c' => $k[4],
        'v' => $k[5]
    ], $klines);

    $response['data'][$int] = $cleaned;
}

// --- 4. Вывод ---------------------------------------------------------------
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
