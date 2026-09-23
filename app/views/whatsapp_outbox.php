<?php
/**
 * whatsapp_outbox.php – global WhatsApp send log.
 *
 * Expects:
 *   $whatsappRows – array of message rows joined to users + patients
 *   $configured   – bool from WhatsAppSender::isConfigured()
 */
$_statusColor = [
	'QUEUED'    => 'secondary',
	'SENT'      => 'info',
	'DELIVERED' => 'primary',
	'READ'      => 'success',
	'FAILED'    => 'danger',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>WhatsApp Outbox | CareSystem</title>
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
				<a class="nav-link" data-widget="pushmenu" href="#" role="button" aria-label="Toggle sidebar"><i class="fas fa-bars"></i></a>
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
						<h1 class="m-0 text-dark"><i class="fab fa-whatsapp text-success mr-2"></i>WhatsApp Outbox</h1>
						<p class="text-muted mb-0">Recent outbound WhatsApp messages to patients.</p>
					</div>
				</div>
			</div>
		</div>

		<section class="content">
			<div class="container-fluid">

				<?php if (!$configured): ?>
				<div class="alert alert-warning">
					<i class="fas fa-exclamation-triangle mr-2"></i>
					<strong>Not configured.</strong> Set <code>WHATSAPP_PROVIDER</code> and <code>WHATSAPP_TOKEN</code>
					in <code>.env</code> to enable delivery. Messages will still be logged with status <code>FAILED</code>.
				</div>
				<?php endif; ?>

				<div class="card">
					<div class="card-body p-0">
						<div class="table-responsive">
							<table class="table table-sm table-hover mb-0">
								<thead class="thead-light">
									<tr>
										<th class="text-nowrap">Sent</th>
										<th>Patient</th>
										<th class="text-nowrap">Phone</th>
										<th>Template</th>
										<th>Body</th>
										<th>Status</th>
										<th>Sender</th>
									</tr>
								</thead>
								<tbody>
									<?php if (empty($whatsappRows)): ?>
									<tr><td colspan="7" class="text-center text-muted py-4">No WhatsApp messages sent yet.</td></tr>
									<?php else: foreach ($whatsappRows as $row): ?>
									<tr>
										<td class="small text-nowrap"><?= htmlspecialchars(date('d M Y H:i', strtotime($row['sent_at']))) ?></td>
										<td>
											<?php if ($row['patient_id']): ?>
												<a href="patients.php?action=view&id=<?= (int)$row['patient_id'] ?>">
													<?= htmlspecialchars($row['patient_name'] ?? '—') ?>
												</a>
												<div class="small text-muted text-monospace"><?= htmlspecialchars($row['patient_code'] ?? '') ?></div>
											<?php else: ?>
												<span class="text-muted">&mdash;</span>
											<?php endif; ?>
										</td>
										<td class="small text-monospace text-nowrap"><?= htmlspecialchars($row['to_phone_e164']) ?></td>
										<td class="small"><?= htmlspecialchars($row['template_name'] ?? '') ?></td>
										<td class="small" style="max-width:340px;">
											<?= nl2br(htmlspecialchars(mb_strimwidth($row['body'], 0, 200, '…'))) ?>
											<?php if (!empty($row['error_message'])): ?>
											<div class="text-danger small mt-1"><i class="fas fa-exclamation-circle mr-1"></i><?= htmlspecialchars($row['error_message']) ?></div>
											<?php endif; ?>
										</td>
										<td><span class="badge badge-<?= $_statusColor[$row['status']] ?? 'secondary' ?>"><?= htmlspecialchars($row['status']) ?></span></td>
										<td class="small"><?= htmlspecialchars($row['sent_by']) ?></td>
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
<script src="assets/js/jquery.min.js"></script>
<script src="assets/js/bootstrap.bundle.min.js"></script>
<script src="assets/js/adminlte.min.js"></script>
<script src="assets/js/theme-toggle.js"></script>
</body>
</html>
