-- 037_add_voice_notes.sql
-- Stores voice recordings attached to a case sheet. Source distinguishes
-- patient dictation (captured at intake) from doctor dictation (captured
-- during review). Files live under uploads/voice_notes/YYYY/MM/ with a
-- UUID filename; the DB holds the relative path + original filename so
-- downloads flow through a permission-gated endpoint. Transcript column
-- is nullable and reserved for the Sarvam ASR integration (task 17).

CREATE TABLE IF NOT EXISTS `case_sheet_voice_notes` (
	`voice_note_id`        BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
	`case_sheet_id`        BIGINT(20) UNSIGNED NOT NULL,
	`source`               ENUM('PATIENT','DOCTOR') NOT NULL,
	`file_path`            VARCHAR(255) NOT NULL,
	`file_name`            VARCHAR(255) NOT NULL,
	`mime_type`            VARCHAR(80)  NOT NULL,
	`duration_secs`        INT UNSIGNED NULL,
	`recorded_by_user_id`  INT(10) UNSIGNED NOT NULL,
	`recorded_at`          DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
	`transcript`           MEDIUMTEXT NULL,
	PRIMARY KEY (`voice_note_id`),
	KEY `idx_cvn_case_sheet` (`case_sheet_id`, `recorded_at`),
	KEY `idx_cvn_user` (`recorded_by_user_id`),
	CONSTRAINT `fk_cvn_case_sheet` FOREIGN KEY (`case_sheet_id`)
		REFERENCES `case_sheets` (`case_sheet_id`) ON DELETE CASCADE,
	CONSTRAINT `fk_cvn_user` FOREIGN KEY (`recorded_by_user_id`)
		REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
