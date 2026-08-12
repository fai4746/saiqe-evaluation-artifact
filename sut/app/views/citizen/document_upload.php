<?php // pageTitle + layout set by DocumentController ?>

<div class="page-header">
    <div>
        <a href="<?= BASE_URL ?>/dashboard/citizen" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="bi bi-arrow-left me-1"></i>Back to Dashboard
        </a>
        <h2>Upload Documents</h2>
        <div class="page-subtitle">Attach supporting files to your applications</div>
    </div>
</div>

<?php $success = Session::get('doc_success'); Session::delete('doc_success'); ?>
<?php if ($success): ?>
    <div class="alert alert-success rounded-3 mb-3" role="alert">
        <i class="bi bi-check-circle me-1"></i><?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger rounded-3 mb-3" role="alert">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row justify-content-center">
<div class="col-lg-7">
    <form method="POST" action="<?= BASE_URL ?>/documents/upload" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
        <div class="card">
            <div class="card-header"><span class="card-title-icon bi bi-cloud-upload"></span>Select Application &amp; Files</div>
            <div class="card-body">
                <div class="mb-3">
                    <label for="application_id" class="form-label">Application <span class="text-danger">*</span></label>
                    <select class="form-select" id="application_id" name="application_id" required aria-required="true">
                        <option value="">Select an application…</option>
                        <?php foreach ($apps as $app): ?>
                            <option value="<?= $app['application_id'] ?>">
                                <?= htmlspecialchars($app['application_number']) ?> — <?= htmlspecialchars($app['program_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="mb-3">
                    <label for="documents" class="form-label">Files <span class="text-danger">*</span></label>
                    <input type="file" class="form-control" id="documents" name="documents[]"
                           multiple accept=".pdf,.doc,.docx,.jpg,.jpeg,.png"
                           required aria-required="true" aria-describedby="doc_help">
                    <div id="doc_help" class="form-text">
                        Allowed: PDF, DOC, DOCX, JPG, PNG &mdash; Max 5 MB each, up to 10 files per application.
                    </div>
                </div>

                <div id="fileList" class="mb-3"></div>

                <button type="submit" class="btn btn-brand w-100 py-2">
                    <i class="bi bi-upload me-1"></i>Upload Files
                </button>
            </div>
        </div>
    </form>
</div>
</div>

<script>
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
                        <span class="${valid ? 'text-success' : 'text-danger fw-semibold'}">${sizeMB} MB${valid ? '' : ' &mdash; exceeds limit'}</span>`;
        ul.appendChild(li);
    }
    list.appendChild(ul);
});
</script>

