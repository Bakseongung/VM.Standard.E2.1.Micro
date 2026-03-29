<?php
$host = 'localhost';
$user = 'root';
$pw = '[비밀번호]';
$db = 'stock_db';

$conn = mysqli_connect($host, $user, $pw, $db);

// 1. 수익률 랭킹 가져오기
$rank_sql = "
    SELECT u.username, 
           u.cash + IFNULL(SUM(us.quantity * p.price), 0) AS total_assets,
           ((u.cash + IFNULL(SUM(us.quantity * p.price), 0) - 10000000) / 10000000 * 100) AS roi
    FROM users u
    LEFT JOIN user_stocks us ON u.id = us.user_id
    LEFT JOIN stock_prices p ON us.stock_code = p.code
    GROUP BY u.id
    ORDER BY total_assets DESC LIMIT 10";
$rank_res = mysqli_query($conn, $rank_sql);

// 2. 가장 많이 보유한 대형주 TOP 5
$stock_sql = "
    SELECT p.name, SUM(us.quantity) as total_qty
    FROM user_stocks us
    JOIN stock_prices p ON us.stock_code = p.code
    GROUP BY us.stock_code
    ORDER BY total_qty DESC LIMIT 5";
$stock_res = mysqli_query($conn, $stock_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>K-Stock Dashboard</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; padding: 20px; }
        .card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        .plus { color: red; font-weight: bold; }
        .minus { color: blue; font-weight: bold; }
    </style>
</head>
<body>
    <h1>📈 주식 시뮬레이터 실시간 대시보드</h1>

    <div class="card">
        <h2>🏆 실시간 수익률 랭킹</h2>
        <table>
            <tr><th>순위</th><th>사용자</th><th>총 자산</th><th>수익률</th></tr>
            <?php 
            $i = 1;
            while($row = mysqli_fetch_assoc($rank_res)) {
                $roi_class = $row['roi'] >= 0 ? 'plus' : 'minus';
                echo "<tr>
                        <td>{$i}위</td>
                        <td>{$row['username']}</td>
                        <td>".number_format($row['total_assets'])."원</td>
                        <td class='{$roi_class}'>".round($row['roi'], 2)."%</td>
                      </tr>";
                $i++;
            } ?>
        </table>
    </div>

    <div class="card">
        <h2>🔥 인기 보유 종목 TOP 5</h2>
        <table>
            <tr><th>종목명</th><th>전체 유저 보유 수량</th></tr>
            <?php while($row = mysqli_fetch_assoc($stock_res)) {
                echo "<tr><td>{$row['name']}</td><td>".number_format($row['total_qty'])."주</td></tr>";
            } ?>
        </table>
    </div>
</body>
</html>
