<?php
/**
 * Investing.comから日経平均株価をスクレイピング
 */

class NikkeiScraper {
    private $url = 'https://jp.investing.com/indices/japan-ni225-historical-data';
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    /**
     * 日経平均株価を取得
     * @return array ['date' => string, 'close' => float, 'open' => float, 'high' => float, 'low' => float, 'change' => float, 'change_percent' => float] or null
     */
    public function getCurrentPrice() {
        try {
            // 履歴データページから最新のOHLCと変動を取得
            $html = $this->fetchPage($this->url);
            if (!$html) {
                throw new Exception('ページの取得に失敗しました');
            }
            
            $data = $this->parseHistoricalData($html);
            if (!$data) {
                throw new Exception('データの解析に失敗しました');
            }
            
            return $data;
        } catch (Exception $e) {
            error_log('Scraping error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * ページを取得
     * @param string $url
     * @return string|false
     */
    private function fetchPage($url) {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HTTPHEADER => [
                'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,*/*;q=0.8',
                'Accept-Language: ja,en-US;q=0.7,en;q=0.3',
                'Accept-Encoding: gzip, deflate',
                'Connection: keep-alive',
                'Upgrade-Insecure-Requests: 1'
            ],
            CURLOPT_ENCODING => 'gzip, deflate'
        ]);
        
        $html = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        
        if ($httpCode !== 200) {
            error_log("HTTP Error: $httpCode");
            return false;
        }
        
        return $html;
    }
    
    /**
     * 数値文字列をfloatに変換
     * @param string $text
     * @return float|null
     */
    private function parseNumber($text) {
        // カンマ、スペース、%記号を削除
        $text = str_replace([',', ' ', '%', '¥', '円'], '', $text);
        
        // 数値として解析
        if (is_numeric($text)) {
            return (float)$text;
        }
        
        // +や-が含まれる場合
        if (preg_match('/([+-]?[\d.]+)/', $text, $matches)) {
            return (float)$matches[1];
        }
        
        return null;
    }
    
    /**
     * 履歴データページから最新のOHLCと変動を取得
     * @param string $html
     * @return array|null
     */
    private function parseHistoricalData($html) {
        try {
            $dom = new DOMDocument();
            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();
            $xpath = new DOMXPath($dom);
            
            // 履歴データテーブルの最初の2行を取得（変動計算のため）
            $rows = $xpath->query("//table[@data-test='historical-data-table']//tbody/tr");
            
            if ($rows->length === 0) {
                // 別のパターン: クラス名を使用
                $rows = $xpath->query("//table[contains(@class, 'freeze-column-w-1')]//tbody/tr");
            }
            
            if ($rows->length === 0) {
                // より広範囲な検索
                $rows = $xpath->query("//table[contains(@class, 'historicalTbl')]//tbody/tr");
            }
            
            if ($rows->length > 0) {
                // 最新データ（1行目）を取得
                $row = $rows->item(0);
                $cells = $xpath->query(".//td", $row);
                
                if ($cells->length >= 5) {
                    // テーブルの列: 日付(0), 終値(1), 始値(2), 高値(3), 安値(4), 出来高(5), 変動率(6)
                    $dateText = trim($cells->item(0)->nodeValue);
                    $date = $this->parseDate($dateText);
                    
                    $close = $this->parseNumber($cells->item(1)->nodeValue);
                    $open = $this->parseNumber($cells->item(2)->nodeValue);
                    $high = $this->parseNumber($cells->item(3)->nodeValue);
                    $low = $this->parseNumber($cells->item(4)->nodeValue);
                    
                    // 値が妥当かチェック（日経平均は通常10,000〜100,000の範囲）
                    if ($close !== null && $close > 1000 && $close < 100000) {
                        $result = [
                            'date' => $date,
                            'close' => $close,
                            'open' => $open ?? $close,
                            'high' => $high ?? $close,
                            'low' => $low ?? $close,
                            'change' => null,
                            'change_percent' => null
                        ];
                        
                        // 前日データから変動を計算
                        if ($rows->length > 1) {
                            $prevRow = $rows->item(1);
                            $prevCells = $xpath->query(".//td", $prevRow);
                            if ($prevCells->length >= 2) {
                                $prevClose = $this->parseNumber($prevCells->item(1)->nodeValue);
                                if ($prevClose !== null && $prevClose > 0) {
                                    $result['change'] = $close - $prevClose;
                                    $result['change_percent'] = round(($result['change'] / $prevClose) * 100, 2);
                                }
                            }
                        }
                        
                        return $result;
                    }
                }
            }
            
            return null;
        } catch (Exception $e) {
            error_log('Historical data parsing error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * 日付文字列をY-m-d形式に変換
     * @param string $text
     * @return string|null
     */
    private function parseDate($text) {
        // Investing.comの日付形式の例:
        // - "2025年11月28日"
        // - "2025/11/28"
        // - "11月28日2025年"
        
        // 全角数字を半角に変換
        $text = mb_convert_kana($text, 'n', 'UTF-8');
        
        // パターン1: YYYY年MM月DD日
        if (preg_match('/(\d{4})年(\d{1,2})月(\d{1,2})日/', $text, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[1], $matches[2], $matches[3]);
        }
        
        // パターン2: YYYY/MM/DD または YYYY-MM-DD
        if (preg_match('/(\d{4})[\/\-](\d{1,2})[\/\-](\d{1,2})/', $text, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[1], $matches[2], $matches[3]);
        }
        
        // パターン3: MM月DD日YYYY年
        if (preg_match('/(\d{1,2})月(\d{1,2})日(\d{4})年/', $text, $matches)) {
            return sprintf('%04d-%02d-%02d', $matches[3], $matches[1], $matches[2]);
        }
        
        // パターン4: strtotime()で解析を試みる
        $timestamp = strtotime($text);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }
        
        // 解析できない場合は今日の日付を返す
        error_log("Date parsing failed for: {$text}, using today's date");
        return date('Y-m-d');
    }
    
    /**
     * デバッグ用：HTMLを保存
     * @param string $html
     */
    public function saveDebugHtml($html, $filename = 'debug_investing.html') {
        file_put_contents($filename, $html);
    }
}

// スタンドアロン実行時のテスト
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    echo "日経平均株価取得テスト\n";
    echo str_repeat('-', 50) . "\n";
    
    $scraper = new NikkeiScraper();
    $result = $scraper->getCurrentPrice();
    
    if ($result) {
        echo "終値: ¥" . number_format($result['close'], 2) . "\n";
        echo "始値: ¥" . number_format($result['open'], 2) . "\n";
        echo "高値: ¥" . number_format($result['high'], 2) . "\n";
        echo "低値: ¥" . number_format($result['low'], 2) . "\n";
        echo "変動: " . ($result['change'] !== null ? number_format($result['change'], 2) : 'N/A') . "\n";
        echo "変動率: " . ($result['change_percent'] !== null ? $result['change_percent'] . '%' : 'N/A') . "\n";
    } else {
        echo "エラー: 価格の取得に失敗しました\n";
    }
}

