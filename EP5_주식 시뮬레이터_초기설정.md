### 현재 Mysql 상황
```bash
sudo fallocate -l 10G /swapfile
innodb_buffer_pool_size = 128M 
performance_schema = OFF 
```
### 추가 성능 확보
```bash
# [기존 설정 유지]
innodb_buffer_pool_size = 128M
performance_schema = OFF

# --- 주식 시뮬레이터(잦은 쓰기 작업)를 위한 추가 설정 ---

# 1. 쓰기 성능 극대화 (1초에 한 번 디스크 기록, 처리량 10배 이상 향상)
innodb_flush_log_at_trx_commit = 2

# 2. 로그 파일 크기 조정 (메모리 부족 시 체크포인트 지연 방지)
innodb_log_file_size = 64M

# 3. 한 번에 들어오는 대량의 주가 데이터를 위한 패킷 크기 확장
max_allowed_packet = 16M

# 4. 커넥션당 메모리 점유 최소화 (1GB 램 환경 보호)
max_connections = 50
read_buffer_size = 256K
read_rnd_buffer_size = 512K
sort_buffer_size = 512K

# 5. 바이너리 로그 자동 삭제 (45GB 용량 확보를 위해 3일치만 보관)
binlog_expire_logs_seconds = 259200
```

```bash
sudo systemctl restart mysql
```

```
ubuntu@instance-micro1:~$ sudo sysctl vm.swappiness=10
# 영구 적용을 위해 아래 명령어도 실행
echo 'vm.swappiness=10' | sudo tee -a /etc/sysctl.conf
vm.swappiness = 10
vm.swappiness=10
```
