<?php // pageTitle set by DashboardController ?>

<div class="page-header">
    <div>
        <h2>Auditor Dashboard</h2>
        <div class="page-subtitle">Read-only compliance view &mdash; Welcome, <?= htmlspecialchars(Session::get('first_name')) ?></div>
    </div>
    <a href="<?= BASE_URL ?>/audit-log" class="btn btn-brand-outline">
        <i class="bi bi-journal-text me-1"></i>Full Audit Log
    </a>
</div>

<!-- Status stat cards -->
<div class="row g-3 mb-4">
    <?php
    $iconMap = [
        'Draft'                => ['bi-pencil',             'gray'],
        'Submitted'            => ['bi-inbox',              'blue'],
        'Eligibility Review'   => ['bi-clipboard-check',   'blue'],
        'Pending Verification' => ['bi-hourglass-split',   'amber'],
        'Document Requested'   => ['bi-paperclip',         'purple'],
        'Document Received'    => ['bi-paperclip-fill',    'teal'],
        'Officer Review'       => ['bi-person-badge',      'amber'],
        'Quality Review'       => ['bi-shield-check',      'teal'],
        'Supervisor Review'    => ['bi-gavel',              'blue'],
        'Approved'             => ['bi-check-circle',      'green'],
        'Denied'               => ['bi-x-circle',          'red'],
        'Closed'               => ['bi-lock',              'gray'],
        'Appealed'             => ['bi-megaphone',         'amber'],
        'Appeal Review'        => ['bi-arrow-return-left', 'amber'],
        'Final Disposition'    => ['bi-flag',              'gray'],
        'Auto-Denied'          => ['bi-ban',               'red'],
    ];
    foreach ($data['statusCounts'] as $status => $cnt):
        [$icon, $color] = $iconMap[$status] ?? ['bi-circle', 'gray'];
    ?>
    <div class="col-sm-6 col-lg-3">
        <div class="stat-card">
            <div class="stat-icon <?= $color ?>"><i class="bi <?= $icon ?>"></i></div>
            <div class="stat-value"><?= number_format($cnt) ?></div>
            <div class="stat-label"><?= htmlspecialchars($status) ?></div>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<div class="row g-3">
    <!-- Recent Activity -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><span class="card-title-icon bi bi-clock-history"></span>Recent Activity</span>
                <a href="<?= BASE_URL ?>/audit-log" class="btn btn-sm btn-outline-secondary">View All</a>
            </div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead>
                        <tr><th>Time</th><th>User</th><th>Role</th><th>Action</th></tr>
                    </thead>
                    <tbody>
                    <?php if (empty($data['recentLogs'])): ?>
                        <tr><td colspan="4" class="text-center text-muted py-3">No recent activity.</td></tr>
                    <?php else: ?>
                    <?php foreach ($data['recentLogs'] as $log): ?>
                        <tr>
                            <td class="text-muted small"><?= htmlspecialchars(date('M d, g:ia', strtotime($log['created_at']))) ?></td>
                            <td class="fw-medium small"><?= htmlspecialchars($log['username'] ?? '—') ?></td>
                            <td>
                                <?php $role = strtolower($log['role'] ?? ''); ?>
                                <span class="badge badge-<?= $role ?: 'citizen' ?>"><?= htmlspecialchars($log['role'] ?? '—') ?></span>
                            </td>
                            <td class="small"><?= htmlspecialchars($log['action_type']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Quick Links + Chart -->
    <div class="col-lg-4 d-flex flex-column gap-3">
        <div class="card">
            <div class="card-header"><span class="card-title-icon bi bi-link-45deg"></span>Quick Links</div>
            <div class="list-group list-group-flush">
                <a href="<?= BASE_URL ?>/audit-log" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <i class="bi bi-journal-text text-muted"></i> Audit Log
                </a>
                <a href="<?= BASE_URL ?>/reports" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <i class="bi bi-bar-chart text-muted"></i> Reports
                </a>
                <a href="<?= BASE_URL ?>/applications" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <i class="bi bi-list-ul text-muted"></i> All Applications
                </a>
                <a href="<?= BASE_URL ?>/cases" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <i class="bi bi-folder2-open text-muted"></i> All Cases
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title-icon bi bi-pie-chart"></span>Status Distribution</div>
            <div class="card-body">
                <div class="chart-wrap">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    const labels = <?= json_encode(array_keys($data['statusCounts'])) ?>;
    const values = <?= json_encode(array_values($data['statusCounts'])) ?>;
    const colors = ['#6366f1','#f59e0b','#a855f7','#14b8a6','#6b7280','#22c55e','#C8102E','#f97316'];
    const ctx = document.getElementById('statusChart');
    if (!ctx || !values.filter(v=>v>0).length) return;
    new Chart(ctx, {
        type: 'doughnut',
        data: { labels, datasets: [{ data: values, backgroundColor: colors, borderWidth: 2, borderColor: '#fff' }] },
        options: {
            responsive: true, maintainAspectRatio: false, cutout: '65%',
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 10, font: { size: 11 } } } }
        }
    });
})();
</script>

