<?php
// 1. 에러 보고 (디버깅용)
error_reporting(E_ALL);
ini_set('display_errors', '1');

// 2. DB 연결 설정
$host = 'localhost';
$user = 'root';
$pw = '[비밀번호]';
$db = 'stock_db';
$conn = mysqli_connect($host, $user, $pw, $db);

if (!$conn) {
    die("DB 연결 실패: " . mysqli_connect_error());
}

$msg = "";

// 3. [매매 처리 로직]
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $stock_input = mysqli_real_escape_string($conn, $_POST['stock_input']);
    $amount = (int)$_POST['amount'];
    $action = $_POST['action'];

    mysqli_begin_transaction($conn);

    try {
        // 유저 정보 잠금 조회
        $u_sql = "SELECT id, cash FROM users WHERE username = '$username' FOR UPDATE";
        $u_res = mysqli_query($conn, $u_sql);
        $user_data = mysqli_fetch_assoc($u_res);

        // 종목 정보 잠금 조회
        $s_sql = "SELECT code, price, name FROM stock_prices WHERE code = '$stock_input' OR name = '$stock_input' FOR UPDATE";
        $s_res = mysqli_query($conn, $s_sql);
        $stock_data = mysqli_fetch_assoc($s_res);

        if (!$user_data || !$stock_data || $amount <= 0) {
            throw new Exception("정보가 올바르지 않거나 수량이 잘못되었습니다.");
        }

        $user_id = $user_data['id'];
        $cash = $user_data['cash'];
        $stock_code = $stock_data['code'];
        $price = $stock_data['price'];
        $total_price = $price * $amount;

        if ($action == 'BUY') {
            if ($cash < $total_price) throw new Exception("잔액이 부족합니다.");
            mysqli_query($conn, "UPDATE users SET cash = cash - $total_price WHERE id = $user_id");
            
            $check_stock = mysqli_query($conn, "SELECT id, quantity, average_price FROM user_stocks WHERE user_id = $user_id AND stock_code = '$stock_code' FOR UPDATE");
            $owned = mysqli_fetch_assoc($check_stock);

            if ($owned) {
                $new_qty = $owned['quantity'] + $amount;
                $new_avg = (($owned['quantity'] * $owned['average_price']) + $total_price) / $new_qty;
                mysqli_query($conn, "UPDATE user_stocks SET quantity = $new_qty, average_price = $new_avg WHERE id = {$owned['id']}");
            } else {
                mysqli_query($conn, "INSERT INTO user_stocks (user_id, stock_code, quantity, average_price) VALUES ($user_id, '$stock_code', $amount, $price)");
            }
        } else {
            $check_stock = mysqli_query($conn, "SELECT id, quantity FROM user_stocks WHERE user_id = $user_id AND stock_code = '$stock_code' FOR UPDATE");
            $owned = mysqli_fetch_assoc($check_stock);
            if (!$owned || $owned['quantity'] < $amount) throw new Exception("보유 수량이 부족합니다.");

            mysqli_query($conn, "UPDATE users SET cash = cash + $total_price WHERE id = $user_id");
            if ($owned['quantity'] == $amount) {
                mysqli_query($conn, "DELETE FROM user_stocks WHERE id = {$owned['id']}");
            } else {
                mysqli_query($conn, "UPDATE user_stocks SET quantity = quantity - $amount WHERE id = {$owned['id']}");
            }
        }

        mysqli_query($conn, "INSERT INTO trade_history (user_id, stock_code, trade_type, amount, price, total_price) VALUES ($user_id, '$stock_code', '$action', $amount, $price, $total_price)");
        mysqli_commit($conn);
        $msg = "✅ 거래 성공!";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $msg = "❌ 에러: " . $e->getMessage();
    }
}

// 4. [데이터 조회]
// 랭킹 조회
$rank_sql = "SELECT u.username, u.cash + IFNULL(SUM(us.quantity * p.price), 0) AS total_assets FROM users u LEFT JOIN user_stocks us ON u.id = us.user_id LEFT JOIN stock_prices p ON us.stock_code = p.code GROUP BY u.id ORDER BY total_assets DESC LIMIT 10";
$rank_res = mysqli_query($conn, $rank_sql);

// 내 포트폴리오 조회 (Seongung 기준)
$my_stock_sql = "SELECT p.name, us.quantity, us.average_price, p.price as current_price FROM user_stocks us JOIN stock_prices p ON us.stock_code = p.code JOIN users u ON us.user_id = u.id WHERE u.username = 'Seongung' ORDER BY (p.price * us.quantity) DESC";
$my_stock_res = mysqli_query($conn, $my_stock_sql);

