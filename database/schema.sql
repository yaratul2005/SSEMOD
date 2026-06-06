-- CREATE DATABASE IF NOT EXISTS socialcc CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
-- USE socialcc;

CREATE TABLE IF NOT EXISTS users (
    anonymous_id VARCHAR(64) PRIMARY KEY,
    fingerprint VARCHAR(128) NOT NULL,
    ip_hash VARCHAR(64) NOT NULL,
    username VARCHAR(64) NULL,
    email VARCHAR(255) UNIQUE NULL,
    password_hash VARCHAR(255) NULL,
    user_type ENUM('guest','registered') DEFAULT 'guest',
    verified TINYINT(1) DEFAULT 0,
    avatar_path VARCHAR(255) NULL,
    bio VARCHAR(160) NULL,
    display_name VARCHAR(20) NOT NULL DEFAULT '',
    age TINYINT UNSIGNED NOT NULL DEFAULT 18,
    gender ENUM('F','M','O') NOT NULL DEFAULT 'O',
    country_flag VARCHAR(8) NULL,
    tags JSON NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    last_seen DATETIME NULL,
    is_banned TINYINT(1) DEFAULT 0,
    remember_token VARCHAR(64) NULL,
    token_expires DATETIME NULL,
    INDEX idx_users_last_seen (last_seen),
    INDEX idx_users_is_banned (is_banned)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rooms (
    room_id VARCHAR(64) PRIMARY KEY,
    user_a_id VARCHAR(64) NOT NULL,
    user_b_id VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    closed_at DATETIME NULL,
    status VARCHAR(32) NOT NULL,
    INDEX idx_rooms_user_a (user_a_id),
    INDEX idx_rooms_user_b (user_b_id),
    INDEX idx_rooms_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    room_id VARCHAR(64) NOT NULL,
    sender_id VARCHAR(64) NOT NULL,
    content TEXT NOT NULL,
    attachment_path VARCHAR(255) NULL,
    attachment_type VARCHAR(64) NULL,
    sent_at DATETIME NOT NULL,
    delivered_at DATETIME NULL,
    INDEX idx_messages_room_sent (room_id, sent_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS queue (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id VARCHAR(64) NOT NULL,
    waiting_since DATETIME NOT NULL,
    interests TEXT NULL,
    status VARCHAR(32) NOT NULL,
    INDEX idx_queue_status_waiting (status, waiting_since)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS reports (
    id INT AUTO_INCREMENT PRIMARY KEY,
    reporter_id VARCHAR(64) NOT NULL,
    reported_id VARCHAR(64) NOT NULL,
    room_id VARCHAR(64) NOT NULL,
    reason VARCHAR(255) NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX idx_reports_reported (reported_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS rate_limits (
    id INT AUTO_INCREMENT PRIMARY KEY,
    identifier VARCHAR(128) NOT NULL,
    action VARCHAR(64) NOT NULL,
    count INT NOT NULL DEFAULT 1,
    window_start INT UNSIGNED NOT NULL,
    UNIQUE KEY uq_rate_limits (identifier, action, window_start)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
