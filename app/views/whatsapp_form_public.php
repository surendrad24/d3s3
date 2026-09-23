<?php
/**
 * whatsapp_form_public.php – patient-facing pre-intake form.
 *
 * Expected variables:
 *   $token, $csrfToken, $error (string|null), $submitted (bool), $prefill (array)
 */
$prefill = $prefill ?? [];
$error   = $error   ?? null;
$submitted = $submitted ?? false;
function _wa_val(array $p, string $k): string { return htmlspecialchars((string)($p[$k] ?? '')); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
	<meta charset="UTF-8" />
	<meta name="viewport" content="width=device-width, initial-scale=1" />
	<title>Pre-Intake Form</title>
	<link rel="stylesheet" href="assets/css/bootstrap.min.css" />
	<link rel="stylesheet" href="assets/icons/css/all.min.css" />
	<style>
		body { background: #f4f6f9; }
		.form-card { max-width: 640px; margin: 24px auto; }
		.form-card .card-header { background: #25D366; color: #fff; }
		.form-card .card-header i { margin-right: .5rem; }
	</style>
</head>
<body>

<div class="form-card">
	<div class="card shadow-sm">
		<div class="card-header">
			<h5 class="mb-0"><i class="fab fa-whatsapp"></i>Pre-Intake Form</h5>
		</div>
		<div class="card-body">

			<?php if ($error): ?>
				<div class="alert alert-danger">
					<i class="fas fa-exclamation-triangle mr-2"></i><?= htmlspecialchars($error) ?>
				</div>
			<?php elseif ($submitted): ?>
				<div class="text-center py-4">
					<i class="fas fa-check-circle fa-3x text-success mb-3"></i>
					<h5>Thank you — your form has been received.</h5>
					<p class="text-muted mb-0">Our team will review it and contact you shortly.</p>
				</div>
			<?php else: ?>
				<p class="text-muted small">
					Please fill in the details below. Everything you submit is confidential and
					reviewed only by your care team.
				</p>
				<form method="POST" action="intake_link.php" autocomplete="on">
					<input type="hidden" name="token" value="<?= htmlspecialchars($csrfToken) ?>" />

					<div class="form-group">
						<label class="small text-muted mb-1">Full name *</label>
						<input type="text" name="full_name" class="form-control form-control-sm" required
						       value="<?= _wa_val($prefill, 'full_name') ?>" />
					</div>

					<div class="form-row">
						<div class="form-group col-sm-6">
							<label class="small text-muted mb-1">Date of birth</label>
							<input type="date" name="date_of_birth" class="form-control form-control-sm"
							       value="<?= _wa_val($prefill, 'date_of_birth') ?>" />
						</div>
						<div class="form-group col-sm-6">
							<label class="small text-muted mb-1">Sex</label>
							<select name="sex" class="form-control form-control-sm">
								<?php $sex = $prefill['sex'] ?? ''; ?>
								<option value="">— Select —</option>
								<option value="FEMALE" <?= $sex === 'FEMALE' ? 'selected' : '' ?>>Female</option>
								<option value="MALE"   <?= $sex === 'MALE'   ? 'selected' : '' ?>>Male</option>
								<option value="OTHER"  <?= $sex === 'OTHER'  ? 'selected' : '' ?>>Other</option>
							</select>
						</div>
					</div>

					<div class="form-row">
						<div class="form-group col-sm-6">
							<label class="small text-muted mb-1">Phone</label>
							<input type="tel" name="phone" class="form-control form-control-sm"
							       value="<?= _wa_val($prefill, 'phone') ?>" />
						</div>
						<div class="form-group col-sm-6">
							<label class="small text-muted mb-1">Email</label>
							<input type="email" name="email" class="form-control form-control-sm"
							       value="<?= _wa_val($prefill, 'email') ?>" />
						</div>
					</div>

					<div class="form-group">
						<label class="small text-muted mb-1">Chief complaint *</label>
						<input type="text" name="chief_complaint" class="form-control form-control-sm" required
						       placeholder="e.g. lower back pain for 3 weeks"
						       value="<?= _wa_val($prefill, 'chief_complaint') ?>" />
					</div>

					<div class="form-group">
						<label class="small text-muted mb-1">Current symptoms</label>
						<textarea name="symptoms" class="form-control" rows="3"><?= _wa_val($prefill, 'symptoms') ?></textarea>
					</div>

					<div class="form-group">
						<label class="small text-muted mb-1">Known allergies</label>
						<input type="text" name="allergies" class="form-control form-control-sm"
						       value="<?= _wa_val($prefill, 'allergies') ?>" />
					</div>

					<div class="form-group">
						<label class="small text-muted mb-1">Current medications</label>
						<textarea name="medications" class="form-control" rows="2"><?= _wa_val($prefill, 'medications') ?></textarea>
					</div>

					<div class="form-group">
						<label class="small text-muted mb-1">Past medical / surgical history</label>
						<textarea name="past_history" class="form-control" rows="2"><?= _wa_val($prefill, 'past_history') ?></textarea>
					</div>

					<button type="submit" class="btn btn-success btn-block">
						<i class="fas fa-paper-plane mr-1"></i>Submit
					</button>
				</form>
			<?php endif; ?>
		</div>
	</div>
	<p class="text-center small text-muted mt-3">D3S3 CareSystem</p>
</div>

</body>
</html>