$labels = [];
$data_values = [];
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>K-Stock Simulator Dashboard</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { font-family: 'Malgun Gothic', sans-serif; background: #f4f7f6; padding: 20px; color: #333; }
        .card { background: white; padding: 25px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-bottom: 25px; }
        .flex-container { display: flex; gap: 30px; flex-wrap: wrap; align-items: flex-start; }
        .chart-container { flex: 1; min-width: 300px; height: 350px; }
        .table-container { flex: 1.5; min-width: 500px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 15px; border-bottom: 1px solid #eee; text-align: left; }
        th { background: #fafafa; color: #666; }
        .btn { padding: 12px 25px; border: none; border-radius: 6px; cursor: pointer; color: white; font-weight: bold; transition: 0.3s; }
        .btn-buy { background: #e74c3c; } .btn-buy:hover { background: #c0392b; }
        .btn-sell { background: #3498db; } .btn-sell:hover { background: #2980b9; }
        input { padding: 12px; margin-right: 10px; border: 1px solid #ddd; border-radius: 6px; width: 180px; }
        .plus { color: #e74c3c; font-weight: bold; }
        .minus { color: #3498db; font-weight: bold; }
    </style>
</head>
<body>

    <h1>📊 주식 시뮬레이터 실시간 대시보드</h1>

    <?php if($msg) echo "<script>alert('$msg');</script>"; ?>

    <div class="card">
        <h2>💰 빠른 매매</h2>
        <form method="POST">
            <input type="text" name="username" value="Seongung" readonly style="background:#eee;">
            <input type="text" name="stock_input" placeholder="종목명 또는 코드" required>
            <input type="number" name="amount" placeholder="수량" min="1" required>
            <button type="submit" name="action" value="BUY" class="btn btn-buy">즉시 매수</button>
            <button type="submit" name="action" value="SELL" class="btn btn-sell">즉시 매도</button>
        </form>
    </div>

    <div class="card" style="border-top: 6px solid #3498db;">
        <h2>💼 [Seongung]님의 실시간 자산 분석</h2>
        <div class="flex-container">
            <div class="chart-container">
                <canvas id="portfolioChart"></canvas>
            </div>
            <div class="table-container">
                <table>
                    <thead>
                        <tr><th>종목명</th><th>보유량</th><th>현재가</th><th>수익률</th></tr>
                    </thead>
                    <tbody>
                        <?php 
                        if (mysqli_num_rows($my_stock_res) > 0) {
                            while($row = mysqli_fetch_assoc($my_stock_res)) {
                                $labels[] = $row['name'];
                                $eval_amt = $row['current_price'] * $row['quantity'];
                                $data_values[] = $eval_amt;
                                $profit_rate = (($row['current_price'] - $row['average_price']) / $row['average_price']) * 100;
                                $p_class = $profit_rate >= 0 ? 'plus' : 'minus';
                                echo "<tr>
                                        <td><strong>{$row['name']}</strong></td>
                                        <td>".number_format($row['quantity'])."주</td>
                                        <td>".number_format($row['current_price'])."원</td>
                                        <td class='{$p_class}'>".round($profit_rate, 2)."%</td>
                                      </tr>";
                            }
                        } else {
                            echo "<tr><td colspan='4' style='text-align:center; padding:50px;'>보유한 주식이 없습니다. 상단에서 매수해보세요!</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="card">
        <h2>🏆 전체 수익률 랭킹</h2>
        <table>
            <thead>
                <tr><th>순위</th><th>사용자</th><th>총 자산(현금+주식)</th></tr>
            </thead>
            <tbody>
                <?php 
                $rank = 1;
                while($row = mysqli_fetch_assoc($rank_res)) {
                    $is_me = ($row['username'] == 'Seongung') ? "style='background:#fff9f9; font-weight:bold;'" : "";
                    echo "<tr $is_me>
                            <td>{$rank}위</td>
                            <td>{$row['username']}</td>
                            <td>".number_format($row['total_assets'])."원</td>
                          </tr>";
                    $rank++;
                } ?>
            </tbody>
        </table>
    </div>

    <script>
        const ctx = document.getElementById('portfolioChart').getContext('2d');
        new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode($labels); ?>,
                datasets: [{
                    data: <?php echo json_encode($data_values); ?>,
                    backgroundColor: ['#ff6384', '#36a2eb', '#ffce56', '#4bc0c0', '#9966ff', '#fdb45c'],
                    hoverOffset: 15,
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 20 } },
                    tooltip: { callbacks: { label: (item) => ' ' + item.label + ': ' + item.raw.toLocaleString() + '원' } }
                }
            }
        });
    </script>
</body>
</html>
