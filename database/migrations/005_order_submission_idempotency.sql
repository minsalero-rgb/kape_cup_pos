-- Makes customer and Staff checkout retries safe without changing order history.
-- Rollback: DROP TABLE order_submission_requests;
CREATE TABLE `order_submission_requests` (
    `session_key` CHAR(64) NOT NULL,
    `request_key` CHAR(64) NOT NULL,
    `cart_hash` CHAR(64) NOT NULL,
    `order_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`session_key`, `request_key`),
    KEY `idx_order_submission_requests_order` (`order_id`),
    CONSTRAINT `fk_order_submission_requests_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
