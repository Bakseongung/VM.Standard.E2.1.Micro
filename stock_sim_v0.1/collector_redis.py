import asyncio
import aiohttp
import aiomysql
import time
import redis.asyncio as redis
from datetime import datetime

# [설정] DB 및 Redis 정보
DB_CONFIG = {
    'host': '[x]',
    'user': '[x]',
    'password': '[x]',
    'db': '[x]',
    'autocommit': True
}
REDIS_HOST = 'localhost'
REDIS_PORT = 6379

async def fetch_and_update_redis(session, redis_client, code, sem):
    """네이버에서 가격을 가져와 Redis에 즉시 기록"""
    async with sem:
        url = f"https://polling.finance.naver.com/api/realtime?query=SERVICE_ITEM:{code}"
        try:
            async with session.get(url, timeout=5) as res:
                if res.status == 200:
                    data = await res.json(content_type=None)
                    areas = data.get('result', {}).get('areas', [])
                    if areas:
                        datas = areas[0].get('datas', [])
                        if datas:
                            price_val = datas[0].get('nv')
                            if price_val is not None:
                                price = int(price_val)
                                now_str = datetime.now().strftime('%Y-%m-%d %H:%M:%S')
                                
                                await redis_client.set(f"stock:{code}:price", price)
                                await redis_client.set(f"stock:{code}:time", now_str)
        except Exception:
            pass

async def run_collector():
    redis_client = redis.Redis(host=REDIS_HOST, port=REDIS_PORT, decode_responses=True)
    try:
        conn = await aiomysql.connect(**DB_CONFIG)
        async with conn.cursor() as cur:
            await cur.execute("SELECT code FROM stock_prices")
            rows = await cur.fetchall()
            codes = [r[0] for r in rows]
        conn.close()

        if not codes:
            print(f"⚠️ [{datetime.now()}] 수집할 종목이 없습니다.")
            return

        print(f"🚀 [{datetime.now()}] 수집 시작 (총 {len(codes)}개)", flush=True)

        sem = asyncio.Semaphore(50)
        connector = aiohttp.TCPConnector(limit=100, ttl_dns_cache=100)
        
        async with aiohttp.ClientSession(connector=connector) as session:
            tasks = [fetch_and_update_redis(session, redis_client, code, sem) for code in codes]
            await asyncio.gather(*tasks)

        print(f"✅ [{datetime.now()}] 한 사이클 완료")
    finally:
        await redis_client.aclose()

if __name__ == "__main__":
    while True:
        start_time = time.time()
        try:
            asyncio.run(run_collector())
        except Exception as e:
            print(f"🚨 에러 발생: {e}")
        
        elapsed = time.time() - start_time
        print(f"⏱️ 수집 소요 시간: {elapsed:.2f}초")
        time.sleep(5)
