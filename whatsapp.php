<?php
/**
 * whatsapp.php – WhatsApp outbound entry-point.
 *
 * Routes:
 *   POST ?action=send      → send a WhatsApp message (JSON)
 *   GET  ?action=history   → JSON list of a patient's messages
 *   GET  (default)         → global outbox page
 */

require_once __DIR__ . '/app/config/session.php';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require __DIR__ . '/app/middleware/auth.php';
require_once __DIR__ . '/app/controllers/WhatsAppController.php';

$controller = new WhatsAppController();
$action     = $_GET['action'] ?? '';

switch ($action) {
    case 'send':
        $controller->send();
        break;
    case 'history':
        $controller->history();
        break;
    default:
        $controller->outbox();
        break;
}
