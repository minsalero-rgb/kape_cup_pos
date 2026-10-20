-- Adds an optional server-generated image filename for menu items.
-- Keep uploaded image files outside the public document root.
-- Rollback: ALTER TABLE menu_items DROP COLUMN image_filename;
ALTER TABLE `menu_items`
    ADD COLUMN `image_filename` VARCHAR(64) NULL AFTER `description`;
