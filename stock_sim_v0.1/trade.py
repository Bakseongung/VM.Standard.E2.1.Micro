ubuntu@instance-micro1:~$ cat trade.py
import pymysql
import sys
import time
from pymysql import MySQLError

DB_CONFIG = {
    'host': '[x]',
    'user': '[x]',
    'password': '[x]',
    'database': '[x]',
    'charset': '[x]'
}

def buy_stock(username, stock_code, amount):
    max_retries = 3
    for attempt in range(max_retries):
        conn = pymysql.connect(**DB_CONFIG)
        try:
            conn.begin()
            with conn.cursor() as cursor:
                # [1] 유저 잠금
                cursor.execute("SELECT id, cash FROM users WHERE username = %s FOR UPDATE", (username,))
                user = cursor.fetchone()
                if not user:
                    print(f"❌ 유저 '{username}'을 찾을 수 없습니다."); return

                # [2] 종목 잠금
                cursor.execute("SELECT code, price, name FROM stock_prices WHERE code = %s OR name = %s FOR UPDATE", (stock_code, stock_code))
                stock = cursor.fetchone()
                if not stock:
                    print(f"❌ 종목 '{stock_code}'을 찾을 수 없습니다."); return

                user_id, cash = user
                real_code, price, stock_name = stock
                total_cost = price * amount

                # [3] 잔액 검증
                if cash < total_cost:
                    print(f"❌ 잔액 부족! (보유: {cash:,}원 / 필요: {total_cost:,}원)"); return

                # [4] 데이터 업데이트 (현금 차감 -> 주식 추가 -> 기록 저장)
                cursor.execute("UPDATE users SET cash = cash - %s WHERE id = %s", (total_cost, user_id))
                
                cursor.execute("SELECT id, quantity, average_price FROM user_stocks WHERE user_id = %s AND stock_code = %s FOR UPDATE", (user_id, real_code))
                owned = cursor.fetchone()
                if owned:
                    new_qty = owned[1] + amount
                    new_avg = ((owned[1] * owned[2]) + total_cost) // new_qty
                    cursor.execute("UPDATE user_stocks SET quantity = %s, average_price = %s WHERE id = %s", (new_qty, new_avg, owned[0]))
                else:
                    cursor.execute("INSERT INTO user_stocks (user_id, stock_code, quantity, average_price) VALUES (%s, %s, %s, %s)", (user_id, real_code, amount, price))

                cursor.execute("INSERT INTO trade_history (user_id, stock_code, trade_type, amount, price, total_price) VALUES (%s, %s, 'BUY', %s, %s, %s)", 
                               (user_id, real_code, amount, price, total_cost))

                conn.commit()
                print(f"✅ [{username}] {stock_name} {amount}주 매수 완료!"); break 

        except pymysql.err.OperationalError as e:
            if e.args[0] in (1213, 1205): # 데드락 또는 타임아웃
                print(f"🔄 충돌 발생... 재시도 중 ({attempt+1}/{max_retries})")
                time.sleep(0.2); continue
            print(f"❌ DB 운영 에러: {e}"); break
        except Exception as e:
            print(f"❌ 시스템 에러: {e}"); conn.rollback(); break
        finally:
            conn.close()

if __name__ == "__main__":
    if len(sys.argv) == 4: buy_stock(sys.argv[1], sys.argv[2], int(sys.argv[3]))
    else: print("사용법: python3 trade.py [이름] [종목] [수량]")
