<?php
/**
 * referral_pdf.php – Printable referral letter.
 *
 * Designed to be printed / saved as PDF via the browser
 * (Print → Save as PDF). Opens in a new tab, auto-invokes
 * window.print() on load. Server-side PDF libraries are
 * intentionally not required.
 *
 * Provided vars: $caseSheet, $patient
 */
$pName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
$dob   = !empty($patient['date_of_birth']) ? date('d M Y', strtotime($patient['date_of_birth'])) : '';
$age   = $patient['age_years'] ?? '';
$sex   = $patient['sex'] ?? '';
$visit = !empty($caseSheet['visit_datetime']) ? date('d M Y', strtotime($caseSheet['visit_datetime'])) : date('d M Y');
$doctor= trim($caseSheet['doctor_name'] ?? $caseSheet['assigned_doctor_name'] ?? '');
$refTo = trim($caseSheet['referral_to'] ?? '');
$refWhy= trim($caseSheet['referral_reason'] ?? '');
$dx    = trim($caseSheet['doctor_diagnosis'] ?? $caseSheet['diagnosis'] ?? '');
$ax    = trim($caseSheet['doctor_assessment'] ?? $caseSheet['assessment'] ?? '');
$plan  = trim($caseSheet['doctor_plan_notes'] ?? $caseSheet['plan_notes'] ?? '');
$hpi   = trim($caseSheet['history_present_illness'] ?? '');
$cc    = trim($caseSheet['chief_complaint'] ?? '');
$allerg= trim($patient['allergies'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<title>Referral Letter — <?= htmlspecialchars($pName) ?></title>
	<style>
		body { font-family: 'Georgia', 'Times New Roman', serif; color: #222; margin: 0; padding: 32px 40px; }
		.letterhead { border-bottom: 2px solid #2c3e50; padding-bottom: 8px; margin-bottom: 24px; display: flex; justify-content: space-between; align-items: flex-end; }
		.letterhead h1 { margin: 0; font-size: 22px; color: #2c3e50; letter-spacing: 1px; }
		.letterhead .meta { font-size: 12px; color: #666; text-align: right; }
		h2.section { font-size: 14px; color: #2c3e50; border-bottom: 1px solid #d0d0d0; padding-bottom: 4px; margin: 22px 0 10px; text-transform: uppercase; letter-spacing: .5px; }
		.info-grid { display: flex; flex-wrap: wrap; gap: 6px 32px; font-size: 13px; margin-bottom: 6px; }
		.info-grid .k { color: #666; font-weight: bold; margin-right: 4px; }
		p { font-size: 13px; line-height: 1.55; margin: 6px 0; }
		.narrative { white-space: pre-wrap; font-size: 13px; line-height: 1.55; }
		.salutation { margin-top: 24px; }
		.body-copy p { margin: 10px 0; }
		.sig-block { margin-top: 60px; font-size: 13px; }
		.sig-block .sig-line { border-top: 1px solid #333; width: 260px; padding-top: 4px; }
		.no-print { position: fixed; top: 10px; right: 10px; }
		@media print { .no-print { display: none; } body { padding: 20mm 20mm; } }
	</style>
</head>
<body>
	<div class="no-print">
		<button onclick="window.print()" style="padding:6px 12px;">Print / Save as PDF</button>
	</div>

	<div class="letterhead">
		<div>
			<h1>Referral Letter</h1>
			<div style="font-size:11px;color:#666;">Case Sheet #<?= (int)$caseSheet['case_sheet_id'] ?></div>
		</div>
		<div class="meta">
			Date: <strong><?= date('d M Y') ?></strong><br>
			Visit date: <?= htmlspecialchars($visit) ?>
		</div>
	</div>

	<h2 class="section">Referring Physician</h2>
	<div class="info-grid">
		<div><span class="k">Doctor:</span> <?= htmlspecialchars($doctor ?: '—') ?></div>
		<?php if (!empty($caseSheet['doctor_email'])): ?>
			<div><span class="k">Email:</span> <?= htmlspecialchars($caseSheet['doctor_email']) ?></div>
		<?php endif; ?>
		<?php if (!empty($caseSheet['doctor_phone'])): ?>
			<div><span class="k">Phone:</span> <?= htmlspecialchars($caseSheet['doctor_phone']) ?></div>
		<?php endif; ?>
	</div>

	<h2 class="section">Referred To</h2>
	<p><strong><?= htmlspecialchars($refTo ?: 'Concerned Specialist') ?></strong></p>

	<h2 class="section">Patient Information</h2>
	<div class="info-grid">
		<div><span class="k">Name:</span> <?= htmlspecialchars($pName) ?></div>
		<div><span class="k">Patient ID:</span> <?= htmlspecialchars($patient['patient_code'] ?? '') ?></div>
		<?php if ($sex !== ''): ?><div><span class="k">Sex:</span> <?= htmlspecialchars($sex) ?></div><?php endif; ?>
		<?php if ($age !== '' && $age !== null): ?><div><span class="k">Age:</span> <?= htmlspecialchars((string)$age) ?> yr</div><?php endif; ?>
		<?php if ($dob !== ''): ?><div><span class="k">DOB:</span> <?= htmlspecialchars($dob) ?></div><?php endif; ?>
		<?php if (!empty($patient['phone_e164'])): ?><div><span class="k">Phone:</span> <?= htmlspecialchars($patient['phone_e164']) ?></div><?php endif; ?>
		<?php if (!empty($patient['blood_group'])): ?><div><span class="k">Blood group:</span> <?= htmlspecialchars($patient['blood_group']) ?></div><?php endif; ?>
	</div>
	<?php $addr = trim(($patient['address_line1'] ?? '') . ', ' . ($patient['city'] ?? '') . ', ' . ($patient['state_province'] ?? '') . ' ' . ($patient['postal_code'] ?? ''), ', '); ?>
	<?php if (trim($addr, ' ,')): ?>
		<p style="margin-top:4px;"><span style="color:#666;font-weight:bold;">Address:</span> <?= htmlspecialchars($addr) ?></p>
	<?php endif; ?>

	<div class="salutation"><p>Dear Colleague,</p></div>

	<div class="body-copy">
		<p>I am referring the above patient to your care for further evaluation and management. A summary of the presenting picture and my clinical findings is set out below.</p>

		<?php if ($cc !== ''): ?>
			<h2 class="section">Chief Complaint</h2>
			<p class="narrative"><?= htmlspecialchars($cc) ?></p>
		<?php endif; ?>

		<?php if ($hpi !== ''): ?>
			<h2 class="section">History of Present Illness</h2>
			<p class="narrative"><?= htmlspecialchars($hpi) ?></p>
		<?php endif; ?>

		<?php if ($allerg !== ''): ?>
			<h2 class="section">Allergies</h2>
			<p class="narrative"><?= htmlspecialchars($allerg) ?></p>
		<?php endif; ?>

		<?php if ($ax !== ''): ?>
			<h2 class="section">Clinical Assessment</h2>
			<p class="narrative"><?= htmlspecialchars($ax) ?></p>
		<?php endif; ?>

		<?php if ($dx !== ''): ?>
			<h2 class="section">Working Diagnosis</h2>
			<p class="narrative"><?= htmlspecialchars($dx) ?></p>
		<?php endif; ?>

		<?php if ($plan !== ''): ?>
			<h2 class="section">Management To Date</h2>
			<p class="narrative"><?= htmlspecialchars($plan) ?></p>
		<?php endif; ?>

		<h2 class="section">Reason for Referral</h2>
		<p class="narrative"><?= htmlspecialchars($refWhy !== '' ? $refWhy : 'Kindly evaluate and advise further management.') ?></p>

		<p>Thank you for accepting this referral. Please do not hesitate to contact me if any further information is required.</p>
	</div>

	<div class="sig-block">
		<p>With kind regards,</p>
		<div class="sig-line"><strong><?= htmlspecialchars($doctor ?: '') ?></strong></div>
		<div style="color:#666;font-size:11px;margin-top:2px;">Referring Physician</div>
	</div>

	<script>
		// Auto-open the print dialog once the letter is rendered.
		window.addEventListener('load', function () { setTimeout(function () { window.print(); }, 300); });
	</script>
</body>
</html>
