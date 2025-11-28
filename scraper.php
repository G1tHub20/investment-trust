<?php
/**
 * Investing.comから日経平均株価をスクレイピング
 */

class NikkeiScraper {
    private $url = 'https://jp.investing.com/indices/japan-ni225';
    private $userAgent = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';
    
    /**
     * 日経平均株価を取得
     * @return array ['price' => float, 'change' => float, 'change_percent' => float] or null
     */
    public function getCurrentPrice() {
        try {
            $html = $this->fetchPage();
            if (!$html) {
                throw new Exception('ページの取得に失敗しました');
            }
            
            return $this->parsePrice($html);
        } catch (Exception $e) {
            error_log('Scraping error: ' . $e->getMessage());
            return null;
        }
    }
    
    /**
     * ページを取得
     * @return string|false
     */
    private function fetchPage() {
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $this->url,
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
        
        curl_close($ch);
        
        if ($httpCode !== 200) {
            error_log("HTTP Error: $httpCode");
            return false;
        }
        
        return $html;
    }
    
    /**
     * HTMLから価格情報を抽出
     * @param string $html
     * @return array|null
     */
    private function parsePrice($html) {
        // DOMDocumentで解析
        $dom = new DOMDocument();
        @$dom->loadHTML($html);
        $xpath = new DOMXPath($dom);
        
        // 価格を取得（複数のパターンを試行）
        $price = $this->extractPrice($xpath);
        if ($price === null) {
            throw new Exception('価格の抽出に失敗しました');
        }
        
        // 変動値を取得
        $change = $this->extractChange($xpath);
        
        // 変動率を取得
        $changePercent = $this->extractChangePercent($xpath);
        
        return [
            'price' => $price,
            'change' => $change,
            'change_percent' => $changePercent
        ];
    }
    
    /**
     * 価格を抽出
     * @param DOMXPath $xpath
     * @return float|null
     */
    private function extractPrice($xpath) {
        // パターン1: data-test属性を使用
        $nodes = $xpath->query("//*[@data-test='instrument-price-last']");
        if ($nodes->length > 0) {
            $priceText = trim($nodes->item(0)->nodeValue);
            return $this->parseNumber($priceText);
        }
        
        // パターン2: クラス名を使用
        $nodes = $xpath->query("//span[contains(@class, 'text-2xl') or contains(@class, 'instrument-price_last')]");
        if ($nodes->length > 0) {
            $priceText = trim($nodes->item(0)->nodeValue);
            return $this->parseNumber($priceText);
        }
        
        // パターン3: より広範囲な検索
        $nodes = $xpath->query("//span[contains(@class, 'last-price-value')]");
        if ($nodes->length > 0) {
            $priceText = trim($nodes->item(0)->nodeValue);
            return $this->parseNumber($priceText);
        }
        
        return null;
    }
    
    /**
     * 変動値を抽出
     * @param DOMXPath $xpath
     * @return float|null
     */
    private function extractChange($xpath) {
        $nodes = $xpath->query("//*[contains(@class, 'instrument-price_change') or @data-test='instrument-price-change']");
        if ($nodes->length > 0) {
            $changeText = trim($nodes->item(0)->nodeValue);
            return $this->parseNumber($changeText);
        }
        return null;
    }
    
    /**
     * 変動率を抽出
     * @param DOMXPath $xpath
     * @return float|null
     */
    private function extractChangePercent($xpath) {
        $nodes = $xpath->query("//*[contains(@class, 'instrument-price_change-percent') or @data-test='instrument-price-change-percent']");
        if ($nodes->length > 0) {
            $percentText = trim($nodes->item(0)->nodeValue);
            return $this->parseNumber($percentText);
        }
        return null;
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
     * デバッグ用：HTMLを保存
     * @param string $html
     */
    public function saveDebugHtml($html) {
        file_put_contents('debug_investing.html', $html);
    }
}

// スタンドアロン実行時のテスト
if (php_sapi_name() === 'cli' && basename(__FILE__) === basename($_SERVER['PHP_SELF'])) {
    echo "日経平均株価取得テスト\n";
    echo str_repeat('-', 50) . "\n";
    
    $scraper = new NikkeiScraper();
    $result = $scraper->getCurrentPrice();
    
    if ($result) {
        echo "価格: ¥" . number_format($result['price'], 2) . "\n";
        echo "変動: " . ($result['change'] !== null ? number_format($result['change'], 2) : 'N/A') . "\n";
        echo "変動率: " . ($result['change_percent'] !== null ? $result['change_percent'] . '%' : 'N/A') . "\n";
    } else {
        echo "エラー: 価格の取得に失敗しました\n";
    }
}

