<?php
$stats = $data['stats'] ?? [];
?>
<div class="page-header">
    <div>
        <h2>Quality Reviewer Dashboard</h2>
        <div class="page-subtitle">Cases awaiting quality review</div>
    </div>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon text-warning"><i class="bi bi-hourglass-split"></i></div>
            <div class="stat-value"><?= (int)($stats['pending'] ?? 0) ?></div>
            <div class="stat-label">Pending QR</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon text-danger"><i class="bi bi-exclamation-triangle"></i></div>
            <div class="stat-value"><?= (int)($stats['sla_warning'] ?? 0) ?></div>
            <div class="stat-label">SLA Warning</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon text-success"><i class="bi bi-check-circle"></i></div>
            <div class="stat-value"><?= (int)($stats['approved_today'] ?? 0) ?></div>
            <div class="stat-label">Reviewed Today</div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon text-info"><i class="bi bi-graph-up"></i></div>
            <div class="stat-value"><?= (int)($stats['total_reviewed'] ?? 0) ?></div>
            <div class="stat-label">Total Reviewed</div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><span class="card-title-icon bi bi-shield-check"></span>Quality Review Queue</span>
        <span class="badge bg-warning text-dark"><?= (int)($stats['pending'] ?? 0) ?> pending</span>
    </div>
    <div class="card-body p-0">
        <?php if (empty($queue)): ?>
        <div class="empty-state">
            <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
            <div class="empty-state-text">No cases in queue</div>
            <div class="empty-state-sub">All cases have been reviewed.</div>
        </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead>
                    <tr>
                        <th>Case #</th>
                        <th>Application</th>
                        <th>Applicant</th>
                        <th>Risk</th>
                        <th>SLA</th>
                        <th>In QR Since</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($queue as $row): ?>
                <tr>
                    <td><code><?= htmlspecialchars($row['case_number']) ?></code></td>
                    <td><span class="font-monospace small"><?= htmlspecialchars($row['application_number']) ?></span></td>
                    <td><?= htmlspecialchars($row['applicant_name'] ?? '—') ?></td>
                    <td>
                        <?php $rl = $row['risk_level'] ?? 'unknown'; ?>
                        <span class="badge <?= riskBadgeClass($rl) ?>"><?= ucfirst($rl) ?></span>
                    </td>
                    <td>
                        <?php $sla = $row['sla_status'] ?? 'on_track'; ?>
                        <span class="badge <?= slaBadgeClass($sla) ?>"><?= ucwords(str_replace('_',' ',$sla)) ?></span>
                    </td>
                    <td class="small text-muted"><?= htmlspecialchars($row['status_entered_at'] ?? '—') ?></td>
                    <td>
                        <a href="<?= BASE_URL ?>/quality-review/cases/<?= $row['case_id'] ?>" class="btn btn-sm btn-outline-brand">
                            Review <i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>
