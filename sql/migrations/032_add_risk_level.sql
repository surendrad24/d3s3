-- 032_add_risk_level.sql
-- Lets nurses tag a case at intake with the clinical risk level so that
-- HIGH-risk patients bubble to the top of the doctor's queue and are not
-- lost behind lower-priority walk-ins.
--
-- risk_level:
--   LOW    - routine
--   MEDIUM - warrants attention but not urgent
--   HIGH   - urgent; prioritise ahead of the queue
--   NULL   - not yet triaged
--
-- The queue order becomes: (risk = HIGH) first, then existing manual
-- queue_position order, then visit time.

ALTER TABLE `case_sheets`
	ADD COLUMN `risk_level` ENUM('LOW','MEDIUM','HIGH') NULL AFTER `chief_complaint`,
	ADD INDEX `idx_case_sheets_risk` (`risk_level`);
