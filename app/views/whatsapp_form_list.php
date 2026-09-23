<?php
/**
 * whatsapp_form_list.php – staff view of WhatsApp form submissions.
 *
 * Expects: $submissions
 */
$_statusColor = [
	'PENDING'   => 'warning',
	'PROCESSED' => 'success',
	'DISCARDED' => 'secondary',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>WhatsApp Forms | CareSystem</title>
	<link rel="stylesheet" href="assets/css/bootstrap.min.css" />
	<link rel="stylesheet" href="assets/icons/css/all.min.css" />
	<link rel="stylesheet" href="assets/css/adminlte.min.css" />
	<link rel="stylesheet" href="assets/css/theme.css" />
</head>
<body class="hold-transition sidebar-mini layout-fixed layout-navbar-fixed<?= ($_SESSION['font_size'] ?? 'normal') === 'large' ? ' font-size-large' : '' ?>"
      data-theme-server="<?= htmlspecialchars($_SESSION['theme'] ?? 'system') ?>">
<div class="wrapper">
	<nav class="main-header navbar navbar-expand navbar-white navbar-light">
		<ul class="navbar-nav">
			<li class="nav-item">
				<a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
			</li>
			<li class="nav-item d-none d-sm-inline-block">
				<span class="navbar-brand mb-0 h6 text-primary">CareSystem</span>
			</li>
		</ul>
		<ul class="navbar-nav ml-auto">
			<li class="nav-item">
				<a class="btn btn-sm btn-outline-secondary" href="dashboard.php" role="button">
					<i class="fas fa-arrow-left mr-1"></i>Dashboard
				</a>
			</li>
		</ul>
	</nav>

	<?php require __DIR__ . '/_sidebar.php'; ?>

	<div class="content-wrapper">
		<div class="content-header">
			<div class="container-fluid">
				<div class="row mb-2 align-items-center">
					<div class="col">
						<h1 class="m-0 text-dark"><i class="fab fa-whatsapp text-success mr-2"></i>WhatsApp Forms</h1>
						<p class="text-muted mb-0">Pre-intake forms submitted by patients via a WhatsApp link.</p>
					</div>
					<div class="col text-right">
						<button type="button" class="btn btn-success btn-sm" data-toggle="modal" data-target="#waFormSendModal">
							<i class="fas fa-share-alt mr-1"></i>Send form link
						</button>
					</div>
				</div>
			</div>
		</div>

		<section class="content">
			<div class="container-fluid">
				<div class="card">
					<div class="card-body p-0">
						<div class="table-responsive">
							<table class="table table-sm table-hover mb-0">
								<thead class="thead-light">
									<tr>
										<th>Created</th>
										<th>Patient</th>
										<th>Phone</th>
										<th>Chief complaint</th>
										<th>Status</th>
										<th>Submitted</th>
										<th></th>
									</tr>
								</thead>
								<tbody>
									<?php if (empty($submissions)): ?>
									<tr><td colspan="7" class="text-center text-muted py-4">No submissions yet.</td></tr>
									<?php else: foreach ($submissions as $s):
										$fd = $s['form_data'] ? (json_decode($s['form_data'], true) ?: []) : [];
									?>
									<tr>
										<td class="small text-nowrap"><?= htmlspecialchars(date('d M Y H:i', strtotime($s['created_at']))) ?></td>
										<td>
											<?php if ($s['patient_id']): ?>
												<a href="patients.php?action=view&id=<?= (int)$s['patient_id'] ?>">
													<?= htmlspecialchars($s['patient_name'] ?? '—') ?>
												</a>
												<div class="small text-muted"><?= htmlspecialchars($s['patient_code'] ?? '') ?></div>
											<?php elseif (!empty($fd['full_name'])): ?>
												<?= htmlspecialchars($fd['full_name']) ?>
												<span class="badge badge-light">unlinked</span>
											<?php else: ?>
												<span class="text-muted">—</span>
											<?php endif; ?>
										</td>
										<td class="small text-monospace text-nowrap"><?= htmlspecialchars($s['to_phone_e164'] ?? '') ?></td>
										<td class="small" style="max-width:320px;">
											<?= htmlspecialchars($fd['chief_complaint'] ?? '—') ?>
											<?php if (!empty($fd['symptoms'])): ?>
											<div class="text-muted small mt-1"><?= nl2br(htmlspecialchars(mb_strimwidth($fd['symptoms'], 0, 160, '…'))) ?></div>
											<?php endif; ?>
										</td>
										<td><span class="badge badge-<?= $_statusColor[$s['status']] ?? 'secondary' ?>"><?= htmlspecialchars($s['status']) ?></span></td>
										<td class="small text-nowrap"><?= $s['submitted_at'] ? htmlspecialchars(date('d M Y H:i', strtotime($s['submitted_at']))) : '<span class="text-muted">—</span>' ?></td>
										<td class="text-nowrap">
											<?php if ($s['status'] === 'PENDING' && $s['submitted_at']): ?>
											<button type="button" class="btn btn-xs btn-outline-success wa-attach"
											        data-id="<?= (int)$s['submission_id'] ?>"
											        data-patient="<?= (int)($s['patient_id'] ?? 0) ?>">Attach</button>
											<?php endif; ?>
											<?php if ($s['status'] === 'PENDING'): ?>
											<button type="button" class="btn btn-xs btn-outline-secondary wa-discard"
											        data-id="<?= (int)$s['submission_id'] ?>">Discard</button>
											<?php endif; ?>
										</td>
									</tr>
									<?php endforeach; endif; ?>
								</tbody>
							</table>
						</div>
					</div>
				</div>
			</div>
		</section>
	</div>

	<footer class="main-footer">
		<strong>D3S3 CareSystem</strong> <span class="badge badge-warning" style="font-size:.65rem;vertical-align:middle">Alpha</span>
	</footer>
</div>

<!-- Send link modal -->
<div class="modal fade" id="waFormSendModal" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h5 class="modal-title"><i class="fab fa-whatsapp text-success mr-2"></i>Send pre-intake form link</h5>
				<button type="button" class="close" data-dismiss="modal">&times;</button>
			</div>
			<div class="modal-body">
				<div id="waFormAlert"></div>
				<div class="form-group">
					<label class="small text-muted mb-1">Patient ID (optional)</label>
					<input type="number" id="waFormPatientId" class="form-control form-control-sm" placeholder="Numeric patient_id, or leave blank" />
				</div>
				<div class="form-group">
					<label class="small text-muted mb-1">Phone (E.164)</label>
					<input type="tel" id="waFormPhone" class="form-control form-control-sm" placeholder="+91XXXXXXXXXX" />
					<small class="text-muted">Required if no patient ID is supplied.</small>
				</div>
				<div id="waFormLink" class="small text-muted"></div>
			</div>
			<div class="modal-footer">
				<button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Close</button>
				<button type="button" id="waFormSendBtn" class="btn btn-success btn-sm">
					<i class="fas fa-paper-plane mr-1"></i>Send link
				</button>
			</div>
		</div>
	</div>
</div>

<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/adminlte.min.js"></script>
<script src="assets/js/theme-toggle.js"></script>
<script>
(function () {
	var csrf = <?= json_encode($_SESSION['csrf_token']) ?>;
	function esc(t) { return $('<span>').text(t == null ? '' : t).html(); }

	$('#waFormSendBtn').on('click', function () {
		var $btn = $(this);
		var pid  = $('#waFormPatientId').val();
		var ph   = $('#waFormPhone').val();
		$('#waFormAlert').empty();
		$btn.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i>Sending…');
		$.post('whatsapp_form.php?action=send-link', {
			csrf_token: csrf, patient_id: pid, to_phone: ph
		}, function (r) {
			$btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i>Send link');
			if (r.success) {
				var msg = r.sent ? 'WhatsApp link sent.' : ('Link generated but not delivered: ' + (r.error || 'unknown'));
				$('#waFormAlert').html('<div class="alert alert-' + (r.sent ? 'success' : 'warning') + ' py-1 small">' + esc(msg) + '</div>');
				$('#waFormLink').html('Link: <a href="' + esc(r.link) + '" target="_blank">' + esc(r.link) + '</a>');
				setTimeout(function () { location.reload(); }, 1200);
			} else {
				$('#waFormAlert').html('<div class="alert alert-danger py-1 small">' + esc(r.error || 'Failed.') + '</div>');
			}
		}, 'json').fail(function () {
			$btn.prop('disabled', false).html('<i class="fas fa-paper-plane mr-1"></i>Send link');
			$('#waFormAlert').html('<div class="alert alert-danger py-1 small">Request failed.</div>');
		});
	});

	$('.wa-attach').on('click', function () {
		var $btn = $(this);
		var id   = $btn.data('id');
		var pid  = $btn.data('patient') || prompt('Attach to which patient_id?');
		if (!pid) return;
		var notes = prompt('Notes (optional):') || '';
		$.post('whatsapp_form.php?action=attach', {
			csrf_token: csrf, submission_id: id, patient_id: pid, notes: notes
		}, function (r) { if (r.success) location.reload(); }, 'json');
	});

	$('.wa-discard').on('click', function () {
		if (!confirm('Discard this submission?')) return;
		var id = $(this).data('id');
		$.post('whatsapp_form.php?action=discard', {
			csrf_token: csrf, submission_id: id
		}, function (r) { if (r.success) location.reload(); }, 'json');
	});
})();
</script>
</body>
</html>
