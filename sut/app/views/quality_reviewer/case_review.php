<?php
$risk = $risk ?? null;
$sla  = $sla  ?? null;
// Compute sla_status from raw sla_tracking row
$slaStatus = null;
if ($sla) {
    $slaStatus = $sla['is_breached'] ? 'breached'
               : (($sla['warning_date'] <= date('Y-m-d H:i:s')) ? 'warning' : 'on_track');
}
$caseNumber = 'CASE-' . str_pad($case['case_id'], 5, '0', STR_PAD_LEFT);
?>
<div class="page-header">
    <div>
        <h2>Quality Review — <code><?= htmlspecialchars($caseNumber) ?></code></h2>
        <div class="page-subtitle"><?= htmlspecialchars($case['application_number']) ?></div>
    </div>
    <div class="d-flex gap-2">
        <?php if ($risk): ?>
        <span class="badge <?= riskBadgeClass($risk['risk_level']) ?> fs-6">Risk: <?= ucfirst($risk['risk_level']) ?></span>
        <?php endif; ?>
        <?php if ($slaStatus): ?>
        <span class="badge <?= slaBadgeClass($slaStatus) ?> fs-6"><?= ucwords(str_replace('_',' ',$slaStatus)) ?></span>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($errors)): ?>
<div class="alert alert-danger rounded-3 mb-3"><?php foreach ($errors as $e): ?><div><?= htmlspecialchars($e) ?></div><?php endforeach; ?></div>
<?php endif; ?>

<div class="row g-3">
<div class="col-lg-8">

    <?php if ($risk): ?>
    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-graph-up-arrow"></span>Risk Assessment</div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-sm-4 text-center">
                    <div class="fs-1 fw-bold <?= riskBadgeClass($risk['risk_level']) === 'badge-risk-critical' ? 'text-danger' : ($risk['risk_level']==='high' ? 'text-warning' : 'text-success') ?>">
                        <?= (int)($risk['total_score'] ?? 0) ?>
                    </div>
                    <div class="small text-muted">Risk Score</div>
                </div>
                <div class="col-sm-8">
                    <div class="mb-1 small"><strong>Level:</strong> <span class="badge <?= riskBadgeClass($risk['risk_level']) ?>"><?= ucfirst($risk['risk_level']) ?></span></div>
                    <?php if (!empty($risk['score_breakdown_json'])): ?>
                    <?php $factors = json_decode($risk['score_breakdown_json'], true) ?? []; ?>
                    <?php foreach ($factors as $fKey => $f): ?>
                    <div class="small text-muted">
                        <span class="fw-semibold"><?= htmlspecialchars(ucwords(str_replace('_', ' ', $fKey))) ?></span>:
                        <?= htmlspecialchars($f['detail'] ?? '') ?> (+<?= (int)($f['points'] ?? 0) ?> pts)
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-file-earmark-text"></span>Application Details</div>
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-6"><span class="text-muted small">Status</span>
                    <div><span class="badge <?= statusBadgeClass($case['status']) ?>"><?= htmlspecialchars($case['status']) ?></span></div>
                </div>
                <div class="col-md-6"><span class="text-muted small">Program</span><div><?= htmlspecialchars($case['program_name'] ?? '—') ?></div></div>
                <div class="col-md-6"><span class="text-muted small">Assigned Officer</span><div><?= htmlspecialchars(trim(($case['officer_first']??'').' '.($case['officer_last']??'')) ?: '—') ?></div></div>
                <div class="col-md-6"><span class="text-muted small">Submitted</span><div><?= htmlspecialchars($case['submitted_at'] ?? '—') ?></div></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-clock-history"></span>Status History</div>
        <div class="card-body p-0">
            <?php if (empty($history)): ?>
            <div class="p-3 text-muted small">No history recorded.</div>
            <?php else: ?>
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
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($docs)): ?>
    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-file-earmark"></span>Documents</div>
        <div class="card-body p-0">
            <ul class="list-group list-group-flush">
                <?php foreach ($docs as $doc): ?>
                <li class="list-group-item d-flex align-items-center gap-2 py-2">
                    <i class="bi bi-file-earmark-arrow-down text-brand"></i>
                    <span class="flex-grow-1"><?= htmlspecialchars($doc['original_filename']) ?></span>
                    <span class="badge bg-secondary"><?= htmlspecialchars($doc['document_category'] ?? 'other') ?></span>
                    <a href="<?= BASE_URL ?>/documents/<?= $doc['document_id'] ?>/download" class="btn btn-sm btn-outline-secondary" target="_blank">View</a>
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
                        <strong class="small"><?= htmlspecialchars(($n['first_name']??'').' '.($n['last_name']??'')) ?></strong>
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
    <div class="card mb-3 border-brand">
        <div class="card-header bg-brand text-white"><span class="card-title-icon bi bi-shield-check"></span>QR Decision</div>
        <div class="card-body">
            <form method="POST" action="<?= BASE_URL ?>/quality-review/cases/<?= $case['case_id'] ?>/action">
                <input type="hidden" name="csrf_token" value="<?= Session::csrfToken() ?>">
                <div class="mb-3">
                    <label class="form-label">Action <span class="text-danger">*</span></label>
                    <select name="qr_action" class="form-select" required>
                        <option value="">Select decision…</option>
                        <option value="pass">Pass — Forward to Supervisor</option>
                        <option value="return">Return to Officer Review</option>
                        <option value="flag">Flag Issues (return with flag)</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">Notes <span class="text-danger">*</span></label>
                    <textarea name="qr_notes" class="form-control <?= isset($errors['qr_notes']) ? 'is-invalid' : '' ?>"
                              rows="4" placeholder="Document your findings and reasoning…" required></textarea>
                    <?php if (isset($errors['qr_notes'])): ?><div class="invalid-feedback"><?= htmlspecialchars($errors['qr_notes']) ?></div><?php endif; ?>
                </div>
                <div class="mb-3">
                    <label class="form-label">Checklist</label>
                    <?php foreach ([
                        'docs_complete'    => 'Documents complete and legible',
                        'income_verified'  => 'Income information verified',
                        'identity_verified'=> 'Identity confirmed',
                        'data_consistent'  => 'Application data consistent',
                    ] as $k => $label): ?>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="checklist[<?= $k ?>]" value="1" id="chk_<?= $k ?>">
                        <label class="form-check-label small" for="chk_<?= $k ?>"><?= $label ?></label>
                    </div>
                    <?php endforeach; ?>
                </div>
                <button type="submit" class="btn btn-brand w-100">Submit QR Decision</button>
            </form>
        </div>
    </div>

    <?php if ($sla): ?>
    <div class="card mb-3">
        <div class="card-header"><span class="card-title-icon bi bi-clock"></span>SLA</div>
        <div class="card-body">
            <div class="mb-1 d-flex justify-content-between">
                <span class="small text-muted">Status</span>
                <span class="badge <?= slaBadgeClass($slaStatus) ?>"><?= ucwords(str_replace('_',' ',$slaStatus)) ?></span>
            </div>
            <div class="d-flex justify-content-between">
                <span class="small text-muted">Deadline</span>
                <span class="small"><?= htmlspecialchars($sla['sla_deadline'] ?? '—') ?></span>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>
</div>
