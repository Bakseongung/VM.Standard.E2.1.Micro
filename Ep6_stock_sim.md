```
stock_sim/
├── main.py          # FastAPI 서버 및 스케줄러 실행
├── database.py      # MySQL 연결 및 테이블 스키마
├── scraper.py       # 주식 데이터 수집 로직 
└── requirements.txt # 필요 라이브러리 목록
```

## 1. FinanceDataReader
``` bash
# pip, finance-datareader
sudo apt update
sudo apt install python3-pip -y
pip install finance-datareader pandas --break-system-packages
```

``` bash
nano stock_test.py
```
``` python
import FinanceDataReader as fdr

# 한국거래소 전종목 리스트 (종목코드, 이름, 시장 등 포함)
df_krx = fdr.StockListing('KRX')
print(df_krx[['Code', 'Name', 'Market']].head())

# 삼성전자(005930) 최근 주가
df = fdr.DataReader('005930')
print(df.tail()) # 날짜, 시가, 고가, 저가, 종가, 거래량 등

# ctrl+o, enter, ctrl+x
```

``` bash
ubuntu@instance-micro1:~$ python3 stock_test.py
--- 한국거래소 종목 리스트 상위 5개 ---
     Code      Name Market
0  005930      삼성전자  KOSPI
1  000660    SK하이닉스  KOSPI
2  005935     삼성전자우  KOSPI
3  005380       현대차  KOSPI
4  373220  LG에너지솔루션  KOSPI

--- 삼성전자 최근 주가 ---
              Open    High     Low   Close    Volume    Change
Date                                                          
2026-03-23  190500  191200  186300  186300  30268173 -0.065697
2026-03-24  195500  196000  185500  189700  25458914  0.018250
2026-03-25  193700  196400  189000  189000  22995904 -0.003690
2026-03-26  185500  185900  178900  180100  32074131 -0.047090
2026-03-27  172100  181700  172000  179700  29113466 -0.002221
```

## pymysql
```bash
sudo mysql -u root -p
```

```mysql
-- 1. 데이터베이스 만들기
CREATE DATABASE stock_db;

-- 2. 만든 데이터베이스 사용하기
USE stock_db;

-- 3. 데이터를 담을 테이블 만들기
CREATE TABLE stock_prices (
    code VARCHAR(10) PRIMARY KEY,
    name VARCHAR(50),
    market VARCHAR(10),
    price INT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

```bash
pip install pymysql --break-system-packages
```
```bash
nano stock_to_db.py
```
``` python
import FinanceDataReader as fdr
import pymysql
from datetime import datetime

# [설정] 본인의 MySQL 접속 정보로 수정하세요
DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',          
    'password': '패스워드', # 
    'database': 'stock_db',  
    'charset': 'utf8mb4'
}

def update_db():
    # 1. DB 연결
    conn = pymysql.connect(**DB_CONFIG)
    try:
        with conn.cursor() as cursor:
            # 2. 한국거래소(KRX) 종목 리스트 상위 10개 가져오기
            print("데이터 수집 중...")
            df_krx = fdr.StockListing('KRX').head(10)

            for _, row in df_krx.iterrows():
                code = row['Code']
                name = row['Name']
                market = row['Market']

                # 3. 각 종목의 가장 최근 주가 1일치 가져오기
                df = fdr.DataReader(code)
                if not df.empty:
                    # 마지막 줄(가장 최근일)의 종가(Close) 가져오기
                    current_price = int(df['Close'].iloc[-1])
                    
                    # 4. SQL 쿼리 준비
                    # INSERT 시 중복된 code가 있으면 price와 updated_at만 업데이트함 (UPSERT)
                    sql = """
                    INSERT INTO stock_prices (code, name, market, price)
                    VALUES (%s, %s, %s, %s)
                    ON DUPLICATE KEY UPDATE 
                    price = VALUES(price), 
                    updated_at = NOW();
                    """
                    
                    # 5. 실행
                    cursor.execute(sql, (code, name, market, current_price))
                    print(f"저장 완료: {name} ({code}) -> {current_price}원")

            # 6. 최종 커밋 (실제 DB에 반영)
            conn.commit()
            print("-" * 30)
            print("모든 데이터가 성공적으로 DB에 저장되었습니다.")

    except Exception as e:
        print(f"오류 발생: {e}")
        conn.rollback() # 에러 나면 되돌리기
    finally:
        conn.close() # 연결 닫기

