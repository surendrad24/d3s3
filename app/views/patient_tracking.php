<?php
$_fullName = trim(($patient['first_name'] ?? '') . ' ' . ($patient['last_name'] ?? ''));
$_dob      = !empty($patient['date_of_birth']) ? date('d M Y', strtotime($patient['date_of_birth'])) : '';

$_pipelineStages = ['INTAKE_IN_PROGRESS','INTAKE_COMPLETE','SCHEDULED','DOCTOR_REVIEW','CLOSED'];
$_stageLabels = [
	'INTAKE_IN_PROGRESS' => 'Intake in Progress',
	'INTAKE_COMPLETE'    => 'Intake Complete',
	'SCHEDULED'          => 'Scheduled',
	'DOCTOR_REVIEW'      => 'Doctor Review',
	'CLOSED'             => 'Closed',
];
$_currentStage = $activeCase ? $activeCase['status'] : null;
$_currentIdx   = $_currentStage !== null ? array_search($_currentStage, $_pipelineStages, true) : -1;

$_stageTime = [];
foreach ($statusTimeline as $tl) { $_stageTime[$tl['status']] = $tl; }

$_labStatusColor = ['ORDERED'=>'secondary','IN_PROGRESS'=>'warning','COMPLETED'=>'success','CANCELLED'=>'danger'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Track: <?= htmlspecialchars($_fullName) ?> | CareSystem</title>
	<link rel="stylesheet" href="assets/css/bootstrap.min.css" />
	<link rel="stylesheet" href="assets/icons/css/all.min.css" />
	<link rel="stylesheet" href="assets/css/adminlte.min.css" />
	<link rel="stylesheet" href="assets/css/theme.css" />
	<style>
		.stage-pipe { display:flex; align-items:stretch; gap:0; margin:1rem 0 1.5rem; }
		.stage-cell {
			flex:1 1 0; text-align:center; padding:.9rem .6rem; position:relative;
			background:#f1f3f5; color:#6c757d; border-right:1px solid #fff; font-size:.85rem;
		}
		.stage-cell:first-child { border-radius:.35rem 0 0 .35rem; }
		.stage-cell:last-child  { border-radius:0 .35rem .35rem 0; border-right:0; }
		.stage-cell.done    { background:#d1e7dd; color:#0f5132; }
		.stage-cell.active  { background:#0d6efd; color:#fff; font-weight:600; box-shadow:inset 0 -3px 0 rgba(0,0,0,.2); }
		.stage-cell .stage-time { display:block; font-size:.7rem; margin-top:.15rem; opacity:.75; }
		.timeline-entry { border-left:3px solid #dee2e6; padding:.5rem 0 .5rem 1rem; margin-left:.5rem; position:relative; }
		.timeline-entry::before { content:''; width:11px; height:11px; background:#6c757d; border-radius:50%; position:absolute; left:-7px; top:.85rem; }
		.timeline-entry.closed::before { background:#28a745; }
	</style>
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed<?= ($_SESSION['font_size'] ?? 'normal') === 'large' ? ' font-size-large' : '' ?>"
      data-theme-server="<?= htmlspecialchars($_SESSION['theme'] ?? 'system') ?>">
<div class="wrapper">
	<nav class="main-header navbar navbar-expand navbar-white navbar-light">
		<ul class="navbar-nav">
			<li class="nav-item"><a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></a></li>
			<li class="nav-item d-none d-sm-inline-block"><span class="navbar-brand mb-0 h6 text-primary">CareSystem</span></li>
		</ul>
		<ul class="navbar-nav ml-auto">
			<li class="nav-item mr-2">
				<a class="btn btn-sm btn-outline-secondary" href="patients.php?action=view&id=<?= (int)$patient['patient_id'] ?>"><i class="fas fa-user mr-1"></i>Full Profile</a>
			</li>
			<li class="nav-item mr-2">
				<a class="btn btn-sm btn-outline-secondary" href="patients.php"><i class="fas fa-arrow-left mr-1"></i>Back to Patients</a>
			</li>
		</ul>
	</nav>

	<?php require __DIR__ . '/_sidebar.php'; ?>

	<div class="content-wrapper">
		<section class="content-header">
			<div class="container-fluid">
				<h1 class="m-0 text-dark">
					<i class="fas fa-route mr-2 text-primary"></i>Patient Tracking
					<small class="text-muted ml-2"><?= htmlspecialchars($_fullName) ?> · <?= htmlspecialchars($patient['patient_code'] ?? '') ?></small>
				</h1>
			</div>
		</section>
		<section class="content">
			<div class="container-fluid">

				<div class="card">
					<div class="card-body">
						<div class="d-flex flex-wrap">
							<div class="mr-4"><small class="text-muted d-block">Name</small><strong><?= htmlspecialchars($_fullName) ?></strong></div>
							<?php if ($patient['sex'] ?? ''): ?><div class="mr-4"><small class="text-muted d-block">Sex</small><strong><?= htmlspecialchars($patient['sex']) ?></strong></div><?php endif; ?>
							<?php if (!empty($patient['age_years'])): ?><div class="mr-4"><small class="text-muted d-block">Age</small><strong><?= (int)$patient['age_years'] ?> yr</strong></div><?php endif; ?>
							<?php if ($_dob): ?><div class="mr-4"><small class="text-muted d-block">DOB</small><strong><?= htmlspecialchars($_dob) ?></strong></div><?php endif; ?>
							<?php if (!empty($patient['phone_e164'])): ?><div class="mr-4"><small class="text-muted d-block">Phone</small><strong><?= htmlspecialchars($patient['phone_e164']) ?></strong></div><?php endif; ?>
							<?php if (!empty($patient['blood_group'])): ?><div class="mr-4"><small class="text-muted d-block">Blood group</small><strong><?= htmlspecialchars($patient['blood_group']) ?></strong></div><?php endif; ?>
						</div>
					</div>
				</div>

				<div class="card mt-3">
					<div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-heartbeat mr-2 text-danger"></i>Active Visit</h3></div>
					<div class="card-body">
						<?php if (!$activeCase): ?>
							<p class="text-muted mb-0">No active case sheet. All prior visits are closed.</p>
						<?php else: ?>
							<div class="d-flex flex-wrap mb-2">
								<div class="mr-4"><small class="text-muted d-block">Visit datetime</small><strong><?= htmlspecialchars(date('d M Y H:i', strtotime($activeCase['visit_datetime']))) ?></strong></div>
								<div class="mr-4"><small class="text-muted d-block">Chief complaint</small><strong><?= htmlspecialchars($activeCase['chief_complaint'] ?? '—') ?></strong></div>
								<div class="mr-4"><small class="text-muted d-block">Primary doctor</small><strong><?= htmlspecialchars($activeCase['doctor_name'] ?? $activeCase['assigned_doctor_name'] ?? '— unassigned —') ?></strong></div>
								<?php if (!empty($activeCase['risk_level'])):
									$rc = ['LOW'=>'success','MEDIUM'=>'warning','HIGH'=>'danger'][$activeCase['risk_level']] ?? 'secondary'; ?>
									<div class="mr-4"><small class="text-muted d-block">Risk</small><span class="badge badge-<?= $rc ?>"><?= htmlspecialchars($activeCase['risk_level']) ?></span></div>
								<?php endif; ?>
							</div>

							<div class="stage-pipe">
								<?php foreach ($_pipelineStages as $i => $stg):
									$cls = '';
									if ($_currentIdx >= 0 && $i < $_currentIdx) $cls = 'done';
									elseif ($stg === $_currentStage)           $cls = 'active';
									$t = $_stageTime[$stg]['changed_at'] ?? null; ?>
									<div class="stage-cell <?= $cls ?>">
										<div><?= htmlspecialchars($_stageLabels[$stg]) ?></div>
										<?php if ($t): ?><span class="stage-time"><?= htmlspecialchars(date('d M · H:i', strtotime($t))) ?></span><?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>

							<div class="row">
								<div class="col-md-6">
									<h5 class="mt-3"><i class="fas fa-vial mr-2 text-info"></i>Lab Orders</h5>
									<?php if (empty($labOrders)): ?>
										<p class="text-muted small">No lab orders on this visit.</p>
									<?php else: ?>
										<ul class="list-group list-group-flush">
											<?php foreach ($labOrders as $lo):
												$sc = $_labStatusColor[$lo['status']] ?? 'secondary'; ?>
												<li class="list-group-item d-flex justify-content-between align-items-center px-0">
													<span><?= htmlspecialchars($lo['test_name']) ?></span>
													<span class="badge badge-<?= $sc ?>"><?= htmlspecialchars($lo['status']) ?></span>
												</li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>
								</div>
								<div class="col-md-6">
									<h5 class="mt-3"><i class="fas fa-user-md mr-2 text-primary"></i>Consulting Doctors</h5>
									<?php if (empty($consultants)): ?>
										<p class="text-muted small">Only the primary doctor is on this case.</p>
									<?php else: ?>
										<ul class="list-group list-group-flush">
											<?php foreach ($consultants as $c): ?>
												<li class="list-group-item d-flex justify-content-between align-items-center px-0">
													<span><?= htmlspecialchars($c['name']) ?></span>
													<?php if (!empty($c['role_note'])): ?><small class="text-muted"><?= htmlspecialchars($c['role_note']) ?></small><?php endif; ?>
												</li>
											<?php endforeach; ?>
										</ul>
									<?php endif; ?>

									<h5 class="mt-4"><i class="fas fa-microphone mr-2 text-secondary"></i>Voice Notes</h5>
									<p class="mb-0"><span class="badge badge-info mr-2"><?= (int)$voiceCounts['PATIENT'] ?> patient</span><span class="badge badge-secondary"><?= (int)$voiceCounts['DOCTOR'] ?> doctor</span></p>
								</div>
							</div>

							<div class="mt-3">
								<a href="review.php?case_sheet_id=<?= (int)$activeCase['case_sheet_id'] ?>" class="btn btn-primary btn-sm"><i class="fas fa-stethoscope mr-1"></i>Open in Review</a>
								<a href="intake.php?case_sheet_id=<?= (int)$activeCase['case_sheet_id'] ?>" class="btn btn-outline-secondary btn-sm"><i class="fas fa-clipboard mr-1"></i>Open Intake</a>
							</div>
						<?php endif; ?>
					</div>
				</div>

				<div class="card mt-3">
					<div class="card-header"><h3 class="card-title mb-0"><i class="fas fa-history mr-2 text-muted"></i>Prior Visits Timeline</h3></div>
					<div class="card-body">
						<?php if (empty($priorVisits)): ?>
							<p class="text-muted mb-0">No prior closed visits.</p>
						<?php else: foreach ($priorVisits as $pv): ?>
							<div class="timeline-entry closed">
								<strong><?= htmlspecialchars(date('d M Y', strtotime($pv['visit_datetime']))) ?></strong>
								<span class="badge badge-light border ml-2"><?= htmlspecialchars($pv['visit_type']) ?></span>
								<span class="badge badge-success ml-1">Closed</span>
								<?php if (!empty($pv['closure_type'])): ?><span class="badge badge-outline-secondary ml-1 text-muted small">→ <?= htmlspecialchars($pv['closure_type']) ?></span><?php endif; ?>
								<div class="small text-muted mt-1">
									Chief complaint: <?= htmlspecialchars($pv['chief_complaint'] ?? '—') ?>
									<?php if (!empty($pv['doctor_name'])): ?> · Seen by <?= htmlspecialchars($pv['doctor_name']) ?><?php endif; ?>
								</div>
								<div class="mt-1"><a href="patients.php?action=view&id=<?= (int)$patient['patient_id'] ?>#cs-<?= (int)$pv['case_sheet_id'] ?>" class="btn btn-sm btn-link p-0">Open case sheet</a></div>
							</div>
						<?php endforeach; endif; ?>
					</div>
				</div>

			</div>
		</section>
	</div>
</div>

<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/adminlte.min.js"></script>
<script src="assets/js/theme-toggle.js"></script>
</body>
</html>
