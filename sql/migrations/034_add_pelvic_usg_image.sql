-- 034_add_pelvic_usg_image.sql
-- Lets the nurse attach a pelvic ultrasound (USG) image to the case
-- sheet during the pelvic examination step. Stored under
-- uploads/case_sheets/YYYY/MM/ and served through a permission-gated
-- endpoint so PHI is not exposed via direct URLs.

ALTER TABLE `case_sheets`
	ADD COLUMN `pelvic_usg_image_path` VARCHAR(255) NULL AFTER `diag_pelvic`,
	ADD COLUMN `pelvic_usg_image_name` VARCHAR(255) NULL AFTER `pelvic_usg_image_path`;
