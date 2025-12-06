<?php
/**
 * Slack Webhook通知クラス
 */

class SlackNotifier {
    private $webhookUrl;
    private $timeout = 10;
    
    /**
     * コンストラクタ
     * @param string $webhookUrl Slack Incoming Webhook URL
     */
    public function __construct($webhookUrl) {
        $this->webhookUrl = $webhookUrl;
    }
    
    /**
     * Slackにメッセージを送信
     * @param array $payload Slack Block Kit形式のペイロード
     * @return bool
     */
    private function send(array $payload): bool {
        $ch = curl_init($this->webhookUrl);
        
        curl_setopt_array($ch, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json; charset=utf-8'
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        
        if ($httpCode !== 200 || $response !== 'ok') {
            error_log("Slack notification failed: HTTP $httpCode, Response: $response, Error: $error");
            return false;
        }
        
        return true;
    }
    
    /**
     * 買いシグナル通知を送信
     * @param float $currentPrice 現在価格
     * @param float $buySignalPrice 買いシグナル価格
     * @param float $basePrice 基準価格
     * @return bool
     */
    public function sendBuySignal($currentPrice, $buySignalPrice, $basePrice): bool {
        $difference = $buySignalPrice - $currentPrice;
        
        $payload = [
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => '🟢 買いシグナル発生',
                        'emoji' => true
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => '日経平均株価が買いシグナル価格を下回りました。'
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*現在価格:*\n¥" . number_format($currentPrice, 0)
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*シグナル価格:*\n¥" . number_format($buySignalPrice, 0)
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*基準価格:*\n¥" . number_format($basePrice, 0)
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*差額:*\n¥" . number_format($difference, 0)
                        ]
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => '💡 *推奨アクション:* 買いを検討してください。'
                    ]
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => '日経平均監視システム | ' . date('Y/m/d H:i:s') . ' | ※投資判断は自己責任で'
                        ]
                    ]
                ]
            ]
        ];
        
        return $this->send($payload);
    }
    
    /**
     * 売りシグナル通知を送信
     * @param float $currentPrice 現在価格
     * @param float $sellSignalPrice 売りシグナル価格
     * @param float $basePrice 基準価格
     * @return bool
     */
    public function sendSellSignal($currentPrice, $sellSignalPrice, $basePrice): bool {
        $difference = $currentPrice - $sellSignalPrice;
        
        $payload = [
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => '🔴 売りシグナル発生',
                        'emoji' => true
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => '日経平均株価が売りシグナル価格を上回りました。'
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*現在価格:*\n¥" . number_format($currentPrice, 0)
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*シグナル価格:*\n¥" . number_format($sellSignalPrice, 0)
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*基準価格:*\n¥" . number_format($basePrice, 0)
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*差額:*\n¥" . number_format($difference, 0)
                        ]
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => '💡 *推奨アクション:* 売りを検討してください。'
                    ]
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => '日経平均監視システム | ' . date('Y/m/d H:i:s') . ' | ※投資判断は自己責任で'
                        ]
                    ]
                ]
            ]
        ];
        
        return $this->send($payload);
    }
    
    /**
     * 大幅下落通知を送信
     * @param float $currentPrice 現在価格
     * @param float $yesterdayClose 前日終値
     * @param float $dropAmount 下落額
     * @return bool
     */
    public function sendLargeDropAlert($currentPrice, $yesterdayClose, $dropAmount): bool {
        $dropPercent = ($dropAmount / $yesterdayClose) * 100;
        
        $payload = [
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => '⚠️ 大幅下落警告',
                        'emoji' => true
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => '日経平均株価が前日比で大幅に下落しました。'
                    ]
                ],
                [
                    'type' => 'section',
                    'fields' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => "*現在価格:*\n¥" . number_format($currentPrice, 0)
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*前日終値:*\n¥" . number_format($yesterdayClose, 0)
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*下落額:*\n-¥" . number_format($dropAmount, 0)
                        ],
                        [
                            'type' => 'mrkdwn',
                            'text' => "*下落率:*\n-" . number_format($dropPercent, 2) . "%"
                        ]
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => '⚡ *注意:* 市場が大きく動いています。冷静に状況を判断してください。'
                    ]
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => '日経平均監視システム | ' . date('Y/m/d H:i:s') . ' | ※投資判断は自己責任で'
                        ]
                    ]
                ]
            ]
        ];
        
        return $this->send($payload);
    }
    
    /**
     * テスト通知を送信
     * @return bool
     */
    public function sendTest(): bool {
        $payload = [
            'blocks' => [
                [
                    'type' => 'header',
                    'text' => [
                        'type' => 'plain_text',
                        'text' => '✅ テスト通知',
                        'emoji' => true
                    ]
                ],
                [
                    'type' => 'section',
                    'text' => [
                        'type' => 'mrkdwn',
                        'text' => 'Slack通知の設定が正常に完了しました。'
                    ]
                ],
                [
                    'type' => 'context',
                    'elements' => [
                        [
                            'type' => 'mrkdwn',
                            'text' => '日経平均監視システム | ' . date('Y/m/d H:i:s')
                        ]
                    ]
                ]
            ]
        ];
        
        return $this->send($payload);
    }
}

