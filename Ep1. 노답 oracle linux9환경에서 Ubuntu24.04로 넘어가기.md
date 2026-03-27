```bash
mysql sudo yum install -y mysql-server
```
명령을 내리면 시스템은 "어느 저장소에 mysql-server가 있는지" 찾아야 함 <br>
<br>

```
Ksplice for Oracle Linux 9 (x86_64)                                                            20 MB/s |  19 MB     00:00    
Oracle Linux 9 OCI Included Packages (x86_64)                                                  23 MB/s | 218 MB     00:09    

Killed
```

이때 등록된 모든 저장소의 목록을 훑으며 업데이트가 필요한지 체크하기 때문에, <br>
MySQL과 직접 상관이 없어 보이는 OCI 관련 저장소의 인덱스 정보를 내려받는 모습이 터미널에 노출됨 <br>
그런데 이 과정에서 Killed 나버림. (mysql-server 설치도 못함) <br>
<br>

```
[opc@instance-20260327-1356 ~]$ free -m
               total        used        free      shared  buff/cache   available
Mem:             503         269          33           0         225         234
Swap:            502          83         419
```
RAM이 500mb이기 때문 <br> 
설치 자체가 안되는 문제가 있으므로, disk swap을 고려해봐야함 <br> 
단, disk는 RAM보다 수십~수백 배 느림 <br>
<br>

```bash
[opc@instance-20260327-1356 ~]$ sudo fallocate -l 1G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
Setting up swapspace version 1, size = 1024 MiB (1073737728 bytes)
no label, UUID=b35efd59-9536-4d4c-863d-d394409b4fcd
[opc@instance-20260327-1356 ~]$ free -m
               total        used        free      shared  buff/cache   available
Mem:             503         194         168           1         163         309
Swap:           1526         145        1381
```
- 단계별 swap 조치 
1. sudo fallocate -l 1G /swapfile,루트(/) 경로에 1GB 용량의 빈 파일(swapfile)을 생성 (땅을 파서 자리를 잡는 단계) <br>
2. sudo chmod 600 /swapfile,파일 권한을 소유자(root)만 읽고 쓸 수 있게 제한하고, 보안을 위해 다른 사용자가 접근하지 못하게 잠금 <br>
3. sudo mkswap /swapfile, 생성한 일반 파일을 이제부터 스왑 공간으로 사용하겠다고 시스템에 선언(포맷) <br>
4. sudo swapon /swapfile, 준비된 스왑 파일을 실제로 활성화하여 메모리처럼 사용 <br>
<br>

```bash
[opc@instance-20260327-1356 ~]$ sudo dnf install -y mariadb-server --setopt=install_weak_deps=False
```
너무 느려서 disk swap한 것은 원래 상태로 되돌리고, mariaDB로 바꿔주기로 함 <br> 
그런데 30분째 기다려도 안됨 <br>
<br>

<img width="400" height="200" src="https://github.com/user-attachments/assets/655ad1fb-616b-4596-8a4c-2dc9fd257b88" /><br>
서버 stop에 20분 정도 쓰이길래, 열받아서 terminate로 부트 볼륨까지 삭제함 <br>
<br>

<img width="571" height="488" alt="스크린샷 2026-03-27 오후 4 15 03" src="https://github.com/user-attachments/assets/d9de152f-624f-4f48-86e0-a9e6439face7" /><br>
reddit 사이트 조사해보니 Shape가 VM.Standard.E2.1.Micro임에도 <br>
웹사이트 5개, mysql 서버 1개, PostgreSQL 서버 1개를 호스팅하는 글을 보았음 <br>
인스턴스 생성할 때 image를 oracle linux 9에서 Ubuntu24.04 Server로 변경하는 내용과 10gb 스왑하는 내용이 있어서, <br>
따라하기로 함 <br>
<br>

```zsh
sudo apt update && sudo apt upgrade -y
```
새로 Create한 instance(instance_micro1) OS 최신 상태로 만들기 <br>
<br>

```zsh
# 1. 10GB 크기의 스왑 파일 생성 (약 10~20초 소요)
sudo fallocate -l 10G /swapfile

# 2. 보안 권한 설정 (나만 접근 가능하게)
sudo chmod 600 /swapfile

# 3. 스왑 파일로 포맷
sudo mkswap /swapfile

# 4. 스왑 활성화
sudo swapon /swapfile

# 5. 재부팅 시에도 자동 적용되도록 설정 파일에 기록
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab

# 6. 결과 확인
free -h

             total        used        free      shared  buff/cache   available
Mem:           954Mi       365Mi       380Mi       5.0Mi       359Mi       588Mi
Swap:            9Gi          0B         9Gi

```
스왑까지 해줌 <br>
<br>

<img width="828" height="501" alt="스크린샷 2026-03-27 오후 4 55 05" src="https://github.com/user-attachments/assets/63a845d2-2df9-461f-8f1b-4c6f82ce7cde" /><br>
기존 vcn에 HTTP와 HTTPS 조건을 추가해줘야함 <br>
이를 위해서, add ingress rules 2개를 oci 웹에서 추가해줘야함 <br>
첫 번째는, Source CIDR 0.0.0.0/0, IP Protocol TCP, Destination Port Range 80, <br>
두 번째는, Source CIDR 0.0.0.0/0, IP Protocol TCP, Destination Port Range 443 <br>
<br>

```bash
# 1. 패키지 목록 업데이트 및 Nginx 설치
sudo apt update
sudo apt install nginx -y

# 2. 서버 내부 방화벽(iptables)도 한 번 더 확실히 열어주기
sudo iptables -I INPUT -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT -p tcp --dport 443 -j ACCEPT
sudo apt install iptables-persistent -y
# (창이 뜨면 <Yes>에서 엔터)
```
<br>

![ㅁ](https://github.com/user-attachments/assets/21705808-2cff-4563-aa22-144d2d2b6cdd) <br>
정상적으로 열린것을 확인할 수 있음


```bash
sudo netfilter-persistent save
sudo netfilter-persistent reload
```
방화벽도 규칙을 영구히 저장하면 편함 <br>
<br>






 
