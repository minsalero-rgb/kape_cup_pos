-- DEVELOPMENT ONLY: illustrative sample products and prices, NOT the actual M&E Kape Cup menu.
-- Do not import this file into production. Replace these examples after receiving the approved menu.
-- Requires database/schema.sql and database/seed_categories.sql to have been run first.
-- Safe to rerun: an item is inserted only when that category/name pair does not already exist.
-- This file creates menu_items only; it creates no accounts, passwords, or modifier rules.

INSERT INTO `menu_items` (`category_id`, `name`, `description`, `base_price`)
SELECT
    `categories`.`category_id`,
    `sample_menu`.`item_name`,
    `sample_menu`.`item_description`,
    `sample_menu`.`base_price`
FROM (
    SELECT 'Coffee Series' AS `category_name`, 'Development Sample Americano' AS `item_name`,
           'Illustrative development-only coffee menu item.' AS `item_description`, 95.00 AS `base_price`
    UNION ALL SELECT 'Coffee Series', 'Development Sample Cafe Mocha',
           'Illustrative development-only coffee menu item.', 135.00
    UNION ALL SELECT 'Matcha Series', 'Development Sample Matcha Latte',
           'Illustrative development-only matcha menu item.', 145.00
    UNION ALL SELECT 'Matcha Series', 'Development Sample Iced Matcha',
           'Illustrative development-only matcha menu item.', 155.00
    UNION ALL SELECT 'Fruit Milk', 'Development Sample Strawberry Milk',
           'Illustrative development-only fruit milk menu item.', 120.00
    UNION ALL SELECT 'Fruit Milk', 'Development Sample Mango Milk',
           'Illustrative development-only fruit milk menu item.', 125.00
    UNION ALL SELECT 'Soda', 'Development Sample Citrus Soda',
           'Illustrative development-only soda menu item.', 85.00
    UNION ALL SELECT 'Soda', 'Development Sample Berry Soda',
           'Illustrative development-only soda menu item.', 90.00
    UNION ALL SELECT 'Fruit Shake', 'Development Sample Mango Shake',
           'Illustrative development-only fruit shake menu item.', 130.00
    UNION ALL SELECT 'Fruit Shake', 'Development Sample Banana Shake',
           'Illustrative development-only fruit shake menu item.', 125.00
    UNION ALL SELECT 'Latte Series with Whipped Cream', 'Development Sample Vanilla Cream Latte',
           'Illustrative development-only latte menu item.', 155.00
    UNION ALL SELECT 'Latte Series with Whipped Cream', 'Development Sample Caramel Cream Latte',
           'Illustrative development-only latte menu item.', 165.00
    UNION ALL SELECT 'Main Courses', 'Development Sample Chicken Rice Meal',
           'Illustrative development-only main course menu item.', 195.00
    UNION ALL SELECT 'Main Courses', 'Development Sample Beef Rice Meal',
           'Illustrative development-only main course menu item.', 225.00
    UNION ALL SELECT 'Snacks', 'Development Sample Garlic Fries',
           'Illustrative development-only snack menu item.', 95.00
    UNION ALL SELECT 'Snacks', 'Development Sample Toasted Sandwich',
           'Illustrative development-only snack menu item.', 125.00
) AS `sample_menu`
INNER JOIN `categories`
    ON `categories`.`name` = `sample_menu`.`category_name`
LEFT JOIN `menu_items` AS `existing_item`
    ON `existing_item`.`category_id` = `categories`.`category_id`
   AND `existing_item`.`name` = `sample_menu`.`item_name`
WHERE `existing_item`.`item_id` IS NULL;
