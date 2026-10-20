-- Creates hashed order-submission rate-limit records.
-- Rollback: DROP TABLE order_submission_log;
CREATE TABLE `order_submission_log` (
    `log_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_key` CHAR(64) NOT NULL,
    `session_key` CHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`log_id`),
    KEY `idx_osl_ip_time` (`ip_key`, `created_at`),
    KEY `idx_osl_session_time` (`session_key`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
