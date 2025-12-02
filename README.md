# 日経平均監視通知システム

日経平均株価を自動監視し、設定した買い・売りシグナル価格に達したらGmail経由でメール通知するシステムです。

## 📋 機能

- 📊 日経平均株価のリアルタイム監視
- 🔔 買い・売りシグナルの自動通知（Gmail API経由）
- ⚠️ 大幅下落警告通知（前日比-1500円以上で自動通知）
- ⚙️ Webインターフェースで簡単設定
- 📈 価格履歴・通知履歴の記録と表示
- 🕐 1日3回の自動チェック（cron設定）

## 🚀 セットアップ手順

### 1. 必要な環境

- PHP 7.4以上
- MySQL 5.7以上
- Composer
- Webサーバー（Apache）
- cronジョブ設定権限

### 3. 環境変数ファイルの設定

`.env.example`をコピーして`.env`ファイルを作成し、環境に合わせて編集：

```bash
# Windowsの場合
copy .env.example .env

# Linux/Macの場合
cp .env.example .env
```

`.env`ファイルを編集：

```env
# データベース接続情報
DB_HOST=127.0.0.1
DB_PORT=3308
DB_NAME=investment_trust
DB_USER=root
DB_PASS=your_password_here

# タイムゾーン
TIMEZONE=Asia/Tokyo

# エラー表示（開発環境: 1, 本番環境: 0）
DISPLAY_ERRORS=1
ERROR_REPORTING=E_ALL
```

**重要**: `.env`ファイルには機密情報が含まれるため、Gitにコミットしないでください（`.gitignore`に追加済み）。

### 4. Composerでライブラリをインストール

```bash
composer install
```

### 5. データベースのセットアップ

#### 5.1 データベースを作成

```sql
CREATE DATABASE investment_trust CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 5.2 テーブルを作成

```bash
mysql -u root -p -P 3308 investment_trust < database.sql
```

または、phpMyAdminなどで`database.sql`をインポートしてください。

### 6. Gmail API設定（重要）

#### 6.1 Google Cloud Consoleでプロジェクトを作成

1. [Google Cloud Console](https://console.cloud.google.com/)にアクセス
2. 新しいプロジェクトを作成
3. プロジェクト名: 例）「Nikkei Stock Monitor」

#### 6.2 Gmail APIを有効化

1. 左メニューから「APIとサービス」→「ライブラリ」
2. 「Gmail API」を検索
3. 「有効にする」をクリック

#### 6.3 OAuth 2.0認証情報を作成

1. 「APIとサービス」→「認証情報」
2. 「認証情報を作成」→「OAuth クライアント ID」
3. アプリケーションの種類:
   - **デスクトップアプリ**（推奨）または **Webアプリケーション**
4. Webアプリケーションの場合、承認済みのリダイレクトURIを追加:
   ```
   http://localhost/investment-trust/authenticate.php
   https://yourdomain.com/investment-trust/authenticate.php
   ```
5. 「作成」をクリック
6. `credentials.json`をダウンロード

#### 6.4 credentials.jsonを配置

ダウンロードした`credentials.json`をプロジェクトのルートディレクトリに配置：

```
/investment-trust/
  ├── credentials.json  ← ここに配置
  ├── index.php
  ├── config.php
  └── ...
```

#### 6.5 Gmail APIを認証

1. ブラウザで`http://localhost/investment-trust/authenticate.php`にアクセス
2. 「Googleアカウントで認証」ボタンをクリック
3. Googleアカウントでログイン
4. アクセス許可を承認
5. 認証が完了すると`token.json`が自動生成されます

### 7. 初期設定

1. ブラウザで`http://localhost/investment-trust/index.php`にアクセス
2. 「監視設定」セクションで以下を設定：
   - **基準価格**: 例）50,000円
   - **買いシグナル価格**: 例）49,000円（基準価格より低く設定）
   - **売りシグナル価格**: 例）52,000円（基準価格より高く設定）
   - **通知先メールアドレス**: あなたのメールアドレス
3. 「設定を保存」をクリック

### 8. cronジョブの設定（自動実行）

1日3回、毎日、自動チェックを実行するようにcronを設定します。
- 10:30、14:30: シグナル判定あり（買い・売りシグナル通知）
- 18:30: シグナル判定なし（株価保存のみ、終値確定後のデータ取得用）

#### さくらVPS Ubuntu環境（推奨）

**自動セットアップスクリプトを使用する場合:**

```bash
cd /path/to/investment-trust
chmod +x setup_cron.sh
./setup_cron.sh
```

