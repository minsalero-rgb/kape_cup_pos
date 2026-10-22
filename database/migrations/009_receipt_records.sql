-- Stores OR-numbered receipt issues separately from order/payment history.
-- Staff removal is a soft removal; this table retains the original receipt snapshot.
-- Rollback is lossless only after exporting receipt_records and confirming the
-- application no longer depends on it. Never run rollback as part of deployment.
CREATE TABLE `receipt_records` (
    `receipt_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `order_reference` VARCHAR(27) NOT NULL,
    `or_number` VARCHAR(50) NOT NULL,
    `issued_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `status` ENUM('Active', 'Removed') NOT NULL DEFAULT 'Active',
    `receipt_snapshot` LONGTEXT NOT NULL,
    `created_by_staff_id` BIGINT UNSIGNED NOT NULL,
    `removed_at` DATETIME NULL DEFAULT NULL,
    `removed_by_staff_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `removal_reason` VARCHAR(500) NULL DEFAULT NULL,
    PRIMARY KEY (`receipt_id`),
    UNIQUE KEY `uq_receipt_records_or_number` (`or_number`),
    KEY `idx_receipt_records_order_status` (`order_id`, `status`),
    KEY `idx_receipt_records_reference_issue_date` (`order_reference`, `issued_at`),
    KEY `idx_receipt_records_status_issue_date` (`status`, `issued_at`),
    CONSTRAINT `fk_receipt_records_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_receipt_records_created_by`
        FOREIGN KEY (`created_by_staff_id`) REFERENCES `staff` (`staff_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_receipt_records_removed_by`
        FOREIGN KEY (`removed_by_staff_id`) REFERENCES `staff` (`staff_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