if __name__ == "__main__":
    update_db()

# ctrl+o, enter, ctrl+x
```

```bash
ubuntu@instance-micro1:~$ python3 stock_to_db.py
데이터 수집 중...
저장 완료: 삼성전자 (005930) -> 179700원
저장 완료: SK하이닉스 (000660) -> 922000원
저장 완료: 삼성전자우 (005935) -> 126200원
저장 완료: 현대차 (005380) -> 495000원
저장 완료: LG에너지솔루션 (373220) -> 394500원
저장 완료: 삼성바이오로직스 (207940) -> 1606000원
저장 완료: SK스퀘어 (402340) -> 544000원
저장 완료: 한화에어로스페이스 (012450) -> 1335000원
저장 완료: 두산에너빌리티 (034020) -> 98100원
저장 완료: 기아 (000270) -> 155800원
------------------------------
모든 데이터가 성공적으로 DB에 저장되었습니다.
ubuntu@instance-micro1:~$ sudo mysql -u root -p -e "USE stock_db; SELECT * FROM stock_prices;"
Enter password: 
+--------+-----------------------------+--------+---------+---------------------+
| code   | name                        | market | price   | updated_at          |
+--------+-----------------------------+--------+---------+---------------------+
| 000270 | 기아                        | KOSPI  |  155800 | 2026-03-29 09:20:30 |
| 000660 | SK하이닉스                  | KOSPI  |  922000 | 2026-03-29 09:20:29 |
| 005380 | 현대차                      | KOSPI  |  495000 | 2026-03-29 09:20:29 |
| 005930 | 삼성전자                    | KOSPI  |  179700 | 2026-03-29 09:20:29 |
| 005935 | 삼성전자우                  | KOSPI  |  126200 | 2026-03-29 09:20:29 |
| 012450 | 한화에어로스페이스          | KOSPI  | 1335000 | 2026-03-29 09:20:30 |
| 034020 | 두산에너빌리티              | KOSPI  |   98100 | 2026-03-29 09:20:30 |
| 207940 | 삼성바이오로직스            | KOSPI  | 1606000 | 2026-03-29 09:20:30 |
| 373220 | LG에너지솔루션              | KOSPI  |  394500 | 2026-03-29 09:20:29 |
| 402340 | SK스퀘어                    | KOSPI  |  544000 | 2026-03-29 09:20:30 |
+--------+-----------------------------+--------+---------+---------------------+
```

## 1분마다 주가 갱신 자동화하기
```bash
ubuntu@instance-micro1:~$ which python3
/usr/bin/python3
ubuntu@instance-micro1:~$ crontab -e
no crontab for ubuntu - using an empty one

Select an editor.  To change later, run 'select-editor'.
  1. /bin/nano        <---- easiest
  2. /usr/bin/vim.basic
  3. /usr/bin/vim.tiny
  4. /bin/ed