このスクリプトは以下を自動で行います：
- PHPパスの検出
- logsディレクトリの作成
- cronファイルの生成
- crontabへの登録
- 設定内容の確認

**手動で設定する場合:**

```bash
crontab -e
```

以下を追加：

```cron
# 日経平均監視（毎日10:30と14:30に実行）- シグナル判定あり
30 10 * * * cd /path/to/investment-trust && /usr/bin/php check_price.php >> /path/to/investment-trust/logs/cron.log 2>&1
30 14 * * * cd /path/to/investment-trust && /usr/bin/php check_price.php >> /path/to/investment-trust/logs/cron.log 2>&1

# 日経平均監視（毎日18:30に実行）- シグナル判定なし（株価保存のみ）
30 18 * * * cd /path/to/investment-trust && /usr/bin/php check_price.php --no-signal >> /path/to/investment-trust/logs/cron.log 2>&1
```

**cronの時刻指定について:**
- `30 10 * * *`: 毎日10:30に実行（シグナル判定あり）
- `30 14 * * *`: 毎日14:30に実行（シグナル判定あり）
- `30 18 * * *`: 毎日18:30に実行（シグナル判定なし）
- 土日は市場が閉まっているため、直近の取引日（金曜日）のデータが取得されます

**--no-signalオプション:**
- このオプションを付けると、株価の取得・保存のみ実行し、買い・売りシグナルの判定とメール通知をスキップします
- 18:30は取引終了後のため、終値確定後のデータを保存する目的で使用します
- 大幅下落警告（前日比-1500円以上）は`--no-signal`オプションに関係なく常にチェックされます

**設定確認:**

```bash
# crontabの内容を確認
crontab -l

# ログファイルをリアルタイムで監視
tail -f /path/to/investment-trust/logs/cron.log

# 手動でテスト実行
cd /path/to/investment-trust
php check_price.php
```

#### その他のLinux環境

上記のさくらVPS Ubuntu環境と同じ手順で設定できます。

#### Windowsの場合（タスクスケジューラ）

1. タスクスケジューラを開く
2. 「基本タスクの作成」
3. トリガー: 毎日 10:30と14:30（2つのタスクを作成）
4. 操作: プログラムの開始
   - プログラム: `C:\xampp\php\php.exe`
   - 引数: `C:\xampp\htdocs\investment-trust\check_price.php`
5. 条件タブで「タスクを実行するコンピューターがAC電源を使用している場合のみ」のチェックを外す

#### XAMPPの場合（開発環境）

XAMPPコントロールパネルから「Shell」を開き：

```bash
# 手動でテスト実行
php check_price.php
```

## 📖 使い方

### Web管理画面

`http://localhost/investment-trust/index.php`にアクセスすると以下が確認できます：

- 📊 現在の日経平均株価
- 🔔 シグナル発生状態
- ⚙️ 監視設定の変更
- 📧 通知履歴
- 📈 価格履歴

### 手動チェック

Web管理画面から「今すぐチェック」ボタンをクリックするか、直接以下にアクセス：

```
http://localhost/investment-trust/check_price.php
```

コマンドラインから実行：

```bash
cd /path/to/investment-trust
php check_price.php
```

### cronログの確認

自動実行の状況を確認するには：

```bash
# 最新のログを表示
tail -n 50 /path/to/investment-trust/logs/cron.log

# リアルタイムでログを監視
tail -f /path/to/investment-trust/logs/cron.log

# 特定の日付のログを検索
grep "2025-11-29" /path/to/investment-trust/logs/cron.log
```

ログには以下の情報が記録されます：
- 実行日時
- 取得した株価
- シグナル判定結果
- メール送信結果
- エラーメッセージ（発生時）

## 🔧 トラブルシューティング

### Gmail API認証エラー

**エラー**: `Gmail API認証が必要です`

**解決方法**:
1. `credentials.json`が正しく配置されているか確認
2. `authenticate.php`で再認証
3. `token.json`が生成されているか確認

### スクレイピングエラー

**エラー**: `株価の取得に失敗しました`

**原因**:
- Investing.comのサイト構造が変更された
- ネットワーク接続の問題
- サイトがアクセスをブロックしている

**解決方法**:
1. `scraper.php`のXPathセレクタを更新
2. User-Agentを変更
3. 手動でサイトにアクセスできるか確認

### データベース接続エラー

**エラー**: `データベース接続エラーが発生しました`

**解決方法**:
1. MySQLが起動しているか確認
2. `config.php`の接続情報を確認
3. データベースとテーブルが作成されているか確認

### メール送信エラー

