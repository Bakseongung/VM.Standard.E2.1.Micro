```bash
# 변경된 설정 파일 로드
sudo systemctl daemon-reload
# 서비스 재시작
sudo systemctl restart [서비스명].service
# 상태확인 (Running이 떠야함)
sudo systemctl status [서비스명].service
```
