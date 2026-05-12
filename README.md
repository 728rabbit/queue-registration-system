
# 排隊報名系統 - Redis + MySQL 架構

一個適用於限量名額活動報名的排隊系統，採用 Redis 處理即時排隊，MySQL 做最終資料儲存。

## 功能特點

- ✅ 自動產生籌號，公平排隊
- ✅ 即時查詢排隊位置
- ✅ 批次叫號，限時報名
- ✅ 超時自動釋放名額
- ✅ Redis 處理高並發，MySQL 永久儲存

## 系統架構
    Redis（即時層） MySQL（儲存層）
    ├── 排隊佇列 ├── 報名成功記錄
    ├── 鎖定名額 └── 活動設定
    ├── 叫號狀態
    └── 自動過期（TTL）

## 快速開始

### 1. 安裝 Redis Cloud（免費）

#### Step 1：註冊帳號

1. 前往 [Redis Cloud Console](https://redis.com/redis-enterprise/cloud/)
2. 點擊 **「Start Free」** 或 **「Sign Up」**
3. 用 Google / GitHub 帳號登入

####  Step 2：建立免費資料庫

1. 登入後，點擊 **「New Database」**
2. 選擇 **「Free」** 計劃
3. 填寫設定：
   - Database name：`queue-system`
   - Cloud vendor：AWS 或 Google Cloud
   - Region：Singapore / Tokyo（揀最近香港嘅區域）
4. 點擊 **「Create Database」**

#### Step 3：獲取連線資訊

建立完成後，記低以下資料：

| 項目 | 範例 |
|------|------|
| Public endpoint | `redis-123456.c2345678.ap-southeast-1.cloud.redislabs.com:12345` |
| Password | 系統產生或自己設定 |

####  Step 4：連線測試

##### PHP 用 Predis（唔需要 Redis 擴展）

    <?php
    # composer require predis/predis
    require 'vendor/autoload.php';
    use Predis\Client;
    
    $redis = new Client([
        'scheme' => 'tcp',
        'host' => '你的主機',
        'port' => 你的埠號,
        'password' => '你的密碼',
    ]);
    
    $redis->set('test', 'Hello Redis!');
    echo $redis->get('test');  // Hello Redis!
    ?>


### 2. 建立 MySQL 資料表

    mysql -u root -p < mysql/schema.sql
