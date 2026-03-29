<!DOCTYPE html>
<html lang="ko">
<head>
    <meta charset="UTF-8">
    <title>차트 테스트</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body { background: #0b0e11; color: white; display: flex; flex-direction: column; align-items: center; padding: 50px; }
        .chart-container { width: 600px; height: 400px; background: #1e2329; padding: 20px; border-radius: 10px; }
        h2 { color: #02c076; }
    </style>
</head>
<body>

    <h2>📈 차트 출력 테스트</h2>
    <p>아래 영역에 그래프가 보인다면 라이브러리는 정상입니다.</p>

    <div class="chart-container">
        <canvas id="testChart"></canvas>
    </div>

    <script>
        // 2. 가짜 데이터(더미 데이터)로 즉시 그리기
        const ctx = document.getElementById('testChart').getContext('2d');
        
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['09:00', '10:00', '11:00', '12:00', '13:00', '14:00', '15:00'],
                datasets: [{
                    label: '테스트 시세',
                    data: [50000, 52000, 49000, 53000, 55000, 54000, 58000],
                    borderColor: '#02c076',
                    backgroundColor: 'rgba(2, 192, 118, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: false, grid: { color: '#363c4e' } },
                    x: { grid: { color: '#363c4e' } }
                }
            }
        });
    </script>
</body>
</html>
