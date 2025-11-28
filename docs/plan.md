# 日経平均監視通知システム構築プラン

## システム構成

### 1. データベース設計
- **settings テーブル**: 基準価格、買いシグナル価格、売りシグナル価格、メールアドレスを保存
- **price_history テーブル**: 取得した株価履歴を記録
- **notifications テーブル**: 送信した通知履歴を記録

### 2. ファイル構成
```
/investment-trust/
  ├── index.php              # Web UI（設定画面・履歴表示）
  ├── config.php             # DB接続設定
  ├── check_price.php        # 価格チェック・通知実行スクリプト（cron用）
  ├── gmail_api.php          # Gmail API連携クラス
  ├── scraper.php            # Investing.comスクレイピング処理
  ├── database.sql           # DB初期化SQL
  ├── credentials.json       # Gmail API認証情報（ユーザーが配置）
  ├── token.json             # Gmail APIトークン（自動生成）
  └── css/style.css          # スタイルシート（更新）
```

### 3. 主要機能

#### Web UI（index.php）
- 基準価格、買いシグナル価格、売りシグナル価格の設定フォーム
- 通知先メールアドレスの設定
- 現在の日経平均株価表示
- 過去の通知履歴表示
- 価格履歴グラフ（簡易版）

#### 価格監視スクリプト（check_price.php）
1. Investing.comから日経平均株価を取得（scraper.php使用）
2. DBから設定値を取得
3. 買い/売りシグナル判定
4. 条件を満たせばGmail API経由でメール送信
5. 結果をDBに記録

#### スクレイピング（scraper.php）
- `https://jp.investing.com/indices/japan-ni225-historical-data` から最新価格を取得
- cURL + DOMDocument/XPathで解析
- エラーハンドリング実装

#### Gmail API連携（gmail_api.php）
- Google API Client Libraryを使用
- OAuth2.0認証
- メール送信機能

### 4. 実装手順

1. **データベースセットアップ**
   - database.sqlを作成・実行
   - config.phpでDB接続情報設定

2. **Gmail API設定**
   - Google Cloud Consoleでプロジェクト作成
   - Gmail API有効化
   - OAuth 2.0認証情報作成
   - credentials.jsonダウンロード

3. **Composerでライブラリインストール**
   - `google/apiclient` パッケージ

4. **スクレイピング機能実装**
   - scraper.phpでInvesting.comから価格取得

5. **Web UI実装**
   - 設定フォーム
   - 履歴表示
   - レスポンシブデザイン

6. **監視スクリプト実装**
   - check_price.phpで判定・通知ロジック

7. **cronジョブ設定**
   - 1日2回（例: 9:00, 15:00）実行設定

### 5. セキュリティ考慮事項
- config.phpに.htaccessでアクセス制限
- credentials.json/token.jsonは公開ディレクトリ外に配置推奨
- SQL injection対策（プリペアドステートメント使用）
- XSS対策（htmlspecialchars使用）

### 6. 通知ロジック
毎回条件を満たす度に通知（設定4-bの要望通り）:
- 現在価格 < 買いシグナル価格 → 「買いシグナル」メール送信
- 現在価格 > 売りシグナル価格 → 「売りシグナル」メール送信
- 通知履歴はDBに記録して後で確認可能

## 実装タスク

### ✅ 完了したタスク

1. **MySQLデータベースとテーブル作成**
   - settings, price_history, notifications テーブル
   - database.sql作成

2. **config.phpでDB接続設定を実装**
   - PDO接続
   - 共通関数（getSettings, updateSettings, savePriceHistory等）

3. **scraper.phpでInvesting.comスクレイピング機能実装**
   - NikkeiScraperクラス
   - cURL + DOMDocument/XPath
   - エラーハンドリング

4. **gmail_api.phpでGmail API連携クラス実装**
   - GmailNotifierクラス
   - OAuth2.0認証
   - 買い・売りシグナルメール送信機能
   - composer.json作成

5. **check_price.phpで価格監視・通知ロジック実装**
   - 価格取得
   - シグナル判定
   - メール通知
   - ログ出力

6. **index.phpでWeb UI実装**
   - 設定フォーム
   - 現在の株価表示
   - 通知履歴表示
   - 価格履歴表示
   - システム情報

7. **css/style.cssでモダンなUIスタイリング**
   - レスポンシブデザイン
   - グラデーション背景
   - カードレイアウト
   - アニメーション効果

8. **README.md作成**
   - Gmail API設定手順
   - cron設定方法
   - トラブルシューティング
   - 使い方ガイド

## 技術スタック

- **バックエンド**: PHP 7.4+
- **データベース**: MySQL 5.7+
- **ライブラリ**: 
  - google/apiclient (Gmail API)
  - Composer (依存関係管理)
- **フロントエンド**: HTML5, CSS3, レスポンシブデザイン
- **スクレイピング**: cURL, DOMDocument, XPath
- **自動化**: cron

## システム要件

- PHP 7.4以上
- MySQL 5.7以上
- Composer
- Webサーバー（Apache/Nginx）
- cronジョブ設定権限
- Googleアカウント（Gmail API用）

## セットアップ概要

1. Composerでライブラリインストール
2. データベース作成・テーブル初期化
3. config.phpで接続情報設定
4. Google Cloud ConsoleでGmail API設定
5. credentials.json配置
6. authenticate.phpで認証
7. index.phpで初期設定
8. cronジョブ設定

## 運用

- **監視頻度**: 1日2回（9:00, 15:00推奨）
- **通知方法**: Gmail API経由でメール送信
- **履歴保存**: すべての価格チェックと通知をDB記録
- **管理**: Webインターフェースで設定変更・履歴確認

## 今後の拡張案

- 複数の株価指標に対応
- LINEやSlack通知の追加
- より高度なチャート表示
- バックテスト機能
- 複数ユーザー対応
- モバイルアプリ化

