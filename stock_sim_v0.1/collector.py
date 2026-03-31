import asyncio
import aiohttp
import time
import redis.asyncio as redis
from datetime import datetime

# Redis 설정 (민감정보 가림)
REDIS_HOST = '[x]'
REDIS_PORT = '[x]'

async def fetch_and_update_redis(session, redis_client, code, sem):
    async with sem:
        url = f"https://polling.finance.naver.com/api/realtime?query=SERVICE_ITEM:{code}"
        try:
            async with session.get(url, timeout=10) as res:
                if res.status == 200:
                    data = await res.json(content_type=None)
                    items = data.get('result', {}).get('areas', [{}])[0].get('datas', [])
                    
                    if items:
                        price_val = items[0].get('nv')
                        if price_val is not None:
                            price = int(price_val)

                            # Redis 저장
                            await redis_client.set(f"stock:{code}:price", price)

        except Exception:
            pass  # 네트워크 오류 무시

async def run_collector_redis_only():
    redis_client = redis.Redis(
        host=REDIS_HOST,
        port=REDIS_PORT,
        decode_responses=True
    )

    keys = await redis_client.keys("stock:*:price")
    codes = [key.split(":")[1] for key in keys]

    if not codes:
        print(f"⚠️ [{datetime.now()}] 수집할 종목 코드가 Redis에 없습니다.")
        await redis_client.aclose()
        return

    print(f"🚀 [{datetime.now()}] 실시간 수집 시작 (총 {len(codes)}개, 세마포어: 100)")

    sem = asyncio.Semaphore(100)
    connector = aiohttp.TCPConnector(limit=200, ttl_dns_cache=300)
    
    async with aiohttp.ClientSession(connector=connector) as session:
        tasks = [
            fetch_and_update_redis(session, redis_client, code, sem)
            for code in codes
        ]
        await asyncio.gather(*tasks)

    await redis_client.aclose()
    print(f"✅ [{datetime.now()}] 한 사이클 완료 (Redis 갱신 성공)")

if __name__ == "__main__":
    while True:
        start_time = time.time()
        
        try:
            asyncio.run(run_collector_redis_only())
        except Exception as e:
            print(f"🚨 시스템 에러 발생: {e}")
        
        elapsed = time.time() - start_time
        print(f"⏱️ 이번 수집 소요 시간: {elapsed:.2f}초")
        
        time.sleep(0)
