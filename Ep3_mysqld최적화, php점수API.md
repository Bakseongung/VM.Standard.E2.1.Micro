## mysqld 128M, 모니터링 OFF
```bash
# MySQL 서버 설치
sudo apt update
sudo apt install mysql-server -y

# 보안 설정 실행 (대문자, 특수문자 포함)
sudo mysql_secure_installation

# 서버프로그램 열기
sudo nano /etc/mysql/mysql.conf.d/mysqld.cnf

# 맨 밑에 두줄 주가
innodb_buffer_pool_size = 128M # DB 메모리 저장소 128MB로 제한
performance_schema = OFF # 모니터링 기능을 꺼서 RAM을 추가로 절약

# 설정 적용
sudo systemctl restart mysql

sudo mysql -u root -p
```

## create table rankings
```sql
CREATE DATABASE db_name; -- DB이름
CREATE USER '사용자이름'@'localhost' IDENTIFIED BY '비번'; -- 사용자이름, 비번 
GRANT ALL PRIVILEGES ON pkr_db.* TO 'pkr_user'@'localhost';
FLUSH PRIVILEGES;

USE 사용자이름;

CREATE TABLE rankings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL,
    score INT NOT NULL,
    reached_wave INT DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

EXIT;
```

## save_score.php 
```php
<?php
// DB 접속
$conn = mysqli_connect("localhost", "사용자", "비번", "db");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['username'];
    $score = $_POST['score'];
    $wave = $_POST['wave'];

    // SQL injection
    $name = mysqli_real_escape_string($conn, $name);

    $sql = "INSERT INTO rankings (username, score, reached_wave) VALUES ('$name', $score, $wave)";
    
    if (mysqli_query($conn, $sql)) {
        echo "랭킹 등록 완료!";
    } else {
        echo "에러 발생: " . mysqli_error($conn);
    }
}
mysqli_close($conn);
?>
```

## get_rank.php
```php
<?php
$conn = mysqli_connect("localhost", "사용자", "비번", "db");

$sql = "SELECT username, score, reached_wave FROM rankings ORDER BY score DESC LIMIT 10";
$result = mysqli_query($conn, $sql);

$rankings = [];
while($row = mysqli_fetch_assoc($result)) {
    $rankings[] = $row;
}

// json
echo json_encode($rankings, JSON_UNESCAPED_UNICODE);

mysqli_close($conn);
?>
```



