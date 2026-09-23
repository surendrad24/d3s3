-- 039_add_whatsapp_form_submissions.sql
-- Patient-facing pre-intake form submissions collected via a WhatsApp-shared
-- link. Rows are created when the patient submits the public form; staff can
-- then attach the submission to a real patient / case sheet.
--
-- `token` is the single-use URL slug carried in the WhatsApp link; consumed
-- (submitted_at IS NOT NULL) tokens cannot be reused. `form_data` is a JSON
-- blob of the field values so this schema can absorb new form questions
-- without further migrations.

CREATE TABLE IF NOT EXISTS `whatsapp_form_submissions` (
    `submission_id`     BIGINT(20)         UNSIGNED NOT NULL AUTO_INCREMENT,
    `token`             CHAR(48)           NOT NULL,
    `patient_id`        INT(10)            UNSIGNED NULL,
    `to_phone_e164`     VARCHAR(20)        NULL,
    `form_data`         JSON               NULL,
    `status`            ENUM('PENDING','PROCESSED','DISCARDED') NOT NULL DEFAULT 'PENDING',
    `sent_by_user_id`   INT(10)            UNSIGNED NOT NULL,
    `created_at`        DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `submitted_at`      DATETIME           NULL,
    `processed_by_user_id` INT(10)         UNSIGNED NULL,
    `processed_at`      DATETIME           NULL,
    `notes`             VARCHAR(500)       NULL,
    PRIMARY KEY (`submission_id`),
    UNIQUE KEY `uq_wa_form_token` (`token`),
    KEY `idx_wa_form_patient` (`patient_id`),
    KEY `idx_wa_form_status`  (`status`, `created_at`),
    CONSTRAINT `fk_wa_form_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_wa_form_sender`  FOREIGN KEY (`sent_by_user_id`) REFERENCES `users` (`user_id`),
    CONSTRAINT `fk_wa_form_proc`    FOREIGN KEY (`processed_by_user_id`) REFERENCES `users` (`user_id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
