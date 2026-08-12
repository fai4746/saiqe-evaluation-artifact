<?php
// Wizard step 6 — Review & Submit
$s1 = $data['step1'] ?? [];
$s2 = $data['step2'] ?? [];
$s3 = $data['step3'] ?? [];
$s4 = $data['step4'] ?? [];
$docs = $data['existing_docs'] ?? [];
$programName = '—';
foreach (($programs ?? []) as $p) {
    if ((int)$p['program_id'] === (int)($s4['program_id'] ?? 0)) { $programName = $p['program_name']; break; }
}
?>
<?php include APP_ROOT . '/app/views/wizard/_progress.php'; ?>
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="page-header">
    <div><h2>Step 6 — Review &amp; Submit</h2><div class="page-subtitle">Please review your information before submitting</div></div>
</div>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-3 mb-3"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><span class="card-title-icon bi bi-person"></span>Personal Information</span>
        <a href="<?= BASE_URL ?>/applications/wizard/1" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-6"><span class="text-muted small">Full Name</span><div><?= htmlspecialchars(($s1['first_name'] ?? '') . ' ' . ($s1['last_name'] ?? '')) ?></div></div>
            <div class="col-md-6"><span class="text-muted small">Date of Birth</span><div><?= htmlspecialchars($s1['dob'] ?? '—') ?></div></div>
            <div class="col-md-6"><span class="text-muted small">Email</span><div><?= htmlspecialchars($s1['email'] ?? '—') ?></div></div>
            <div class="col-md-6"><span class="text-muted small">Phone</span><div><?= htmlspecialchars($s1['phone'] ?? '—') ?></div></div>
            <div class="col-12"><span class="text-muted small">Address</span>
                <div><?= htmlspecialchars(($s1['street'] ?? '') . ', ' . ($s1['city'] ?? '') . ', ' . ($s1['state'] ?? '') . ' ' . ($s1['zip'] ?? '')) ?></div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><span class="card-title-icon bi bi-people"></span>Household</span>
        <a href="<?= BASE_URL ?>/applications/wizard/2" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-6"><span class="text-muted small">Marital Status</span><div><?= htmlspecialchars($s2['marital_status'] ?? '—') ?></div></div>
            <div class="col-md-6"><span class="text-muted small">Dependents</span><div><?= htmlspecialchars($s2['num_dependents'] ?? '0') ?></div></div>
            <div class="col-md-6"><span class="text-muted small">Veteran Status</span><div><?= htmlspecialchars($s2['veteran_status'] ?? '—') ?></div></div>
            <div class="col-md-6"><span class="text-muted small">Disability Status</span><div><?= htmlspecialchars($s2['disability_status'] ?? '—') ?></div></div>
            <div class="col-md-6"><span class="text-muted small">Monthly Housing Cost</span><div>$<?= number_format((float)($s2['housing_cost_monthly'] ?? 0), 2) ?></div></div>
            <div class="col-md-6"><span class="text-muted small">Monthly Expenses</span><div>$<?= number_format((float)($s2['monthly_expenses'] ?? 0), 2) ?></div></div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><span class="card-title-icon bi bi-cash-stack"></span>Income &amp; Assets</span>
        <a href="<?= BASE_URL ?>/applications/wizard/3" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-md-6"><span class="text-muted small">Employment Status</span><div><?= htmlspecialchars($s3['employment_status'] ?? '—') ?></div></div>
            <div class="col-md-6"><span class="text-muted small">Annual Income</span><div>$<?= number_format((float)($s3['annual_income'] ?? 0), 2) ?></div></div>
            <div class="col-md-6"><span class="text-muted small">Income Source</span><div><?= htmlspecialchars($s3['income_source'] ?? '—') ?></div></div>
            <div class="col-md-6"><span class="text-muted small">Receiving Benefits?</span><div><?= ($s3['receiving_benefits'] ?? '0') === '1' ? 'Yes' : 'No' ?></div></div>
        </div>
    </div>
</div>

<div class="card mb-3">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><span class="card-title-icon bi bi-clipboard-check"></span>Program &amp; Eligibility</span>
        <a href="<?= BASE_URL ?>/applications/wizard/4" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>
    </div>
    <div class="card-body">
        <div class="row g-2">
            <div class="col-12"><span class="text-muted small">Selected Program</span><div><?= htmlspecialchars($programName) ?></div></div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><span class="card-title-icon bi bi-file-earmark"></span>Documents</span>
        <a href="<?= BASE_URL ?>/applications/wizard/5" class="btn btn-sm btn-outline-secondary"><i class="bi bi-pencil me-1"></i>Edit</a>
    </div>
    <div class="card-body">
        <?php if (empty($docs)): ?>
        <p class="text-muted small mb-0">No documents uploaded yet.</p>
        <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($docs as $doc): ?>
            <li class="list-group-item px-0 py-1 d-flex align-items-center gap-2">
                <i class="bi bi-file-earmark-check text-success"></i>
                <span><?= htmlspecialchars($doc['original_filename']) ?></span>
                <span class="badge bg-secondary ms-auto"><?= htmlspecialchars($doc['document_category'] ?? 'other') ?></span>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<form method="POST" action="<?= BASE_URL ?>/applications/wizard/submit">
    <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
    <div class="card mb-4 border-brand">
        <div class="card-body">
            <div class="form-check">
                <input class="form-check-input <?= isset($errors['certification']) ? 'is-invalid' : '' ?>"
                       type="checkbox" name="certification" value="1" id="certCheck" required>
                <label class="form-check-label" for="certCheck">
                    <strong>I certify</strong> that all information provided in this application is true, accurate, and complete to the best of my knowledge.
                    I understand that providing false information may result in denial, termination of benefits, and potential legal action.
                    I authorize the agency to verify the information provided. <span class="text-danger">*</span>
                </label>
                <?php if (isset($errors['certification'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['certification']) ?></div><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/applications/wizard/5" class="btn btn-outline-secondary px-4"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <button type="submit" class="btn btn-brand flex-grow-1 py-2">
            <i class="bi bi-send-check me-1"></i>Submit Application
        </button>
    </div>
</form>
</div>
</div>
