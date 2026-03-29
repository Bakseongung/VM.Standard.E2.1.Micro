import pymysql

DB_CONFIG = {
    'host': '[x]',
    'user': '[x]',
    'password': '[x]',
    'database': '[x]',
    'charset': '[x]'
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
