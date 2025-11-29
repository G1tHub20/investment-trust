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
    
    // 認証コードが送信された場合（GET または POST）
    $authCode = $_POST['auth_code'] ?? $_GET['code'] ?? null;
    
    if ($authCode) {
        if ($notifier->authenticate($authCode)) {
            echo "<!DOCTYPE html>";
            echo "<html lang='ja'>";
            echo "<head>";
            echo "<meta charset='UTF-8'>";
            echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
            echo "<title>認証成功</title>";
            echo "<style>";
            echo "body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }";
            echo "h1 { color: #4CAF50; }";
            echo ".button { display: inline-block; padding: 15px 30px; background-color: #4285f4; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 20px; }";
            echo ".button:hover { background-color: #357ae8; }";
            echo ".success { background-color: #d4edda; padding: 15px; border-radius: 5px; border-left: 4px solid #4CAF50; }";
            echo "</style>";
            echo "</head>";
            echo "<body>";
            echo "<h1>✅ 認証成功</h1>";
            echo "<div class='success'>";
            echo "<p>Gmail APIの認証が完了しました。</p>";
            echo "<p>メール通知機能が有効になりました。</p>";
            echo "</div>";
            echo "<a href='index.php' class='button'>管理画面に戻る</a>";
            echo "</body>";
            echo "</html>";
        } else {
            echo "<!DOCTYPE html>";
            echo "<html lang='ja'>";
            echo "<head>";
            echo "<meta charset='UTF-8'>";
            echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
            echo "<title>認証失敗</title>";
            echo "<style>";
            echo "body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }";
            echo "h1 { color: #f44336; }";
            echo ".button { display: inline-block; padding: 15px 30px; background-color: #4285f4; color: white; text-decoration: none; border-radius: 5px; font-weight: bold; margin-top: 20px; }";
            echo ".button:hover { background-color: #357ae8; }";
            echo ".error { background-color: #f8d7da; padding: 15px; border-radius: 5px; border-left: 4px solid #f44336; }";
            echo "</style>";
            echo "</head>";
            echo "<body>";
            echo "<h1>❌ 認証失敗</h1>";
            echo "<div class='error'>";
            echo "<p>認証に失敗しました。認証コードが間違っているか、期限切れの可能性があります。</p>";
            echo "</div>";
            echo "<a href='authenticate.php' class='button'>再試行</a>";
            echo "</body>";
            echo "</html>";
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
        echo ".warning { background-color: #fff3cd; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #ffc107; }";
        echo "input[type='text'] { width: 100%; padding: 10px; margin: 10px 0; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }";
        echo "input[type='submit'] { padding: 15px 30px; background-color: #4CAF50; color: white; border: none; border-radius: 5px; font-weight: bold; cursor: pointer; }";
        echo "input[type='submit']:hover { background-color: #45a049; }";
        echo "ol { line-height: 1.8; }";
        echo "</style>";
        echo "</head>";
        echo "<body>";
        echo "<h1>Gmail API 認証</h1>";
        echo "<div class='info'>";
        echo "<p>このシステムでメール通知を送信するには、Gmail APIの認証が必要です。</p>";
        echo "</div>";
        
        echo "<div class='warning'>";
        echo "<h3>📝 認証手順（デスクトップアプリ）</h3>";
        echo "<ol>";
        echo "<li>下の「Googleアカウントで認証」ボタンをクリック</li>";
        echo "<li>Googleアカウントでログインし、アクセスを許可</li>";
        echo "<li>リダイレクトされたURL（<code>http://localhost/?code=...</code>）から<strong>code=</strong>以降の文字列をコピー</li>";
        echo "<li>下のフォームに貼り付けて「認証を完了」をクリック</li>";
        echo "</ol>";
        echo "</div>";
        
        echo "<a href='{$authUrl}' class='button' target='_blank'>Googleアカウントで認証</a>";
        
        echo "<form method='POST' action='authenticate.php' style='margin-top: 30px;'>";
        echo "<h3>認証コードを入力</h3>";
        echo "<input type='text' name='auth_code' placeholder='4/0Ab32j90pjcn-AY9Ca9NYzXHvHEVe9Km...' required>";
        echo "<br>";
        echo "<input type='submit' value='認証を完了'>";
        echo "</form>";
        
        echo "</body>";
        echo "</html>";
    }
} catch (Exception $e) {
    echo "<!DOCTYPE html>";
    echo "<html lang='ja'>";
    echo "<head>";
    echo "<meta charset='UTF-8'>";
    echo "<meta name='viewport' content='width=device-width, initial-scale=1.0'>";
    echo "<title>エラー</title>";
    echo "<style>";
    echo "body { font-family: Arial, sans-serif; max-width: 600px; margin: 50px auto; padding: 20px; }";
    echo "h1 { color: #f44336; }";
    echo ".info { background-color: #f0f0f0; padding: 15px; border-radius: 5px; margin: 20px 0; }";
    echo "</style>";
    echo "</head>";
    echo "<body>";
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
    echo "</body>";
    echo "</html>";
}

