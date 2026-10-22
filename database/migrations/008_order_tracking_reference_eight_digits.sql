-- Optional tightening only after verifying every order reference and every
-- associated receipt snapshot is exactly eight digits. This migration does not
-- backfill values and must not be used to truncate or replace issued references.
ALTER TABLE `orders`
    MODIFY COLUMN `order_reference` VARCHAR(8) NOT NULL;
