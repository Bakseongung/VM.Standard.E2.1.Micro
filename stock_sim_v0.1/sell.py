import pymysql
import sys
import time

DB_CONFIG = {
    'host': '[x]',
    'user': '[x]',
    'password': '[x]',
    'database': '[x]',
    'charset': '[x]'
}

def sell_stock(username, stock_code, amount):
    max_retries = 3
    for attempt in range(max_retries):
        conn = pymysql.connect(**DB_CONFIG)
        try:
            conn.begin()
            with conn.cursor() as cursor:
                # [1] 유저 잠금
                cursor.execute("SELECT id FROM users WHERE username = %s FOR UPDATE", (username,))
                user_res = cursor.fetchone()
                if not user_res: print("❌ 유저 미발견"); return
                user_id = user_res[0]

                # [2] 종목/보유주식 잠금
                cursor.execute("SELECT code, price, name FROM stock_prices WHERE code = %s OR name = %s FOR UPDATE", (stock_code, stock_code))
                stock = cursor.fetchone()
                real_code, price, stock_name = stock

                cursor.execute("SELECT quantity, average_price FROM user_stocks WHERE user_id = %s AND stock_code = %s FOR UPDATE", (user_id, real_code))
                owned = cursor.fetchone()

                if not owned or owned[0] < amount:
                    print("❌ 수량 부족"); return

                # [3] 업데이트 (현금 증가 -> 주식 차감 -> 기록 저장)
                total_receive = price * amount
                cursor.execute("UPDATE users SET cash = cash + %s WHERE id = %s", (total_receive, user_id))
                
                if owned[0] == amount:
                    cursor.execute("DELETE FROM user_stocks WHERE user_id = %s AND stock_code = %s", (user_id, real_code))
                else:
                    cursor.execute("UPDATE user_stocks SET quantity = quantity - %s WHERE user_id = %s AND stock_code = %s", (amount, user_id, real_code))

                cursor.execute("INSERT INTO trade_history (user_id, stock_code, trade_type, amount, price, total_price) VALUES (%s, %s, 'SELL', %s, %s, %s)", 
                               (user_id, real_code, amount, price, total_receive))

                conn.commit()
                print(f"✅ [{username}] {stock_name} 매도 완료 (+{total_receive:,}원)"); break

        except Exception as e:
            print(f"❌ 에러: {e}"); conn.rollback(); time.sleep(0.2)
        finally:
            conn.close()

if __name__ == "__main__":
    if len(sys.argv) == 4: sell_stock(sys.argv[1], sys.argv[2], int(sys.argv[3]))
