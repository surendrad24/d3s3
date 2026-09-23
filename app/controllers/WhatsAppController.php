<?php
/**
 * WhatsAppController – outbound WhatsApp messaging.
 *
 * Sends messages to patients through the configured provider (Meta Cloud API
 * or Twilio) and logs every attempt in the `whatsapp_messages` table. Also
 * powers the patient-profile "WhatsApp" panel and the global outbox.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../permissions.php';
require_once __DIR__ . '/../services/WhatsAppSender.php';

class WhatsAppController
{
    private function requireAccess(): void
    {
        $role = $_SESSION['user_role'] ?? '';
        if (!can($role, 'patient_data')) {
            http_response_code(403);
            exit('Access denied.');
        }
    }

    /** POST — send a WhatsApp message to a patient. JSON. */
    public function send(): void
    {
        $this->requireAccess();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'POST required']); exit;
        }
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Invalid security token.']); exit;
        }

        $patientId    = (int)($_POST['patient_id'] ?? 0);
        $body         = trim((string)($_POST['body'] ?? ''));
        $templateName = trim((string)($_POST['template_name'] ?? ''));
        $overridePhone= trim((string)($_POST['to_phone'] ?? ''));

        if ($patientId <= 0) { echo json_encode(['success' => false, 'error' => 'Missing patient.']); exit; }
        if ($body === '' && $templateName === '') {
            echo json_encode(['success' => false, 'error' => 'Message body or template name is required.']); exit;
        }
        if (strlen($body) > 4000) {
            echo json_encode(['success' => false, 'error' => 'Message too long (max 4000 chars).']); exit;
        }

        $pdo = getDBConnection();
        $stmt = $pdo->prepare('SELECT patient_id, phone_e164, phone_secondary_e164 FROM patients WHERE patient_id = ?');
        $stmt->execute([$patientId]);
        $patient = $stmt->fetch();
        if (!$patient) { echo json_encode(['success' => false, 'error' => 'Patient not found.']); exit; }

        $to = $overridePhone !== '' ? $overridePhone : (string)($patient['phone_e164'] ?? $patient['phone_secondary_e164'] ?? '');
        if ($to === '') {
            echo json_encode(['success' => false, 'error' => 'Patient has no phone number on file.']); exit;
        }

        $result = WhatsAppSender::send($to, $body, $templateName !== '' ? $templateName : null);

        $status = $result['success'] ? 'SENT' : 'FAILED';
        $ins = $pdo->prepare(
            'INSERT INTO whatsapp_messages
               (patient_id, to_phone_e164, template_name, body, status,
                provider_message_id, error_message, sent_by_user_id)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $ins->execute([
            $patientId,
            $to,
            $templateName !== '' ? $templateName : null,
            $body !== '' ? $body : ('[template] ' . $templateName),
            $status,
            $result['provider_message_id'],
            $result['error'],
            (int)$_SESSION['user_id'],
        ]);
        $id = (int)$pdo->lastInsertId();

        echo json_encode([
            'success'      => $result['success'],
            'error'        => $result['error'],
            'message_id'   => $id,
            'status'       => $status,
            'to'           => $to,
            'sent_at'      => date('d M Y H:i'),
        ]);
        exit;
    }

    /** GET — JSON list of messages for a patient. */
    public function history(): void
    {
        $this->requireAccess();
        header('Content-Type: application/json');
        $patientId = (int)($_GET['patient_id'] ?? 0);
        if ($patientId <= 0) { echo json_encode(['success' => false, 'messages' => []]); exit; }
        $pdo = getDBConnection();
        $stmt = $pdo->prepare(
            "SELECT wm.whatsapp_message_id, wm.to_phone_e164, wm.template_name, wm.body,
                    wm.status, wm.provider_message_id, wm.error_message, wm.sent_at,
                    TRIM(CONCAT(u.first_name,' ',u.last_name)) AS sent_by
               FROM whatsapp_messages wm
               JOIN users u ON u.user_id = wm.sent_by_user_id
              WHERE wm.patient_id = ?
              ORDER BY wm.sent_at DESC
              LIMIT 100"
        );
        $stmt->execute([$patientId]);
        echo json_encode([
            'success'    => true,
            'configured' => WhatsAppSender::isConfigured(),
            'messages'   => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ]);
        exit;
    }

    /** GET — full outbox view (staff-facing page). */
    public function outbox(): void
    {
        $this->requireAccess();
        $pdo = getDBConnection();
        $rows = $pdo->query(
            "SELECT wm.*, TRIM(CONCAT(u.first_name,' ',u.last_name)) AS sent_by,
                    TRIM(CONCAT(p.first_name,' ',p.last_name)) AS patient_name,
                    p.patient_code
               FROM whatsapp_messages wm
               JOIN users u ON u.user_id = wm.sent_by_user_id
               LEFT JOIN patients p ON p.patient_id = wm.patient_id
              ORDER BY wm.sent_at DESC
              LIMIT 500"
        )->fetchAll(PDO::FETCH_ASSOC);
        $configured = WhatsAppSender::isConfigured();
        $whatsappRows = $rows;
        require __DIR__ . '/../views/whatsapp_outbox.php';
    }
}
