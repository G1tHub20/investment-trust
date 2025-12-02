<?php
/**
 * 日経平均株価監視・通知スクリプト
 * cronで定期実行するためのスクリプト
 */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/scraper.php';
require_once __DIR__ . '/gmail_api.php';

// ログ出力関数
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logMessage = "[{$timestamp}] {$message}\n";
    echo $logMessage;
    error_log($logMessage);
}

// メイン処理
function main($skipSignal = false) {
    logMessage("=== 日経平均株価監視開始 ===");
    
    try {
        // 設定を取得
        $settings = getSettings();
        if (!$settings) {
            logMessage("エラー: 設定が見つかりません");
            return false;
        }
        
        logMessage("設定読み込み完了");
        logMessage("基準価格: ¥" . number_format($settings['base_price'], 0));
        logMessage("買いシグナル: ¥" . number_format($settings['buy_signal_price'], 0));
        logMessage("売りシグナル: ¥" . number_format($settings['sell_signal_price'], 0));
        
        // 株価を取得
        $scraper = new NikkeiScraper();
        $priceData = $scraper->getCurrentPrice();
        
        if (!$priceData || !isset($priceData['close'])) {
            logMessage("エラー: 株価の取得に失敗しました");
            return false;
        }
        
        $currentClose = $priceData['close'];
        $currentPrice = $currentClose; // 互換性のため
        $priceDate = $priceData['date'] ?? null; // スクレイピングで取得した日付
        
        logMessage("現在終値: ¥" . number_format($currentClose, 0));
        if ($priceDate) {
            logMessage("取引日: " . $priceDate);
        }
        
        // 前日の終値を取得（保存する前に取得）
        $yesterdayClose = getYesterdayClose();
        
        // 変動率を計算: (today.close - yesterday.close) / yesterday.close
        $priceChangeRate = null;
        if ($yesterdayClose && $yesterdayClose > 0) {
            $priceChangeRate = ($currentClose - $yesterdayClose) / $yesterdayClose;
            logMessage("前日終値: ¥" . number_format($yesterdayClose, 0));
            logMessage("変動率: " . number_format($priceChangeRate * 100, 2) . "%");
        } else {
            logMessage("前日の終値が見つかりません。初回実行の可能性があります。");
        }
        
        // 価格履歴を保存（同日の場合は更新）
        $saved = savePriceHistory(
            $priceDate,  // 日付（必須）
            $priceData['close'],
            $priceData['open'],
            $priceData['high'],
            $priceData['low'],
            $priceChangeRate
        );
        
        if ($saved) {
            logMessage("価格履歴を保存しました（同日の場合は更新）");
        } else {
            logMessage("エラー: 価格履歴の保存に失敗しました");
        }
        
        // 大幅下落チェック（前日比-1500円以上）
        $dropThreshold = 1500;
        if ($yesterdayClose && ($yesterdayClose - $currentPrice) >= $dropThreshold) {
            $dropAmount = $yesterdayClose - $currentPrice;
            logMessage("⚠️ 大幅下落検出！");
            logMessage("下落額: ¥" . number_format($dropAmount, 0) . " (前日比)");
            
            // メール送信
            $emailSent = sendLargeDropNotification(
                $settings['email_address'],
                $currentPrice,
                $yesterdayClose,
                $dropAmount
            );
            
            if ($emailSent) {
                logMessage("✅ 大幅下落通知メールを送信しました");
            } else {
                logMessage("❌ 大幅下落通知メールの送信に失敗しました");
            }
        }
        
        // シグナル判定（skipSignalがtrueの場合はスキップ）
        if (!$skipSignal) {
            $signalTriggered = false;
            
            // 買いシグナルチェック
            if ($currentPrice < $settings['buy_signal_price']) {
                logMessage("🔔 買いシグナル発生！");
                logMessage("現在価格 (¥" . number_format($currentPrice, 0) . ") < 買いシグナル価格 (¥" . number_format($settings['buy_signal_price'], 0) . ")");
                
                // メール送信
                $emailSent = sendBuyNotification(
                    $settings['email_address'],
                    $currentPrice,
                    $settings['buy_signal_price'],
                    $settings['base_price']
                );
                
                if ($emailSent) {
                    logMessage("✅ 買いシグナル通知メールを送信しました");
                } else {
                    logMessage("❌ 買いシグナル通知メールの送信に失敗しました");
                }
                
                $signalTriggered = true;
            }
            
            // 売りシグナルチェック
            if ($currentPrice > $settings['sell_signal_price']) {
                logMessage("🔔 売りシグナル発生！");
                logMessage("現在価格 (¥" . number_format($currentPrice, 0) . ") > 売りシグナル価格 (¥" . number_format($settings['sell_signal_price'], 0) . ")");
                
                // メール送信
                $emailSent = sendSellNotification(
                    $settings['email_address'],
                    $currentPrice,
                    $settings['sell_signal_price'],
                    $settings['base_price']
                );
                
                if ($emailSent) {
                    logMessage("✅ 売りシグナル通知メールを送信しました");
                } else {
                    logMessage("❌ 売りシグナル通知メールの送信に失敗しました");
                }
                
                $signalTriggered = true;
            }
            
            if (!$signalTriggered) {
                logMessage("シグナルなし（正常範囲内）");
                logMessage("買いシグナル価格 (¥" . number_format($settings['buy_signal_price'], 0) . ") < 現在価格 (¥" . number_format($currentPrice, 0) . ") < 売りシグナル価格 (¥" . number_format($settings['sell_signal_price'], 0) . ")");
            }
        } else {
            logMessage("シグナル判定をスキップしました（--no-signal オプション）");
        }
        
        logMessage("=== 監視完了 ===");
        return true;
        
    } catch (Exception $e) {
        logMessage("エラー: " . $e->getMessage());
        logMessage("スタックトレース: " . $e->getTraceAsString());
        return false;
    }
}

