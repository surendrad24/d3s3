-- 030_add_consultation_source.sql
-- Records how the patient came to us for this specific visit and, when
-- the source is REFERRAL, who referred them.
--
-- consultation_source
--   WALK_IN   - patient walked in
--   CALL      - phone booking / call-in
--   CAMP      - health camp
--   REFERRAL  - referred by another provider or a prior patient
--   FOLLOW_UP - follow-up on an existing case
--   OTHER
--
-- referred_by            : free-text name/description of the referrer
-- referred_by_case_sheet_id : optional link to an earlier case sheet in
--   this system whose patient / doctor made the referral. Nullable FK.

ALTER TABLE `case_sheets`
	ADD COLUMN `consultation_source` ENUM('WALK_IN','CALL','CAMP','REFERRAL','FOLLOW_UP','OTHER')
		NOT NULL DEFAULT 'WALK_IN' AFTER `visit_type`,
	ADD COLUMN `referred_by` VARCHAR(255) NULL AFTER `consultation_source`,
	ADD COLUMN `referred_by_case_sheet_id` BIGINT UNSIGNED NULL AFTER `referred_by`,
	ADD INDEX `idx_case_sheets_source`      (`consultation_source`),
	ADD INDEX `idx_case_sheets_referred_by` (`referred_by_case_sheet_id`),
	ADD CONSTRAINT `fk_case_sheets_referred_by`
		FOREIGN KEY (`referred_by_case_sheet_id`) REFERENCES `case_sheets` (`case_sheet_id`)
		ON DELETE SET NULL;
