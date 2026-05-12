CREATE DATABASE IF NOT EXISTS provision CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE provision;

CREATE TABLE IF NOT EXISTS customer (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trongate_user_id INT UNSIGNED NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    active TINYINT(1) DEFAULT 1,
    onboarded_at DATETIME DEFAULT NULL,
    onboarding_provider VARCHAR(20) DEFAULT NULL,
    onboarding_server_id INT UNSIGNED DEFAULT NULL,
    onboarding_dns_ssl_seen TINYINT(1) DEFAULT 0,
    ssh_public_key TEXT DEFAULT NULL,
    failed_login_attempts INT DEFAULT 0,
    last_failed_attempt INT DEFAULT 0,
    login_blocked_until INT DEFAULT 0,
    failed_login_ip VARCHAR(45) DEFAULT '',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
