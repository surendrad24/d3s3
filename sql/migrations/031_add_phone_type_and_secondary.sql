-- 031_add_phone_type_and_secondary.sql
-- Extends patients contact info to capture up to two phone numbers along
-- with the type of handset on each (smartphone vs non-smart / feature phone).
-- Knowing whether a patient has a smartphone drives which channels we can
-- use to reach them (WhatsApp, patient portal, image reminders, etc.).
--
-- phone_type / phone_secondary_type:
--   SMARTPHONE - can use WhatsApp / portal / receive images
--   NON_SMART  - SMS / voice call only
--   UNKNOWN    - not yet asked

ALTER TABLE `patients`
	ADD COLUMN `phone_type` ENUM('SMARTPHONE','NON_SMART','UNKNOWN')
		NOT NULL DEFAULT 'UNKNOWN' AFTER `phone_e164`,
	ADD COLUMN `phone_secondary_e164` VARCHAR(20) NULL AFTER `phone_type`,
	ADD COLUMN `phone_secondary_type` ENUM('SMARTPHONE','NON_SMART','UNKNOWN')
		NOT NULL DEFAULT 'UNKNOWN' AFTER `phone_secondary_e164`,
	ADD INDEX `idx_patients_phone_secondary` (`phone_secondary_e164`);
