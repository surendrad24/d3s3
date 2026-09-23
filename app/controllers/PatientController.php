<?php
/**
 * app/controllers/PatientController.php
 *
 * Handles the patient records page:
 *   - Patient search / list
 *   - Full patient profile view (demographics, case sheets, grievances)
 *   - AJAX patient search
 *
 * IMPORTANT: Every call to view() automatically writes a row to
 * patient_record_access_log so that administrators can audit who has
 * been looking at patient records and when.  Only roles with
 * patient_data read access can reach this controller.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/permissions.php';

class PatientController
{
	// ── Search / list ─────────────────────────────────────────

	public function index(): void
	{
		$this->requireRead();

		$flashError = null;
		if (isset($_SESSION['patients_error'])) {
			$flashError = $_SESSION['patients_error'];
			unset($_SESSION['patients_error']);
		}

		require __DIR__ . '/../views/patients.php';
	}

	// ── Patient profile view ──────────────────────────────────

	public function view(int $patientId): void
	{
		$this->requireRead();

		if ($patientId <= 0) {
			header('Location: patients.php');
			exit;
		}

		$pdo  = getDBConnection();
		$role = $_SESSION['user_role'] ?? '';

		// Flash messages from portal account actions
		$flashSuccess = null;
		if (isset($_SESSION['patients_success'])) {
			$flashSuccess = $_SESSION['patients_success'];
			unset($_SESSION['patients_success']);
		}
		$flashError = null;
		if (isset($_SESSION['patients_error'])) {
			$flashError = $_SESSION['patients_error'];
			unset($_SESSION['patients_error']);
		}

		// Load patient record
		$stmt = $pdo->prepare('SELECT * FROM patients WHERE patient_id = ?');
		$stmt->execute([$patientId]);
		$patient = $stmt->fetch();

		if (!$patient) {
			$_SESSION['patients_error'] = 'Patient record not found.';
			header('Location: patients.php');
			exit;
		}

		// Load portal account (if any)
		$portalAccount = null;
		try {
			$stmt = $pdo->prepare(
				'SELECT pa.*, u.first_name AS created_by_first, u.last_name AS created_by_last
				   FROM patient_accounts pa
				   LEFT JOIN users u ON u.user_id = pa.created_by_user_id
				  WHERE pa.patient_id = ?'
			);
			$stmt->execute([$patientId]);
			$portalAccount = $stmt->fetch() ?: null;
		} catch (Throwable $e) {
			$portalAccount = null; // table not yet created
		}

		$canManagePortal = can($role, 'users', 'W');

		// Load all case sheets for this patient (most recent first),
		// joining to get the names of the intake nurse and assigned doctor.
		$stmt = $pdo->prepare(
			'SELECT cs.*,
			        u1.first_name AS creator_first, u1.last_name AS creator_last,
			        u2.first_name AS doctor_first,  u2.last_name AS doctor_last
			   FROM case_sheets cs
			   LEFT JOIN users u1 ON u1.user_id = cs.created_by_user_id
			   LEFT JOIN users u2 ON u2.user_id = cs.assigned_doctor_user_id
			  WHERE cs.patient_id = ?
			  ORDER BY cs.visit_datetime DESC'
		);
		$stmt->execute([$patientId]);
		$caseSheets = $stmt->fetchAll();

		// Load patient-portal grievances / feedback (only for roles that can
		// read the feedback resource — same gate as the Feedback menu item).
		$grievances     = [];
		$canSeeFeedback = can($role, 'feedback');
		if ($canSeeFeedback) {
			try {
				$stmt = $pdo->prepare(
					'SELECT pf.feedback_id, pf.feedback_type, pf.rating,
					        pf.feedback_text, pf.status, pf.admin_notes,
					        pf.created_at, pf.updated_at,
					        u.first_name AS related_first, u.last_name AS related_last
					   FROM patient_feedback pf
					   LEFT JOIN users u ON u.user_id = pf.related_user_id
					  WHERE pf.patient_id = ?
					  ORDER BY pf.created_at DESC'
				);
				$stmt->execute([$patientId]);
				$grievances = $stmt->fetchAll();
			} catch (Throwable $e) {
				$grievances = [];
			}
		}

		// Load access log — SUPER_ADMIN and ADMIN only.
		// We load BEFORE logging the current visit so the admin sees the
		// history up to (but not including) this exact page load.
		$accessLog       = [];
		$canSeeAccessLog = in_array($role, ['SUPER_ADMIN', 'ADMIN'], true);
		if ($canSeeAccessLog) {
			try {
				$stmt = $pdo->prepare(
					'SELECT pal.log_id, pal.access_type, pal.ip_address,
					        pal.accessed_at,
					        u.first_name, u.last_name, u.role AS viewer_role
					   FROM patient_record_access_log pal
					   JOIN users u ON u.user_id = pal.accessed_by_user_id
					  WHERE pal.patient_id = ?
					  ORDER BY pal.accessed_at DESC
					  LIMIT 200'
				);
				$stmt->execute([$patientId]);
				$accessLog = $stmt->fetchAll();
			} catch (Throwable $e) {
				$accessLog = [];
			}
		}

		// Write access log entry for the current visit.
		$this->logAccess($pdo, $patientId, 'VIEW_PROFILE');

		require __DIR__ . '/../views/patient_profile.php';
	}

	// ── AJAX patient search ───────────────────────────────────
	// Accepts GET params: name (string), dob (YYYY-MM-DD).
	// At least one must be supplied; name alone requires ≥2 chars.
	// Returns JSON array with visit_count, last_visit, and allergies
	// so the front-end can show a rich, clinically useful result row.

	public function searchAjax(): void
	{
		$this->requireRead();

		header('Content-Type: application/json; charset=utf-8');

		$name = trim($_GET['name'] ?? '');
		$dob  = trim($_GET['dob']  ?? '');

		// For name-only searches, require ≥2 chars once the user starts typing;
		// an empty query is fine — we return the recent-patients list.
		if ($name !== '' && mb_strlen($name) < 2 && $dob === '') {
			echo json_encode([]);
			exit;
		}

		$pdo        = getDBConnection();
		$conditions = [];
		$params     = [];

		if ($name !== '') {
			$escaped      = str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $name);
			$like         = '%' . $escaped . '%';
			$conditions[] = "(p.first_name LIKE ? ESCAPE '\\\\' OR p.last_name LIKE ? ESCAPE '\\\\'"
			              . " OR CONCAT(p.first_name, ' ', IFNULL(p.last_name, '')) LIKE ? ESCAPE '\\\\')";
			array_push($params, $like, $like, $like);
		}

		if ($dob !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dob)) {
			$conditions[] = 'p.date_of_birth = ?';
			$params[]     = $dob;
		}

		$where = empty($conditions) ? '1 = 1' : implode(' AND ', $conditions);

		// With no filters, sort by most-recently-created so staff see the fresh
		// patients first. With filters, alphabetical is more useful.
		$orderBy = empty($conditions)
			? 'p.created_at DESC, p.patient_id DESC'
			: 'p.last_name, p.first_name';

		$stmt = $pdo->prepare(
			"SELECT p.patient_id, p.patient_code,
			        p.first_name, p.last_name,
			        p.sex, p.date_of_birth, p.age_years,
			        p.phone_e164, p.city,
			        p.blood_group, p.allergies,
			        p.is_active,
			        COUNT(cs.case_sheet_id) AS visit_count,
			        MAX(cs.visit_datetime)  AS last_visit
			   FROM patients p
			   LEFT JOIN case_sheets cs ON cs.patient_id = p.patient_id
			  WHERE {$where}
			  GROUP BY p.patient_id
			  ORDER BY {$orderBy}
			  LIMIT 50"
		);
		$stmt->execute($params);

		echo json_encode($stmt->fetchAll());
		exit;
	}

	// ── Patient tracking view ─────────────────────────────────

	public function track(int $patientId): void
	{
		$this->requireRead();
		if ($patientId <= 0) {
			header('Location: patients.php'); exit;
		}

		$pdo = getDBConnection();

		$stmt = $pdo->prepare(
			'SELECT patient_id, patient_code, first_name, last_name, sex, date_of_birth,
			        age_years, phone_e164, city, blood_group
			   FROM patients WHERE patient_id = ?'
		);
		$stmt->execute([$patientId]);
		$patient = $stmt->fetch();
		if (!$patient) { header('Location: patients.php'); exit; }

		$this->logAccess($pdo, $patientId, 'VIEW');

		// Active (open) case sheet, if any
		$stmt = $pdo->prepare(
			"SELECT cs.*, TRIM(CONCAT(u.first_name,' ',u.last_name)) AS doctor_name
			   FROM case_sheets cs
			   LEFT JOIN users u ON u.user_id = cs.assigned_doctor_user_id
			  WHERE cs.patient_id = ? AND cs.is_closed = 0
			  ORDER BY cs.visit_datetime DESC LIMIT 1"
		);
		$stmt->execute([$patientId]);
		$activeCase = $stmt->fetch() ?: null;

		$statusTimeline = [];
		$labOrders      = [];
		$consultants    = [];
		$voiceCounts    = ['PATIENT' => 0, 'DOCTOR' => 0];

		if ($activeCase) {
			$stmt = $pdo->prepare(
				"SELECT al.new_value AS status, al.changed_at,
				        TRIM(CONCAT(u.first_name,' ',u.last_name)) AS actor
				   FROM case_sheet_audit_log al
				   JOIN users u ON u.user_id = al.user_id
				  WHERE al.case_sheet_id = ? AND al.field_name = 'status'
				  ORDER BY al.changed_at ASC"
			);
			$stmt->execute([$activeCase['case_sheet_id']]);
			$statusTimeline = $stmt->fetchAll();

			try {
				$stmt = $pdo->prepare(
					'SELECT lo.lab_order_id, lo.test_name, lo.status, lo.ordered_at, lo.completed_at
					   FROM lab_orders lo
					  WHERE lo.case_sheet_id = ?
					  ORDER BY lo.ordered_at ASC'
				);
				$stmt->execute([$activeCase['case_sheet_id']]);
				$labOrders = $stmt->fetchAll();
			} catch (Throwable $e) { $labOrders = []; }

			try {
				$stmt = $pdo->prepare(
					"SELECT csc.doctor_user_id, csc.role_note,
					        TRIM(CONCAT(u.first_name,' ',u.last_name)) AS name
					   FROM case_sheet_consultants csc
					   JOIN users u ON u.user_id = csc.doctor_user_id
					  WHERE csc.case_sheet_id = ?
					  ORDER BY csc.added_at ASC"
				);
				$stmt->execute([$activeCase['case_sheet_id']]);
				$consultants = $stmt->fetchAll();
			} catch (Throwable $e) { $consultants = []; }

			try {
				$stmt = $pdo->prepare(
					"SELECT source, COUNT(*) AS n
					   FROM case_sheet_voice_notes
					  WHERE case_sheet_id = ?
					  GROUP BY source"
				);
				$stmt->execute([$activeCase['case_sheet_id']]);
				foreach ($stmt->fetchAll() as $r) { $voiceCounts[$r['source']] = (int)$r['n']; }
			} catch (Throwable $e) { /* table not yet migrated */ }
		}

		// Prior visits timeline
		$stmt = $pdo->prepare(
			"SELECT cs.case_sheet_id, cs.visit_datetime, cs.visit_type, cs.status,
			        cs.closure_type, cs.chief_complaint, cs.is_closed, cs.closed_at,
			        TRIM(CONCAT(u.first_name,' ',u.last_name)) AS doctor_name
			   FROM case_sheets cs
			   LEFT JOIN users u ON u.user_id = cs.assigned_doctor_user_id
			  WHERE cs.patient_id = ? AND cs.is_closed = 1
			  ORDER BY cs.visit_datetime DESC LIMIT 25"
		);
		$stmt->execute([$patientId]);
		$priorVisits = $stmt->fetchAll();

		require __DIR__ . '/../views/patient_tracking.php';
	}

	// ── Access log writer ─────────────────────────────────────

	private function logAccess(PDO $pdo, int $patientId, string $type): void
	{
		try {
			$ip        = substr($_SERVER['REMOTE_ADDR']      ?? '', 0, 45);
			$userAgent = substr($_SERVER['HTTP_USER_AGENT']  ?? '', 0, 500);

			$pdo->prepare(
				'INSERT INTO patient_record_access_log
				    (patient_id, accessed_by_user_id, access_type, ip_address, user_agent)
				 VALUES (?, ?, ?, ?, ?)'
			)->execute([$patientId, $_SESSION['user_id'], $type, $ip ?: null, $userAgent ?: null]);
		} catch (Throwable $e) {
			// Silently skip — if the table hasn't been migrated yet the page
			// continues to function; the admin will see an empty log.
		}
	}

	// ── Permission guard ──────────────────────────────────────

	private function requireRead(): void
	{
		if (!can($_SESSION['user_role'] ?? '', 'patient_data')) {
			$_SESSION['dashboard_notice'] = 'You do not have permission to access patient records.';
			header('Location: dashboard.php');
			exit;
		}
	}
}
