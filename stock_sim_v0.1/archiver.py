import redis
import pymysql
from datetime import datetime

# Redis 연결 (민감정보 가림)
r = redis.Redis(
    host='[x]',
    port='[x]',
    decode_responses=True
)

keys = r.keys("stock:*:price")

# MySQL 연결 (민감정보 가림)
db = pymysql.connect(
    host='[x]', 
    user='[x]', 
    password='[x]',
    db='[x]',
    charset='utf8mb4'
)

cursor = db.cursor()

# 현재 시점 데이터 스냅샷
now = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
print(f"[{now}] 적재 시작...")

try:
    for key in keys:
        code = key.split(":")[1]
        price = r.get(key)
        
        sql = "INSERT INTO stock_history (stock_code, price, recorded_at) VALUES (%s, %s, %s)"
        cursor.execute(sql, (code, price, now))

    db.commit()
    print(f"✅ {len(keys)}개 종목 적재 완료!")

except Exception as e:
    print(f"❌ 에러 발생: {e}")
    db.rollback()

finally:
    db.close()
