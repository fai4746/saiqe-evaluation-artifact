<?php
// Wizard step 3 — Income & Assets
$s3 = $data['step3'] ?? [];
?>
<?php include APP_ROOT . '/app/views/wizard/_progress.php'; ?>
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="page-header">
    <div><h2>Step 3 — Income &amp; Assets</h2><div class="page-subtitle">Report your income and financial information</div></div>
</div>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-3 mb-3"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<form method="POST" action="<?= BASE_URL ?>/applications/wizard/3">
    <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-briefcase"></span>Employment</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Employment Status <span class="text-danger">*</span></label>
                    <select name="employment_status" id="employmentStatus" class="form-select" required>
                        <option value="">Select…</option>
                        <?php $es = $old['employment_status'] ?? $s3['employment_status'] ?? ''; ?>
                        <?php foreach (['Full-Time','Part-Time','Self-Employed','Unemployed','Retired','Student','Disabled/Unable to Work'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $es === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6" id="employerField">
                    <label class="form-label">Employer Name</label>
                    <input type="text" name="employer_name" class="form-control"
                           value="<?= htmlspecialchars($old['employer_name'] ?? $s3['employer_name'] ?? '') ?>"
                           placeholder="Optional">
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-cash-stack"></span>Annual Income</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Total Annual Household Income ($) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="annual_income" class="form-control <?= isset($errors['annual_income']) ? 'is-invalid' : '' ?>"
                               min="0" step="0.01" placeholder="0.00"
                               value="<?= htmlspecialchars($old['annual_income'] ?? $s3['annual_income'] ?? '') ?>" required>
                    </div>
                    <div class="form-text">Include all household income: wages, benefits, child support, alimony, rental income, etc.</div>
                    <?php if (isset($errors['annual_income'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['annual_income']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Primary Income Source <span class="text-danger">*</span></label>
                    <select name="income_source" class="form-select" required>
                        <option value="">Select…</option>
                        <?php $is_ = $old['income_source'] ?? $s3['income_source'] ?? ''; ?>
                        <?php foreach (['Employment','Self-Employment','Social Security','Disability Benefits','Pension/Retirement','Unemployment','Child Support/Alimony','Other Government Benefits','No Income'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $is_ === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Pay Frequency</label>
                    <select name="pay_frequency" class="form-select">
                        <option value="">Select…</option>
                        <?php $pf = $old['pay_frequency'] ?? $s3['pay_frequency'] ?? ''; ?>
                        <?php foreach (['Weekly','Bi-Weekly','Semi-Monthly','Monthly','Annually','Irregular'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $pf === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header"><span class="card-title-icon bi bi-bank"></span>Assets &amp; Benefits</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Approximate Total Assets ($)</label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="total_assets" class="form-control"
                               min="0" step="0.01" placeholder="0.00"
                               value="<?= htmlspecialchars($old['total_assets'] ?? $s3['total_assets'] ?? '') ?>">
                    </div>
                    <div class="form-text">Savings, checking, investments (exclude primary home).</div>
                </div>
                <div class="col-md-6">
                    <label class="form-label d-block">Currently Receiving Benefits?</label>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="receiving_benefits" value="1" id="benYes"
                               <?= ($old['receiving_benefits'] ?? $s3['receiving_benefits'] ?? '') === '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="benYes">Yes</label>
                    </div>
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" type="radio" name="receiving_benefits" value="0" id="benNo"
                               <?= ($old['receiving_benefits'] ?? $s3['receiving_benefits'] ?? '') !== '1' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="benNo">No</label>
                    </div>
                </div>
                <div class="col-12" id="benefitDetailField">
                    <label class="form-label">Benefit Programs Currently Enrolled In</label>
                    <input type="text" name="current_benefits" class="form-control"
                           placeholder="e.g. SNAP, Medicaid, WIC"
                           value="<?= htmlspecialchars($old['current_benefits'] ?? $s3['current_benefits'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/applications/wizard/2" class="btn btn-outline-secondary px-4"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <button type="submit" class="btn btn-brand flex-grow-1 py-2"><i class="bi bi-arrow-right me-1"></i>Continue to Step 4</button>
    </div>
</form>
</div>
</div>
<script>
(function(){
    function toggleBenefitField(){
        var v = document.querySelector('input[name="receiving_benefits"]:checked');
        document.getElementById('benefitDetailField').style.display = (v && v.value==='1') ? '' : 'none';
    }
    document.querySelectorAll('input[name="receiving_benefits"]').forEach(function(r){r.addEventListener('change',toggleBenefitField);});
    toggleBenefitField();
})();
</script>
