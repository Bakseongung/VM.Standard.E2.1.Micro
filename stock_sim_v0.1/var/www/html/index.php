<?php
$host = 'localhost';
$user = 'root';
$pw = '[비밀번호]';
$db = 'stock_db';
$conn = mysqli_connect($host, $user, $pw, $db);

// --- [매매 처리 로직 시작] ---
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $stock_input = mysqli_real_escape_string($conn, $_POST['stock_input']);
    $amount = (int)$_POST['amount'];
    $action = $_POST['action']; // 'BUY' 또는 'SELL'

    // 트랜잭션 시작
    mysqli_begin_transaction($conn);

    try {
        // 1. 유저 확인 및 잠금 (FOR UPDATE)
        $u_sql = "SELECT id, cash FROM users WHERE username = '$username' FOR UPDATE";
        $u_res = mysqli_query($conn, $u_sql);
        $user_data = mysqli_fetch_assoc($u_res);

        // 2. 종목 확인 및 잠금 (코드 또는 이름으로 검색)
        $s_sql = "SELECT code, price, name FROM stock_prices WHERE code = '$stock_input' OR name = '$stock_input' FOR UPDATE";
        $s_res = mysqli_query($conn, $s_sql);
        $stock_data = mysqli_fetch_assoc($s_res);

        if (!$user_data || !$stock_data || $amount <= 0) {
            throw new Exception("유저/종목 정보가 올바르지 않거나 수량이 잘못되었습니다.");
        }

        $user_id = $user_data['id'];
        $cash = $user_data['cash'];
        $stock_code = $stock_data['code'];
        $price = $stock_data['price'];
        $total_price = $price * $amount;

        if ($action == 'BUY') {
            // [매수 로직]
            if ($cash < $total_price) throw new Exception("잔액이 부족합니다."); // 여기서 에러가 났던 것입니다.
            
            mysqli_query($conn, "UPDATE users SET cash = cash - $total_price WHERE id = $user_id");
            
            $check_stock = mysqli_query($conn, "SELECT id, quantity, average_price FROM user_stocks WHERE user_id = $user_id AND stock_code = '$stock_code' FOR UPDATE");
            $owned = mysqli_fetch_assoc($check_stock);

            if ($owned) {
                $new_qty = $owned['quantity'] + $amount;
                // 계산식에도 $가 빠졌는지 확인하세요
                $new_avg = (($owned['quantity'] * $owned['average_price']) + $total_price) / $new_qty;
                mysqli_query($conn, "UPDATE user_stocks SET quantity = $new_qty, average_price = $new_avg WHERE id = {$owned['id']}");
            } else {
                mysqli_query($conn, "INSERT INTO user_stocks (user_id, stock_code, quantity, average_price) VALUES ($user_id, '$stock_code', $amount, $price)");
            }
        } else {
            // [매도 로직]
            $check_stock = mysqli_query($conn, "SELECT id, quantity, average_price FROM user_stocks WHERE user_id = $user_id AND stock_code = '$stock_code' FOR UPDATE");
            $owned = mysqli_fetch_assoc($check_stock);

            if (!$owned || $owned['quantity'] < $amount) throw new Exception("보유 수량이 부족합니다.");

            mysqli_query($conn, "UPDATE users SET cash = cash + $total_price WHERE id = $user_id");
            
            if ($owned['quantity'] == $amount) {
                mysqli_query($conn, "DELETE FROM user_stocks WHERE id = {$owned['id']}");
            } else {
                mysqli_query($conn, "UPDATE user_stocks SET quantity = quantity - $amount WHERE id = {$owned['id']}");
            }
        }

        // 거래 기록 저장
        mysqli_query($conn, "INSERT INTO trade_history (user_id, stock_code, trade_type, amount, price, total_price) VALUES ($user_id, '$stock_code', '$action', $amount, $price, $total_price)");

        mysqli_commit($conn);
        $msg = "✅ 거래 성공: {$stock_data['name']} {$amount}주 " . ($action == 'BUY' ? "매수" : "매도") . " 완료!";
    } catch (Exception $e) {
        mysqli_rollback($conn);
        $msg = "❌ 거래 실패: " . $e->getMessage();
    }
}
// --- [매매 처리 로직 끝] ---

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

// 2. [추가] 특정 유저(Seongung)의 실시간 보유 주식 리스트
$my_stock_sql = "
    SELECT p.name, us.quantity, us.average_price, p.price as current_price,
           (p.price - us.average_price) * us.quantity as profit,
           ((p.price - us.average_price) / us.average_price * 100) as profit_rate
    FROM user_stocks us
    JOIN stock_prices p ON us.stock_code = p.code
    JOIN users u ON us.user_id = u.id
    WHERE u.username = 'Seongung'
    ORDER BY profit DESC";
$my_stock_res = mysqli_query($conn, $my_stock_sql);
?>

<!DOCTYPE html>
<html>
<head>
    <title>K-Stock Dashboard</title>
    <style>
        body { font-family: sans-serif; background: #f4f7f6; padding: 20px; }
        .card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); margin-bottom: 20px; }
        .btn { padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer; color: white; font-weight: bold; }
        .btn-buy { background: #e74c3c; }
        .btn-sell { background: #3498db; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { padding: 12px; border-bottom: 1px solid #ddd; text-align: left; }
        .plus { color: red; font-weight: bold; }
        .minus { color: blue; font-weight: bold; }
        input { padding: 8px; margin-right: 10px; border: 1px solid #ddd; border-radius: 4px; }
    </style>
</head>
<body>
    <h1>📈 주식 시뮬레이터 대시보드</h1>

    <?php if(isset($msg)) echo "<script>alert('$msg');</script>"; ?>

    <div class="card">
        <h2>💰 빠른 매매</h2>
        <form method="POST">
            <input type="text" name="username" placeholder="사용자 이름" required>
            <input type="text" name="stock_input" placeholder="종목명 또는 코드" required>
            <input type="number" name="amount" placeholder="수량" min="1" required>
            <button type="submit" name="action" value="BUY" class="btn btn-buy">즉시 매수</button>
            <button type="submit" name="action" value="SELL" class="btn btn-sell">즉시 매도</button>
        </form>
    </div>

    <div class="card" style="border-top: 5px solid #3498db;">
        <h2>💼 [Seongung]님의 실시간 포트폴리오</h2>
        <table>
            <tr>
                <th>종목명</th>
                <th>보유수량</th>
                <th>평균단가</th>
                <th>현재가</th>
                <th>수익금</th>
                <th>수익률</th>
            </tr>
            <?php 
            if (mysqli_num_rows($my_stock_res) > 0) {
                while($row = mysqli_fetch_assoc($my_stock_res)) {
                    $p_class = $row['profit'] >= 0 ? 'plus' : 'minus';
                    $p_sign = $row['profit'] >= 0 ? '+' : '';
                    echo "<tr>
                            <td><strong>{$row['name']}</strong></td>
                            <td>".number_format($row['quantity'])."주</td>
                            <td>".number_format($row['average_price'])."원</td>
                            <td>".number_format($row['current_price'])."원</td>
                            <td class='{$p_class}'>{$p_sign}".number_format($row['profit'])."원</td>
                            <td class='{$p_class}'>{$p_sign}".round($row['profit_rate'], 2)."%</td>
                          </tr>";
                }
            } else {
                echo "<tr><td colspan='6' style='text-align:center;'>보유 중인 주식이 없습니다.</td></tr>";
            }
            ?>
        </table>
    </div>

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

</body>
</html>
