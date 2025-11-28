-- 日経平均監視通知システム データベース初期化スクリプト

-- データベース作成（必要に応じて）
-- CREATE DATABASE IF NOT EXISTS investment_trust CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE investment_trust;

-- 設定テーブル
CREATE TABLE IF NOT EXISTS settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    base_price DECIMAL(10, 2) NOT NULL COMMENT '基準価格',
    buy_signal_price DECIMAL(10, 2) NOT NULL COMMENT '買いシグナル価格',
    sell_signal_price DECIMAL(10, 2) NOT NULL COMMENT '売りシグナル価格',
    email_address VARCHAR(255) NOT NULL COMMENT '通知先メールアドレス',
    is_active TINYINT(1) DEFAULT 1 COMMENT '監視有効フラグ',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 価格履歴テーブル
CREATE TABLE IF NOT EXISTS price_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    price DECIMAL(10, 2) NOT NULL COMMENT '株価',
    price_change DECIMAL(10, 2) COMMENT '前日比',
    price_change_percent DECIMAL(5, 2) COMMENT '変動率(%)',
    checked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '取得日時',
    INDEX idx_checked_at (checked_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 通知履歴テーブル
CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    signal_type ENUM('buy', 'sell') NOT NULL COMMENT 'シグナルタイプ',
    current_price DECIMAL(10, 2) NOT NULL COMMENT '通知時の株価',
    trigger_price DECIMAL(10, 2) NOT NULL COMMENT 'トリガー価格',
    email_sent TINYINT(1) DEFAULT 0 COMMENT 'メール送信成功フラグ',
    error_message TEXT COMMENT 'エラーメッセージ',
    notified_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '通知日時',
    INDEX idx_notified_at (notified_at),
    INDEX idx_signal_type (signal_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 初期設定データ挿入（例）
INSERT INTO settings (base_price, buy_signal_price, sell_signal_price, email_address) 
VALUES (50000.00, 49000.00, 52000.00, 'your-email@gmail.com')
ON DUPLICATE KEY UPDATE id=id;

