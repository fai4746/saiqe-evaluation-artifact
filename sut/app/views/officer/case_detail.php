<?php // pageTitle + layout set by CaseController
$locked = in_array($case['status'], ['Approved','Denied']);
$roleId = Session::get('role_id');
$userId = Session::get('user_id');
$isAssignedOfficer = ($roleId === ROLE_OFFICER && (int)$case['assigned_officer_id'] === $userId);
?>

<div class="page-header">
    <div>
        <a href="<?= BASE_URL ?>/cases" class="btn btn-sm btn-outline-secondary mb-2">
            <i class="bi bi-arrow-left me-1"></i>Back to Cases
        </a>
        <h2>Case #<?= $case['case_id'] ?> &mdash; <code><?= htmlspecialchars($case['application_number']) ?></code></h2>
        <div class="page-subtitle"><?= htmlspecialchars($case['program_name']) ?> &bull; Submitted <?= date('M d, Y', strtotime($case['submitted_at'])) ?></div>
    </div>
    <div class="d-flex flex-column align-items-end gap-2">
        <?php $sc = strtolower(str_replace([' ','/','.'],'-',$case['status'])); ?>
        <span class="badge <?= statusBadgeClass($case['status']) ?> fs-6 px-3 py-2"><?= htmlspecialchars($case['status']) ?></span>
        <?php if (!empty($risk)): ?>
        <span class="badge <?= riskBadgeClass($risk['risk_level']) ?> px-2 py-1">
            <i class="bi bi-graph-up me-1"></i>Risk: <?= ucfirst($risk['risk_level']) ?> (<?= (int)($risk['total_score'] ?? $risk['risk_score'] ?? 0) ?>)
        </span>
        <?php endif; ?>
        <?php if (!empty($sla)): ?>
        <?php $slaStatus = $sla['is_breached'] ? 'breached' : (($sla['warning_date'] <= date('Y-m-d H:i:s')) ? 'warning' : 'on_track'); ?>
        <span class="badge <?= slaBadgeClass($slaStatus) ?> px-2 py-1">
            <i class="bi bi-clock me-1"></i>SLA: <?= ucwords(str_replace('_',' ',$slaStatus)) ?>
        </span>
        <?php endif; ?>
        <?php if ($locked): ?>
            <span class="badge bg-dark px-3 py-2"><i class="bi bi-lock-fill me-1"></i>Locked</span>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger rounded-3 mb-3" role="alert">
        <?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?>
    </div>
<?php endif; ?>

