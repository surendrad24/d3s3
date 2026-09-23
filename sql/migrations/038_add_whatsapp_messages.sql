-- 038_add_whatsapp_messages.sql
-- Outbound WhatsApp message log. Populated by the WhatsAppSender service and
-- read by the patient-profile "WhatsApp" panel. Provider status is updated
-- either by the initial send response or (future) by delivery-receipt webhooks.

CREATE TABLE IF NOT EXISTS `whatsapp_messages` (
    `whatsapp_message_id` BIGINT(20)         UNSIGNED NOT NULL AUTO_INCREMENT,
    `patient_id`          INT(10)            UNSIGNED NULL,
    `to_phone_e164`       VARCHAR(20)        NOT NULL,
    `template_name`       VARCHAR(100)       NULL,
    `body`                TEXT               NOT NULL,
    `status`              ENUM('QUEUED','SENT','FAILED','DELIVERED','READ') NOT NULL DEFAULT 'QUEUED',
    `provider_message_id` VARCHAR(120)       NULL,
    `error_message`       VARCHAR(500)       NULL,
    `sent_by_user_id`     INT(10)            UNSIGNED NOT NULL,
    `sent_at`             DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME           NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`whatsapp_message_id`),
    KEY `idx_wa_patient` (`patient_id`, `sent_at`),
    KEY `idx_wa_status`  (`status`),
    CONSTRAINT `fk_wa_patient` FOREIGN KEY (`patient_id`) REFERENCES `patients` (`patient_id`) ON DELETE SET NULL,
    CONSTRAINT `fk_wa_user`    FOREIGN KEY (`sent_by_user_id`) REFERENCES `users` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
