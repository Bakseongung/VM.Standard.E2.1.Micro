import pymysql

DB_CONFIG = {
    'host': '[x]',
    'user': '[x]',
    'password': '[x]',
    'database': '[x]',
    'charset': '[x]'
}

def show_roi_rankings():
    conn = pymysql.connect(**DB_CONFIG)
    INITIAL_CASH = 10000000 # 초기 자본금 1,000만 원
    
    try:
        with conn.cursor() as cursor:
            # SQL: 유저별 (현금 + 주식 평가액) 계산
            sql = """
            SELECT 
                u.username, 
                u.cash + IFNULL(SUM(us.quantity * p.price), 0) AS total_assets,
                IFNULL(SUM(us.quantity * p.price), 0) AS stock_value,
                u.cash AS current_cash
            FROM users u
            LEFT JOIN user_stocks us ON u.id = us.user_id
            LEFT JOIN stock_prices p ON us.stock_code = p.code
            GROUP BY u.id
            ORDER BY total_assets DESC;
            """
            cursor.execute(sql)
            rankings = cursor.fetchall()

            print("\n🔥 [주식 시뮬레이터 수익률 TOP 랭킹] 🔥")
            print(f"{'순위':<4} | {'사용자':<12} | {'수익률':<10} | {'총 자산':<15}")
            print("=" * 55)

            for i, (name, total, stock, cash) in enumerate(rankings, 1):
                # 수익률 계산: ((현재자산 - 초기자본) / 초기자본) * 100
                roi = ((total - INITIAL_CASH) / INITIAL_CASH) * 100
                
                # 수익률 색깔 체감 (터미널용 간단 표시)
                trend = "▲" if roi > 0 else "▼" if roi < 0 else "-"
                
                print(f"{i:>3}위 | {name:<12} | {trend} {roi:>6.2f}% | {total:,}원")
                print(f"      (주식: {stock:,}원 / 현금: {cash:,}원)")
                print("-" * 55)

    finally:
        conn.close()

if __name__ == "__main__":
    show_roi_rankings()
