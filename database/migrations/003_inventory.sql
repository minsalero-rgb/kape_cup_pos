-- Adds persisted on-hand quantities and an audit trail for inventory changes.
-- Existing items start at zero because initial stock counts must be confirmed.
-- Rollback: DROP TABLE inventory_stock_logs; ALTER TABLE menu_items DROP COLUMN low_stock_threshold, DROP COLUMN stock_quantity;
ALTER TABLE `menu_items`
    ADD COLUMN `stock_quantity` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `base_price`,
    ADD COLUMN `low_stock_threshold` INT UNSIGNED NOT NULL DEFAULT 5 AFTER `stock_quantity`;

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
