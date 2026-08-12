<?php
// Wizard step 1 — Personal Information
$s1 = $data['step1'] ?? [];
$c  = $citizen ?? [];
$states = ['AL','AK','AZ','AR','CA','CO','CT','DE','FL','GA','HI','ID','IL','IN','IA','KS','KY','LA','ME','MD','MA','MI','MN','MS','MO','MT','NE','NV','NH','NJ','NM','NY','NC','ND','OH','OK','OR','PA','RI','SC','SD','TN','TX','UT','VT','VA','WA','WV','WI','WY'];
?>
<?php include APP_ROOT . '/app/views/wizard/_progress.php'; ?>
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="page-header">
    <div><h2>Step 1 — Personal Information</h2><div class="page-subtitle">Confirm your personal details</div></div>
</div>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-3 mb-3"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<form method="POST" action="<?= BASE_URL ?>/applications/wizard/1">
    <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-person"></span>Identity</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">First Name <span class="text-danger">*</span></label>
                    <input type="text" name="first_name" class="form-control" value="<?= htmlspecialchars($old['first_name'] ?? $s1['first_name'] ?? $user['first_name'] ?? '') ?>" required>
                    <?php if (isset($errors['first_name'])): ?><div class="text-danger small"><?= htmlspecialchars($errors['first_name']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Last Name <span class="text-danger">*</span></label>
                    <input type="text" name="last_name" class="form-control" value="<?= htmlspecialchars($old['last_name'] ?? $s1['last_name'] ?? $user['last_name'] ?? '') ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Date of Birth <span class="text-danger">*</span></label>
                    <input type="date" name="dob" class="form-control <?= isset($errors['dob']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($old['dob'] ?? $s1['dob'] ?? ($c['date_of_birth'] ?? '')) ?>" required>
                    <?php if (isset($errors['dob'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['dob']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">SSN Last 4 Digits <span class="text-danger">*</span></label>
                    <input type="text" name="ssn_last4" class="form-control <?= isset($errors['ssn_last4']) ? 'is-invalid' : '' ?>"
                           maxlength="4" pattern="\d{4}" placeholder="••••"
                           value="<?= htmlspecialchars($old['ssn_last4'] ?? $s1['ssn_last4'] ?? ($c['ssn_last4'] ?? '')) ?>" required>
                    <?php if (isset($errors['ssn_last4'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['ssn_last4']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Email Address <span class="text-danger">*</span></label>
                    <input type="email" name="email" class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>"
                           value="<?= htmlspecialchars($old['email'] ?? $s1['email'] ?? $user['email'] ?? '') ?>" required>
                    <?php if (isset($errors['email'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['email']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Phone Number <span class="text-danger">*</span></label>
                    <input type="tel" name="phone" class="form-control"
                           placeholder="(555) 555-5555"
                           value="<?= htmlspecialchars($old['phone'] ?? $s1['phone'] ?? ($c['phone'] ?? '')) ?>" required>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header"><span class="card-title-icon bi bi-geo-alt"></span>Address</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Street Address <span class="text-danger">*</span></label>
                    <input type="text" name="street" class="form-control"
                           value="<?= htmlspecialchars($old['street'] ?? $s1['street'] ?? ($c['address_street'] ?? '')) ?>" required>
                </div>
                <div class="col-md-5">
                    <label class="form-label">City <span class="text-danger">*</span></label>
                    <input type="text" name="city" class="form-control"
                           value="<?= htmlspecialchars($old['city'] ?? $s1['city'] ?? ($c['address_city'] ?? '')) ?>" required>
                </div>
                <div class="col-md-3">
                    <label class="form-label">State <span class="text-danger">*</span></label>
                    <select name="state" class="form-select" required>
                        <option value="">Select…</option>
                        <?php $sel = $old['state'] ?? $s1['state'] ?? ($c['address_state'] ?? ''); ?>
                        <?php foreach ($states as $st): ?>
                            <option value="<?= $st ?>" <?= $sel === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">ZIP Code <span class="text-danger">*</span></label>
                    <input type="text" name="zip" class="form-control"
                           value="<?= htmlspecialchars($old['zip'] ?? $s1['zip'] ?? ($c['address_zip'] ?? '')) ?>" required>
                </div>
            </div>
        </div>
    </div>
    <button type="submit" class="btn btn-brand w-100 py-2"><i class="bi bi-arrow-right me-1"></i>Continue to Step 2</button>
</form>
</div>
</div>
