-- phpMyAdmin SQL Dump
-- version 5.2.1deb3
-- https://www.phpmyadmin.net/
--
-- ホスト: localhost:3306
-- 生成日時: 2025 年 12 月 02 日 14:16
-- サーバのバージョン： 8.0.44-0ubuntu0.24.04.1
-- PHP のバージョン: 8.3.6

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- データベース: `investment_trust`
--

-- --------------------------------------------------------

--
-- テーブルの構造 `notifications`
--

CREATE TABLE `notifications` (
  `id` int NOT NULL,
  `signal_type` enum('buy','sell') CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'buy:買いシグナル sell:売りシグナル',
  `current_price` decimal(10,2) NOT NULL COMMENT '通知時の株価',
  `trigger_price` decimal(10,2) NOT NULL COMMENT 'トリガー価格',
  `email_sent` tinyint(1) DEFAULT '0' COMMENT 'メール送信成功フラグ（0:失敗 1:成功）',
  `error_message` text COLLATE utf8mb4_unicode_ci COMMENT 'エラーメッセージ',
  `notified_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '通知日時'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `price_history`
--

CREATE TABLE `price_history` (
  `id` int NOT NULL,
  `date` date NOT NULL COMMENT '日付',
  `close` decimal(10,2) NOT NULL COMMENT '終値',
  `open` decimal(10,2) NOT NULL COMMENT '始値',
  `high` decimal(10,2) NOT NULL COMMENT '高値',
  `low` decimal(10,2) NOT NULL COMMENT '低値',
  `price_change_rate` decimal(5,4) DEFAULT NULL COMMENT '前日比',
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- テーブルの構造 `settings`
--

CREATE TABLE `settings` (
  `id` int NOT NULL,
  `base_price` decimal(10,2) NOT NULL COMMENT '基準価格',
  `buy_signal_price` decimal(10,2) NOT NULL COMMENT '買いシグナル価格',
  `sell_signal_price` decimal(10,2) NOT NULL COMMENT '売りシグナル価格',
  `email_address` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '通知先メールアドレス',
  `is_active` tinyint(1) DEFAULT '1' COMMENT '監視有効フラグ',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- ダンプしたテーブルのインデックス
--

--
-- テーブルのインデックス `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_notified_at` (`notified_at`),
  ADD KEY `idx_signal_type` (`signal_type`);

--
-- テーブルのインデックス `price_history`
--
ALTER TABLE `price_history`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `date` (`date`);

--
-- テーブルのインデックス `settings`
--
ALTER TABLE `settings`
  ADD PRIMARY KEY (`id`);

--
-- ダンプしたテーブルの AUTO_INCREMENT
--

--
-- テーブルの AUTO_INCREMENT `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `price_history`
--
ALTER TABLE `price_history`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;

--
-- テーブルの AUTO_INCREMENT `settings`
--
ALTER TABLE `settings`
  MODIFY `id` int NOT NULL AUTO_INCREMENT;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