**原因**:
- Gmail APIの認証期限切れ
- 送信制限に達した

**解決方法**:
1. `authenticate.php`で再認証
2. Googleアカウントのセキュリティ設定を確認
3. Gmail APIの使用制限を確認

## 📁 ファイル構成

```
investment-trust/
├── index.php              # Web UI（メイン画面）
├── config.php             # データベース接続設定
├── check_price.php        # 価格監視スクリプト（cron実行用）
├── scraper.php            # スクレイピング処理
├── gmail_api.php          # Gmail API連携
├── authenticate.php       # Gmail API認証画面
├── database.sql           # データベース初期化SQL
├── setup_cron.sh          # cron自動セットアップスクリプト
├── composer.json          # Composer設定
├── composer.lock          # Composerロックファイル
├── credentials.json       # Gmail API認証情報（要配置）
├── token.json             # Gmail APIトークン（自動生成）
├── .htaccess              # セキュリティ設定
├── README.md              # このファイル
├── cron/
│   └── schedule.cron      # cronスケジュール設定
├── logs/
│   └── cron.log           # cron実行ログ（自動生成）
├── css/
│   └── style.css          # スタイルシート
└── vendor/                # Composerライブラリ（自動生成）
```

## 🔒 セキュリティ

- `.htaccess`で機密ファイルへの直接アクセスを制限
- `credentials.json`と`token.json`は公開ディレクトリ外に配置推奨
- SQLインジェクション対策（プリペアドステートメント使用）
- XSS対策（htmlspecialchars使用）

## ⚠️ 注意事項

- このシステムは投資判断の補助ツールです
- 実際の投資判断は自己責任で行ってください
- スクレイピングは対象サイトの利用規約を遵守してください
- Gmail APIには送信制限があります（1日あたり約100通）
- 市場が閉まっている時間帯は価格が更新されません

## 📊 通知ロジック

システムは以下のロジックで通知を送信します：

### 買い・売りシグナル（--no-signalオプションなしの場合のみ）

1. **買いシグナル**: 現在価格 < 買いシグナル価格
   - メール件名: 「【買いシグナル】日経平均株価が下落しました」
   - 推奨アクション: 買いを検討

2. **売りシグナル**: 現在価格 > 売りシグナル価格
   - メール件名: 「【売りシグナル】日経平均株価が上昇しました」
   - 推奨アクション: 売りを検討

3. **正常範囲**: 買いシグナル価格 ≤ 現在価格 ≤ 売りシグナル価格
   - 通知なし

### 大幅下落警告（常時チェック）

4. **大幅下落警告**: 前日終値 - 現在価格 ≥ 1500円
   - メール件名: 「【警告】日経平均が大幅下落しました」
   - 前日比で1500円以上の下落を検出した場合に通知
   - `--no-signal`オプションに関係なく常にチェックされます

※ 条件を満たす度に毎回通知されます

## 🛠️ カスタマイズ

### チェック頻度の変更

`cron`設定を編集して頻度を変更できます：

```cron
# 1時間ごと
0 * * * * /usr/bin/php /path/to/check_price.php

# 30分ごと
*/30 * * * * /usr/bin/php /path/to/check_price.php
```

### 通知メールのカスタマイズ

`gmail_api.php`の以下のメソッドを編集してメール内容をカスタマイズできます：
- `sendBuySignal()`: 買いシグナル通知
- `sendSellSignal()`: 売りシグナル通知
- `sendLargeDropAlert()`: 大幅下落警告通知

### 大幅下落の閾値変更

`check_price.php`の`$dropThreshold`変数を編集して閾値を変更できます：

```php
// 大幅下落チェック（前日比-1500円以上）
$dropThreshold = 1500;  // この値を変更
```

### スクレイピング対象の変更

`scraper.php`を編集して別のサイトから株価を取得することも可能です。

## 📞 サポート

問題が発生した場合は、以下を確認してください：

1. PHPエラーログ
2. `check_price.php`の実行ログ
3. データベースの接続状態
4. Gmail APIの認証状態

## 📝 ライセンス

このプロジェクトは個人利用を目的としています。

## 🔄 更新履歴

- **2025-12-02**: 機能追加
  - 大幅下落警告機能を追加（前日比-1500円以上で通知）
  - `--no-signal`オプションを追加（シグナル判定をスキップして株価保存のみ実行）
  - 18:30の定期実行を追加（終値確定後のデータ取得用）

- **2025-11-28**: 初回リリース
  - 基本機能実装
  - Gmail API連携
  - Web UI実装

---

**開発者**: Investment Trust System
**最終更新**: 2025年12月2日

