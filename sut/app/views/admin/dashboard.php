<div class="page-header">
    <div>
        <h2><i class="bi bi-speedometer2 me-2" style="color:var(--brand-red)"></i>Admin Dashboard</h2>
        <div class="page-subtitle">Welcome back, <strong><?= htmlspecialchars(Session::get('first_name')) ?></strong> &mdash; Full system overview</div>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>/users" class="btn btn-brand btn-sm"><i class="bi bi-person-plus me-1"></i>Add User</a>
        <a href="<?= BASE_URL ?>/reports" class="btn btn-brand-outline btn-sm"><i class="bi bi-bar-chart me-1"></i>Reports</a>
        <a href="<?= BASE_URL ?>/admin/training-defects" class="btn btn-outline-secondary btn-sm"><i class="bi bi-bug me-1"></i>Training</a>
    </div>
</div>

<?php
$counts = $data['statusCounts'];
$total  = array_sum($counts);
$approved = $counts['Approved'] ?? 0;
$denied   = $counts['Denied']   ?? 0;
$pending  = ($counts['Submitted'] ?? 0) + ($counts['Officer Review'] ?? 0) + ($counts['Quality Review'] ?? 0) + ($counts['Supervisor Review'] ?? 0);
$slaCounts = $data['slaCounts'] ?? ['on_track'=>0,'warning'=>0,'breached'=>0];
?>

<!-- Stat Row -->
<div class="row g-3 mb-4">
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="bi bi-files"></i></div>
            <div><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Applications</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div><div class="stat-value"><?= $pending ?></div><div class="stat-label">Pending / In Progress</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
            <div><div class="stat-value"><?= $approved ?></div><div class="stat-label">Approved</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-x-circle"></i></div>
            <div><div class="stat-value"><?= $denied ?></div><div class="stat-label">Denied</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon purple"><i class="bi bi-people"></i></div>
            <div><div class="stat-value"><?= $data['userCounts']['total'] ?? 0 ?></div><div class="stat-label">Total Users</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon teal"><i class="bi bi-person-vcard"></i></div>
            <div><div class="stat-value"><?= $data['userCounts']['citizens'] ?? 0 ?></div><div class="stat-label">Citizens</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon gray"><i class="bi bi-person-gear"></i></div>
            <div><div class="stat-value"><?= $data['userCounts']['internal'] ?? 0 ?></div><div class="stat-label">Internal Staff</div></div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-clipboard-check"></i></div>
            <div><div class="stat-value"><?= ($counts['Supervisor Review'] ?? 0) + ($counts['Quality Review'] ?? 0) ?></div><div class="stat-label">Awaiting Decision</div></div>
        </div>
    </div>
</div>

<!-- SLA Summary Row -->
<div class="row g-3 mb-4">
    <div class="col-12"><div class="section-heading"><i class="bi bi-clock me-2"></i>SLA Status</div></div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check-circle"></i></div>
            <div><div class="stat-value"><?= (int)$slaCounts['on_track'] ?></div><div class="stat-label">On Track</div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-exclamation-triangle"></i></div>
            <div><div class="stat-value"><?= (int)$slaCounts['warning'] ?></div><div class="stat-label">SLA Warning</div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-alarm"></i></div>
            <div><div class="stat-value"><?= (int)$slaCounts['breached'] ?></div><div class="stat-label">SLA Breached</div></div>
        </div>
    </div>
</div>

