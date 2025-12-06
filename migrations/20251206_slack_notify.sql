/* メール通知からSlack通知への変更に伴うカラムの追加・削除 */
-- settingsテーブル: slack_webhook_url追加
ALTER TABLE settings 
  ADD COLUMN slack_webhook_url VARCHAR(500) DEFAULT NULL COMMENT '通知先Slack webフック' AFTER email_address;

-- settingsテーブル: email_address削除
ALTER TABLE settings
  DROP COLUMN email_address;

-- notificationsテーブル: slack_sent追加
ALTER TABLE notifications 
  ADD COLUMN slack_sent TINYINT(1) DEFAULT 0 COMMENT 'Slack送信成功フラグ（0:失敗 1:成功）' AFTER email_sent;

-- notificationsテーブル: email_sent削除
ALTER TABLE notifications
  DROP COLUMN email_sent;