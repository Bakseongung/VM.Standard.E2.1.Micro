<?php
header('Content-Type: application/json');

$redis = new Redis();
try {
    $redis->connect('[x]', [x]);
} catch (Exception $e) {
    echo json_encode(['error' => 'Redis 연결 실패']);
    exit;
}

$code = $_GET['code'] ?? '';

if ($code) {
    $price = $redis->get("stock:{$code}:price");
    $updated_at = $redis->get("stock:{$code}:time");

    if ($price === false) {
        $price = 0;
    }
    if ($updated_at === false) {
        $updated_at = date("Y-m-d H:i:s");
    }

    echo json_encode([
        'price' => (int)$price,
        'market' => 'KOSPI/KOSDAQ',
        'updated_at' => (string)$updated_at
    ]);
} else {
    echo json_encode(['error' => 'Code missing']);
}
?>
