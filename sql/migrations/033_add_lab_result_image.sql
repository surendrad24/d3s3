-- 033_add_lab_result_image.sql
-- Allows the lab technician to upload a scanned/photographed lab report
-- alongside the free-text result notes. Path is stored relative to the
-- uploads/lab_reports/ base and served through a permission-gated endpoint.

ALTER TABLE `lab_orders`
	ADD COLUMN `result_image_path` VARCHAR(255) NULL AFTER `result_notes`,
	ADD COLUMN `result_image_name` VARCHAR(255) NULL AFTER `result_image_path`;
