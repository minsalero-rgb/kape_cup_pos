-- First verify there are no duplicate non-NULL references, then apply only
-- this unique-key statement so the missing-reference backfill is protected
-- against collisions. Multiple NULL values remain valid while backfilling.
ALTER TABLE `orders`
    ADD UNIQUE KEY `uq_orders_order_reference` (`order_reference`);

-- Run only after the backfill utility reports that every order has a valid
-- reference. Do not run against an unverified production DB.
ALTER TABLE `orders`
    MODIFY COLUMN `order_reference` VARCHAR(27) NOT NULL;
