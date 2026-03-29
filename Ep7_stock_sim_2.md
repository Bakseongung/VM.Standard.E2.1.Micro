### ranking
```bash
nano ranking.py
```

```python
import pymysql

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '[비밀번호]',
    'database': 'stock_db',
    'charset': 'utf8mb4'
}

def show_rankings():
    conn = pymysql.connect(**DB_CONFIG)
    try:
        with conn.cursor() as cursor:
            # SQL 설명: 
            # 1. 유저별 보유 주식의 (수량 * 현재가) 총합을 구함 (IFNULL은 주식 없는 유저 배려)
            # 2. 유저의 현금(cash)과 주식 가치를 더해 '총 자산' 계산
            sql = """
            SELECT 
                u.username, 
                u.cash + IFNULL(SUM(us.quantity * p.price), 0) AS total_assets,
                u.cash AS current_cash,
                IFNULL(SUM(us.quantity * p.price), 0) AS stock_value
            FROM users u
            LEFT JOIN user_stocks us ON u.id = us.user_id
            LEFT JOIN stock_prices p ON us.stock_code = p.code
            GROUP BY u.id
            ORDER BY total_assets DESC;
            """
            cursor.execute(sql)
            rankings = cursor.fetchall()

            print("\n🏆 [주식 시뮬레이터 실시간 랭킹] 🏆")
            print(f"{'순위':<4} | {'사용자':<12} | {'총 자산':<15}")
            print("-" * 40)

            for i, (name, total, cash, stock) in enumerate(rankings, 1):
                print(f"{i:>3}위 | {name:<15} | {total:,}원")
                print(f"      (현금: {cash:,} / 주식: {stock:,})")
                print("-" * 40)

    finally:
        conn.close()

if __name__ == "__main__":
    show_rankings()
```