<div class="row g-3">
    <!-- LEFT: Application + Citizen + Docs -->
    <div class="col-lg-5 d-flex flex-column gap-3">

        <!-- Application Details -->
        <div class="card">
            <div class="card-header"><span class="card-title-icon bi bi-file-earmark-text"></span>Application Details</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5 text-muted">Program</dt><dd class="col-7 fw-medium"><?= htmlspecialchars($case['program_name']) ?></dd>
                    <dt class="col-5 text-muted">Income Range</dt><dd class="col-7"><?= htmlspecialchars($case['income_range']) ?></dd>
                    <dt class="col-5 text-muted">Household Size</dt><dd class="col-7"><?= $case['household_size'] ?></dd>
                    <dt class="col-5 text-muted">Urgency</dt>
                    <dd class="col-7"><span class="priority-dot <?= $case['urgency_level'] ?>"></span><span class="badge badge-<?= $case['urgency_level'] ?>"><?= ucfirst($case['urgency_level']) ?></span></dd>
                    <dt class="col-5 text-muted">Submitted</dt><dd class="col-7"><?= date('M d, Y g:ia', strtotime($case['submitted_at'])) ?></dd>
                    <dt class="col-5 text-muted">IP Address</dt><dd class="col-7 text-muted"><?= htmlspecialchars($case['ip_address'] ?? '—') ?></dd>
                </dl>
                <div class="mt-3">
                    <div class="section-heading">Reason for Request</div>
                    <p class="small mb-0" style="line-height:1.6"><?= nl2br(htmlspecialchars($case['reason_for_request'])) ?></p>
                </div>
            </div>
        </div>

        <!-- Citizen Info -->
        <div class="card">
            <div class="card-header"><span class="card-title-icon bi bi-person"></span>Citizen Contact</div>
            <div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-4 text-muted">Name</dt><dd class="col-8 fw-medium"><?= htmlspecialchars($case['citizen_first'].' '.$case['citizen_last']) ?></dd>
                    <dt class="col-4 text-muted">Email</dt><dd class="col-8"><a href="mailto:<?= htmlspecialchars($case['citizen_email']) ?>"><?= htmlspecialchars($case['citizen_email']) ?></a></dd>
                    <dt class="col-4 text-muted">Phone</dt><dd class="col-8"><?= htmlspecialchars($case['phone']) ?></dd>
                </dl>
            </div>
        </div>

        <!-- Assign Officer (Admin) -->
        <?php if ($roleId === ROLE_ADMIN): ?>
        <div class="card">
            <div class="card-header"><span class="card-title-icon bi bi-person-check"></span>Assign Officer</div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>/cases/<?= $case['case_id'] ?>/assign" class="d-flex gap-2">
                    <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
                    <select name="officer_id" class="form-select form-select-sm" required>
                        <option value="">Select officer…</option>
                        <?php foreach ((new UserModel())->getOfficers() as $o): ?>
                            <option value="<?= $o['user_id'] ?>" <?= (int)$case['assigned_officer_id'] === $o['user_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($o['first_name'].' '.$o['last_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn btn-brand btn-sm flex-shrink-0">Assign</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Documents -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><span class="card-title-icon bi bi-paperclip"></span>Documents</span>
                <span class="badge bg-secondary"><?= count($docs) ?>/10</span>
            </div>
            <?php if (empty($docs)): ?>
                <div class="card-body text-muted small">No documents uploaded.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach ($docs as $doc): ?>
                    <li class="list-group-item d-flex justify-content-between align-items-center py-2">
                        <div>
                            <i class="bi bi-file-earmark me-1 text-muted"></i>
                            <span class="small"><?= htmlspecialchars($doc['original_filename']) ?></span>
                            <div class="text-muted" style="font-size:0.73rem"><?= round($doc['file_size_bytes']/1024,1) ?> KB &bull; <?= date('M d, Y', strtotime($doc['uploaded_at'])) ?></div>
                        </div>
                        <?php if (in_array($roleId, [ROLE_OFFICER,ROLE_SUPERVISOR,ROLE_AUDITOR,ROLE_ADMIN])): ?>
                        <a href="<?= BASE_URL ?>/documents/<?= $doc['document_id'] ?>/download"
                           class="btn btn-sm btn-outline-secondary" aria-label="Download <?= htmlspecialchars($doc['original_filename']) ?>">
                            <i class="bi bi-download"></i>
                        </a>
                        <?php endif; ?>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

    </div><!-- /col left -->

    <!-- RIGHT: Status update, Notes, History timeline -->
    <div class="col-lg-7 d-flex flex-column gap-3">

        <!-- Status Update -->
        <?php if (!$locked && $isAssignedOfficer): ?>
        <div class="card border-primary">
            <div class="card-header" style="background:#eff6ff;border-color:#bfdbfe">
                <span class="card-title-icon bi bi-arrow-repeat" style="color:#1d4ed8"></span>Update Case Status
            </div>
            <div class="card-body">
                <?php $transitions = VALID_TRANSITIONS[$case['status']] ?? []; ?>
                <?php if (empty($transitions)): ?>
                    <p class="text-muted mb-0 small">No further officer transitions available from current status.</p>
                <?php else: ?>
                <form method="POST" action="<?= BASE_URL ?>/cases/<?= $case['case_id'] ?>/status">
                    <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label for="status_select" class="form-label">New Status <span class="text-danger">*</span></label>
                            <select class="form-select" id="status_select" name="status" required aria-required="true">
                                <option value="">Select…</option>
                                <?php foreach ($transitions as $t): ?>
                                    <option value="<?= $t ?>"><?= $t ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-7">
                            <label for="comments" class="form-label">Comments</label>
                            <input type="text" class="form-control" id="comments" name="comments" placeholder="Optional note for this change">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-brand mt-3">Update Status</button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Add Note -->
        <?php if (!$locked && $isAssignedOfficer): ?>
        <div class="card">
            <div class="card-header"><span class="card-title-icon bi bi-pencil-square"></span>Add Case Note</div>
            <div class="card-body">
                <form method="POST" action="<?= BASE_URL ?>/cases/<?= $case['case_id'] ?>/notes">
                    <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
                    <textarea class="form-control" name="note_text" rows="2" placeholder="Add a note about this case…" required aria-required="true" aria-label="Case note"></textarea>
                    <button type="submit" class="btn btn-outline-secondary btn-sm mt-2">Add Note</button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Case Notes -->
        <div class="card">
            <div class="card-header d-flex justify-content-between">
                <span><span class="card-title-icon bi bi-chat-left-text"></span>Case Notes</span>
                <span class="badge bg-secondary"><?= count($notes) ?></span>
            </div>
            <?php if (empty($notes)): ?>
                <div class="card-body text-muted small">No notes added yet.</div>
            <?php else: ?>
                <ul class="list-group list-group-flush">
                    <?php foreach (array_reverse($notes) as $n): ?>
                    <li class="list-group-item py-2">
                        <div class="d-flex justify-content-between mb-1">
                            <span class="fw-semibold small"><?= htmlspecialchars($n['first_name'].' '.$n['last_name']) ?></span>
                            <span class="text-muted small"><?= date('M d, Y g:ia', strtotime($n['created_at'])) ?></span>
                        </div>
                        <p class="mb-0 small"><?= nl2br(htmlspecialchars($n['note_text'])) ?></p>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <!-- Status History Timeline -->
        <div class="card">
            <div class="card-header"><span class="card-title-icon bi bi-clock-history"></span>Status History</div>
            <div class="card-body">
                <?php if (empty($history)): ?>
                    <p class="text-muted small mb-0">No history recorded.</p>
                <?php else: ?>
                <ul class="timeline">
                    <?php foreach (array_reverse($history) as $h): ?>
                    <li class="timeline-item">
                        <div class="timeline-dot"><i class="bi bi-arrow-right"></i></div>
                        <div class="timeline-body">
                            <div class="d-flex flex-wrap gap-1 align-items-center mb-1">
                                <?php if ($h['old_status']): ?>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($h['old_status']) ?></span>
                                    <i class="bi bi-arrow-right text-muted small"></i>
                                <?php endif; ?>
                                <span class="badge" style="background:var(--brand-red)"><?= htmlspecialchars($h['new_status']) ?></span>
                            </div>
                            <div class="timeline-meta">
                                <?= htmlspecialchars($h['first_name'].' '.$h['last_name']) ?>
                                <span class="badge badge-<?= $h['role_name'] ?> ms-1"><?= $h['role_name'] ?></span>
                                &bull; <?= date('M d, Y g:ia', strtotime($h['changed_at'])) ?>
                            </div>
                            <?php if ($h['comments']): ?>
                                <div class="small text-muted mt-1"><?= nl2br(htmlspecialchars($h['comments'])) ?></div>
                            <?php endif; ?>
                        </div>
                    </li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
        </div>

    </div><!-- /col right -->
</div><!-- /row -->

