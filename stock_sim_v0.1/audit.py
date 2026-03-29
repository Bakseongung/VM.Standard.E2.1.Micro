import pymysql

DB_CONFIG = {
    'host': '[x]',
    'user': '[x]',
    'password': '[x]',
    'database': '[x]',
    'charset': '[x]'
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
