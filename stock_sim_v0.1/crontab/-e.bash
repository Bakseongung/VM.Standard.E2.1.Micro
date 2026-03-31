# [1. 실시간 시세 수집] -> (제거) 이제 systemd 서비스가 24시간 담당함
# [2. MySQL 스냅샷 적재] 매 분 정각에 실행 (새로 만든 archiver.py)
* * * * * /usr/bin/python3 /home/ubuntu/archiver.py >> /home/ubuntu/archive_log.log 2>&1

# [3. 종목 리스트 동기화] 매일 새벽 5시에 실행
0 5 * * * /usr/bin/python3 /home/ubuntu/stock_to_db.py >> /home/ubuntu/sync_log.log 2>&1

# [4. 데이터 청소] -> (제거) MySQL Event Scheduler가 내부에서 알아서 지움
