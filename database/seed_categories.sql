-- Category seed for the eight categories used by assets/img/ and the approved project menu.
-- Safe to rerun: existing categories keep their IDs; only their display order is refreshed.
-- Run after database/schema.sql.

INSERT INTO `categories` (`name`, `sort_order`)
VALUES
    ('Coffee Series', 1),
    ('Matcha Series', 2),
    ('Fruit Milk', 3),
    ('Soda', 4),
    ('Fruit Shake', 5),
    ('Latte Series with Whipped Cream', 6),
    ('Main Courses', 7),
    ('Snacks', 8)
ON DUPLICATE KEY UPDATE
    `sort_order` = VALUES(`sort_order`);
