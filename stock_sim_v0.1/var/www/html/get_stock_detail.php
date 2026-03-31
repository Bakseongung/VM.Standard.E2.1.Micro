<?php
header('Content-Type: application/json');

// 서버 타임존 설정 
date_default_timezone_set('Asia/Seoul');

$host = '[x]';
$user = '[x]';
$pw   = '[x]';
$db   = '[x]';

$conn = mysqli_connect($host, $user, $pw, $db);

if (!$conn) {
    die(json_encode(['error' => 'DB 연결 실패']));
}

// 입력값 필터링
$code = isset($_GET['code']) ? mysqli_real_escape_string($conn, $_GET['code']) : '';

// 데이터 조회
$sql = "SELECT price, market, 
               DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s') as updated_at 
        FROM stock_prices 
        WHERE code = '$code'";

$result = mysqli_query($conn, $sql);
$row = mysqli_fetch_assoc($result);

// 데이터 없을 경우
if (!$row) {
    echo json_encode([
        "price" => 0,
        "market" => "-",
        "updated_at" => date("Y-m-d H:i:s")
    ]);
} else {
    $row['price'] = (int)$row['price'];
    echo json_encode($row);
}

mysqli_close($conn);
?>
