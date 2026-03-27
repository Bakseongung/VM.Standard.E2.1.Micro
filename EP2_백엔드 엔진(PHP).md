```
# 최신 버전의 PHP와 Nginx 연결을 위한 FPM, MySQL 연결용 패키지 설치
sudo apt update
sudo apt install php-fpm php-mysql -y

php -v
```
8.3.6으로 나왔음 <br>
<br>

```
sudo nano /etc/nginx/sites-available/default
```
편집기 파일 열어줌 <br>
<br>

<img width="575" height="343" src="https://github.com/user-attachments/assets/7df5368f-3c0f-47b9-8338-f56eb08e232f" /> <br>
<img width="438" height="152" src="https://github.com/user-attachments/assets/6b193b8a-7685-448a-b279-d126bf58befd" /> <br>
수정할 부분은 수정함(index 뒤에 index.php 추가, 7.4->8.3 등등) <br>
Ctrl+O(저장) -> Enter(이름지정, 딱히 변경할 일 없으니 Enter) -> Ctrl+X(편집기 닫기) 
<br>

```
# Ngix 재시작
sudo nginx -t  # 설정 파일에 오타 없는지 검사
sudo systemctl restart nginx

# 테스트 파일 생성
echo "<?php phpinfo(); ?>" | sudo tee /var/www/html/info.php
```

<img width="600" height="481" src="https://github.com/user-attachments/assets/c6e0aefc-b039-405f-afe2-47e7065ba7bd" /> <br>
잘 생성된 것을 확인 가능 <br>
<br>

