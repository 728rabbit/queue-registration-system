<?php
// queue.php - 排隊相關 API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');

require_once 'config.php';

class QueueAPI {
    private $redis;
    private $mysql;
    
    public function __construct($redisConfig, $mysqlConfig) {
        $this->connectRedis($redisConfig);
        $this->connectMySQL($mysqlConfig);
    }
    
    private function connectRedis($config) {
        require_once 'vendor/autoload.php';
        use Predis\Client;
        
        $this->redis = new Client([
            'scheme' => 'tcp',
            'host' => $config['host'],
            'port' => $config['port'],
            'password' => $config['password'],
        ]);
    }
    
    private function connectMySQL($config) {
        $this->mysql = new mysqli(
            $config['host'],
            $config['user'],
            $config['password'],
            $config['database']
        );
        
        if ($this->mysql->connect_error) {
            throw new Exception("MySQL 連線失敗：" . $this->mysql->connect_error);
        }
    }
    
    // 獲取籌號
    public function joinQueue($userId = null) {
        // 檢查活動是否開放
        if (!$this->isEventActive()) {
            return ['error' => '活動未開放或已額滿'];
        }
        
        // 產生籌號：日期(YYMMDD) + 流水號(6位)
        $ticketNo = $this->generateTicketNo();
        $timestamp = time();
        
        // 加入 Redis 等候隊列
        $this->redis->zadd('waiting_queue', $timestamp, $ticketNo);
        
        // 記錄籌號詳細資訊
        $this->redis->hmset("ticket:$ticketNo", [
            'user_id' => $userId,
            'status' => 'waiting',
            'enter_time' => $timestamp,
            'ip' => $_SERVER['REMOTE_ADDR']
        ]);
        
        // 設定過期時間（1小時後未叫號就自動刪除）
        $this->redis->expire("ticket:$ticketNo", 3600);
        
        // 計算排隊位置
        $position = $this->redis->zrank('waiting_queue', $ticketNo);
        
        // 記錄日誌
        $this->logAction($ticketNo, 'join', "加入排隊，位置：" . ($position + 1));
        
        return [
            'success' => true,
            'ticket_no' => $ticketNo,
            'position' => $position + 1,
            'waiting_count' => $this->getWaitingCount(),
            'estimated_wait_seconds' => $this->estimateWaitTime($position + 1)
        ];
    }
    
    // 查詢排隊狀態
    public function getStatus($ticketNo) {
        // 先檢查 Redis
        $status = $this->redis->hget("ticket:$ticketNo", 'status');
        
        if (!$status) {
            // Redis 冇記錄，去 MySQL 睇下係咪已完成報名
            if ($this->isCompleted($ticketNo)) {
                return ['status' => 'completed', 'message' => '報名成功'];
            }
            return ['error' => '籌號不存在或已過期'];
        }
        
        $position = $this->redis->zrank('waiting_queue', $ticketNo);
        
        // 如果狀態係 calling，檢查鎖定仲有冇效
        $lockValid = false;
        $expireSeconds = null;
        if ($status == 'calling') {
            $lockValid = $this->redis->exists("lock:$ticketNo");
            if ($lockValid) {
                $ttl = $this->redis->ttl("lock:$ticketNo");
                $expireSeconds = $ttl > 0 ? $ttl : 0;
            } else {
                // 鎖已過期，更新狀態
                $status = 'expired';
                $this->redis->hset("ticket:$ticketNo", 'status', 'expired');
                $this->logAction($ticketNo, 'timeout', '叫號後超時未報名');
            }
        }
        
        return [
            'ticket_no' => $ticketNo,
            'status' => $status,
            'position' => ($position === null) ? 0 : $position + 1,
            'waiting_count' => $this->getWaitingCount(),
            'is_called' => ($status == 'calling' && $lockValid),
            'expire_seconds' => $expireSeconds
        ];
    }
    
