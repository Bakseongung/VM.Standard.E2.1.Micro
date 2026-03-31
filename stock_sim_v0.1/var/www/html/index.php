ubuntu@instance-micro1:/var/www/html$ cat index.php
<?php
error_reporting(E_ALL); ini_set('display_errors', '1');
$host = '[x]'; $user = '[x]'; $pw = '[x]'; $db = '[x]';
$conn = mysqli_connect($host, $user, $pw, $db);

$current_user = '[x]'; 
$msg = "";

// [매매 로직]
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    $username = mysqli_real_escape_string($conn, $_POST['username']);
    $stock_input = mysqli_real_escape_string($conn, $_POST['stock_input']);
    $amount = (int)$_POST['amount'];
    $action = $_POST['action'];

    mysqli_begin_transaction($conn);
    try {
        $u_res = mysqli_query($conn, "SELECT id, cash FROM users WHERE username = '$username' FOR UPDATE");
        $s_res = mysqli_query($conn, "SELECT code, price FROM stock_prices WHERE code = '$stock_input' OR name = '$stock_input' FOR UPDATE");
        $u_data = mysqli_fetch_assoc($u_res);
        $s_data = mysqli_fetch_assoc($s_res);

        if (!$u_data || !$s_data || $amount <= 0) throw new Exception("입력 정보가 올바르지 않습니다.");

        $u_id = $u_data['id']; $s_code = $s_data['code']; $total = $s_data['price'] * $amount;

        if ($action == 'BUY') {
            if ($u_data['cash'] < $total) throw new Exception("잔액이 부족합니다.");
            mysqli_query($conn, "UPDATE users SET cash = cash - $total WHERE id = $u_id");
            $owned_res = mysqli_query($conn, "SELECT id, quantity, average_price FROM user_stocks WHERE user_id = $u_id AND stock_code = '$s_code' FOR UPDATE");
            $owned = mysqli_fetch_assoc($owned_res);
            if ($owned) {
                $new_qty = $owned['quantity'] + $amount;
                $new_avg = (($owned['quantity'] * $owned['average_price']) + $total) / $new_qty;
                mysqli_query($conn, "UPDATE user_stocks SET quantity = $new_qty, average_price = $new_avg WHERE id = {$owned['id']}");
            } else {
                mysqli_query($conn, "INSERT INTO user_stocks (user_id, stock_code, quantity, average_price) VALUES ($u_id, '$s_code', $amount, {$s_data['price']})");
            }
        } else {
            $owned_res = mysqli_query($conn, "SELECT id, quantity FROM user_stocks WHERE user_id = $u_id AND stock_code = '$s_code' FOR UPDATE");
            $owned = mysqli_fetch_assoc($owned_res);
            if (!$owned || $owned['quantity'] < $amount) throw new Exception("보유 수량이 부족합니다.");
            mysqli_query($conn, "UPDATE users SET cash = cash + $total WHERE id = $u_id");
            if ($owned['quantity'] == $amount) mysqli_query($conn, "DELETE FROM user_stocks WHERE id = {$owned['id']}");
            else mysqli_query($conn, "UPDATE user_stocks SET quantity = quantity - $amount WHERE id = {$owned['id']}");
        }
        mysqli_commit($conn); $msg = "✅ 거래 완료!";
    } catch (Exception $e) { mysqli_rollback($conn); $msg = "❌ " . $e->getMessage(); }
}

// [자산 데이터 조회]
$u_info = mysqli_fetch_assoc(mysqli_query($conn, "SELECT cash FROM users WHERE username = '$current_user'"));
$my_cash = $u_info['cash'] ?? 0;

$s_list_sql = "SELECT p.code, p.name, us.quantity, p.price as curr_price, us.average_price 
               FROM user_stocks us 
               JOIN stock_prices p ON us.stock_code = p.code 
               JOIN users u ON us.user_id = u.id 
               WHERE u.username = '$current_user'
               ORDER BY (p.price * us.quantity) DESC";
$s_list_res = mysqli_query($conn, $s_list_sql);

$portfolio = []; 
$total_stock_val = 0;
while($r = mysqli_fetch_assoc($s_list_res)) {
    $portfolio[] = $r; 
    $total_stock_val += ($r['curr_price'] * $r['quantity']);
}
$total_assets = $my_cash + $total_stock_val;
$all_s = mysqli_query($conn, "SELECT code, name FROM stock_prices ORDER BY name ASC");
?>

