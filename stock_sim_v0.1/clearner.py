import pymysql
from datetime import datetime

DB_CONFIG = {
    'host': '[x]',
    'user': '[x]',
    'password': '[x]',
    'database': '[x]'
}

def clean_old_data():
    conn = pymysql.connect(**DB_CONFIG)
    try:
        with conn.cursor() as cursor:
            # 7일 지난 데이터 삭제 쿼리
            sql = "DELETE FROM stock_history WHERE recorded_at < NOW() - INTERVAL 7 DAY;"
            cursor.execute(sql)
            conn.commit()
            print(f"🧹 [{datetime.now()}] 7일 경과 데이터 청소 완료!")
    except Exception as e:
        print(f"❌ 청소 중 오류 발생: {e}")
    finally:
        conn.close()

if __name__ == "__main__":
    clean_old_data()
