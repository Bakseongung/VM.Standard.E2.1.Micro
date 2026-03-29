ubuntu@instance-micro1:~$ cat collector.py
import requests
from bs4 import BeautifulSoup
import pymysql
import time

# DB 연결 설정
db = pymysql.connect(host='[x]', user='[x]', password='[x]', db='[x]')
cursor = db.cursor()

def get_realtime_price(code):
    url = f"https://finance.naver.com/item/main.naver?code={code}"
    res = requests.get(url)
    soup = BeautifulSoup(res.text, 'html.parser')
    # 네이버 금융에서 현재가 추출
    price_tag = soup.select_one(".today .no_today .blind")
    if price_tag:
        return int(price_tag.text.replace(',', ''))
    return None

# 수집할 종목 코드 리스트
codes = ['373220', '005930', '000660'] 

print("🚀 실시간 시세 수집 시작...")

try:
    while True:
        for code in codes:
            price = get_realtime_price(code)
            if price:
                # 1. 현재가 테이블 업데이트 (stock_prices)
                cursor.execute("UPDATE stock_prices SET price=%s WHERE code=%s", (price, code))
                # 2. 차트 히스토리 추가 (stock_history) -> 이게 있어야 실시간 차트가 그려짐!
                cursor.execute("INSERT INTO stock_history (stock_code, price) VALUES (%s, %s)", (code, price))
                db.commit()
                print(f"[{time.strftime('%H:%M:%S')}] {code}: {price}원 업데이트 완료")
        
        time.sleep(60) # 1분마다 반복
except KeyboardInterrupt:
    db.close()
