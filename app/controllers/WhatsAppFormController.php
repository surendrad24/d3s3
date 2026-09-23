<?php
/**
 * WhatsAppFormController – "share an intake form via WhatsApp" workflow.
 *
 * Staff routes (require patient_data access):
 *   POST send-link     → generate a token and WhatsApp a form link to a patient
 *   GET  index         → pending / processed submission list
 *   POST attach        → promote a submission into a patient record + closes it
 *   POST discard       → mark a submission DISCARDED
 *
 * Public routes (no auth — token is the credential):
 *   GET  form?token=T  → renders the patient-facing form
 *   POST submit        → accepts the form submission
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../../permissions.php';
require_once __DIR__ . '/../services/WhatsAppSender.php';

class WhatsAppFormController
{
    private function requireStaff(): void
    {
        $role = $_SESSION['user_role'] ?? '';
        if (!can($role, 'patient_data')) {
            http_response_code(403);
            exit('Access denied.');
        }
    }

    /** Staff — POST — generate token, log message, send WhatsApp with link. */
    public function sendLink(): void
    {
        $this->requireStaff();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'error' => 'POST required']); exit;
        }
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Invalid security token.']); exit;
        }

        $patientId = (int)($_POST['patient_id'] ?? 0) ?: null;
        $phone     = trim((string)($_POST['to_phone'] ?? ''));
        $pdo       = getDBConnection();

        if ($patientId) {
            $stmt = $pdo->prepare('SELECT phone_e164 FROM patients WHERE patient_id = ?');
            $stmt->execute([$patientId]);
            $row = $stmt->fetch();
            if (!$row) { echo json_encode(['success' => false, 'error' => 'Patient not found.']); exit; }
            if ($phone === '') $phone = (string)$row['phone_e164'];
        }
        if ($phone === '') {
            echo json_encode(['success' => false, 'error' => 'A destination phone number is required.']); exit;
        }

        $token = bin2hex(random_bytes(24));
        $pdo->prepare(
            'INSERT INTO whatsapp_form_submissions (token, patient_id, to_phone_e164, sent_by_user_id)
             VALUES (?, ?, ?, ?)'
        )->execute([$token, $patientId, $phone, (int)$_SESSION['user_id']]);

        $link  = self::baseUrl() . '/intake_link.php?token=' . $token;
        $body  = "Hello — please fill in your pre-intake form here:\n" . $link
               . "\nThis link is single-use and expires after submission.";

        $result = WhatsAppSender::send($phone, $body);
        $status = $result['success'] ? 'SENT' : 'FAILED';
        $pdo->prepare(
            'INSERT INTO whatsapp_messages
               (patient_id, to_phone_e164, template_name, body, status,
                provider_message_id, error_message, sent_by_user_id)
             VALUES (?, ?, NULL, ?, ?, ?, ?, ?)'
        )->execute([
            $patientId, $phone, $body, $status,
            $result['provider_message_id'], $result['error'], (int)$_SESSION['user_id'],
        ]);

        echo json_encode([
            'success' => true,
            'link'    => $link,
            'sent'    => $result['success'],
            'error'   => $result['error'],
        ]);
        exit;
    }

    /** Staff — GET — list submissions. */
    public function index(): void
    {
        $this->requireStaff();
        $pdo = getDBConnection();
        $rows = $pdo->query(
            "SELECT s.*, TRIM(CONCAT(u.first_name,' ',u.last_name)) AS sent_by,
                    TRIM(CONCAT(p.first_name,' ',p.last_name)) AS patient_name,
                    p.patient_code
               FROM whatsapp_form_submissions s
               JOIN users u ON u.user_id = s.sent_by_user_id
               LEFT JOIN patients p ON p.patient_id = s.patient_id
              ORDER BY s.created_at DESC
              LIMIT 300"
        )->fetchAll(PDO::FETCH_ASSOC);
        $submissions = $rows;
        require __DIR__ . '/../views/whatsapp_form_list.php';
    }

    /** Staff — POST — mark submission attached / processed with notes. */
    public function attach(): void
    {
        $this->requireStaff();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false]); exit; }
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Invalid security token.']); exit;
        }
        $id     = (int)($_POST['submission_id'] ?? 0);
        $target = (int)($_POST['patient_id']    ?? 0) ?: null;
        $notes  = trim((string)($_POST['notes'] ?? ''));
        if ($id <= 0) { echo json_encode(['success' => false, 'error' => 'Missing id.']); exit; }
        $pdo = getDBConnection();
        $pdo->prepare(
            "UPDATE whatsapp_form_submissions
                SET status = 'PROCESSED',
                    processed_by_user_id = ?, processed_at = NOW(),
                    patient_id = COALESCE(?, patient_id),
                    notes = ?
              WHERE submission_id = ?"
        )->execute([(int)$_SESSION['user_id'], $target, $notes ?: null, $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    /** Staff — POST — mark submission discarded. */
    public function discard(): void
    {
        $this->requireStaff();
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(['success' => false]); exit; }
        if (!hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'Invalid security token.']); exit;
        }
        $id = (int)($_POST['submission_id'] ?? 0);
        if ($id <= 0) { echo json_encode(['success' => false, 'error' => 'Missing id.']); exit; }
        $pdo = getDBConnection();
        $pdo->prepare(
            "UPDATE whatsapp_form_submissions
                SET status='DISCARDED', processed_by_user_id=?, processed_at=NOW()
              WHERE submission_id=?"
        )->execute([(int)$_SESSION['user_id'], $id]);
        echo json_encode(['success' => true]);
        exit;
    }

    // ─── Public routes ────────────────────────────────────────────

    /** Public — GET — render the patient-facing form. */
    public function showForm(): void
    {
        $token = (string)($_GET['token'] ?? '');
        $error = null;
        $submission = $this->loadValidToken($token, $error);
        // Even for used / invalid tokens we still render the same view with $error.
        $submitted = $submission && $submission['submitted_at'];
        $prefill   = $submission && !empty($submission['form_data'])
            ? (json_decode((string)$submission['form_data'], true) ?: [])
            : [];
        $csrfToken = $token; // token doubles as the anti-CSRF nonce for this public form
        require __DIR__ . '/../views/whatsapp_form_public.php';
    }

    /** Public — POST — accept form submission. */
    public function submitForm(): void
    {
        $token = (string)($_POST['token'] ?? '');
        $error = null;
        $submission = $this->loadValidToken($token, $error);
        if (!$submission || $submission['submitted_at']) {
            http_response_code(400);
            $submitted = true;
            $prefill   = [];
            $csrfToken = $token;
            require __DIR__ . '/../views/whatsapp_form_public.php';
            return;
        }

        $data = [
            'full_name'       => trim((string)($_POST['full_name']       ?? '')),
            'date_of_birth'   => trim((string)($_POST['date_of_birth']   ?? '')),
            'sex'             => trim((string)($_POST['sex']             ?? '')),
            'phone'           => trim((string)($_POST['phone']           ?? '')),
            'email'           => trim((string)($_POST['email']           ?? '')),
            'chief_complaint' => trim((string)($_POST['chief_complaint'] ?? '')),
            'symptoms'        => trim((string)($_POST['symptoms']        ?? '')),
            'allergies'       => trim((string)($_POST['allergies']       ?? '')),
            'medications'     => trim((string)($_POST['medications']     ?? '')),
            'past_history'    => trim((string)($_POST['past_history']    ?? '')),
        ];

        // Minimal validation.
        if ($data['full_name'] === '' || $data['chief_complaint'] === '') {
            $error = 'Please provide at least your name and chief complaint.';
            $submitted = false;
            $prefill   = $data;
            $csrfToken = $token;
            require __DIR__ . '/../views/whatsapp_form_public.php';
            return;
        }

        $pdo = getDBConnection();
        $pdo->prepare(
            "UPDATE whatsapp_form_submissions
                SET form_data = ?, submitted_at = NOW()
              WHERE submission_id = ?"
        )->execute([json_encode($data, JSON_UNESCAPED_UNICODE), (int)$submission['submission_id']]);

        // Re-render the view in the "thank you" state.
        $submitted = true;
        $prefill   = $data;
        $csrfToken = $token;
        require __DIR__ . '/../views/whatsapp_form_public.php';
    }

    private function loadValidToken(string $token, ?string &$error): ?array
    {
        if (!preg_match('/^[a-f0-9]{48}$/', $token)) {
            $error = 'This link is invalid.';
            return null;
        }
        $pdo  = getDBConnection();
        $stmt = $pdo->prepare('SELECT * FROM whatsapp_form_submissions WHERE token = ?');
        $stmt->execute([$token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $error = 'This link is invalid or has been withdrawn.';
            return null;
        }
        if ($row['status'] === 'DISCARDED') {
            $error = 'This form has been closed by our team.';
            return null;
        }
        return $row;
    }

    private static function baseUrl(): string
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        $base   = rtrim(str_replace('\\', '/', dirname($script)), '/');
        return $scheme . '://' . $host . $base;
    }
}
