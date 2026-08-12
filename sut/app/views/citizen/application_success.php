<?php $pageTitle = 'Application Submitted'; include APP_ROOT . '/app/views/shared/layout_header.php'; ?>

<div class="row justify-content-center mt-4">
    <div class="col-md-7">
        <div class="card text-center py-2">
            <div class="card-body py-5 px-4">
                <div style="width:80px;height:80px;border-radius:50%;background:rgba(34,197,94,.15);display:flex;align-items:center;justify-content:center;margin:0 auto 1.5rem">
                    <i class="bi bi-check-circle-fill text-success" style="font-size:2.5rem"></i>
                </div>
                <h2 class="fw-bold mb-2">Application Submitted!</h2>
                <p class="text-muted mb-4">Your application has been received and is now in our review queue.</p>

                <?php if ($appNumber): ?>
                <div class="mb-4">
                    <div class="text-muted small mb-1">Your Application Number</div>
                    <div class="d-inline-block px-4 py-2 rounded-3 fw-bold fs-4" style="background:var(--brand-light);border:2px dashed var(--brand-red)">
                        <code style="color:var(--brand-red)"><?= htmlspecialchars($appNumber) ?></code>
                    </div>
                    <div class="text-muted small mt-2">Keep this number for your records.</div>
                </div>
                <?php endif; ?>

                <div class="card mb-4" style="background:var(--brand-light)">
                    <div class="card-body py-3">
                        <div class="row g-0">
                            <div class="col-4 border-end py-2">
                                <div class="small text-muted">Step 1</div>
                                <div class="fw-semibold small">Submitted</div>
                                <i class="bi bi-check-circle-fill text-success mt-1"></i>
                            </div>
                            <div class="col-4 border-end py-2">
                                <div class="small text-muted">Step 2</div>
                                <div class="fw-semibold small">Under Review</div>
                                <i class="bi bi-hourglass-split text-warning mt-1"></i>
                            </div>
                            <div class="col-4 py-2">
                                <div class="small text-muted">Step 3</div>
                                <div class="fw-semibold small">Decision</div>
                                <i class="bi bi-circle text-muted mt-1"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-center flex-wrap">
                    <a href="<?= BASE_URL ?>/dashboard/citizen" class="btn btn-brand">
                        <i class="bi bi-house me-1"></i>Go to Dashboard
                    </a>
                    <a href="<?= BASE_URL ?>/documents/upload" class="btn btn-outline-secondary">
                        <i class="bi bi-paperclip me-1"></i>Upload Documents
                    </a>
                    <a href="<?= BASE_URL ?>/applications/submit" class="btn btn-outline-secondary">
                        <i class="bi bi-plus me-1"></i>New Application
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include APP_ROOT . '/app/views/shared/layout_footer.php'; ?>
