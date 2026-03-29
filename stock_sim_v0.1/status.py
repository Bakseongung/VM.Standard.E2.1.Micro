ubuntu@instance-micro1:~$ cat status.py
import pymysql

DB_CONFIG = {
    'host': '[x]',
    'user': '[x]',
    'password': '[x]', # 영문/숫자로 된 실제 비밀번호
    'database': '[x]',
    'charset': '[x]'
}

def show_status(username):
    conn = pymysql.connect(**DB_CONFIG)
    try:
        with conn.cursor() as cursor:
            # 1. 유저 현금 잔고 가져오기
            cursor.execute("SELECT cash FROM users WHERE username = %s", (username,))
            cash = cursor.fetchone()[0]

            # 2. 유저 보유 주식 정보와 현재가(stock_prices 테이블) 조인해서 가져오기
            sql = """
            SELECT p.name, us.quantity, us.average_price, p.price
            FROM user_stocks us
            JOIN stock_prices p ON us.stock_code = p.code
            JOIN users u ON us.user_id = u.id
            WHERE u.username = %s;
            """
            cursor.execute(sql, (username,))
            stocks = cursor.fetchall()

            print(f"\n===== [{username}] 님의 자산 현황 =====")
            print(f"보유 현금: {cash:,}원")
            print("-" * 45)
            total_stock_value = 0
            for name, qty, avg, current in stocks:
                total_value = qty * current
                total_stock_value += total_value
                profit_rate = ((current - avg) / avg) * 100
                print(f"종목: {name:10} | 수량: {qty:3} | 평단: {avg:,}원")
                print(f"현재가: {current:,}원 | 평가금: {total_value:,}원 | 수익률: {profit_rate:.2f}%")
                print("-" * 45)

            total_assets = cash + total_stock_value
            print(f"총 자산 (현금+주식): {total_assets:,}원")
            print("====================================\n")

    finally:
        conn.close()

if __name__ == "__main__":
    show_status('Seongung')
