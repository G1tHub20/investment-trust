-- price_historyテーブルにdateカラムを追加するマイグレーション
-- 実行日: 2025-11-28

-- Step 1: dateカラムを追加（NULL許可で一旦追加）
ALTER TABLE `price_history` 
ADD COLUMN `date` DATE NULL COMMENT '価格取得日' AFTER `id`;

-- Step 2: 既存データのdateを埋める（created_atから日付部分を抽出）
UPDATE `price_history` 
SET `date` = DATE(created_at);

-- Step 3: dateをNOT NULLに変更
ALTER TABLE `price_history` 
MODIFY COLUMN `date` DATE NOT NULL COMMENT '価格取得日';

-- Step 4: dateにUNIQUE制約を追加（1日1行を保証）
ALTER TABLE `price_history` 
ADD UNIQUE KEY `uk_date` (`date`);

-- Step 5: dateにインデックスを追加（検索高速化）
ALTER TABLE `price_history` 
ADD KEY `idx_date` (`date`);

-- 完了後のテーブル構造確認
-- DESCRIBE price_history;

