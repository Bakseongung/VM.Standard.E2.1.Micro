```
1. 동시성 제어, 예외처리, 최적화
2. 시스템 정산과 무결성 검증
```

### 동시성 제어, 예외처리
#### trade.py
```python
import pymysql
import sys
import time
from pymysql import MySQLError

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '[비밀번호]',
    'database': 'stock_db',
    'charset': 'utf8mb4'
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
```

#### sell.py
```python
import pymysql
import sys
import time

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '[비밀번호]',
    'database': 'stock_db',
    'charset': 'utf8mb4'
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
```

#### stock_to_db.py (종목추가)
```python
import FinanceDataReader as fdr
import pymysql
import time
from datetime import datetime

# [설정] 본인의 MySQL 접속 정보
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '[비밀번호]',
    'database': 'stock_db',
    'charset': 'utf8mb4'
}

def is_market_open():
    """현재 시간이 주식 시장 운영 시간인지 확인 (월-금 09:00~15:40)"""
    now = datetime.now()
    # 주말 제외 (5:토요일, 6:일요일)
    if now.weekday() >= 5:
        return False
    
    # 시간 체크 (09:00 ~ 15:40)
    current_time = now.hour * 100 + now.minute
    if current_time < 900 or current_time > 1540:
        return False
        
    return True

def update_all_stocks():
    # 1. 장 마감 체크 (운영 환경에서는 이 주석을 해제하여 자원을 아끼세요)
    # if not is_market_open():
    #     print(f"[{datetime.now()}] 😴 장 마감 시간입니다. 수집을 건너뜁니다.")
    #     return

    conn = pymysql.connect(**DB_CONFIG)
    try:
        with conn.cursor() as cursor:
            print("🚀 전종목 리스트 수집 시작 (KOSPI + KOSDAQ 상위권)...")
            
            # 2. 코스피/코스닥 종목 리스트 가져오기
            # StockListing은 현재가(Close) 정보를 이미 포함하고 있어 매우 빠릅니다.
            df_kospi = fdr.StockListing('KOSPI')
            df_kosdaq = fdr.StockListing('KOSDAQ')
            
            # 3. 1GB 램 서버를 위해 상위 종목 위주로 결합 (각 150개씩 총 300개 권장)
            df_all = df_kospi.head(150)._append(df_kosdaq.head(150))
            
            print(f"📦 총 {len(df_all)}개 종목의 데이터베이스 동기화 중...")

            for _, row in df_all.iterrows():
                code = row['Code']
                name = row['Name']
                market = row['Market']
                
                # StockListing에서 제공하는 'Close' 혹은 'ChgCode' 등의 데이터 활용
                # 데이터 소스에 따라 'Close' 컬럼이 없을 경우를 대비해 0 처리
                current_price = int(row.get('Close', 0))
                
                if current_price == 0:
                    continue # 가격 정보가 없으면 스킵

                # 4. UPSERT 쿼리 (중복 시 업데이트)
                sql = """
                INSERT INTO stock_prices (code, name, market, price)
                VALUES (%s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE 
                price = VALUES(price), 
                updated_at = NOW();
                """
                cursor.execute(sql, (code, name, market, current_price))

            # 5. 최종 커밋
            conn.commit()
            print(f"✅ [{datetime.now()}] {len(df_all)}개 종목 DB 업데이트 완료!")

    except Exception as e:
        print(f"❌ 오류 발생: {e}")
        conn.rollback()
    finally:
        conn.close()

if __name__ == "__main__":
    update_all_stocks()
```

#### audit.py(시스템 정산 및 무결성 검증)
```python
import pymysql

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '[비밀번호]',
    'database': 'stock_db',
    'charset': 'utf8mb4'
}

def run_audit():
    conn = pymysql.connect(**DB_CONFIG)
    INITIAL_CASH = 10000000  # 유저당 초기 자본금 (1,000만 원)
    
    try:
        with conn.cursor() as cursor:
            # 1. 전체 유저 현황 파악
            cursor.execute("SELECT COUNT(*), SUM(cash) FROM users")
            user_count, total_cash_now = cursor.fetchone()
            user_count = user_count or 0
            total_cash_now = total_cash_now or 0
            
            # 2. 유저들이 보유한 주식의 현재 가치(평가액) 합산
            cursor.execute("""
                SELECT SUM(us.quantity * p.price) 
                FROM user_stocks us 
                JOIN stock_prices p ON us.stock_code = p.code
            """)
            total_stock_value = cursor.fetchone()[0] or 0
            
            # 3. 거래 기록(History)을 통한 총 거래 대금 검증
            # 매수(+)와 매도(-)의 흐름이 실제 잔액 변화와 일치하는지 확인하기 위함
            cursor.execute("""
                SELECT 
                    SUM(CASE WHEN trade_type = 'BUY' THEN total_price ELSE 0 END) as total_buy,
                    SUM(CASE WHEN trade_type = 'SELL' THEN total_price ELSE 0 END) as total_sell
                FROM trade_history
            """)
            trade_summary = cursor.fetchone()
            total_buy = trade_summary[0] or 0
            total_sell = trade_summary[1] or 0

            # 4. 이론상 존재해야 할 총 자산 vs 실제 자산 대조
            expected_system_total = user_count * INITIAL_CASH
            actual_system_total = total_cash_now + total_stock_value
            system_diff = actual_system_total - expected_system_total

            print(f"\n======== [ 🛡️ 시스템 무결성 정산 보고서 ] ========")
            print(f"📅 정산 일시: {pymysql.escape_string(str(pymysql.DATETIME)) if False else '실시간'}")
            print(f"👥 활성 유저: {user_count}명")
            print("-" * 45)
            print(f"💰 유저 보유 현금 총액: {total_cash_now:>15,}원")
            print(f"📈 유저 보유 주식 총액: {total_stock_value:>15,}원")
            print(f"🏦 시스템 실제 총 자산: {actual_system_total:>15,}원")
            print(f"🏛️ 이론상 초기 총 자산: {expected_system_total:>15,}원")
            print("-" * 45)

            if system_diff == 0:
                print("✅ [PASS] 시스템 자산이 완벽하게 일치합니다.")
            else:
                print(f"⚠️ [FAIL] 자산 불일치 발생! 오차: {system_diff:+,}원")
                print("   (원인: 수수료 미반영, 수동 DB 수정, 또는 동시성 버그)")

            print("-" * 45)
            print(f"📝 누적 매수액: {total_buy:>15,}원")
            print(f"📝 누적 매도액: {total_sell:>15,}원")
            print("==============================================\n")

    except Exception as e:
        print(f"❌ 정산 도중 오류 발생: {e}")
    finally:
        conn.close()

if __name__ == "__main__":
    run_audit()
```

```
# 크론탭 편집창 열기
crontab -e

# 매분 실행되도록 등록 (출력은 log파일로 저장)
* * * * * /usr/bin/python3 /home/ubuntu/stock_to_db.py >> /home/ubuntu/stock_log.log 2>&1
```
