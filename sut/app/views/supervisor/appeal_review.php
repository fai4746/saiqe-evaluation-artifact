<div class="page-header">
    <div>
        <h2>Review Appeal #<?= (int)$appeal['appeal_id'] ?></h2>
        <div class="page-subtitle">Application <?= htmlspecialchars($app['application_number'] ?? '—') ?></div>
    </div>
    <span class="badge bg-warning text-dark fs-6">Appeal Review</span>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-3 mb-3"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="row g-3">
<div class="col-lg-8">

    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-megaphone"></span>Appeal Details</div>
        <div class="card-body">
            <div class="row g-2 mb-3">
                <div class="col-md-6"><span class="text-muted small">Filed On</span><div><?= htmlspecialchars($appeal['submitted_at'] ?? '—') ?></div></div>
                <div class="col-md-6">
                    <span class="text-muted small">Window Expires</span>
                    <div class="<?= (!empty($appeal['appeal_window_expires_at']) && strtotime($appeal['appeal_window_expires_at']) < time() + 86400*3) ? 'text-danger fw-semibold' : '' ?>">
                        <?= htmlspecialchars($appeal['appeal_window_expires_at'] ?? '—') ?>
                    </div>
                </div>
                <div class="col-md-6"><span class="text-muted small">Citizen</span><div><?= htmlspecialchars(trim(($appeal['citizen_first']??'').' '.($appeal['citizen_last']??'')) ?: '—') ?></div></div>
                <div class="col-md-6"><span class="text-muted small">Program</span><div><?= htmlspecialchars($app['program_name'] ?? '—') ?></div></div>
            </div>
            <div>
                <span class="text-muted small">Appeal Reason</span>
                <div class="mt-1 p-3 bg-light rounded"><?= nl2br(htmlspecialchars($appeal['appeal_reason'])) ?></div>
            </div>
        </div>
    </div>

    <?php if (!empty($docs)): ?>
    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-file-earmark"></span>Appeal Documents</div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <?php foreach ($docs as $doc): ?>
                <li class="list-group-item d-flex align-items-center gap-2 py-2">
                    <i class="bi bi-file-earmark text-brand"></i>
                    <span class="flex-grow-1"><?= htmlspecialchars($doc['original_filename']) ?></span>
                    <span class="small text-muted"><?= number_format($doc['file_size_bytes'] / 1024, 0) ?> KB</span>
                    <a href="<?= BASE_URL ?>/supervisor/appeals/document/<?= $doc['doc_id'] ?>" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($history)): ?>
    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-clock-history"></span>Case History</div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <?php foreach ($history as $h): ?>
                <li class="list-group-item py-2">
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge <?= statusBadgeClass($h['new_status']) ?>"><?= htmlspecialchars($h['new_status']) ?></span>
                        <small class="text-muted"><?= htmlspecialchars($h['changed_at']) ?></small>
                    </div>
                    <?php if ($h['comments']): ?><div class="small text-muted mt-1"><?= htmlspecialchars($h['comments']) ?></div><?php endif; ?>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($notes)): ?>
    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-chat-left-text"></span>Case Notes</div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <?php foreach ($notes as $n): ?>
                <li class="list-group-item py-2">
                    <div class="d-flex justify-content-between mb-1">
                        <strong class="small"><?= htmlspecialchars(trim(($n['first_name']??'').' '.($n['last_name']??'')) ?: 'Unknown') ?></strong>
                        <small class="text-muted"><?= htmlspecialchars($n['created_at']) ?></small>
                    </div>
                    <div class="small"><?= nl2br(htmlspecialchars($n['note_text'])) ?></div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php endif; ?>
</div>

<div class="col-lg-4">
    <div class="card border-brand">
        <div class="card-header bg-brand text-white"><span class="card-title-icon bi bi-gavel"></span>Appeal Decision</div>
        <div class="card-body">
            <form method="POST" action="<?= BASE_URL ?>/supervisor/appeals/<?= (int)$appeal['appeal_id'] ?>/decision">
                <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
                <div class="mb-3">
                    <label class="form-label">Decision <span class="text-danger">*</span></label>
                    <div class="d-flex gap-2">
                        <div class="form-check flex-grow-1 border rounded p-3">
                            <input class="form-check-input" type="radio" name="decision" value="upheld" id="decUpheld" required>
                            <label class="form-check-label" for="decUpheld">
                                <strong class="text-success">Uphold Appeal</strong>
                                <div class="small text-muted">Case → Approved</div>
                            </label>
                        </div>
                        <div class="form-check flex-grow-1 border rounded p-3">
                            <input class="form-check-input" type="radio" name="decision" value="denied" id="decDenied" required>
                            <label class="form-check-label" for="decDenied">
                                <strong class="text-danger">Deny Appeal</strong>
                                <div class="small text-muted">Case → Final Disposition</div>
                            </label>
                        </div>
                    </div>
                    <?php if (isset($errors['decision'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['decision']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Decision Notes <span class="text-danger">*</span></label>
                    <textarea name="decision_notes" class="form-control <?= isset($errors['decision_notes']) ? 'is-invalid' : '' ?>"
                              rows="5" placeholder="Provide detailed reasoning (minimum 50 characters)…" required></textarea>
                    <?php if (isset($errors['decision_notes'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['decision_notes']) ?></div><?php endif; ?>
                    <div class="form-text">Minimum 50 characters required.</div>
                </div>
                <button type="submit" class="btn btn-brand w-100">Submit Decision</button>
                <a href="<?= BASE_URL ?>/supervisor/appeals" class="btn btn-outline-secondary w-100 mt-2">Back to Queue</a>
            </form>
        </div>
    </div>
</div>
</div>
