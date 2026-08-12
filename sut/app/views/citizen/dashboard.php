<?php

$apps = $data['applications'] ?? [];
$statusCounts = [];
foreach ($apps as $a) $statusCounts[$a['status']] = ($statusCounts[$a['status']] ?? 0) + 1;
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-house me-2" style="color:var(--brand-red)"></i>My Dashboard</h2>
        <div class="page-subtitle">Welcome, <strong><?= htmlspecialchars(Session::get('first_name').' '.Session::get('last_name')) ?></strong></div>
    </div>
    <a href="<?= BASE_URL ?>/applications/wizard" class="btn btn-brand">
        <i class="bi bi-plus-circle me-1"></i>New Application
    </a>
</div>

<?php if (($data['unreadCount'] ?? 0) > 0): ?>
<div class="alert d-flex align-items-center gap-2 mb-4" style="background:#fef2f4;border:1px solid #fecdd3;border-radius:10px" role="alert">
    <i class="bi bi-bell-fill" style="color:var(--brand-red)" aria-hidden="true"></i>
    <span>You have <strong><?= $data['unreadCount'] ?></strong> unread notification(s).</span>
    <a href="<?= BASE_URL ?>/notifications" class="ms-auto btn btn-sm btn-brand">View</a>
</div>
<?php endif; ?>

<!-- Summary stats -->
<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-files"></i></div>
            <div><div class="stat-value"><?= count($apps) ?></div><div class="stat-label">Total Applications</div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
            <div><div class="stat-value"><?= $statusCounts['Approved'] ?? 0 ?></div><div class="stat-label">Approved</div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div>
                <?php $activeTerminal = ['Approved','Denied','Closed','Auto-Denied','Final Disposition']; ?>
                <div class="stat-value"><?= count(array_filter($apps, fn($a) => !in_array($a['status'], $activeTerminal))) ?></div>
                <div class="stat-label">Active</div>
            </div>
        </div>
    </div>
</div>

<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><span class="card-title-icon bi bi-file-earmark-text"></span>My Applications</span>
        <a href="<?= BASE_URL ?>/applications/wizard" class="btn btn-sm btn-brand">+ New Application</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Application #</th><th>Program</th><th>Urgency</th><th>Status</th><th>Submitted</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($apps)): ?>
                <tr><td colspan="6">
                    <div class="empty-state">
                        <div class="empty-state-icon"><i class="bi bi-inbox"></i></div>
                        <div class="empty-state-text">No applications yet</div>
                        <div class="empty-state-sub"><a href="<?= BASE_URL ?>/applications/wizard">Start your first application</a></div>
                    </div>
                </td></tr>
            <?php else: ?>
                <?php foreach ($apps as $app): ?>
                <?php
                    $isDenied    = $app['status'] === 'Denied';
                    $submittedAt = strtotime($app['submitted_at']);
                    $appealWindow = 30 * 24 * 3600; // 30 days
                    $canAppeal   = $isDenied && (time() - $submittedAt) <= $appealWindow
                                   && !in_array($app['status'] ?? '', ['Appealed','Appeal Review','Final Disposition','Closed']);
                ?>
                <tr onclick="window.location='<?= BASE_URL ?>/applications/<?= $app['application_id'] ?>'">
                    <td><code><?= htmlspecialchars($app['application_number']) ?></code></td>
                    <td><?= htmlspecialchars($app['program_name']) ?></td>
                    <td><span class="priority-dot <?= $app['urgency_level'] ?>"></span><span class="badge badge-<?= $app['urgency_level'] ?>"><?= ucfirst($app['urgency_level']) ?></span></td>
                    <td>
                        <span class="badge <?= statusBadgeClass($app['status']) ?>"><?= htmlspecialchars($app['status']) ?></span>
                    </td>
                    <td class="text-muted small"><?= date('M d, Y', strtotime($app['submitted_at'])) ?></td>
                    <td class="text-end">
                        <div class="d-flex gap-1 justify-content-end" onclick="event.stopPropagation()">
                            <a href="<?= BASE_URL ?>/applications/<?= $app['application_id'] ?>" class="btn btn-sm btn-outline-secondary">View</a>
                            <?php if ($canAppeal): ?>
                            <a href="<?= BASE_URL ?>/appeals/new?app=<?= $app['application_id'] ?>"
                               class="btn btn-sm btn-outline-danger"
                               title="File an appeal — window closes <?= date('M d, Y', $submittedAt + $appealWindow) ?>">
                                <i class="bi bi-megaphone me-1"></i>Appeal
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
