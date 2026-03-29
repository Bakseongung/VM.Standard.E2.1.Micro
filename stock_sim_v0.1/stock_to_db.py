import FinanceDataReader as fdr
import pymysql
import pandas as pd
from datetime import datetime

# [설정] MySQL 접속 정보
DB_CONFIG = {
    'host': '[x]',
    'user': '[x]',
    'password': '[x]',
    'database': '[x]',
    'charset': '[x]'
}

def update_all_stocks():
    conn = pymysql.connect(**DB_CONFIG)
    try:
        with conn.cursor() as cursor:
            print(f"🚀 [{datetime.now()}] 국내 전 종목(KOSPI/KOSDAQ/KONEX) 동기화 시작...")
            
            # 1. 국내 시장 모든 종목 리스트 가져오기 (KRX는 코스피/코스닥/코넥스 합본입니다)
            df_all = fdr.StockListing('KRX')
            
            total_count = len(df_all)
            print(f"📦 총 {total_count}개 종목을 처리합니다.")
            
            success_count = 0
            for _, row in df_all.iterrows():
                code = row['Code']
                name = row['Name']
                market = row['Market']
                
                # 'Close' 가격 정보 추출 (데이터가 없거나 0이면 스킵)
                try:
                    current_price = int(row.get('Close', 0))
                except:
                    current_price = 0
                    
                if current_price == 0:
                    continue

                # 2. UPSERT (데이터가 있으면 업데이트, 없으면 삽입)
                upsert_sql = """
                INSERT INTO stock_prices (code, name, market, price)
                VALUES (%s, %s, %s, %s)
                ON DUPLICATE KEY UPDATE 
                price = VALUES(price), 
                updated_at = NOW();
                """
                cursor.execute(upsert_sql, (code, name, market, current_price))
                
                # 3. 실시간 차트용 히스토리 기록
                history_sql = "INSERT INTO stock_history (stock_code, price) VALUES (%s, %s)"
                cursor.execute(history_sql, (code, current_price))
                
                success_count += 1

            # 4. 최종 커밋
            conn.commit()
            print(f"✅ 완료: 총 {success_count}개 종목 업데이트 성공! 이제 국내 모든 주식을 볼 수 있습니다. 😎")

    except Exception as e:
        print(f"❌ 오류 발생: {e}")
        conn.rollback()
    finally:
        conn.close()

if __name__ == "__main__":
    update_all_stocks()
