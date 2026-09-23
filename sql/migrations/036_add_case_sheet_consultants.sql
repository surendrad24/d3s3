-- 036_add_case_sheet_consultants.sql
-- Multi-consult ACL. Lets the primary doctor invite one or more
-- additional doctors as consultants on a case sheet. Any consultant
-- (including the primary) can open the review chart and record findings.
-- The primary doctor remains in case_sheets.assigned_doctor_user_id
-- and is the only one who can add/remove consultants or close the chart.

CREATE TABLE IF NOT EXISTS `case_sheet_consultants` (
	`case_sheet_id`    BIGINT(20) UNSIGNED NOT NULL,
	`doctor_user_id`   INT(10) UNSIGNED NOT NULL,
	`added_at`         DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`added_by_user_id` INT(10) UNSIGNED NOT NULL,
	`role_note`        VARCHAR(255) NULL,
	PRIMARY KEY (`case_sheet_id`, `doctor_user_id`),
	KEY `idx_csc_doctor` (`doctor_user_id`),
	CONSTRAINT `fk_csc_case_sheet` FOREIGN KEY (`case_sheet_id`)
		REFERENCES `case_sheets` (`case_sheet_id`) ON DELETE CASCADE,
	CONSTRAINT `fk_csc_doctor` FOREIGN KEY (`doctor_user_id`)
		REFERENCES `users` (`user_id`) ON DELETE CASCADE,
	CONSTRAINT `fk_csc_added_by` FOREIGN KEY (`added_by_user_id`)
		REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