<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
    <title>StockSim v0.2 Live</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root { --bg: #0b0e11; --card: #1e2329; --input: #2b3139; --text: #eaecef; --sub: #848e9c; --green: #02c076; --red: #f6465d; --blue: #2f80ed; --border: #363c4e; --yellow: #f1c40f; }
        body { font-family: 'Malgun Gothic', sans-serif; background: var(--bg); color: var(--text); margin: 0; padding: 15px 15px 120px; overflow-x: hidden; }
        .summary-label { font-size: 13px; color: var(--sub); margin-top: 10px; }
        .summary-val { font-size: 34px; font-weight: bold; margin: 10px 0 20px 0; display: block; }
        .card { background: var(--card); border-radius: 12px; padding: 18px; margin-bottom: 15px; border: 1px solid var(--border); position: relative; }
        .main-container { display: flex; gap: 15px; align-items: flex-start; }
        .side-panel { flex: 4; min-width: 320px; }
        .main-panel { flex: 6; }
        @media (max-width: 1024px) { .main-container { flex-direction: column; } .side-panel, .main-panel { width: 100%; flex: none; } }
        .trade-box { display: flex; gap: 8px; position: relative; }
        .search-container { position: relative; flex: 2; }
        input { background: var(--input); border: 1px solid var(--border); color: white; padding: 12px; border-radius: 6px; width: 100%; box-sizing: border-box; outline: none; }
        .btn { padding: 12px 20px; border-radius: 6px; font-weight: bold; border: none; color: white; cursor: pointer; min-width: 70px; }
        #search_results { position: absolute; top: 100%; left: 0; right: 0; background: var(--input); border: 1px solid var(--border); border-radius: 8px; max-height: 280px; overflow-y: auto; z-index: 2000; display: none; margin-top: 5px; }
        .search-item { padding: 12px 15px; cursor: pointer; display: flex; justify-content: space-between; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .search-item:hover { background: var(--border); }
        table { width: 100%; border-collapse: collapse; font-size: 14px; }
        th { text-align: left; color: var(--sub); padding-bottom: 10px; border-bottom: 1px solid var(--border); }
        td { padding: 14px 0; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .stock-name { color: var(--blue); cursor: pointer; font-weight: bold; text-decoration: underline; }
        .live-ticker { display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 15px; }
        .ticker-price { font-size: 32px; font-weight: bold; color: var(--green); transition: color 0.3s ease; }
        .chart-ctrl { display: flex; justify-content: space-between; margin-bottom: 15px; align-items: center; border-top: 1px solid var(--border); padding-top: 15px; }
        .range-btns button { background: var(--input); color: var(--sub); border: none; padding: 6px 12px; border-radius: 4px; font-size: 12px; cursor: pointer; margin-left: 4px; }
        .range-btns button.active { background: var(--blue); color: white; }
        .chart-wrap { height: 400px; position: relative; }
        .nav-bar { position: fixed; bottom: 0; left: 0; right: 0; background: #161a1e; display: flex; padding: 15px; border-top: 1px solid var(--border); z-index: 1000; }
        .nav-item { flex: 1; text-align: center; border-right: 1px solid var(--border); }
        .nav-l { font-size: 11px; color: var(--sub); display: block; }
        .nav-v { font-size: 15px; font-weight: bold; }
    </style>
</head>
<body>

<?php if($msg): ?>
    <div style="background:var(--card); padding:10px; border-radius:8px; margin-bottom:15px; text-align:center; border:1px solid var(--green)"><?php echo $msg; ?></div>
<?php endif; ?>

<div class="summary-label">총 평가 자산</div>
<span class="summary-val" id="top_total_assets">₩<?php echo number_format($total_assets); ?></span>

<div class="card" style="z-index: 2001;">
    <form method="POST" class="trade-box" id="tradeForm">
        <input type="hidden" name="username" value="[x]">
        <div class="search-container">
            <input type="text" id="stock_input" name="stock_input" placeholder="종목명 또는 코드 입력" autocomplete="off" required>
            <div id="search_results"></div>
        </div>
        <input type="number" name="amount" placeholder="수량" required style="flex:1;">
        <button type="submit" name="action" value="BUY" class="btn" style="background:var(--red)">매수</button>
        <button type="submit" name="action" value="SELL" class="btn" style="background:var(--blue)">매도</button>
    </form>
</div>

<div class="main-container">
    <div class="side-panel">
        <div class="card" style="min-height: 520px;">
            <h3 style="margin-top:0; font-size: 16px;">내 자산 현황</h3>
            <table>
                <thead><tr><th>종목</th><th>보유량</th><th>수익률</th></tr></thead>
                <tbody id="portfolio_body">
                    <?php foreach($portfolio as $p): 
                        $rate = ($p['average_price'] > 0) ? (($p['curr_price'] - $p['average_price']) / $p['average_price']) * 100 : 0; ?>
                        <tr>
                            <td class="stock-name" onclick="changeStock('<?php echo $p['code']; ?>', '<?php echo $p['name']; ?>')"><?php echo $p['name']; ?></td>
                            <td><?php echo number_format($p['quantity']); ?>주</td>
                            <td style="color:<?php echo $rate >= 0 ? 'var(--green)' : 'var(--red)'; ?>"><?php echo round($rate, 2); ?>%</td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <div class="main-panel">
        <div class="card" style="min-height: 520px;">
            <div class="live-ticker">
                <div>
                    <div id="ticker_name" style="font-size: 18px; font-weight: bold; color: var(--blue);">종목 선택</div>
                    <div id="ticker_market" style="font-size: 11px; color: var(--sub); background: var(--input); padding: 2px 6px; border-radius: 4px; display: inline-block; margin-top: 5px;">-</div>
                </div>
                <div style="text-align: right;">
                    <div id="ticker_price" class="ticker-price">0</div>
                    <div id="ticker_time" style="font-size: 12px; color: var(--sub);">최근 업데이트: -</div>
                </div>
            </div>

            <div class="chart-ctrl">
                <div id="c_title" style="font-size: 14px; font-weight: bold; color: var(--sub);">실시간 차트</div>
                <div class="range-btns">
                    <button onclick="updateRange('1d')">1일</button>
                    <button onclick="updateRange('1w')">1주</button>
                    <button onclick="updateRange('1m')">1달</button>
                    <button onclick="updateRange('all')" class="active">전체</button>
                </div>
            </div>
            <div class="chart-wrap">
                <canvas id="mainChart"></canvas>
            </div>
        </div>
    </div>
</div>

<nav class="nav-bar">
    <div class="nav-item"><span class="nav-l">예수금</span><span class="nav-v" style="color:var(--yellow)">₩<?php echo number_format($my_cash); ?></span></div>
    <div class="nav-item"><span class="nav-l">주식평가액</span><span class="nav-v" id="nav_stock_val">₩<?php echo number_format($total_stock_val); ?></span></div>
    <div class="nav-item"><span class="nav-l">총 자산</span><span class="nav-v" id="nav_total_assets">₩<?php echo number_format($total_assets); ?></span></div>
</nav>

<script>
let chart = null;
let c_code = null;
let c_name = null;
let c_range = 'all';
let last_db_time = null; 

// [0] 실시간 시계 (초 단위)
function updateLiveClock() {
    const now = new Date();
    const hours = String(now.getHours()).padStart(2, '0');
    const minutes = String(now.getMinutes()).padStart(2, '0');
    const seconds = String(now.getSeconds()).padStart(2, '0');
    // ticker_time 옆이나 적당한 곳에 배치하기 위해 innerHTML 사용
    const clockStr = `<span style="color:var(--blue); margin-right:10px;">현재시간: ${hours}:${minutes}:${seconds}</span>`;
    document.getElementById('live_clock').innerHTML = clockStr;
}
setInterval(updateLiveClock, 1000);

const allStocks = [
    <?php mysqli_data_seek($all_s, 0); while($s = mysqli_fetch_assoc($all_s)) { echo "{name:'".addslashes($s['name'])."', code:'".$s['code']."'},"; } ?>
];

const stockInput = document.getElementById('stock_input');
const resultsDiv = document.getElementById('search_results');

stockInput.addEventListener('input', function() {
    const val = this.value.toUpperCase();
    resultsDiv.innerHTML = '';
    if(!val) { resultsDiv.style.display = 'none'; return; }
    const filtered = allStocks.filter(s => s.name.toUpperCase().includes(val) || s.code.includes(val)).slice(0, 10);
    if(filtered.length > 0) {
        filtered.forEach(s => {
            const div = document.createElement('div');
            div.className = 'search-item';
            div.innerHTML = `<span class="s-name">${s.name}</span><span class="s-code">${s.code}</span>`;
            div.onclick = () => { 
                stockInput.value = s.name; 
                resultsDiv.style.display = 'none'; 
                changeStock(s.code, s.name); 
            };
            resultsDiv.appendChild(div);
        });
        resultsDiv.style.display = 'block';
    } else { resultsDiv.style.display = 'none'; }
});

function changeStock(code, name) {
    if (c_code === code) return;
    c_code = code;
    c_name = name;
    last_db_time = null; 
    updateDashboard();
}

function updateRange(range) {
    c_range = range;
    document.querySelectorAll('.range-btns button').forEach(b => {
        b.classList.remove('active');
        const text = b.innerText;
        const mapping = {'1d':'1일', '1w':'1주', '1m':'1달', 'all':'전체'};
        if(mapping[range] === text) b.classList.add('active');
    });
    last_db_time = null;
    updateDashboard();
}

function updateDashboard() {
    if(!c_code) return;

    fetch(`get_stock_detail.php?code=${c_code}`)
        .then(r => r.json())
        .then(data => {
            const new_db_time = data.updated_at;

            // 업데이트 감지 로직
            if (new_db_time !== last_db_time) {
                last_db_time = new_db_time;

                const priceEl = document.getElementById('ticker_price');
                const oldPrice = parseInt(priceEl.innerText.replace(/,/g, '')) || 0;
                const newPrice = parseInt(data.price);

                // 가격 변동 애니메이션 효과
                priceEl.style.transition = 'none';
                if (newPrice > oldPrice && oldPrice !== 0) priceEl.style.color = '#fff'; // 반짝 효과
                else if (newPrice < oldPrice && oldPrice !== 0) priceEl.style.color = '#fff';

                setTimeout(() => {
                    priceEl.style.transition = 'color 0.5s';
                    priceEl.style.color = (newPrice > oldPrice) ? 'var(--green)' : 'var(--red)';
                    if(newPrice === oldPrice) priceEl.style.color = 'var(--text)';
                }, 100);

                document.getElementById('ticker_name').innerText = c_name + " (" + c_code + ")";
                priceEl.innerText = newPrice.toLocaleString();
                document.getElementById('ticker_market').innerText = data.market;
                
                // 업데이트 시간 표시부
                document.getElementById('ticker_time').innerText = "DB 갱신: " + (new_db_time ? new_db_time.split(' ')[1] : '-');

                refreshChart();
            }
        });
}

function refreshChart() {
    fetch(`get_history.php?code=${c_code}&range=${c_range}`)
        .then(r => r.json())
        .then(data => {
            const ctx = document.getElementById('mainChart').getContext('2d');
            if(chart) {
                chart.data.labels = data.labels;
                chart.data.datasets[0].data = data.prices;
                chart.update('none'); 
            } else {
                chart = new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: data.labels,
                        datasets: [{
                            data: data.prices, borderColor: '#02c076', borderWidth: 2, pointRadius: 0, tension: 0.1, fill: true,
                            backgroundColor: c => {
                                const g = c.chart.ctx.createLinearGradient(0, 0, 0, 400);
                                g.addColorStop(0, 'rgba(2, 192, 118, 0.3)'); g.addColorStop(1, 'rgba(2, 192, 118, 0)');
                                return g;
                            }
                        }]
                    },
                    options: { 
                        responsive: true, maintainAspectRatio: false, animation: false,
                        plugins: { legend: { display: false } },
                        scales: { 
                            x: { grid: { color: '#2b3139' }, ticks: { color: '#848e9c', maxTicksLimit: 6 } }, 
                            y: { position: 'right', grid: { color: '#2b3139' }, ticks: { color: '#02c076' } } 
                        } 
                    }
                });
            }
        });
}

window.onload = () => {
    // [추가] 실시간 시계 들어갈 자리 확보 (없으면 생성)
    if(!document.getElementById('live_clock')) {
        const timeBox = document.getElementById('ticker_time').parentNode;
        const clockDiv = document.createElement('div');
        clockDiv.id = 'live_clock';
        clockDiv.style.fontSize = '12px';
        clockDiv.style.marginBottom = '2px';
        timeBox.insertBefore(clockDiv, document.getElementById('ticker_time'));
    }

    const firstStockRow = document.querySelector('.stock-name');
    if (firstStockRow) {
        const initCode = "<?php echo isset($portfolio[0]) ? $portfolio[0]['code'] : '005930'; ?>";
        const initName = "<?php echo isset($portfolio[0]) ? $portfolio[0]['name'] : '삼성전자'; ?>";
        changeStock(initCode, initName);
    } else {
        changeStock('005930', '삼성전자');
    }

    // 체크 주기를 1초로 단축 (수집기 종료 시점을 더 빨리 낚아채기 위함)
    setInterval(updateDashboard, 1000);
};
</script>

</body>
</html>
