<?php
/**
 * app/controllers/LabResultsController.php
 *
 * Handles the Labwork queue:
 *   index()         – lists all PENDING lab orders for the lab technician
 *   completeOrder() – AJAX POST: marks an order COMPLETED with result notes
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/permissions.php';

class LabResultsController
{
	private const UPLOAD_BASE   = 'uploads/lab_reports/';
	private const MAX_FILE_BYTES = 15 * 1024 * 1024; // 15 MB
	private const ALLOWED_MIME  = [
		'image/jpeg'      => 'jpg',
		'image/png'       => 'png',
		'image/webp'      => 'webp',
		'application/pdf' => 'pdf',
	];

	// ── Labwork queue (GET) ──────────────────────────────

	public function index(): void
	{
		$this->requireRead();

		$pdo = getDBConnection();

		// Pending orders – oldest first so the queue is FIFO
		try {
			$stmt = $pdo->prepare(
				'SELECT lo.order_id, lo.test_name, lo.order_notes, lo.ordered_at,
				        lo.case_sheet_id,
				        p.first_name, p.last_name, p.patient_code,
				        u.first_name AS ordered_by_first, u.last_name AS ordered_by_last
				   FROM lab_orders lo
				   JOIN patients p ON p.patient_id  = lo.patient_id
				   JOIN users    u ON u.user_id      = lo.ordered_by_user_id
				  WHERE lo.status = ?
				  ORDER BY lo.ordered_at ASC'
			);
			$stmt->execute(['PENDING']);
			$pendingOrders = $stmt->fetchAll();

			// Completed orders from the last 48 hours (most recent first)
			$stmt = $pdo->prepare(
				'SELECT lo.order_id, lo.test_name, lo.order_notes, lo.result_notes,
				        lo.result_image_path, lo.result_image_name,
				        lo.ordered_at, lo.completed_at, lo.case_sheet_id,
				        p.first_name, p.last_name, p.patient_code,
				        u.first_name  AS ordered_by_first,  u.last_name  AS ordered_by_last,
				        uc.first_name AS completed_by_first, uc.last_name AS completed_by_last
				   FROM lab_orders lo
				   JOIN patients p  ON p.patient_id   = lo.patient_id
				   JOIN users    u  ON u.user_id       = lo.ordered_by_user_id
				   LEFT JOIN users uc ON uc.user_id    = lo.completed_by_user_id
				  WHERE lo.status = ?
				    AND lo.completed_at >= NOW() - INTERVAL 48 HOUR
				  ORDER BY lo.completed_at DESC
				  LIMIT 50'
			);
			$stmt->execute(['COMPLETED']);
			$recentCompleted = $stmt->fetchAll();
		} catch (Exception $e) {
			// lab_orders table not yet created
			$pendingOrders   = [];
			$recentCompleted = [];
		}

		require __DIR__ . '/../views/lab_results.php';
	}

	// ── Complete a lab order (AJAX POST) ─────────────────

	public function completeOrder(): void
	{
		$this->requireWrite();
		header('Content-Type: application/json');

		if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
			echo json_encode(['success' => false, 'message' => 'POST required']);
			exit;
		}

		// Accept either JSON (no file) or multipart/form-data (with optional file).
		$isMultipart = !empty($_POST);
		if ($isMultipart) {
			$csrf        = $_POST['csrf_token']   ?? '';
			$orderId     = (int)($_POST['order_id'] ?? 0);
			$resultNotes = trim($_POST['result_notes'] ?? '');
		} else {
			$input       = json_decode(file_get_contents('php://input'), true) ?: [];
			$csrf        = $input['csrf_token']   ?? '';
			$orderId     = (int)($input['order_id'] ?? 0);
			$resultNotes = trim($input['result_notes'] ?? '');
		}

		if (!hash_equals($_SESSION['csrf_token'] ?? '', $csrf)) {
			echo json_encode(['success' => false, 'message' => 'Invalid security token.']);
			exit;
		}
		if ($orderId <= 0) {
			echo json_encode(['success' => false, 'message' => 'Invalid order ID.']);
			exit;
		}

		// Optional image/PDF upload
		$imagePath = null;
		$imageName = null;
		if ($isMultipart
			&& isset($_FILES['result_image'])
			&& $_FILES['result_image']['error'] !== UPLOAD_ERR_NO_FILE
		) {
			$upload = $this->handleFileUpload($_FILES['result_image']);
			if (is_string($upload)) {
				echo json_encode(['success' => false, 'message' => $upload]);
				exit;
			}
			$imagePath = $upload['path'];
			$imageName = $upload['name'];
		}

		$pdo  = getDBConnection();
		$stmt = $pdo->prepare(
			'UPDATE lab_orders
			    SET status                = ?,
			        completed_by_user_id  = ?,
			        completed_at          = NOW(),
			        result_notes          = ?,
			        result_image_path     = ?,
			        result_image_name     = ?
			  WHERE order_id = ?
			    AND status   = ?'
		);
		$stmt->execute([
			'COMPLETED',
			$_SESSION['user_id'],
			$resultNotes !== '' ? $resultNotes : null,
			$imagePath,
			$imageName,
			$orderId,
			'PENDING',
		]);

		if ($stmt->rowCount() === 0) {
			echo json_encode(['success' => false, 'message' => 'Order not found or already completed.']);
			exit;
		}

		echo json_encode(['success' => true]);
		exit;
	}

	// ── Serve an uploaded lab report file (permission-gated) ────────────

	public function downloadFile(): void
	{
		if (empty($_SESSION['user_id'])) {
			http_response_code(403); exit('Access denied.');
		}
		if (!can($_SESSION['user_role'] ?? '', 'labwork')
			&& !can($_SESSION['user_role'] ?? '', 'case_sheets')
		) {
			http_response_code(403); exit('Access denied.');
		}
		$orderId = (int)($_GET['order_id'] ?? 0);
		if ($orderId <= 0) { http_response_code(404); exit; }

		$pdo  = getDBConnection();
		$stmt = $pdo->prepare('SELECT result_image_path, result_image_name FROM lab_orders WHERE order_id = ?');
		$stmt->execute([$orderId]);
		$row = $stmt->fetch();
		if (!$row || empty($row['result_image_path'])) {
			http_response_code(404); exit('File not found.');
		}

		$rel = $row['result_image_path'];
		// Prevent traversal
		if (strpos($rel, '..') !== false) { http_response_code(400); exit; }

		$full = __DIR__ . '/../../' . self::UPLOAD_BASE . $rel;
		if (!is_file($full)) { http_response_code(404); exit('File missing on server.'); }

		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mime  = $finfo->file($full) ?: 'application/octet-stream';
		$name  = $row['result_image_name'] ?: basename($full);

		header('Content-Type: ' . $mime);
		header('Content-Disposition: inline; filename="' . rawurlencode($name) . '"');
		header('Content-Length: ' . (string)filesize($full));
		header('X-Content-Type-Options: nosniff');
		header('Cache-Control: private, max-age=3600');
		readfile($full);
		exit;
	}

	// ── File upload helper ──────────────────────────────────────────────

	/** @return string|array error message, or [path, name, size] */
	private function handleFileUpload(array $file): string|array
	{
		if ($file['error'] !== UPLOAD_ERR_OK) {
			return 'Upload failed (code ' . $file['error'] . ').';
		}
		if ($file['size'] > self::MAX_FILE_BYTES) {
			return 'File too large. Maximum 15 MB.';
		}
		$finfo = new finfo(FILEINFO_MIME_TYPE);
		$mime  = $finfo->file($file['tmp_name']);
		if (!isset(self::ALLOWED_MIME[$mime])) {
			return 'Unsupported file type. Allowed: JPG, PNG, WebP, PDF.';
		}
		$ext    = self::ALLOWED_MIME[$mime];
		$uuid   = bin2hex(random_bytes(16));
		$subdir = date('Y/m');
		$rel    = $subdir . '/' . $uuid . '.' . $ext;
		$dir    = __DIR__ . '/../../' . self::UPLOAD_BASE . $subdir;
		if (!is_dir($dir) && !mkdir($dir, 0755, true)) {
			return 'Could not create upload directory.';
		}
		if (!move_uploaded_file($file['tmp_name'], $dir . '/' . $uuid . '.' . $ext)) {
			return 'Could not save the uploaded file.';
		}
		return ['path' => $rel, 'name' => $file['name'], 'size' => $file['size']];
	}

	// ── Role guards ──────────────────────────────────────

	private function requireRead(): void
	{
		if (!can($_SESSION['user_role'] ?? '', 'labwork')) {
			$_SESSION['dashboard_notice'] = 'You do not have permission to access Labwork.';
			header('Location: dashboard.php');
			exit;
		}
	}

	private function requireWrite(): void
	{
		if (!can($_SESSION['user_role'] ?? '', 'labwork', 'W')) {
			header('Content-Type: application/json');
			echo json_encode(['success' => false, 'message' => 'Permission denied.']);
			exit;
		}
	}
}