<!-- Charts + Audit -->
<div class="row g-3 mb-3">
    <!-- Doughnut: Status distribution -->
    <div class="col-lg-4">
        <div class="card h-100">
            <div class="card-header"><span class="card-title-icon bi bi-pie-chart"></span>Application Status</div>
            <div class="card-body d-flex align-items-center justify-content-center">
                <div class="chart-wrap w-100"><canvas id="statusChart"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Bar: Program distribution -->
    <div class="col-lg-5">
        <div class="card h-100">
            <div class="card-header"><span class="card-title-icon bi bi-bar-chart"></span>Applications by Program</div>
            <div class="card-body">
                <div class="chart-wrap"><canvas id="programChart"></canvas></div>
            </div>
        </div>
    </div>

    <!-- Quick links -->
    <div class="col-lg-3">
        <div class="card h-100">
            <div class="card-header"><span class="card-title-icon bi bi-lightning"></span>Quick Actions</div>
            <div class="list-group list-group-flush rounded-bottom-3">
                <a href="<?= BASE_URL ?>/users" class="list-group-item list-group-item-action py-2"><i class="bi bi-person-plus me-2 text-primary"></i>Manage Users</a>
                <a href="<?= BASE_URL ?>/citizens" class="list-group-item list-group-item-action py-2"><i class="bi bi-person-vcard me-2 text-info"></i>View Citizens</a>
                <a href="<?= BASE_URL ?>/cases" class="list-group-item list-group-item-action py-2"><i class="bi bi-folder2-open me-2 text-warning"></i>All Cases</a>
                <a href="<?= BASE_URL ?>/reports" class="list-group-item list-group-item-action py-2"><i class="bi bi-bar-chart me-2" style="color:var(--brand-red)"></i>Reports</a>
                <a href="<?= BASE_URL ?>/audit-log" class="list-group-item list-group-item-action py-2"><i class="bi bi-journal-text me-2 text-secondary"></i>Audit Log</a>
                <a href="<?= BASE_URL ?>/reports/export?type=status_summary" class="list-group-item list-group-item-action py-2"><i class="bi bi-download me-2 text-success"></i>Export CSV</a>
            </div>
        </div>
    </div>
</div>

<!-- Recent Audit Log -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <span><span class="card-title-icon bi bi-journal-text"></span>Recent Audit Activity</span>
        <a href="<?= BASE_URL ?>/audit-log" class="btn btn-sm btn-outline-secondary">View All</a>
    </div>
    <div class="table-responsive">
        <table class="table table-hover mb-0">
            <thead><tr><th>Time</th><th>User</th><th>Role</th><th>Action</th><th>Entity</th><th>IP</th></tr></thead>
            <tbody>
            <?php foreach ($data['recentLogs'] as $log): ?>
            <tr>
                <td class="text-muted small"><?= date('M d, H:i', strtotime($log['created_at'])) ?></td>
                <td class="fw-medium"><?= htmlspecialchars($log['username'] ?? '—') ?></td>
                <td><span class="badge badge-<?= htmlspecialchars($log['role'] ?? 'citizen') ?>"><?= htmlspecialchars($log['role'] ?? '—') ?></span></td>
                <td><?= htmlspecialchars($log['action_type']) ?></td>
                <td class="text-muted small"><?= htmlspecialchars($log['entity_type'] ?? '') ?><?= $log['entity_id'] ? ' #'.$log['entity_id'] : '' ?></td>
                <td class="text-muted small"><?= htmlspecialchars($log['ip_address'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
const brandRed = '#C8102E';
const statusData = <?= json_encode($counts) ?>;
const programData = <?= json_encode((new ApplicationModel())->getByProgram()) ?>;

// Doughnut
new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: Object.keys(statusData),
        datasets: [{
            data: Object.values(statusData),
            backgroundColor: ['#6366f1','#f59e0b','#ef4444','#10b981','#f87171','#16a34a','#dc2626','#d97706'],
            borderWidth: 2, borderColor: '#fff',
        }]
    },
    options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { position: 'bottom', labels: { font: { size: 10 }, boxWidth: 12 } } } }
});

// Bar
new Chart(document.getElementById('programChart'), {
    type: 'bar',
    data: {
        labels: programData.map(r => r.program_name),
        datasets: [{ label: 'Applications', data: programData.map(r => r.cnt), backgroundColor: brandRed, borderRadius: 5 }]
    },
    options: {
        responsive: true, maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } }, x: { ticks: { font: { size: 10 } } } }
    }
});
</script>

