<?php
/**
 * Slack Webhook テストスクリプト
 * ブラウザでアクセスして動作確認
 */

require_once __DIR__ . '/config.php';

// 設定からWebhook URLを取得
$settings = getSettings();
$webhookUrl = $settings['slack_webhook_url'] ?? '';

echo "<h2>Slack Webhook テスト</h2>";
echo "<hr>";

// Webhook URLの確認
echo "<h3>1. Webhook URL確認</h3>";
if (empty($webhookUrl)) {
    echo "<p style='color:red'>❌ Webhook URLが設定されていません。index.phpで設定してください。</p>";
    exit;
} else {
    // URLの一部をマスク
    $maskedUrl = preg_replace('/services\/.*/', 'services/***', $webhookUrl);
    echo "<p style='color:green'>✅ Webhook URL: {$maskedUrl}</p>";
}

// cURL拡張の確認
echo "<h3>2. cURL拡張確認</h3>";
if (function_exists('curl_init')) {
    echo "<p style='color:green'>✅ cURL拡張が有効です</p>";
} else {
    echo "<p style='color:red'>❌ cURL拡張が無効です。php.iniで有効化してください。</p>";
    exit;
}

// テスト送信
echo "<h3>3. テスト送信</h3>";

$payload = [
    'text' => '🧪 PHPからのテスト通知 - ' . date('Y/m/d H:i:s')
];

$ch = curl_init($webhookUrl);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json; charset=utf-8'
    ],
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_SSL_VERIFYPEER => false,  // ローカル環境用にSSL検証を無効化
    CURLOPT_VERBOSE => true,
]);

// 詳細ログをキャプチャ
$verbose = fopen('php://temp', 'w+');
curl_setopt($ch, CURLOPT_STDERR, $verbose);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
$errno = curl_errno($ch);

// 詳細ログを取得
rewind($verbose);
$verboseLog = stream_get_contents($verbose);
fclose($verbose);

echo "<table border='1' cellpadding='10' style='border-collapse: collapse;'>";
echo "<tr><th>項目</th><th>値</th></tr>";
echo "<tr><td>HTTPステータスコード</td><td>" . ($httpCode === 200 ? "<span style='color:green'>$httpCode</span>" : "<span style='color:red'>$httpCode</span>") . "</td></tr>";
echo "<tr><td>レスポンス</td><td>" . ($response === 'ok' ? "<span style='color:green'>$response</span>" : "<span style='color:red'>$response</span>") . "</td></tr>";
echo "<tr><td>cURLエラー番号</td><td>" . ($errno === 0 ? "<span style='color:green'>$errno (なし)</span>" : "<span style='color:red'>$errno</span>") . "</td></tr>";
echo "<tr><td>cURLエラーメッセージ</td><td>" . (empty($error) ? "<span style='color:green'>なし</span>" : "<span style='color:red'>$error</span>") . "</td></tr>";
echo "</table>";

// 結果判定
echo "<h3>4. 結果</h3>";
if ($httpCode === 200 && $response === 'ok') {
    echo "<p style='color:green; font-size:18px; font-weight:bold;'>✅ 成功！Slackを確認してください。</p>";
} else {
    echo "<p style='color:red; font-size:18px; font-weight:bold;'>❌ 失敗</p>";
    
    // エラー原因の推測
    echo "<h4>考えられる原因:</h4>";
    echo "<ul>";
    if ($errno === 60 || $errno === 77) {
        echo "<li>SSL証明書の検証エラー（このスクリプトでは無効化済み）</li>";
    }
    if ($errno === 6) {
        echo "<li>ホスト名の解決に失敗（インターネット接続を確認）</li>";
    }
    if ($errno === 7) {
        echo "<li>接続に失敗（ファイアウォールやプロキシを確認）</li>";
    }
    if ($errno === 28) {
        echo "<li>タイムアウト（ネットワーク遅延）</li>";
    }
    if ($httpCode === 400) {
        echo "<li>不正なリクエスト（ペイロード形式エラー）</li>";
    }
    if ($httpCode === 403) {
        echo "<li>アクセス拒否（Webhook URLが無効または期限切れ）</li>";
    }
    if ($httpCode === 404) {
        echo "<li>Webhook URLが存在しない</li>";
    }
    echo "</ul>";
    
    // 詳細ログ表示
    echo "<h4>詳細ログ:</h4>";
    echo "<pre style='background:#f0f0f0; padding:10px; overflow:auto;'>" . htmlspecialchars($verboseLog) . "</pre>";
}

echo "<hr>";
echo "<p><a href='index.php'>← 管理画面に戻る</a></p>";