Choose 1-4 [1]: 1 #1번이 nano
crontab: installing new crontab
```
```bash
# 마지막 줄에 추가
* * * * * /usr/bin/python3 /home/ubuntu/stock_to_db.py >> /home/ubuntu/stock_log.log 2>&1
```
```bash
# 설명
* * * * *: 매 분, 매 시, 매 일, 매 월, 매 요일 실행 (1분마다)
/usr/bin/python3: 아까 which로 확인한 파이썬 경로
/home/ubuntu/stock_to_db.py: 실행할 파일의 절대 경로
>> /home/ubuntu/stock_log.log 2>&1: 실행 결과나 에러 메시지를 stock_log.log 파일에 계속 기록하라는 뜻 (나중에 문제 생기면 확인용)
```

```bash
# 1분 전후로 찍힘
ubuntu@instance-micro1:~$ sudo mysql -u root -p -e "USE stock_db; SELECT name, price, updated_at FROM stock_prices;"
Enter password: 
+-----------------------------+---------+---------------------+
| name                        | price   | updated_at          |
+-----------------------------+---------+---------------------+
| 기아                        |  155800 | 2026-03-29 09:29:06 |
| SK하이닉스                  |  922000 | 2026-03-29 09:29:05 |
| 현대차                      |  495000 | 2026-03-29 09:29:05 |
| 삼성전자                    |  179700 | 2026-03-29 09:29:04 |
| 삼성전자우                  |  126200 | 2026-03-29 09:29:05 |
| 한화에어로스페이스          | 1335000 | 2026-03-29 09:29:06 |
| 두산에너빌리티              |   98100 | 2026-03-29 09:29:06 |
| 삼성바이오로직스            | 1606000 | 2026-03-29 09:29:05 |
| LG에너지솔루션              |  394500 | 2026-03-29 09:29:05 |
| SK스퀘어                    |  544000 | 2026-03-29 09:29:05 |
+-----------------------------+---------+---------------------+
```

## 지갑 만들기
```
sql: user,user_stocks, user:Seongung
python: trade.py(+10), status.py, sell.py(-5)
```
```mysql
USE stock_db;

-- 1. 유저 테이블
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    cash BIGINT DEFAULT 10000000, -- 초기 자본금 10,000,000원
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. 유저 보유 주식 테이블
CREATE TABLE user_stocks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT,
    stock_code VARCHAR(10),
    quantity INT DEFAULT 0, -- 보유 수량
    average_price INT DEFAULT 0, -- 매수 평단가 (수익률 계산용)
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (stock_code) REFERENCES stock_prices(code)
);

-- 테스트용 유저 한 명 생성
INSERT INTO users (username) VALUES ('Seongung');
```

```bash
nano trade.py
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

def buy_stock(username, stock_code, amount):
    conn = pymysql.connect(**DB_CONFIG)
    try:
        with conn.cursor() as cursor:
            # 1. 유저 잔액 및 주식 현재가 확인
            cursor.execute("SELECT id, cash FROM users WHERE username = %s", (username,))
            user = cursor.fetchone()
            cursor.execute("SELECT price, name FROM stock_prices WHERE code = %s", (stock_code,))
            stock = cursor.fetchone()

            if not user or not stock:
                print("유저 또는 종목 정보를 찾을 수 없습니다.")
                return

            user_id, cash = user
            price, stock_name = stock
            total_cost = price * amount

            # 2. 잔액 확인
            if cash < total_cost:
                print(f"잔액 부족! (필요: {total_cost}원 / 보유: {cash}원)")
                return

            # 3. 현금 차감
            cursor.execute("UPDATE users SET cash = cash - %s WHERE id = %s", (total_cost, user_id))

            # 4. 주식 보유 목록 업데이트
            cursor.execute("SELECT id, quantity, average_price FROM user_stocks WHERE user_id = %s AND stock_code = %s", (user_id, stock_code))
            owned = cursor.fetchone()

            if owned:
                # 이미 보유 중이면 수량 추가 및 평단가 계산
                new_qty = owned[1] + amount
                new_avg = ((owned[1] * owned[2]) + total_cost) // new_qty
                cursor.execute("UPDATE user_stocks SET quantity = %s, average_price = %s WHERE id = %s", (new_qty, new_avg, owned[0]))
            else:
                # 처음 사는 종목이면 새로 추가
                cursor.execute("INSERT INTO user_stocks (user_id, stock_code, quantity, average_price) VALUES (%s, %s, %s, %s)", (user_id, stock_code, amount, price))

            conn.commit()
            print(f"✅ {stock_name} {amount}주 매수 완료! (총 {total_cost}원)")

    finally:
        conn.close()

