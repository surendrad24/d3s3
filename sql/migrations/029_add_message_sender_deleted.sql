-- 029_add_message_sender_deleted.sql
-- Adds a soft-delete flag for the sender on staff-to-staff messages.
-- Set to 1 when the sender deletes a message from the Sent view; the
-- recipient's row is unaffected.

ALTER TABLE `messages`
	ADD COLUMN `sender_deleted` TINYINT(1) NOT NULL DEFAULT 0 AFTER `is_read`,
	ADD INDEX `idx_messages_sender_deleted` (`sender_user_id`, `sender_deleted`);
