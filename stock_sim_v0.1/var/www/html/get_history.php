<?php
header('Content-Type: application/json');

$host = '[x]'; 
$user = '[x]'; 
$pw   = '[x]'; 
$db   = '[x]';

$conn = mysqli_connect($host, $user, $pw, $db);

if (!$conn) { die(json_encode(['error' => 'DB 연결 실패'])); }

// 한국 시간대 설정
date_default_timezone_set('Asia/Seoul');

$code = isset($_GET['code']) ? mysqli_real_escape_string($conn, $_GET['code']) : '';
$range = $_GET['range'] ?? 'all';

// 마지막 날짜 조회
$last_date_res = mysqli_query($conn, "SELECT MAX(DATE(recorded_at)) as last_day FROM stock_history WHERE stock_code = '$code'");
$last_day_row = mysqli_fetch_assoc($last_date_res);
$last_day = $last_day_row['last_day'];

if (!$last_day) {
    echo json_encode(['labels' => [], 'prices' => []]);
    exit;
}

// 기간 필터 설정
if ($range == '1d') {
    $date_cond = "DATE(recorded_at) = '$last_day'";
    $format = '%H:%i';
} elseif ($range == '1w') {
    $date_cond = "recorded_at >= DATE_SUB('$last_day', INTERVAL 7 DAY)";
    $format = '%m-%d %H:%i';
} elseif ($range == '1m') {
    $date_cond = "recorded_at >= DATE_SUB('$last_day', INTERVAL 1 MONTH)";
    $format = '%m-%d';
} else {
    $date_cond = "1=1";
    $format = '%m-%d';
}

// 데이터 조회
$sql = "SELECT price, 
               DATE_FORMAT(recorded_at, '$format') as t_label 
        FROM stock_history 
        WHERE stock_code = '$code' AND $date_cond 
        ORDER BY recorded_at ASC";

$result = mysqli_query($conn, $sql);

$labels = []; 
$prices = [];

while($row = mysqli_fetch_assoc($result)) {
    $labels[] = $row['t_label'];
    $prices[] = (int)$row['price'];
}

echo json_encode(['labels' => $labels, 'prices' => $prices]);

mysqli_close($conn);
?>