# 테스트 실행: 'Seongung' 유저가 '삼성전자(005930)'를 10주 매수
if __name__ == "__main__":
    buy_stock('Seongung', '005930', 10)
```

```
nano status.py
```
```python
import pymysql

DB_CONFIG = {
    'host': 'localhost',
    'user': 'root',
    'password': '[비밀번호]', #영문/숫자/특수문자 허용
    'database': 'stock_db',
    'charset': 'utf8mb4'
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
```

```bash
ubuntu@instance-micro1:~$ python3 status.py

===== [Seongung] 님의 자산 현황 =====
보유 현금: 4,609,000원
---------------------------------------------
종목: 삼성전자       | 수량:  30 | 평단: 179,700원  # python3 trade.py 3번해서 수량 30개 추가했음
현재가: 179,700원 | 평가금: 5,391,000원 | 수익률: 0.00%
---------------------------------------------
총 자산 (현금+주식): 10,000,000원
====================================
```
```bash
nano sell.py
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

def sell_stock(username, stock_code, amount):
    conn = pymysql.connect(**DB_CONFIG)
    try:
        with conn.cursor() as cursor:
            # 1. 유저 정보 및 보유 주식 확인
            cursor.execute("SELECT id FROM users WHERE username = %s", (username,))
            user_id = cursor.fetchone()[0]
            
            cursor.execute("""
                SELECT quantity, average_price FROM user_stocks 
                WHERE user_id = %s AND stock_code = %s
            """, (user_id, stock_code))
            owned = cursor.fetchone()

            if not owned or owned[0] < amount:
                print(f"❌ 매도 실패: 보유 수량이 부족합니다. (현재 보유: {owned[0] if owned else 0}주)")
                return

            # 2. 현재가 가져오기
            cursor.execute("SELECT price, name FROM stock_prices WHERE code = %s", (stock_code,))
            stock = cursor.fetchone()
            current_price, stock_name = stock
            
            total_receive = current_price * amount

            # 3. 현금 추가 및 주식 수량 차감
            cursor.execute("UPDATE users SET cash = cash + %s WHERE id = %s", (total_receive, user_id))
            
            new_qty = owned[0] - amount
            if new_qty == 0:
                # 전량 매도 시 목록에서 삭제 (깔끔하게 관리)
                cursor.execute("DELETE FROM user_stocks WHERE user_id = %s AND stock_code = %s", (user_id, stock_code))
            else:
                cursor.execute("UPDATE user_stocks SET quantity = %s WHERE user_id = %s AND stock_code = %s", (new_qty, user_id, stock_code))

            conn.commit()
            
            # 수익 계산 (참고용)
            profit = (current_price - owned[1]) * amount
            print(f"✅ {stock_name} {amount}주 매도 완료!")
            print(f"💰 입금액: {total_receive:,}원 | 예상 수익: {profit:,}원")

    except Exception as e:
        print(f"❌ 에러 발생: {e}")
        conn.rollback()
    finally:
        conn.close()

if __name__ == "__main__":
    # 테스트: 'Seongung' 유저가 '삼성전자(005930)'를 5주 매도 
    sell_stock('Seongung', '005930', 5)
```
```bash
ubuntu@instance-micro1:~$ python3 sell.py
✅ 삼성전자 5주 매도 완료!
💰 입금액: 898,500원 | 예상 수익: 0원
ubuntu@instance-micro1:~$ python3 status.py

===== [Seongung] 님의 자산 현황 =====
보유 현금: 5,507,500원
---------------------------------------------
종목: 삼성전자       | 수량:  25 | 평단: 179,700원
현재가: 179,700원 | 평가금: 4,492,500원 | 수익률: 0.00%
---------------------------------------------
총 자산 (현금+주식): 10,000,000원
====================================
```

