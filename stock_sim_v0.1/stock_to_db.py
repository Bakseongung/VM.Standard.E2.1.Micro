import FinanceDataReader as fdr
import pymysql
import pandas as pd
from datetime import datetime

# MySQL 접속 정보 (민감정보 가림)
DB_CONFIG = {
    'host': '[x]',
    'user': '[x]',
    'password': '[x]',
    'database': '[x]',
    'charset': 'utf8mb4'
}

def update_all_stocks():
    conn = pymysql.connect(**DB_CONFIG)
    try:
        with conn.cursor() as cursor:
            print(f"🚀 [{datetime.now()}] 국내 전 종목 리스트 동기화 시작...")
            
            df_all = fdr.StockListing('KRX')
            
            total_count = len(df_all)
            print(f"📦 총 {total_count}개 종목을 처리합니다.")
            
            success_count = 0
            
            for _, row in df_all.iterrows():
                code = row['Code']
                name = row['Name']
                market = row['Market']
                
                try:
                    current_price = int(row.get('Close', 0))
                except:
                    current_price = 0
                
                upsert_sql = """
                INSERT INTO stock_prices (code, name, market, price)
                VALUES (%s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE 
                name = VALUES(name),
                market = VALUES(market),
                price = VALUES(price), 
                updated_at = NOW();
                """
                cursor.execute(upsert_sql, (code, name, market, current_price))
                
                success_count += 1

            conn.commit()
            print(f"✅ 완료: 총 {success_count}개 종목 리스트 업데이트 성공!")

    except Exception as e:
        print(f"❌ 오류 발생: {e}")
        conn.rollback()
    finally:
        conn.close()

if __name__ == "__main__":
    update_all_stocks()
