<?php // pageTitle + layout set by SupervisorController ?>

<div class="page-header">
    <div>
        <a href="<?= BASE_URL ?>/supervisor/queue" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="bi bi-arrow-left me-1"></i>Back to Queue
        </a>
        <h2>Final Decision — <code><?= htmlspecialchars($case['application_number']) ?></code></h2>
        <div class="page-subtitle"><?= htmlspecialchars($case['program_name']) ?></div>
    </div>
    <span class="badge <?= statusBadgeClass($case['status']) ?> fs-6 px-3 py-2"><?= htmlspecialchars($case['status']) ?></span>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger rounded-3 mb-3" role="alert">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <!-- LEFT -->
    <div class="col-lg-5 d-flex flex-column gap-3">

        <div class="card">
            <div class="card-header"><span class="card-title-icon bi bi-file-earmark-text"></span>Application Summary</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">App #</dt><dd class="col-7"><code><?= htmlspecialchars($case['application_number']) ?></code></dd>
                    <dt class="col-5 text-muted">Program</dt><dd class="col-7 fw-medium"><?= htmlspecialchars($case['program_name']) ?></dd>
                    <dt class="col-5 text-muted">Income</dt><dd class="col-7"><?= htmlspecialchars($case['income_range']) ?></dd>
                    <dt class="col-5 text-muted">Household</dt><dd class="col-7"><?= $case['household_size'] ?></dd>
                    <dt class="col-5 text-muted">Urgency</dt><dd class="col-7"><span class="badge badge-<?= $case['urgency_level'] ?>"><?= ucfirst($case['urgency_level']) ?></span></dd>
                    <dt class="col-5 text-muted">Citizen</dt><dd class="col-7"><?= htmlspecialchars($case['citizen_first'].' '.$case['citizen_last']) ?></dd>
                    <dt class="col-5 text-muted">Officer</dt><dd class="col-7"><?= htmlspecialchars(($case['officer_first']??'').' '.($case['officer_last']??'Unassigned')) ?></dd>
                </dl>
                <div class="mt-3">
                    <div class="section-heading">Reason</div>
                    <p class="small mb-0"><?= nl2br(htmlspecialchars($case['reason_for_request'])) ?></p>
                </div>
            </div>
        </div>

        <!-- Documents -->
        <div class="card">
            <div class="card-header"><span class="card-title-icon bi bi-paperclip"></span>Documents (<?= count($docs) ?>)</div>
            <?php if (empty($docs)): ?><div class="card-body text-muted small">None uploaded.</div>
            <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($docs as $doc): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                    <span class="small"><?= htmlspecialchars($doc['original_filename']) ?></span>
                    <a href="<?= BASE_URL ?>/documents/<?= $doc['document_id'] ?>/download" class="btn btn-sm btn-outline-secondary"><i class="bi bi-download"></i></a>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <!-- Decision Form -->
        <div class="card" style="border:2px solid var(--brand-red)">
            <div class="card-header" style="background:var(--brand-red);color:#fff">
                <i class="bi bi-gavel me-2"></i>Submit Final Decision
            </div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>/supervisor/cases/<?= $case['case_id'] ?>/decision" id="decisionForm">
                    <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">

                    <div class="mb-3">
                        <label class="form-label">Decision <span class="text-danger">*</span></label>
                        <div class="d-flex gap-3 flex-wrap">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="decision" id="dec_approve" value="Approved" required aria-required="true">
                                <label class="form-check-label fw-bold text-success" for="dec_approve">
                                    <i class="bi bi-check-circle me-1"></i>Approve
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="decision" id="dec_deny" value="Denied">
                                <label class="form-check-label fw-bold text-danger" for="dec_deny">
                                    <i class="bi bi-x-circle me-1"></i>Deny
                                </label>
                            </div>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="decision" id="dec_return" value="Quality Review">
                                <label class="form-check-label fw-bold text-warning" for="dec_return">
                                    <i class="bi bi-arrow-return-left me-1"></i>Return for Re-review (QR)
                                </label>
                            </div>
                        </div>
                        <?php if (isset($errors['decision'])): ?><div class="text-danger small mt-1"><?= htmlspecialchars($errors['decision']) ?></div><?php endif; ?>
                    </div>

                    <div class="mb-3">
                        <label for="decision_comments" class="form-label">
                            Decision Comments <span class="text-danger">*</span>
                        </label>
                        <textarea class="form-control <?= isset($errors['decision_comments']) ? 'is-invalid' : '' ?>"
                                  id="decision_comments" name="decision_comments" rows="4"
                                  required aria-required="true"
                                  aria-describedby="dc_help <?= isset($errors['decision_comments']) ? 'dc_err' : '' ?>"
                                  placeholder="Provide your reasoning for this decision…"></textarea>
                        <div id="dc_help" class="form-text">Required before any decision can be submitted.</div>
                        <?php if (isset($errors['decision_comments'])): ?><div class="invalid-feedback" id="dc_err"><?= htmlspecialchars($errors['decision_comments']) ?></div><?php endif; ?>
                    </div>

                    <button type="submit" class="btn btn-brand w-100 py-2">
                        <i class="bi bi-send me-1"></i>Submit Decision
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- RIGHT: Notes + Timeline -->
    <div class="col-lg-7 d-flex flex-column gap-3">
        <div class="card">
            <div class="card-header"><span class="card-title-icon bi bi-chat-left-text"></span>Officer Notes</div>
            <?php if (empty($notes)): ?><div class="card-body text-muted small">No notes added.</div>
            <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach (array_reverse($notes) as $n): ?>
                <li class="list-group-item py-2">
                    <div class="d-flex justify-content-between mb-1">
                        <span class="fw-semibold small"><?= htmlspecialchars($n['first_name'].' '.$n['last_name']) ?></span>
                        <span class="text-muted small"><?= date('M d, Y', strtotime($n['created_at'])) ?></span>
                    </div>
                    <p class="mb-0 small"><?= nl2br(htmlspecialchars($n['note_text'])) ?></p>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title-icon bi bi-clock-history"></span>Status History</div>
            <div class="card-body">
                <ul class="timeline">
                <?php foreach (array_reverse($history) as $h): ?>
                <li class="timeline-item">
                    <div class="timeline-dot"><i class="bi bi-arrow-right"></i></div>
                    <div class="timeline-body">
                        <div class="d-flex flex-wrap gap-1 align-items-center mb-1">
                            <?php if ($h['old_status']): ?><span class="badge bg-secondary"><?= htmlspecialchars($h['old_status']) ?></span><i class="bi bi-arrow-right text-muted small"></i><?php endif; ?>
                            <span class="badge" style="background:var(--brand-red)"><?= htmlspecialchars($h['new_status']) ?></span>
                        </div>
                        <div class="timeline-meta"><?= htmlspecialchars($h['first_name'].' '.$h['last_name']) ?> &bull; <?= date('M d, Y', strtotime($h['changed_at'])) ?></div>
                        <?php if ($h['comments']): ?><div class="small text-muted mt-1"><?= nl2br(htmlspecialchars($h['comments'])) ?></div><?php endif; ?>
                    </div>
                </li>
                <?php endforeach; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('decisionForm').addEventListener('submit', function(e) {
    if (!document.querySelector('[name="decision"]:checked')) { e.preventDefault(); alert('Please select a decision.'); return; }
    if (!document.getElementById('decision_comments').value.trim()) { e.preventDefault(); alert('Decision comments are required.'); }
});
</script>

