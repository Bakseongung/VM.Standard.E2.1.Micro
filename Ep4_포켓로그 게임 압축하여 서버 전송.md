```
깃허브로, https://github.com/pagefaultgames/pokerogue를 받아준 뒤,
이에 맞는 node.js와 패키지(npm) 모두 설치
```
```bash
cd pokerogue

# 포켓로그 폴더안에 dist.tar.gz 생성(서버 전송할 때 압축해서 보내면 10배 빠름)
tar -czvf pokerogue_dist.tar.gz -C dist .

# 서버로 전송
scp -i [private 키경로] pokerogue_dist.tar.gz ubuntu@[인스턴스 IP]:~/
```

```bash
#서버 터미널 접속(인스턴스 켜있다고 가정)
ssh -i [private키 경로] ubuntu@[인스턴스 IP]

# 서버 안에서 파일을 옮기기
sudo cp /home/ubuntu/pokerogue_dist.tar.gz /var/www/html/

# 압축 풀고 .gz 삭제
sudo tar -xzvf pokerogue_dist.tar.gz
sudo rm pokerogue_dist.tar.gz

# 모든 파일의 주인을 웹 서버 실행 계정(www-data)으로 변경
sudo chown -R www-data:www-data /var/www/html

# 폴더와 파일의 읽기 권한 부여
sudo chmod -R 755 /var/www/html
```

<img width="1325" height="745" src="https://github.com/user-attachments/assets/2d5a2546-564f-4db6-bb82-eb284c9138d0" />

```
잘 돌아감 로그인 삭제하고 랭킹 기록 생성함.
뭐 기능 추가하려고 php 나 로켓로그 .env 수정하면
npm run build로 다시 빌드 찍고 서버 전송하고 .. 노가다..
```

```bash
# 서버 내 파일 삭제
cd /var/www/html
sudo rm -rf *
cd ..
rm -rf php_backup

# mysql
DROP USER 'pkr_user'@'localhost';
DROP DATABASE pokerogue;
FLUSH PRIVILEGES;
EXIT;
```