### 신규 user 추가, trade.py 수정
```sql
sudo mysql -u root -p
USE stock_db;
INSERT INTO users (username) VALUES ('Chulsoo'), ('Younghee'); # 기본 자산 1,000만원
SELECT * FROM users;
exit
```
```bash
nano trade.py
```
```python
import pymysql
import sys

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '[비밀번호]',
    'database': 'stock_db',
    'charset': 'utf8mb4'
}

def buy_stock(username, stock_code, amount):
    conn = pymysql.connect(**DB_CONFIG)
    try:
        with conn.cursor() as cursor:
            # 1. 유저 확인
            cursor.execute("SELECT id, cash FROM users WHERE username = %s", (username,))
            user = cursor.fetchone()
            if not user:
                print(f"❌ '{username}' 유저를 찾을 수 없습니다.")
                return

            # 2. 종목 확인 (이름으로도 찾을 수 있게 개선)
            cursor.execute("SELECT code, price, name FROM stock_prices WHERE code = %s OR name = %s", (stock_code, stock_code))
            stock = cursor.fetchone()
            if not stock:
                print(f"❌ 종목 '{stock_code}'을(를) 찾을 수 없습니다.")
                return

            user_id, cash = user
            real_code, price, stock_name = stock
            total_cost = price * amount

            if cash < total_cost:
                print(f"❌ 잔액 부족! (보유: {cash:,}원 / 필요: {total_cost:,}원)")
                return

            # 3. 매매 처리
            cursor.execute("UPDATE users SET cash = cash - %s WHERE id = %s", (total_cost, user_id))
            
            cursor.execute("SELECT id, quantity, average_price FROM user_stocks WHERE user_id = %s AND stock_code = %s", (user_id, real_code))
            owned = cursor.fetchone()

            if owned:
                new_qty = owned[1] + amount
                new_avg = ((owned[1] * owned[2]) + total_cost) // new_qty
                cursor.execute("UPDATE user_stocks SET quantity = %s, average_price = %s WHERE id = %s", (new_qty, new_avg, owned[0]))
            else:
                cursor.execute("INSERT INTO user_stocks (user_id, stock_code, quantity, average_price) VALUES (%s, %s, %s, %s)", (user_id, real_code, amount, price))

            conn.commit()
            print(f"✅ [{username}]님, {stock_name}({real_code}) {amount}주 매수 완료!")

    finally:
        conn.close()

if __name__ == "__main__":
    # 터미널에서 바로 입력받기: python3 trade.py 유저이름 종목코드/이름 수량
    if len(sys.argv) == 4:
        buy_stock(sys.argv[1], sys.argv[2], int(sys.argv[3]))
    else:
        print("사용법: python3 trade.py [유저이름] [종목코드 또는 이름] [수량]")
```
```bash
ubuntu@instance-micro1:~$ python3 trade.py Chulsoo SK하이닉스 5
✅ [Chulsoo]님, SK하이닉스(000660) 5주 매수 완료!
ubuntu@instance-micro1:~$ python3 trade.py Younghee 현대차 20
✅ [Younghee]님, 현대차(005380) 20주 매수 완료!
ubuntu@instance-micro1:~$ python3 ranking.py

🏆 [주식 시뮬레이터 실시간 랭킹] 🏆
순위   | 사용자          | 총 자산           
----------------------------------------
  1위 | Seongung        | 10,000,000원
      (현금: 5,507,500 / 주식: 4,492,500)
----------------------------------------
  2위 | Chulsoo         | 10,000,000원
      (현금: 5,390,000 / 주식: 4,610,000)
----------------------------------------
  3위 | Younghee        | 10,000,000원
      (현금: 100,000 / 주식: 9,900,000)
----------------------------------------
```
### reset.py
```python
import pymysql

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '[비밀번호]',
    'database': 'stock_db',
    'charset': 'utf8mb4'
}

def reset_simulation():
    # 1. 사용자 확인 (실수 방지)
    confirm = input("⚠️ 정말 모든 유저의 자산과 주식을 초기화하시겠습니까? (y/n): ")
    if confirm.lower() != 'y':
        print("초기화가 취소되었습니다.")
        return

    conn = pymysql.connect(**DB_CONFIG)
    try:
        with conn.cursor() as cursor:
            print("🧹 초기화 작업 시작...")

            # 2. 모든 유저의 보유 주식 삭제 (자식 테이블 먼저)
            cursor.execute("DELETE FROM user_stocks")
            
            # 3. 모든 유저의 현금을 다시 1,000만 원으로 업데이트
            cursor.execute("UPDATE users SET cash = 10000000")
            
            # 4. 주가 정보는 유지할 수도 있지만, 깔끔하게 정리하고 싶다면 아래 주석 해제
            # cursor.execute("DELETE FROM stock_prices")

            conn.commit()
            print("✨ 모든 유저의 자산이 1,000만 원으로 초기화되었습니다!")
            print("✨ 보유 주식 목록이 모두 비워졌습니다.")

    except Exception as e:
        print(f"❌ 오류 발생: {e}")
        conn.rollback()
    finally:
        conn.close()

if __name__ == "__main__":
    reset_simulation()
```
```
ubuntu@instance-micro1:~$ python3 reset.py
⚠️ 정말 모든 유저의 자산과 주식을 초기화하시겠습니까? (y/n): y
🧹 초기화 작업 시작...
✨ 모든 유저의 자산이 1,000만 원으로 초기화되었습니다!
✨ 보유 주식 목록이 모두 비워졌습니다.
ubuntu@instance-micro1:~$ python3 ranking.py

🏆 [주식 시뮬레이터 실시간 랭킹] 🏆
순위   | 사용자          | 총 자산           
----------------------------------------
  1위 | Seongung        | 10,000,000원
      (현금: 10,000,000 / 주식: 0)
----------------------------------------
  2위 | Chulsoo         | 10,000,000원
      (현금: 10,000,000 / 주식: 0)
----------------------------------------
  3위 | Younghee        | 10,000,000원
      (현금: 10,000,000 / 주식: 0)
----------------------------------------
ubuntu@instance-micro1:~$ python3 trade.py Seongung 삼성전자 50
✅ [Seongung]님, 삼성전자(005930) 50주 매수 완료!
ubuntu@instance-micro1:~$ python3 trade.py Chulsoo SK하이닉스 10
✅ [Chulsoo]님, SK하이닉스(000660) 10주 매수 완료!
ubuntu@instance-micro1:~$ python3 trade.py Younghee 현대차 15
✅ [Younghee]님, 현대차(005380) 15주 매수 완료!
ubuntu@instance-micro1:~$ python3 ranking.py

🏆 [주식 시뮬레이터 실시간 랭킹] 🏆
순위   | 사용자          | 총 자산           
----------------------------------------
  1위 | Seongung        | 10,000,000원
      (현금: 1,015,000 / 주식: 8,985,000)
----------------------------------------
  2위 | Chulsoo         | 10,000,000원
      (현금: 780,000 / 주식: 9,220,000)
----------------------------------------
  3위 | Younghee        | 10,000,000원
      (현금: 2,575,000 / 주식: 7,425,000)
----------------------------------------
```

### 수익률 중심 랭킹 추가(ranking.py 수정)
```
import pymysql

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '[비밀번호]',
    'database': 'stock_db',
    'charset': 'utf8mb4'
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
```
```
ubuntu@instance-micro1:~$ python3 ranking.py

🔥 [주식 시뮬레이터 수익률 TOP 랭킹] 🔥
순위   | 사용자          | 수익률        | 총 자산           
=======================================================
  1위 | Seongung     | -   0.00% | 10,000,000원
      (주식: 8,985,000원 / 현금: 1,015,000원)
-------------------------------------------------------
  2위 | Chulsoo      | -   0.00% | 10,000,000원
      (주식: 9,220,000원 / 현금: 780,000원)
-------------------------------------------------------
  3위 | Younghee     | -   0.00% | 10,000,000원
      (주식: 7,425,000원 / 현금: 2,575,000원)
-------------------------------------------------------
```
