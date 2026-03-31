import redis
import time
import os

# Redis 연결 (민감정보 가림)
r = redis.Redis(
    host='[x]',
    port='[x]',
    decode_responses=True
)

def show_orderbook(code, name):
    while True:
        price = r.get(f"stock:{code}:price")
        
        if price:
            price = int(price)
            os.system('clear')
            
            print(f"=== [실시간 호가창: {name} ({code})] ===")
            print(f"  매도호가3: {price + 300:,}")
            print(f"  매도호가2: {price + 200:,}")
            print(f"  매도호가1: {price + 100:,}")
            print(f"🚀 현재가  : {price:,} 🔥")
            print(f"  매수호가1: {price - 100:,}")
            print(f"  매수호가2: {price - 200:,}")
            print(f"  매수호가3: {price - 300:,}")
            print("======================================")
            print("대기 중... (종료: Ctrl+C)")
        
        time.sleep(0.5)

if __name__ == "__main__":
    show_orderbook("[x]", "[x]")
