<?php $pageTitle = 'Submit Application'; include APP_ROOT . '/app/views/shared/layout_header.php'; ?>

<div class="page-header">
    <div>
        <h2>Submit Application</h2>
        <div class="page-subtitle">Apply for federal citizen assistance programs</div>
    </div>
    <a href="<?= BASE_URL ?>/dashboard/citizen" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
    </a>
</div>

<?php if (isset($errors['limit'])): ?>
    <div class="alert alert-danger rounded-3 mb-3" role="alert">
        <i class="bi bi-exclamation-triangle me-1"></i><?= htmlspecialchars($errors['limit']) ?>
    </div>
<?php endif; ?>
<?php if (!empty($errors) && !isset($errors['limit'])): ?>
    <div class="alert alert-danger rounded-3 mb-3" role="alert">
        Please correct the errors below before submitting.
    </div>
<?php endif; ?>

<div class="row justify-content-center">
<div class="col-lg-8">
    <form method="POST" action="<?= BASE_URL ?>/applications/submit" enctype="multipart/form-data" novalidate id="appForm">
        <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">

        <!-- Program Information -->
        <div class="card mb-3">
            <div class="card-header">
                <span class="card-title-icon bi bi-grid-3x3-gap"></span>Program Information
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label for="program_id" class="form-label">Program Type <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['program_id']) ? 'is-invalid' : '' ?>"
                                id="program_id" name="program_id" required aria-required="true">
                            <option value="">Select a program…</option>
                            <?php foreach ($programs as $p): ?>
                                <option value="<?= $p['program_id'] ?>" <?= ($old['program_id'] ?? '') == $p['program_id'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($p['program_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['program_id'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['program_id']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label for="income_range" class="form-label">Annual Income Range <span class="text-danger">*</span></label>
                        <select class="form-select <?= isset($errors['income_range']) ? 'is-invalid' : '' ?>"
                                id="income_range" name="income_range" required aria-required="true">
                            <option value="">Select range…</option>
                            <?php foreach (['$0-$15,000','$15,001-$30,000','$30,001-$45,000','$45,001-$60,000','$60,001+'] as $inc): ?>
                                <option value="<?= $inc ?>" <?= ($old['income_range'] ?? '') === $inc ? 'selected' : '' ?>><?= $inc ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['income_range'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['income_range']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label for="household_size" class="form-label">Household Size <span class="text-danger">*</span></label>
                        <input type="number" class="form-control <?= isset($errors['household_size']) ? 'is-invalid' : '' ?>"
                               id="household_size" name="household_size"
                               min="1" max="20"
                               value="<?= htmlspecialchars($old['household_size'] ?? '') ?>"
                               required aria-required="true" aria-describedby="hs_help">
                        <div id="hs_help" class="form-text">1–20 members.</div>
                        <?php if (isset($errors['household_size'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['household_size']) ?></div><?php endif; ?>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Urgency Level <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3 mt-1 flex-wrap">
                            <?php foreach (['normal'=>'Normal','high'=>'High','emergency'=>'Emergency'] as $val => $label): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="urgency_level"
                                       id="urg_<?= $val ?>" value="<?= $val ?>"
                                       <?= ($old['urgency_level'] ?? 'normal') === $val ? 'checked' : '' ?>
                                       required aria-required="true">
                                <label class="form-check-label" for="urg_<?= $val ?>">
                                    <span class="badge badge-<?= $val ?>"><?= $label ?></span>
                                </label>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <label for="preferred_contact" class="form-label">Preferred Contact <span class="text-danger">*</span></label>
                        <select class="form-select" id="preferred_contact" name="preferred_contact" required>
                            <?php foreach (['email'=>'Email','phone'=>'Phone','mail'=>'Mail'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= ($old['preferred_contact'] ?? 'email') === $val ? 'selected' : '' ?>><?= $label ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reason for Request -->
        <div class="card mb-3">
            <div class="card-header">
                <span class="card-title-icon bi bi-chat-left-text"></span>Reason for Request
            </div>
            <div class="card-body">
                <label for="reason_for_request" class="form-label">
                    Explain your need for assistance <span class="text-danger">*</span>
                </label>
                <textarea class="form-control <?= isset($errors['reason_for_request']) ? 'is-invalid' : '' ?>"
                          id="reason_for_request" name="reason_for_request"
                          rows="5" minlength="50" required aria-required="true"
                          aria-describedby="reason_help"
                          placeholder="Describe your situation and why you need this assistance…"><?= htmlspecialchars($old['reason_for_request'] ?? '') ?></textarea>
                <div id="reason_help" class="form-text">
                    Minimum 50 characters. <span id="charCount" class="fw-semibold">0</span> entered.
                </div>
                <?php if (isset($errors['reason_for_request'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['reason_for_request']) ?></div><?php endif; ?>
            </div>
        </div>

        <!-- Documents -->
        <div class="card mb-4">
            <div class="card-header">
                <span class="card-title-icon bi bi-paperclip"></span>Supporting Documents <span class="text-muted fw-normal small">(optional)</span>
            </div>
            <div class="card-body">
                <input type="file" class="form-control" id="documents" name="documents[]"
                       multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                       aria-describedby="doc_help">
                <div id="doc_help" class="form-text">
                    Allowed: PDF, DOC, DOCX, JPG, PNG. Max 5 MB per file, up to 10 files.
                </div>
                <div id="fileList" class="mt-2"></div>
            </div>
        </div>

        <button type="submit" class="btn btn-brand btn-lg w-100 py-2">
            <i class="bi bi-send me-1"></i>Submit Application
        </button>
    </form>
</div>
</div>

<script>
const reason = document.getElementById('reason_for_request');
const counter = document.getElementById('charCount');
function updateCount() {
    const n = reason.value.length;
    counter.textContent = n;
    counter.style.color = n < 50 ? 'var(--brand-red)' : 'var(--bs-success)';
}
reason.addEventListener('input', updateCount);
updateCount();

document.getElementById('documents').addEventListener('change', function() {
    const list = document.getElementById('fileList');
    list.innerHTML = '';
    if (!this.files.length) return;
    const ul = document.createElement('ul');
    ul.className = 'list-group list-group-flush';
    for (const f of this.files) {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between align-items-center py-2 small';
        const sizeMB = (f.size / 1024 / 1024).toFixed(2);
        const valid = f.size <= 5 * 1024 * 1024;
        li.innerHTML = `<span><i class="bi bi-file-earmark me-1 text-muted"></i>${f.name}</span>
                        <span class="${valid ? 'text-success' : 'text-danger fw-semibold'}">${sizeMB} MB${valid ? '' : ' — too large'}</span>`;
        ul.appendChild(li);
    }
    list.appendChild(ul);
});
</script>

<?php include APP_ROOT . '/app/views/shared/layout_footer.php'; ?>
