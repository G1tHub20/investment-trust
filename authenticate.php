<?php
/**
 * Gmail API 初回認証スクリプト
 * このスクリプトをブラウザで実行して、Gmail APIの認証を行います
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/gmail_api.php';

// セッション開始
session_start();

try {
    $notifier = new GmailNotifier();
    
    // 認証コードが送信された場合
    if (isset($_GET['code'])) {
        $authCode = $_GET['code'];
        
        if ($notifier->authenticate($authCode)) {
            echo "<h1>✅ 認証成功</h1>";
            echo "<p>Gmail APIの認証が完了しました。</p>";
            echo "<p><a href='index.php'>管理画面に戻る</a></p>";
        } else {
            echo "<h1>❌ 認証失敗</h1>";
            echo "<p>認証に失敗しました。もう一度お試しください。</p>";
            echo "<p><a href='authenticate.php'>再試行</a></p>";
        }
    } else {
        // 認証URLを表示
        $authUrl = $notifier->getAuthUrl();
        
        echo "<!DOCTYPE html>";
        echo "<html lang='ja'>";
        echo "<head>";
        echo "<meta charset='UTF-8'>";
        echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
        echo "<title>Gmail API 認証</title>";
        echo "<style>";
        echo "body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }";
        echo "h1 { color: #333; }";
        echo ".button { display: inline-block; padding: 15px 30px; background-color: #4285f4; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; }";
        echo ".button:hover { background-color: #357ae8; }";
        echo ".info { background-color: #f0f0f0; padding: 15px; border-radius: 5px; margin: 20px 0; }";
        echo "</style>";
        echo "</head>";
        echo "<body>";
        echo "<h1>Gmail API 認証</h1>";
        echo "<div class='info'>";
        echo "<p>このシステムでメール通知を送信するには、Gmail APIの認証が必要です。</p>";
        echo "<p>以下のボタンをクリックして、Googleアカウントでログインし、アクセスを許可してください。</p>";
        echo "</div>";
        echo "<a href='{$authUrl}' class='button'>Googleアカウントで認証</a>";
        echo "</body>";
        echo "</html>";
    }
} catch (Exception $e) {
    echo "<h1>エラー</h1>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<div class='info'>";
    echo "<p><strong>credentials.jsonが見つからない場合：</strong></p>";
    echo "<ol>";
    echo "<li>Google Cloud Consoleでプロジェクトを作成</li>";
    echo "<li>Gmail APIを有効化</li>";
    echo "<li>OAuth 2.0クライアントIDを作成（デスクトップアプリまたはWebアプリ）</li>";
    echo "<li>credentials.jsonをダウンロードして、このディレクトリに配置</li>";
    echo "</ol>";
    echo "<p>詳細は<a href='README.md'>README.md</a>を参照してください。</p>";
    echo "</div>";
}