/**
 * 買いシグナル通知を送信
 */
function sendBuyNotification($emailAddress, $currentPrice, $buySignalPrice, $basePrice) {
    try {
        $notifier = new GmailNotifier();
        $result = $notifier->sendBuySignal($emailAddress, $currentPrice, $buySignalPrice, $basePrice);
        
        // 通知履歴を保存
        saveNotification(
            'buy',
            $currentPrice,
            $buySignalPrice,
            $result,
            $result ? null : 'メール送信に失敗しました'
        );
        
        return $result;
    } catch (Exception $e) {
        logMessage("買いシグナル通知エラー: " . $e->getMessage());
        
        // エラーも履歴に保存
        saveNotification(
            'buy',
            $currentPrice,
            $buySignalPrice,
            false,
            $e->getMessage()
        );
        
        return false;
    }
}

/**
 * 売りシグナル通知を送信
 */
function sendSellNotification($emailAddress, $currentPrice, $sellSignalPrice, $basePrice) {
    try {
        $notifier = new GmailNotifier();
        $result = $notifier->sendSellSignal($emailAddress, $currentPrice, $sellSignalPrice, $basePrice);
        
        // 通知履歴を保存
        saveNotification(
            'sell',
            $currentPrice,
            $sellSignalPrice,
            $result,
            $result ? null : 'メール送信に失敗しました'
        );
        
        return $result;
    } catch (Exception $e) {
        logMessage("売りシグナル通知エラー: " . $e->getMessage());
        
        // エラーも履歴に保存
        saveNotification(
            'sell',
            $currentPrice,
            $sellSignalPrice,
            false,
            $e->getMessage()
        );
        
        return false;
    }
}

/**
 * 大幅下落通知を送信
 */
function sendLargeDropNotification($emailAddress, $currentPrice, $yesterdayClose, $dropAmount) {
    try {
        $notifier = new GmailNotifier();
        $result = $notifier->sendLargeDropAlert($emailAddress, $currentPrice, $yesterdayClose, $dropAmount);
        
        // 通知履歴を保存
        saveNotification(
            'large_drop',
            $currentPrice,
            $yesterdayClose,
            $result,
            $result ? null : 'メール送信に失敗しました'
        );
        
        return $result;
    } catch (Exception $e) {
        logMessage("大幅下落通知エラー: " . $e->getMessage());
        
        // エラーも履歴に保存
        saveNotification(
            'large_drop',
            $currentPrice,
            $yesterdayClose,
            false,
            $e->getMessage()
        );
        
        return false;
    }
}

// CLI実行時のみ実行
if (php_sapi_name() === 'cli') {
    $skipSignal = ($argv[1] ?? '') === '--no-signal';
    $result = main($skipSignal);
    exit($result ? 0 : 1);
}

