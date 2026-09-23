-- 035_add_consent_form.sql
-- Stores an uploaded signed consent form (scan/photo/PDF) captured at
-- intake. Path is relative to uploads/case_sheets/ and served through a
-- permission-gated endpoint so PHI is not exposed via direct URLs.

ALTER TABLE `case_sheets`
	ADD COLUMN `consent_form_path` VARCHAR(255) NULL AFTER `pelvic_usg_image_name`,
	ADD COLUMN `consent_form_name` VARCHAR(255) NULL AFTER `consent_form_path`,
	ADD COLUMN `consent_uploaded_at` DATETIME NULL AFTER `consent_form_name`;
