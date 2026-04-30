CREATE DATABASE IF NOT EXISTS provision CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE provision;

CREATE TABLE IF NOT EXISTS trongate_user_levels (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    level_name VARCHAR(100) NOT NULL
);

INSERT IGNORE INTO trongate_user_levels (id, level_name) VALUES
    (1, 'admin'),
    (2, 'customer');

CREATE TABLE IF NOT EXISTS trongate_users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(32) NOT NULL,
    user_level_id INT UNSIGNED NOT NULL DEFAULT 1
);

CREATE TABLE IF NOT EXISTS trongate_tokens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token VARCHAR(64) NOT NULL,
    expiry_date INT NOT NULL DEFAULT 0,
    date_created INT NOT NULL DEFAULT 0
);

CREATE TABLE IF NOT EXISTS trongate_administrators (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    trongate_user_id INT UNSIGNED NOT NULL,
    username VARCHAR(100) NOT NULL,
    password VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    email_address VARCHAR(255)
);

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

CREATE TABLE IF NOT EXISTS environment (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    php_version VARCHAR(10) NOT NULL DEFAULT '8.4',
    web_root VARCHAR(200) DEFAULT '/var/www/html',
    domain VARCHAR(255) DEFAULT NULL,
    db_name VARCHAR(100) DEFAULT NULL,
    variables TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS server (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    environment_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    ipv6_address VARCHAR(45) DEFAULT NULL,
    ssh_user VARCHAR(100) DEFAULT 'root',
    ssh_port INT DEFAULT 22,
    provider VARCHAR(50) DEFAULT 'manual',
    provider_id VARCHAR(100) DEFAULT NULL,
    region VARCHAR(50) DEFAULT NULL,
    server_type VARCHAR(50) DEFAULT NULL,
    status ENUM('pending', 'provisioning', 'active', 'failed') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS deployment (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    server_id INT UNSIGNED NOT NULL,
    environment_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    script_id INT UNSIGNED DEFAULT NULL,
    source_type ENUM('git','zip') NOT NULL DEFAULT 'git',
    repo_url VARCHAR(500) DEFAULT NULL,
    branch VARCHAR(100) DEFAULT 'main',
    zip_path VARCHAR(500) DEFAULT NULL,
    status ENUM('pending','script_ready','queued','running','success','failed') DEFAULT 'pending',
    is_canary TINYINT(1) NOT NULL DEFAULT 0,
    canary_weight TINYINT UNSIGNED NOT NULL DEFAULT 100,
    deployed_sha VARCHAR(40) DEFAULT NULL,
    notes TEXT,
    queued_script LONGTEXT DEFAULT NULL,
    run_log MEDIUMTEXT DEFAULT NULL,
    started_at DATETIME DEFAULT NULL,
    finished_at DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS service (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    environment_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    type ENUM('mysql','redis','postgresql','memcached','http','custom') DEFAULT 'mysql',
    host VARCHAR(255) DEFAULT NULL,
    port INT DEFAULT 3306,
    status ENUM('pending','active','stopped','failed') DEFAULT 'pending',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS secrets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    module VARCHAR(100) NOT NULL,
    module_id INT UNSIGNED NOT NULL,
    service VARCHAR(100) DEFAULT NULL,
    encryption_key VARCHAR(255) NOT NULL,
    variables TEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_secret (module, module_id, service)
);

CREATE TABLE IF NOT EXISTS script (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id INT UNSIGNED NOT NULL,
    server_id INT UNSIGNED DEFAULT NULL,
    name VARCHAR(150) NOT NULL,
    description VARCHAR(500) DEFAULT NULL,
    type ENUM('lamp','deploy') NOT NULL DEFAULT 'deploy',
    body LONGTEXT NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    KEY idx_server_id (server_id)
);

CREATE TABLE IF NOT EXISTS health_check (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    target_type ENUM('deployment','service') NOT NULL,
    target_id INT UNSIGNED NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    status ENUM('healthy','unhealthy','unknown') DEFAULT 'unknown',
    response_time_ms INT DEFAULT NULL,
    http_status INT DEFAULT NULL,
    message VARCHAR(500) DEFAULT NULL,
    checked_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE IF NOT EXISTS event_log (
    id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    event_type  VARCHAR(80)  NOT NULL,
    customer_id INT UNSIGNED NOT NULL,
    entity_type VARCHAR(40)  NOT NULL,
    entity_id   INT UNSIGNED NOT NULL DEFAULT 0,
    payload     LONGTEXT     NOT NULL,
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_entity   (entity_type, entity_id),
    KEY idx_customer (customer_id, created_at),
    KEY idx_type     (event_type),
    KEY idx_created  (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='Immutable append-only event log — never UPDATE or DELETE rows';
