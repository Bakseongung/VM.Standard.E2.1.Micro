# 서버
stock_sim_v0.1 (24H): http://168.107.38.18/ <br>

```
주식 시뮬레이터(계속 패치중)
- 가상자산, 실제 주식시장 반영
- 총 2881개 종목, 국내 모든 주식 가능
- collector.py(->redis) : 차트 실시간 19초마다 변경(마포어: 100)
- archiver.py(->mysql) : 1분마다 Mysql에 데이터 적재
- 매일 새벽 3시 청소 (7일 지난 주가 데이터는 삭제)
```

# OCI
## instance_micro1(stock_sim_v0.1 server)
```yml
Image : Canonical-Ubuntu-24.04-2026.02.28-0
Shape : VM.Standard.E2.1.Micro
OCPU count : 1
Network bandwidth (Gbps) : 0.48
Memory (GB) : 1
Storage : 45GB
```