    // 叫號時獲取 Token（由後台批次處理觸發）
    public function getCallToken($ticketNo) {
        $status = $this->redis->hget("ticket:$ticketNo", 'status');
        
        if ($status != 'calling') {
            return ['error' => '未輪到你，請耐心等候'];
        }
        
        $lockExists = $this->redis->exists("lock:$ticketNo");
        if (!$lockExists) {
            return ['error' => '操作已過期，請重新排隊'];
        }
        
        // 產生操作 Token（有效期同鎖定時間一樣）
        $token = bin2hex(random_bytes(32));
        $ttl = $this->redis->ttl("lock:$ticketNo");
        $this->redis->setex("token:$token", $ttl, $ticketNo);
        
        return [
            'success' => true,
            'token' => $token,
            'expire_seconds' => $ttl
        ];
    }
    
    // 後台批次叫號（由 cron job 每 3-5 秒呼叫）
    public function processBatch() {
        $availableSlots = $this->getRemainingSlots();
        if ($availableSlots <= 0) {
            return ['processed' => 0, 'message' => '名額已滿'];
        }
        
        // 取得活動設定
        $batchSize = $this->getBatchSize();
        $timeout = $this->getTimeoutSeconds();
        
        // 實際叫號人數 = min(批次大小, 剩餘名額)
        $callCount = min($batchSize, $availableSlots);
        
        // 從等候隊列取出前 N 人
        $tickets = $this->redis->zrange('waiting_queue', 0, $callCount - 1);
        
        $processed = 0;
        foreach ($tickets as $ticketNo) {
            // 鎖定名額
            $this->redis->setex("lock:$ticketNo", $timeout, time());
            // 更新狀態
            $this->redis->hset("ticket:$ticketNo", 'status', 'calling');
            $this->redis->hset("ticket:$ticketNo", 'expire_at', time() + $timeout);
            // 從等候隊列移除
            $this->redis->zrem('waiting_queue', $ticketNo);
            // 記錄日誌
            $this->logAction($ticketNo, 'call', '叫號，請在 ' . $timeout . ' 秒內報名');
            $processed++;
        }
        
        return ['processed' => $processed, 'message' => "已叫號 {$processed} 人"];
    }
    
    // 產生籌號
    private function generateTicketNo() {
        $date = date('ymd');
        $counter = $this->redis->incr("counter:$date");
        $this->redis->expire("counter:$date", 86400); // 每日重置
        return $date . str_pad($counter, 6, '0', STR_PAD_LEFT);
    }
    
    // 取得等候人數
    private function getWaitingCount() {
        return $this->redis->zcard('waiting_queue');
    }
    
    // 取得剩餘名額
    private function getRemainingSlots() {
        $total = $this->getTotalSlots();
        $completed = $this->redis->get('completed_count') ?: 0;
        return max(0, $total - $completed);
    }
    
    // 檢查是否已完成報名
    private function isCompleted($ticketNo) {
        $stmt = $this->mysql->prepare("SELECT 1 FROM registrations WHERE ticket_no = ? LIMIT 1");
        $stmt->bind_param("s", $ticketNo);
        $stmt->execute();
        $result = $stmt->get_result();
        return $result->num_rows > 0;
    }
    
    // 日誌記錄
    private function logAction($ticketNo, $action, $message = '') {
        $stmt = $this->mysql->prepare(
            "INSERT INTO queue_logs (ticket_no, action, message) VALUES (?, ?, ?)"
        );
        $stmt->bind_param("sss", $ticketNo, $action, $message);
        $stmt->execute();
    }
    
    // 以下方法從 MySQL 或 Redis 讀取設定
    private function isEventActive() { return true; }
    private function getTotalSlots() { return 100; }
    private function getBatchSize() { return 10; }
    private function getTimeoutSeconds() { return 300; }
    private function estimateWaitTime($position) { return $position * 30; }
}

// 處理請求
$config = require 'config.php';
$api = new QueueAPI($config['redis'], $config['mysql']);

$method = $_SERVER['REQUEST_METHOD'];
$path = $_SERVER['PATH_INFO'] ?? '/';

if ($method == 'POST' && $path == '/join') {
    $result = $api->joinQueue($_POST['user_id'] ?? null);
} elseif ($method == 'GET' && isset($_GET['ticket_no'])) {
    $result = $api->getStatus($_GET['ticket_no']);
} elseif ($method == 'GET' && isset($_GET['token'])) {
    $result = $api->getCallToken($_GET['ticket_no']);
} else {
    $result = ['error' => '無效的請求'];
}

echo json_encode($result);
?>
