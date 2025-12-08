<?php
/**
 * 日経平均監視システム Web UI
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/scraper.php';

// セッション開始
session_start();

// メッセージ処理
$message = '';
$messageType = '';

if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    $messageType = $_SESSION['message_type'];
    unset($_SESSION['message']);
    unset($_SESSION['message_type']);
}

// フォーム送信処理
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'update_settings') {
        $basePrice = floatval($_POST['base_price']);
        $buySignalPrice = floatval($_POST['buy_signal_price']);
        $sellSignalPrice = floatval($_POST['sell_signal_price']);
        $slackWebhookUrl = trim($_POST['slack_webhook_url']);
        
        // バリデーション
        if ($basePrice <= 0 || $buySignalPrice <= 0 || $sellSignalPrice <= 0) {
            $message = '価格は0より大きい値を入力してください。';
            $messageType = 'error';
        } elseif (empty($slackWebhookUrl) || !preg_match('/^https:\/\/hooks\.slack\.com\//', $slackWebhookUrl)) {
            $message = '有効なSlack Webhook URLを入力してください。';
            $messageType = 'error';
        } elseif ($buySignalPrice >= $basePrice) {
            $message = '買いシグナル価格は基準価格より低く設定してください。';
            $messageType = 'error';
        } elseif ($sellSignalPrice <= $basePrice) {
            $message = '売りシグナル価格は基準価格より高く設定してください。';
            $messageType = 'error';
        } else {
            if (updateSettings($basePrice, $buySignalPrice, $sellSignalPrice, $slackWebhookUrl)) {
                $message = '設定を更新しました。';
                $messageType = 'success';
            } else {
                $message = '設定の更新に失敗しました。';
                $messageType = 'error';
            }
        }
    }
}

// 現在の設定を取得
$settings = getSettings();
if (!$settings) {
    // デフォルト設定
    $settings = [
        'base_price' => 50000.00,
        'buy_signal_price' => 49000.00,
        'sell_signal_price' => 52000.00,
        'slack_webhook_url' => ''
    ];
}

// 現在の株価を取得（エラーハンドリング付き）
$currentPriceData = null;
try {
    $scraper = new NikkeiScraper();
    $currentPriceData = $scraper->getCurrentPrice();
} catch (Exception $e) {
    error_log('Price fetch error: ' . $e->getMessage());
}

// 価格履歴を取得
$priceHistory = getRecentPriceHistory(14);

// 通知履歴を取得
$notifications = getRecentNotifications(10);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>日経平均監視システム</title>
    <link href="css/style.css" rel="stylesheet">
</head>
<body>
    <div class="container">
        <header class="header">
            <h1>日経平均監視システム</h1>
            <p class="subtitle">自動売買シグナル通知</p>
        </header>

        <?php if ($message): ?>
        <div class="message <?php echo htmlspecialchars($messageType); ?>">
            <?php echo htmlspecialchars($message); ?>
        </div>
        <?php endif; ?>

        <!-- 現在の株価表示 -->
        <section class="card">
            <h2>📊 現在の日経平均株価</h2>
            <?php if ($currentPriceData && isset($currentPriceData['close'])): ?>
                <div class="current-price">
                    <div class="price-value">¥<?php echo floor($currentPriceData['close'], 2); ?></div>
                    <?php if ($currentPriceData['change'] !== null): ?>
                        <div class="price-change <?php echo $currentPriceData['change'] >= 0 ? 'positive' : 'negative'; ?>">
                            <?php echo $currentPriceData['change'] >= 0 ? '+' : ''; ?>
                            <?php echo floor($currentPriceData['change'], 2); ?>
                            <?php if ($currentPriceData['change_percent'] !== null): ?>
                                (<?php echo floor($currentPriceData['change_percent'], 2); ?>%)
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                    <div class="price-time">取得時刻: <?php echo date('Y年m月d日 H:i:s'); ?></div>
                </div>
                
                <!-- シグナル状態表示 -->
                <div class="signal-status">
                    <?php
                    $currentPrice = $currentPriceData['close'];
                    if ($currentPrice < $settings['buy_signal_price']):
                    ?>
                        <div class="signal-alert buy">
                            🔔 買いシグナル発生中！
                        </div>
                    <?php elseif ($currentPrice > $settings['sell_signal_price']): ?>
                        <div class="signal-alert sell">
                            🔔 売りシグナル発生中！
                        </div>
                    <?php else: ?>
                        <div class="signal-alert normal">
                            ✅ 正常範囲内（シグナルなし）
                        </div>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="error-message">
                    株価の取得に失敗しました。しばらくしてから再度お試しください。
                </div>
            <?php endif; ?>
        </section>

        <!-- 設定フォーム -->
        <section class="card">
            <h2>⚙️ 監視設定</h2>
            <form method="POST" action="" class="settings-form">
                <input type="hidden" name="action" value="update_settings">
                
                <div class="form-group">
                    <label for="base_price">基準価格 (円)</label>
                    <input type="number" id="base_price" name="base_price" 
                           value="<?php echo floor($settings['base_price']); ?>" 
                           step="1" required>
                    <small>基準となる価格を設定します</small>
                </div>

                <div class="form-group">
                    <label for="buy_signal_price">買いシグナル価格 (円)</label>
                    <input type="number" id="buy_signal_price" name="buy_signal_price" 
                           value="<?php echo floor($settings['buy_signal_price']); ?>" 
                           step="1" required>
                    <small>この価格を下回ったら買いシグナルを通知</small>
                </div>

                <div class="form-group">
                    <label for="sell_signal_price">売りシグナル価格 (円)</label>
                    <input type="number" id="sell_signal_price" name="sell_signal_price" 
                           value="<?php echo floor($settings['sell_signal_price']); ?>" 
                           step="1" required>
                    <small>この価格を上回ったら売りシグナルを通知</small>
                </div>

                <div class="form-group">
                    <label for="slack_webhook_url">Slack Webhook URL</label>
                    <input type="url" id="slack_webhook_url" name="slack_webhook_url" 
                           value="<?php echo htmlspecialchars($settings['slack_webhook_url'] ?? ''); ?>" 
                           placeholder="https://hooks.slack.com/services/..."
                           required>
                    <small>シグナル発生時のSlack通知先</small>
                </div>

                <button type="submit" class="btn btn-primary">設定を保存</button>
            </form>

            <div class="info-box">
                <h3>💡 設定のヒント</h3>
                <ul>
                    <li>基準価格: 現在の市場価格を参考に設定</li>
                    <li>買いシグナル: 基準価格より低く設定（例: -1,000円）</li>
                    <li>売りシグナル: 基準価格より高く設定（例: +2,000円）</li>
                    <li>大幅下落検出：前日比-1500円以上（固定値）</li>
                    <li>通知は1日2回（10:30、14:30）のチェック時に送信されます</li>
                </ul>
            </div>
        </section>

        <!-- 通知履歴 -->
        <section class="card">
            <h2>🔔 通知履歴</h2>
            <?php if (count($notifications) > 0): ?>
                <div class="table-responsive">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>日時</th>
                                <th>シグナル</th>
                                <th>株価</th>
                                <th>トリガー価格</th>
                                <th>状態</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($notifications as $notification): ?>
                            <tr>
                                <td><?php echo date('Y/m/d H:i', strtotime($notification['notified_at'])); ?></td>
                                <td>
                                    <span class="badge <?php echo $notification['signal_type']; ?>">
                                        <?php 
                                        $signalLabels = ['buy' => '買い', 'sell' => '売り', 'large_drop' => '大幅下落'];
                                        echo $signalLabels[$notification['signal_type']] ?? $notification['signal_type']; 
                                        ?>
                                    </span>
                                </td>
                                <td>¥<?php echo floor($notification['current_price'], 0); ?></td>
                                <td>¥<?php echo floor($notification['trigger_price'], 0); ?></td>
                                <td>
                                    <?php if ($notification['slack_sent']): ?>
                                        <span class="status-success">✅ 送信済</span>
                                    <?php else: ?>
                                        <span class="status-error">❌ 失敗</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="no-data">通知履歴はまだありません。</p>
            <?php endif; ?>
        </section>

        <!-- 価格履歴 -->
        <section class="card">
            <h2>📈 価格履歴（直近14日）</h2>
            <?php if (count($priceHistory) > 0): ?>
                <div class="table-responsive">
                    <table class="history-table">
                        <thead>
                            <tr>
                                <th>日付</th>
                                <th>終値</th>
                                <th>始値</th>
                                <th>高値</th>
                                <th>安値</th>
                                <th>変動率</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($priceHistory as $history): ?>
                            <tr>
                                <td><?php echo date('Y/m/d', strtotime($history['date'])); ?></td>
                                <td>¥<?php echo floor($history['close'], 0); ?></td>
                                <td>¥<?php echo floor($history['open'] ?? 0, 0); ?></td>
                                <td>¥<?php echo floor($history['high'] ?? 0, 0); ?></td>
                                <td>¥<?php echo floor($history['low'] ?? 0, 0); ?></td>
                                <td class="<?php echo ($history['price_change_rate'] ?? 0) >= 0 ? 'positive' : 'negative'; ?>">
                                    <?php if ($history['price_change_rate'] !== null): ?>
                                        <?php echo ($history['price_change_rate'] >= 0 ? '+' : ''); ?>
                                        <?php echo floor($history['price_change_rate'] * 100, 2); ?>%
                                    <?php else: ?>
                                        -
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="no-data">価格履歴はまだありません。</p>
            <?php endif; ?>
        </section>

        <!-- システム情報 -->
        <section class="card">
            <h2>🔧 システム情報</h2>
            <div class="system-info">
                <div class="info-item">
                    <strong>監視URL:</strong>
                    <a href="https://jp.investing.com/indices/japan-ni225-historical-data" target="_blank" rel="noopener">
                        Investing.com - 日経平均
                    </a>
                </div>
                <div class="info-item">
                    <strong>チェック頻度:</strong> 1日3回（cronで自動実行）
                    <ul class="schedule-list">
                        <li>10:30 - シグナル判定あり</li>
                        <li>14:30 - シグナル判定あり</li>
                        <li>18:30 - 価格記録のみ（シグナル判定なし）</li>
                    </ul>
                </div>
                <div class="info-item">
                    <strong>Slack通知:</strong>
                    <?php if (!empty($settings['slack_webhook_url'])): ?>
                        <span class="status-success">✅ 設定済み</span>
                    <?php else: ?>
                        <span class="status-error">❌ 未設定</span>
                    <?php endif; ?>
                </div>
                <div class="info-item">
                    <strong>手動チェック:</strong>
                    <a href="check_price.php" class="btn btn-small" target="_blank">今すぐチェック</a>
                </div>
            </div>
        </section>

        <footer class="footer">
            <p>&copy; 2025 日経平均監視システム | データ提供: Investing.com</p>
        </footer>
    </div>
</body>
</html>
