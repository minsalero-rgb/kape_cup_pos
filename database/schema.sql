-- Database schema for M&E Kape Cup POS.
-- Target: MySQL 5.7.40. Creates and selects the project database `db_kape_cup_pos`.
-- Fresh installs use this complete schema. For existing installs, apply migrations
-- 001_login_attempts.sql, 002_order_submission_log.sql, 003_inventory.sql,
-- 004_menu_item_images.sql, and 005_order_submission_idempotency.sql.
-- Business rules that require PHP validation are noted below; MySQL 5.7 CHECK constraints are not relied upon.

CREATE DATABASE IF NOT EXISTS `db_kape_cup_pos`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `db_kape_cup_pos`;

CREATE TABLE `staff` (
    `staff_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `full_name` VARCHAR(120) NOT NULL,
    `username` VARCHAR(80) NOT NULL,
    `password_hash` VARCHAR(255) NOT NULL,
    `role` ENUM('staff', 'admin') NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`staff_id`),
    UNIQUE KEY `uq_staff_username` (`username`),
    KEY `idx_staff_role_active` (`role`, `is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `staff_shifts` (
    `shift_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `staff_id` BIGINT UNSIGNED NOT NULL,
    `login_timestamp` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `logout_timestamp` DATETIME NULL DEFAULT NULL,
    -- NULL for closed shifts; the unique index then permits any number of closed shifts.
    `open_staff_id` BIGINT UNSIGNED GENERATED ALWAYS AS (
        CASE WHEN `logout_timestamp` IS NULL THEN `staff_id` ELSE NULL END
    ) STORED,
    PRIMARY KEY (`shift_id`),
    UNIQUE KEY `uq_staff_shifts_open_staff_id` (`open_staff_id`),
    -- Explicit unique target for orders' composite (shift_id, staff_id) foreign key.
    UNIQUE KEY `uq_staff_shifts_id_staff_id` (`shift_id`, `staff_id`),
    KEY `idx_staff_shifts_staff_logout` (`staff_id`, `logout_timestamp`),
    CONSTRAINT `fk_staff_shifts_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `categories` (
    `category_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`category_id`),
    UNIQUE KEY `uq_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu_items` (
    `item_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(150) NOT NULL,
    `description` TEXT NULL,
    `image_filename` VARCHAR(64) NULL,
    `base_price` DECIMAL(10,2) NOT NULL,
    `stock_quantity` INT UNSIGNED NOT NULL DEFAULT 0,
    `low_stock_threshold` INT UNSIGNED NOT NULL DEFAULT 5,
    `is_sold_out` TINYINT(1) NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`item_id`),
    KEY `idx_menu_items_category_active_soldout` (`category_id`, `is_active`, `is_sold_out`),
    CONSTRAINT `fk_menu_items_category`
        FOREIGN KEY (`category_id`) REFERENCES `categories` (`category_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu_modifier_groups` (
    `modifier_group_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id` BIGINT UNSIGNED NOT NULL,
    `group_key` VARCHAR(60) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `is_required` TINYINT(1) NOT NULL,
    `min_selections` SMALLINT UNSIGNED NOT NULL,
    `max_selections` SMALLINT UNSIGNED NOT NULL,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`modifier_group_id`),
    UNIQUE KEY `uq_menu_modifier_groups_item_key` (`item_id`, `group_key`),
    KEY `idx_menu_modifier_groups_item_active` (`item_id`, `is_active`),
    CONSTRAINT `fk_menu_modifier_groups_item`
        FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `menu_modifier_options` (
    `modifier_option_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `modifier_group_id` BIGINT UNSIGNED NOT NULL,
    `option_key` VARCHAR(60) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `price_delta` DECIMAL(10,2) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `sort_order` INT UNSIGNED NOT NULL DEFAULT 0,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`modifier_option_id`),
    UNIQUE KEY `uq_menu_modifier_options_group_key` (`modifier_group_id`, `option_key`),
    KEY `idx_menu_modifier_options_group_active` (`modifier_group_id`, `is_active`),
    CONSTRAINT `fk_menu_modifier_options_group`
        FOREIGN KEY (`modifier_group_id`) REFERENCES `menu_modifier_groups` (`modifier_group_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `orders` (
    `order_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `customer_name` VARCHAR(120) NOT NULL,
    `order_type` ENUM('Dine-In', 'Take-Out') NOT NULL,
    `table_label` VARCHAR(64) NULL DEFAULT NULL,
    `payment_method` ENUM('Cash', 'GCash', 'Credit Card') NOT NULL,
    `total_amount` DECIMAL(10,2) NOT NULL,
    `cash_tendered` DECIMAL(10,2) NULL DEFAULT NULL,
    `change_due` DECIMAL(10,2) NULL DEFAULT NULL,
    `status` ENUM('Pending', 'Paid', 'Preparing', 'Completed') NOT NULL DEFAULT 'Pending',
    `staff_id` BIGINT UNSIGNED NOT NULL,
    `shift_id` BIGINT UNSIGNED NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `paid_at` DATETIME NULL DEFAULT NULL,
    `preparing_at` DATETIME NULL DEFAULT NULL,
    `completed_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`order_id`),
    KEY `idx_orders_status_created` (`status`, `created_at`),
    KEY `idx_orders_status_completed` (`status`, `completed_at`),
    KEY `idx_orders_shift_created` (`shift_id`, `created_at`),
    KEY `idx_orders_shift_staff` (`shift_id`, `staff_id`),
    KEY `idx_orders_payment_status_created` (`payment_method`, `status`, `created_at`),
    CONSTRAINT `fk_orders_shift_owner`
        FOREIGN KEY (`shift_id`, `staff_id`)
        REFERENCES `staff_shifts` (`shift_id`, `staff_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_items` (
    `order_item_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_id` BIGINT UNSIGNED NOT NULL,
    `item_id` BIGINT UNSIGNED NOT NULL,
    `item_name_snapshot` VARCHAR(150) NOT NULL,
    `base_price_snapshot` DECIMAL(10,2) NOT NULL,
    `quantity` INT UNSIGNED NOT NULL,
    `unit_price_snapshot` DECIMAL(10,2) NOT NULL,
    `line_total_snapshot` DECIMAL(10,2) NOT NULL,
    `notes` VARCHAR(500) NULL DEFAULT NULL,
    PRIMARY KEY (`order_item_id`),
    KEY `idx_order_items_order` (`order_id`),
    KEY `idx_order_items_item_order` (`item_id`, `order_id`),
    CONSTRAINT `fk_order_items_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_order_items_menu_item`
        FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `order_item_modifiers` (
    `order_item_modifier_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `order_item_id` BIGINT UNSIGNED NOT NULL,
    `modifier_option_id` BIGINT UNSIGNED NOT NULL,
    `group_name_snapshot` VARCHAR(100) NOT NULL,
    `option_name_snapshot` VARCHAR(100) NOT NULL,
    `price_delta_snapshot` DECIMAL(10,2) NOT NULL,
    PRIMARY KEY (`order_item_modifier_id`),
    UNIQUE KEY `uq_order_item_modifiers_line_option` (`order_item_id`, `modifier_option_id`),
    CONSTRAINT `fk_order_item_modifiers_order_item`
        FOREIGN KEY (`order_item_id`) REFERENCES `order_items` (`order_item_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    -- Required reference preserves the source option; deactivate referenced options instead of deleting them.
    CONSTRAINT `fk_order_item_modifiers_option`
        FOREIGN KEY (`modifier_option_id`) REFERENCES `menu_modifier_options` (`modifier_option_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `inventory_stock_logs` (
    `log_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `item_id` BIGINT UNSIGNED NOT NULL,
    `staff_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `quantity_change` INT NOT NULL,
    `reason` VARCHAR(255) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`log_id`),
    KEY `idx_inventory_stock_logs_item_time` (`item_id`, `created_at`),
    KEY `idx_inventory_stock_logs_order` (`order_id`),
    CONSTRAINT `fk_inventory_stock_logs_item`
        FOREIGN KEY (`item_id`) REFERENCES `menu_items` (`item_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_inventory_stock_logs_staff`
        FOREIGN KEY (`staff_id`) REFERENCES `staff` (`staff_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT,
    CONSTRAINT `fk_inventory_stock_logs_order`
        FOREIGN KEY (`order_id`) REFERENCES `orders` (`order_id`)
        ON DELETE RESTRICT ON UPDATE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mirrors database/migrations/001_login_attempts.sql.
CREATE TABLE `login_attempts` (
    `attempt_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `username_key` CHAR(64) NOT NULL,
    `ip_key` CHAR(64) NOT NULL,
    `attempted_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`attempt_id`),
    KEY `idx_login_attempts_user_time` (`username_key`, `attempted_at`),
    KEY `idx_login_attempts_ip_time` (`ip_key`, `attempted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mirrors database/migrations/002_order_submission_log.sql.
CREATE TABLE `order_submission_log` (
    `log_id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ip_key` CHAR(64) NOT NULL,
    `session_key` CHAR(64) NOT NULL,
    `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`log_id`),
    KEY `idx_osl_ip_time` (`ip_key`, `created_at`),
    KEY `idx_osl_session_time` (`session_key`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Mirrors database/migrations/005_order_submission_idempotency.sql.
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

-- Application validation/transactions must enforce positive quantities and prices, modifier counts and
-- applicability, dine-in allowlist labels, cash sufficiency, calculated snapshots/totals, active Staff shift
-- acceptance, manual payment verification, and the forward-only status flow:
-- Pending -> Paid -> Preparing -> Completed. No cancellation or automatic payment confirmation is included.
-- There is intentionally no dining_tables registry, sample menu data, or account data in this script.
