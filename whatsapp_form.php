<?php
/**
 * whatsapp_form.php – staff-facing WhatsApp form workflow.
 *
 * Routes:
 *   POST ?action=send-link → generate + send a patient form link (JSON)
 *   POST ?action=attach    → mark submission processed (JSON)
 *   POST ?action=discard   → mark submission discarded (JSON)
 *   GET  (default)         → submission list
 */

require_once __DIR__ . '/app/config/session.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require __DIR__ . '/app/middleware/auth.php';
require_once __DIR__ . '/app/controllers/WhatsAppFormController.php';

$controller = new WhatsAppFormController();
$action     = $_GET['action'] ?? '';

switch ($action) {
    case 'send-link':
        $controller->sendLink();
        break;
    case 'attach':
        $controller->attach();
        break;
    case 'discard':
        $controller->discard();
        break;
    default:
        $controller->index();
        break;
}
