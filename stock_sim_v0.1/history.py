import pymysql

DB_CONFIG = {
    'host': '[x]',
    'user': '[x]',
    'password': '[x]',
    'database': '[x]',
    'charset': '[x]'
}

def show_history(username):
    conn = pymysql.connect(**DB_CONFIG)
    try:
        with conn.cursor() as cursor:
            # 유저의 최근 거래 10개를 시간순(최신순)으로 가져옴
            sql = """
            SELECT h.trade_at, p.name, h.trade_type, h.amount, h.price, h.total_price
            FROM trade_history h
            JOIN users u ON h.user_id = u.id
            JOIN stock_prices p ON h.stock_code = p.code
            WHERE u.username = %s
            ORDER BY h.trade_at DESC
            LIMIT 10;
            """
            cursor.execute(sql, (username,))
            rows = cursor.fetchall()

            if not rows:
                print(f"[{username}] 님의 거래 내역이 없습니다.")
                return

            print(f"\n📜 [{username}] 님의 최근 거래 내역")
            print("=" * 70)
            print(f"{'거래시간':<20} | {'종목명':<10} | {'구분':<4} | {'수량':<4} | {'단가':<10} | {'총액':<12}")
            print("-" * 70)

            for row in rows:
                time, name, t_type, qty, price, total = row
                type_str = "매수" if t_type == 'BUY' else "매도"
                print(f"{str(time):<20} | {name:<10} | {type_str:<4} | {qty:>4}주 | {price:>10,}원 | {total:>12,}원")
            print("=" * 70)

    finally:
        conn.close()

if __name__ == "__main__":
    show_history('Seongung')
