-- Add the customer-facing reference as nullable so existing orders remain intact.
-- Preview and repair references as documented. Run only the unique-key statement
-- in migration 007 before apply; run its NOT NULL statement after backfill.
ALTER TABLE `orders`
    ADD COLUMN `order_reference` VARCHAR(27) NULL DEFAULT NULL AFTER `order_id`;

-- Keep reference-lookup throttling separate from order-submission rate counts.
ALTER TABLE `order_submission_log`
    ADD COLUMN `event_type` ENUM('submission', 'tracking_lookup')
        NOT NULL DEFAULT 'submission' AFTER `session_key`;
