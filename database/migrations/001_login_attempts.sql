-- Creates storage for hashed login-attempt identifiers and timestamps.
-- Rollback: DROP TABLE login_attempts;
CREATE TABLE `login_attempts` (
    `attempt_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username_key` CHAR(64) NOT NULL,
    `ip_key` CHAR(64) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`attempt_id`),
    KEY `idx_login_attempts_user_time` (`username_key`, `attempted_at`),
    KEY `idx_login_attempts_ip_time` (`ip_key`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
