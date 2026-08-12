<div class="row justify-content-center">
<div class="col-lg-8">
<div class="page-header">
    <div>
        <h2>File an Appeal</h2>
        <div class="page-subtitle">Application <?= htmlspecialchars($app['application_number']) ?></div>
    </div>
</div>

<div class="alert alert-info rounded-3 mb-4">
    <i class="bi bi-info-circle me-2"></i>
    <strong>Appeal Window:</strong> Your appeal must be submitted by
    <strong><?= $appealWindowExpires ? $appealWindowExpires->format('F j, Y') : 'N/A' ?></strong>.
    Appeals must include a detailed explanation of at least 100 characters.
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-3 mb-3"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<form method="POST" action="<?= BASE_URL ?>/appeals" enctype="multipart/form-data">
    <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
    <input type="hidden" name="application_id" value="<?= (int)$app['application_id'] ?>">

    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-megaphone"></span>Appeal Reason</div>
        <div class="card-body">
            <div class="mb-3">
                <label class="form-label">Why are you appealing this decision? <span class="text-danger">*</span></label>
                <textarea name="appeal_reason"
                          class="form-control <?= isset($errors['appeal_reason']) ? 'is-invalid' : '' ?>"
                          rows="8"
                          placeholder="Please provide a detailed explanation of why you believe this decision should be reconsidered. Include any relevant facts, circumstances, or new information. Minimum 100 characters."
                          required><?= htmlspecialchars($old['appeal_reason'] ?? '') ?></textarea>
                <?php if (isset($errors['appeal_reason'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['appeal_reason']) ?></div><?php endif; ?>
                <div class="form-text">
                    <span id="charCount">0</span> characters (minimum 100 required)
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-file-earmark-plus"></span>Supporting Documents <span class="badge bg-secondary ms-1">Optional</span></div>
        <div class="card-body">
            <p class="small text-muted">Upload up to 3 documents supporting your appeal (PDF, JPG, PNG — max 10 MB each).</p>
            <input type="file" name="appeal_documents[]" class="form-control"
                   accept=".pdf,.jpg,.jpeg,.png" multiple>
        </div>
    </div>

    <div class="card mb-4">
        <div class="card-body">
            <div class="form-check">
                <input class="form-check-input <?= isset($errors['certification']) ? 'is-invalid' : '' ?>"
                       type="checkbox" name="certification" value="1" id="certCheck" required>
                <label class="form-check-label" for="certCheck">
                    I certify that the information provided in this appeal is true and accurate to the best of my knowledge.
                    <span class="text-danger">*</span>
                </label>
                <?php if (isset($errors['certification'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['certification']) ?></div><?php endif; ?>
            </div>
        </div>
    </div>

    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/dashboard/citizen" class="btn btn-outline-secondary px-4">Cancel</a>
        <button type="submit" class="btn btn-brand flex-grow-1 py-2">
            <i class="bi bi-send me-1"></i>Submit Appeal
        </button>
    </div>
</form>
</div>
</div>
<script>
(function(){
    var ta = document.querySelector('textarea[name="appeal_reason"]');
    var cc = document.getElementById('charCount');
    function update(){ var n = ta.value.length; cc.textContent = n; cc.className = n >= 100 ? 'text-success' : 'text-danger'; }
    ta.addEventListener('input', update);
    update();
})();
</script>
