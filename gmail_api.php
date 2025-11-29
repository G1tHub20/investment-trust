<?php
/**
 * Gmail API連携クラス
 */

require_once __DIR__ . '/vendor/autoload.php';

class GmailNotifier {
    private $client;
    private $service;
    private $credentialsPath;
    private $tokenPath;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->credentialsPath = __DIR__ . '/credentials.json';
        $this->tokenPath = __DIR__ . '/token.json';
        
        $this->initializeClient();
    }
    
    /**
     * Google Clientを初期化
     */
    private function initializeClient() {
        $this->client = new Google_Client();
        $this->client->setApplicationName('Nikkei Stock Monitor');
        $this->client->setScopes(Google_Service_Gmail::GMAIL_SEND);
        $this->client->setAuthConfig($this->credentialsPath);
        $this->client->setAccessType('offline');
        $this->client->setPrompt('select_account consent');
        
        // トークンの読み込み
        if (file_exists($this->tokenPath)) {
            $accessToken = json_decode(file_get_contents($this->tokenPath), true);
            $this->client->setAccessToken($accessToken);
            
            // トークンの更新が必要な場合
            if ($this->client->isAccessTokenExpired()) {
                if ($this->client->getRefreshToken()) {
                    $this->client->fetchAccessTokenWithRefreshToken($this->client->getRefreshToken());
                    
                    // 新しいトークンを保存
                    if (!file_put_contents($this->tokenPath, json_encode($this->client->getAccessToken()))) {
                        throw new Exception('トークンの保存に失敗しました');
                    }
                } else {
                    // 新規認証が必要
                    throw new Exception('Gmail API認証が必要です。authenticate.phpを実行してください。');
                }
            }
        }
        // token.jsonが存在しない場合は、エラーを投げずに続行（初回認証用）
        
        $this->service = new Google_Service_Gmail($this->client);
    }
    
    /**
     * 認証URLを取得（初回認証用）
     * @return string
     */
    public function getAuthUrl() {
        return $this->client->createAuthUrl();
    }
    
    /**
     * 認証コードからトークンを取得（初回認証用）
     * @param string $authCode
     * @return bool
     */
    public function authenticate($authCode) {
        try {
            $accessToken = $this->client->fetchAccessTokenWithAuthCode($authCode);
            
            if (array_key_exists('error', $accessToken)) {
                throw new Exception(join(', ', $accessToken));
            }
            
            // トークンを保存
            if (!file_put_contents($this->tokenPath, json_encode($accessToken))) {
                throw new Exception('トークンの保存に失敗しました');
            }
            
            return true;
        } catch (Exception $e) {
            error_log('Authentication error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * メールを送信
     * @param string $to 送信先メールアドレス
     * @param string $subject 件名
     * @param string $body 本文（HTML可）
     * @return bool
     */
    public function sendEmail($to, $subject, $body) {
        try {
            $message = $this->createMessage($to, $subject, $body);
            $this->service->users_messages->send('me', $message);
            return true;
        } catch (Exception $e) {
            error_log('Email sending error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * メールメッセージを作成
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return Google_Service_Gmail_Message
     */
    private function createMessage($to, $subject, $body) {
        $message = new Google_Service_Gmail_Message();
        
        $rawMessage = $this->createRawMessage($to, $subject, $body);
        $message->setRaw($rawMessage);
        
        return $message;
    }
    
    /**
     * RAWメッセージを作成
     * @param string $to
     * @param string $subject
     * @param string $body
     * @return string
     */
    private function createRawMessage($to, $subject, $body) {
        $boundary = uniqid(rand(), true);
        
        $rawMessage = "To: {$to}\r\n";
        $rawMessage .= "Subject: =?UTF-8?B?" . base64_encode($subject) . "?=\r\n";
        $rawMessage .= "MIME-Version: 1.0\r\n";
        $rawMessage .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $rawMessage .= "\r\n";
        
        // テキストパート
        $rawMessage .= "--{$boundary}\r\n";
        $rawMessage .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $rawMessage .= "Content-Transfer-Encoding: base64\r\n";
        $rawMessage .= "\r\n";
        $rawMessage .= base64_encode(strip_tags($body)) . "\r\n";
        
        // HTMLパート
        $rawMessage .= "--{$boundary}\r\n";
        $rawMessage .= "Content-Type: text/html; charset=UTF-8\r\n";
        $rawMessage .= "Content-Transfer-Encoding: base64\r\n";
        $rawMessage .= "\r\n";
        $rawMessage .= base64_encode($body) . "\r\n";
        
        $rawMessage .= "--{$boundary}--\r\n";
        
        return base64_encode($rawMessage);
    }
    
    /**
     * 買いシグナル通知を送信
     * @param string $to
     * @param float $currentPrice
     * @param float $buySignalPrice
     * @param float $basePrice
     * @return bool
     */
    public function sendBuySignal($to, $currentPrice, $buySignalPrice, $basePrice) {
        $subject = '【買いシグナル】日経平均株価が下落しました';
        
        $body = "
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #4CAF50; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 0 0 5px 5px; }
                .price { font-size: 24px; font-weight: bold; color: #4CAF50; margin: 10px 0; }
                .info { margin: 10px 0; padding: 10px; background-color: white; border-left: 4px solid #4CAF50; }
                .footer { margin-top: 20px; font-size: 12px; color: #666; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🔔 買いシグナル発生</h2>
                </div>
                <div class='content'>
                    <p>日経平均株価が買いシグナル価格を下回りました。</p>
                    
                    <div class='info'>
                        <strong>現在価格:</strong>
                        <div class='price'>¥" . number_format($currentPrice, 2) . "</div>
                    </div>
                    
                    <div class='info'>
                        <strong>基準価格:</strong> ¥" . number_format($basePrice, 2) . "<br>
                        <strong>買いシグナル価格:</strong> ¥" . number_format($buySignalPrice, 2) . "<br>
                        <strong>差額:</strong> ¥" . number_format($buySignalPrice - $currentPrice, 2) . "
                    </div>
                    
                    <p style='margin-top: 20px;'>
                        <strong>推奨アクション:</strong> 買いを検討してください。
                    </p>
                    
                    <p style='font-size: 12px; color: #666; margin-top: 20px;'>
                        ※ この通知は自動送信されています。投資判断は自己責任で行ってください。
                    </p>
                </div>
                <div class='footer'>
                    日経平均監視システム - " . date('Y年m月d日 H:i:s') . "
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($to, $subject, $body);
    }
    
    /**
     * 売りシグナル通知を送信
     * @param string $to
     * @param float $currentPrice
     * @param float $sellSignalPrice
     * @param float $basePrice
     * @return bool
     */
    public function sendSellSignal($to, $currentPrice, $sellSignalPrice, $basePrice) {
        $subject = '【売りシグナル】日経平均株価が上昇しました';
        
        $body = "
        <html>
        <head>
            <style>
                body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #FF5722; color: white; padding: 20px; text-align: center; border-radius: 5px 5px 0 0; }
                .content { background-color: #f9f9f9; padding: 20px; border: 1px solid #ddd; border-radius: 0 0 5px 5px; }
                .price { font-size: 24px; font-weight: bold; color: #FF5722; margin: 10px 0; }
                .info { margin: 10px 0; padding: 10px; background-color: white; border-left: 4px solid #FF5722; }
                .footer { margin-top: 20px; font-size: 12px; color: #666; text-align: center; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🔔 売りシグナル発生</h2>
                </div>
                <div class='content'>
                    <p>日経平均株価が売りシグナル価格を上回りました。</p>
                    
                    <div class='info'>
                        <strong>現在価格:</strong>
                        <div class='price'>¥" . number_format($currentPrice, 2) . "</div>
                    </div>
                    
                    <div class='info'>
                        <strong>基準価格:</strong> ¥" . number_format($basePrice, 2) . "<br>
                        <strong>売りシグナル価格:</strong> ¥" . number_format($sellSignalPrice, 2) . "<br>
                        <strong>差額:</strong> ¥" . number_format($currentPrice - $sellSignalPrice, 2) . "
                    </div>
                    
                    <p style='margin-top: 20px;'>
                        <strong>推奨アクション:</strong> 売りを検討してください。
                    </p>
                    
                    <p style='font-size: 12px; color: #666; margin-top: 20px;'>
                        ※ この通知は自動送信されています。投資判断は自己責任で行ってください。
                    </p>
                </div>
                <div class='footer'>
                    日経平均監視システム - " . date('Y年m月d日 H:i:s') . "
                </div>
            </div>
        </body>
        </html>
        ";
        
        return $this->sendEmail($to, $subject, $body);
    }
}

