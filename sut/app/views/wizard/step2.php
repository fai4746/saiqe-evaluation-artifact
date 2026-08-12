<?php
// Wizard step 2 — Household Information
$s2 = $data['step2'] ?? [];
?>
<?php include APP_ROOT . '/app/views/wizard/_progress.php'; ?>
<div class="row justify-content-center">
<div class="col-lg-8">
<div class="page-header">
    <div><h2>Step 2 — Household Information</h2><div class="page-subtitle">Tell us about your household</div></div>
</div>
<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-3 mb-3"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>
<form method="POST" action="<?= BASE_URL ?>/applications/wizard/2">
    <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-people"></span>Household Composition</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Marital Status <span class="text-danger">*</span></label>
                    <select name="marital_status" class="form-select <?= isset($errors['marital_status']) ? 'is-invalid' : '' ?>" required>
                        <option value="">Select…</option>
                        <?php $ms = $old['marital_status'] ?? $s2['marital_status'] ?? ''; ?>
                        <?php foreach (['Single','Married','Divorced','Widowed','Separated','Domestic Partnership'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $ms === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['marital_status'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['marital_status']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Household Size <span class="text-danger">*</span></label>
                    <input type="number" name="household_size" id="householdSize"
                           class="form-control <?= isset($errors['household_size']) ? 'is-invalid' : '' ?>"
                           min="1" max="20" value="<?= htmlspecialchars($old['household_size'] ?? $s2['household_size'] ?? '1') ?>" required>
                    <div class="form-text">Total number of people living in your household (1–20).</div>
                    <?php if (isset($errors['household_size'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['household_size']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Number of Dependents <span class="text-danger">*</span></label>
                    <input type="number" name="num_dependents" id="numDependents"
                           class="form-control <?= isset($errors['num_dependents']) ? 'is-invalid' : '' ?>"
                           min="0" max="19" value="<?= htmlspecialchars($old['num_dependents'] ?? $s2['num_dependents'] ?? '0') ?>" required>
                    <div class="form-text">Cannot exceed Household Size − 1.</div>
                    <?php if (isset($errors['num_dependents'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['num_dependents']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Veteran Status <span class="text-danger">*</span></label>
                    <select name="veteran_status" class="form-select" required>
                        <option value="">Select…</option>
                        <?php $vs = $old['veteran_status'] ?? $s2['veteran_status'] ?? ''; ?>
                        <?php foreach (['Non-Veteran','Active Duty','Veteran','Reserve/National Guard','Surviving Spouse'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $vs === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Disability Status <span class="text-danger">*</span></label>
                    <select name="disability_status" class="form-select" required>
                        <option value="">Select…</option>
                        <?php $ds = $old['disability_status'] ?? $s2['disability_status'] ?? ''; ?>
                        <?php foreach (['None','Physical Disability','Cognitive Disability','Sensory Disability','Multiple Disabilities'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $ds === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
    </div>
    <div class="card mb-4">
        <div class="card-header"><span class="card-title-icon bi bi-house"></span>Housing</div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">Housing Situation <span class="text-danger">*</span></label>
                    <select name="housing_situation" class="form-select" required>
                        <option value="">Select…</option>
                        <?php $hs = $old['housing_situation'] ?? $s2['housing_situation'] ?? ''; ?>
                        <?php foreach (['Renting','Own Home','Living with Family','Homeless/Shelter','Subsidized Housing','Other'] as $opt): ?>
                        <option value="<?= $opt ?>" <?= $hs === $opt ? 'selected' : '' ?>><?= $opt ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Monthly Housing Cost ($) <span class="text-danger">*</span></label>
                    <input type="number" name="housing_cost_monthly" class="form-control <?= isset($errors['housing_cost_monthly']) ? 'is-invalid' : '' ?>"
                           min="0" step="0.01" placeholder="0.00"
                           value="<?= htmlspecialchars($old['housing_cost_monthly'] ?? $s2['housing_cost_monthly'] ?? '') ?>" required>
                    <?php if (isset($errors['housing_cost_monthly'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['housing_cost_monthly']) ?></div><?php endif; ?>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Total Monthly Expenses ($) <span class="text-danger">*</span></label>
                    <div class="input-group">
                        <span class="input-group-text">$</span>
                        <input type="number" name="monthly_expenses" class="form-control <?= isset($errors['monthly_expenses']) ? 'is-invalid' : '' ?>"
                               min="0" step="0.01" placeholder="0.00"
                               value="<?= htmlspecialchars($old['monthly_expenses'] ?? $s2['monthly_expenses'] ?? '') ?>" required>
                    </div>
                    <div class="form-text">Include rent/mortgage, utilities, food, transportation, childcare, medical costs.</div>
                    <?php if (isset($errors['monthly_expenses'])): ?><div class="text-danger small"><?= htmlspecialchars($errors['monthly_expenses']) ?></div><?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/applications/wizard/1" class="btn btn-outline-secondary px-4"><i class="bi bi-arrow-left me-1"></i>Back</a>
        <button type="submit" class="btn btn-brand flex-grow-1 py-2"><i class="bi bi-arrow-right me-1"></i>Continue to Step 3</button>
    </div>
</form>
</div>
</div>
<script>
(function () {
    var hsInput  = document.getElementById('householdSize');
    var depInput = document.getElementById('numDependents');
    function validateDep() {
        var hs  = parseInt(hsInput.value, 10)  || 1;
        var dep = parseInt(depInput.value, 10) || 0;
        depInput.max = Math.max(0, hs - 1);
        if (dep >= hs) {
            depInput.setCustomValidity('Dependents cannot equal or exceed household size (' + hs + ').');
        } else {
            depInput.setCustomValidity('');
        }
    }
    hsInput.addEventListener('input', validateDep);
    depInput.addEventListener('input', validateDep);
    validateDep();
})();
</script>
