CREATE DATABASE IF NOT EXISTS queue_system;
USE queue_system;

-- 報名成功記錄表
CREATE TABLE IF NOT EXISTS registrations (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ticket_no VARCHAR(32) NOT NULL UNIQUE COMMENT '籌號',
    user_id VARCHAR(50) COMMENT '用戶ID（可選）',
    name VARCHAR(100) NOT NULL COMMENT '姓名',
    phone VARCHAR(20) NOT NULL COMMENT '電話',
    email VARCHAR(100) COMMENT '電郵',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '報名時間',
    INDEX idx_ticket_no (ticket_no),
    INDEX idx_phone (phone),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='報名成功記錄';

-- 活動設定表
CREATE TABLE IF NOT EXISTS event_config (
    id INT PRIMARY KEY DEFAULT 1,
    total_slots INT NOT NULL COMMENT '總名額',
    batch_size INT DEFAULT 10 COMMENT '每次叫號人數',
    operation_timeout_seconds INT DEFAULT 300 COMMENT '操作時限（秒）',
    is_active BOOLEAN DEFAULT TRUE COMMENT '是否開放報名',
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='活動設定';

-- 插入預設設定
INSERT INTO event_config (id, total_slots, batch_size, operation_timeout_seconds) 
VALUES (1, 100, 10, 300)
ON DUPLICATE KEY UPDATE id=id;

-- （可選）排隊日誌表
CREATE TABLE IF NOT EXISTS queue_logs (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    ticket_no VARCHAR(32) COMMENT '籌號',
    action VARCHAR(20) COMMENT '動作：join/timeout/cancelled/success',
    message TEXT COMMENT '詳細訊息',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ticket_no (ticket_no),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='排隊日誌';
