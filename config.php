<?php
/**
 * データベース接続設定
 */

// .envファイルを読み込み
require_once __DIR__ . '/load_env.php';

// データベース接続情報（.envから取得）
define('DB_HOST', env('DB_HOST', '127.0.0.1:3308'));
define('DB_NAME', env('DB_NAME', 'investment_trust'));
define('DB_USER', env('DB_USER', 'root'));
define('DB_PASS', env('DB_PASS', ''));
define('DB_CHARSET', 'utf8mb4');

// タイムゾーン設定
date_default_timezone_set(env('TIMEZONE', 'Asia/Tokyo'));

// エラー表示設定（本番環境では0に設定）
ini_set('display_errors', env('DISPLAY_ERRORS', 1));
$errorLevel = env('ERROR_REPORTING', 'E_ALL');
error_reporting(defined($errorLevel) ? constant($errorLevel) : E_ALL);

/**
 * データベース接続を取得
 * @return PDO
 */
function getDbConnection() {
    static $pdo = null;
    
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            error_log('Database connection error: ' . $e->getMessage());
            die('データベース接続エラーが発生しました。');
        }
    }
    
    return $pdo;
}

/**
 * 設定を取得
 * @return array|null
 */
function getSettings() {
    $pdo = getDbConnection();
    $stmt = $pdo->query('SELECT * FROM settings WHERE is_active = 1 ORDER BY id DESC LIMIT 1');
    return $stmt->fetch();
}

/**
 * 設定を更新
 * @param float $basePrice
 * @param float $buySignalPrice
 * @param float $sellSignalPrice
 * @param string $emailAddress
 * @return bool
 */
function updateSettings($basePrice, $buySignalPrice, $sellSignalPrice, $emailAddress) {
    $pdo = getDbConnection();
    
    // 既存の設定を無効化
    $pdo->exec('UPDATE settings SET is_active = 0');
    
    // 新しい設定を挿入
    $stmt = $pdo->prepare('
        INSERT INTO settings (base_price, buy_signal_price, sell_signal_price, email_address, is_active)
        VALUES (:base_price, :buy_signal_price, :sell_signal_price, :email_address, 1)
    ');
    
    return $stmt->execute([
        ':base_price' => $basePrice,
        ':buy_signal_price' => $buySignalPrice,
        ':sell_signal_price' => $sellSignalPrice,
        ':email_address' => $emailAddress
    ]);
}

/**
 * 価格履歴を保存
 * @param float $price
 * @param float|null $priceChange
 * @param float|null $priceChangePercent
 * @return bool
 */
function savePriceHistory($price, $priceChange = null, $priceChangePercent = null) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('
        INSERT INTO price_history (price, price_change, price_change_percent)
        VALUES (:price, :price_change, :price_change_percent)
    ');
    
    return $stmt->execute([
        ':price' => $price,
        ':price_change' => $priceChange,
        ':price_change_percent' => $priceChangePercent
    ]);
}

/**
 * 通知履歴を保存
 * @param string $signalType 'buy' or 'sell'
 * @param float $currentPrice
 * @param float $triggerPrice
 * @param bool $emailSent
 * @param string|null $errorMessage
 * @return bool
 */
function saveNotification($signalType, $currentPrice, $triggerPrice, $emailSent, $errorMessage = null) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('
        INSERT INTO notifications (signal_type, current_price, trigger_price, email_sent, error_message)
        VALUES (:signal_type, :current_price, :trigger_price, :email_sent, :error_message)
    ');
    
    return $stmt->execute([
        ':signal_type' => $signalType,
        ':current_price' => $currentPrice,
        ':trigger_price' => $triggerPrice,
        ':email_sent' => $emailSent ? 1 : 0,
        ':error_message' => $errorMessage
    ]);
}

/**
 * 最新の価格履歴を取得
 * @param int $limit
 * @return array
 */
function getRecentPriceHistory($limit = 30) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT * FROM price_history ORDER BY created_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * 最新の通知履歴を取得
 * @param int $limit
 * @return array
 */
function getRecentNotifications($limit = 20) {
    $pdo = getDbConnection();
    $stmt = $pdo->prepare('SELECT * FROM notifications ORDER BY notified_at DESC LIMIT :limit');
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

