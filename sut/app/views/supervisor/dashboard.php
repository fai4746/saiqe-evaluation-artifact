<?php
$pendingQueue = $data['queue'];
$urgentCases  = array_filter($pendingQueue, fn($c) => ($c['urgency_level'] ?? '') === 'emergency');
?>

<div class="page-header">
    <div>
        <h2><i class="bi bi-clipboard2-check me-2" style="color:var(--brand-red)"></i>Supervisor Dashboard</h2>
        <div class="page-subtitle">Welcome, <strong><?= htmlspecialchars(Session::get('first_name')) ?></strong> — Cases awaiting your final decision</div>
    </div>
    <a href="<?= BASE_URL ?>/supervisor/queue" class="btn btn-brand">
        <i class="bi bi-clipboard-check me-1"></i>Open Queue
    </a>
</div>

<div class="row g-3 mb-4">
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon amber"><i class="bi bi-hourglass-split"></i></div>
            <div><div class="stat-value"><?= count($data['queue']) ?></div><div class="stat-label">Pending Decisions</div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon red"><i class="bi bi-exclamation-triangle"></i></div>
            <div><div class="stat-value"><?= count($urgentCases) ?></div><div class="stat-label">Emergency</div></div>
        </div>
    </div>
    <div class="col-sm-4">
        <div class="stat-card">
            <div class="stat-icon green"><i class="bi bi-check2-circle"></i></div>
            <div><div class="stat-value"><?= (int)($data['unreadCount'] ?? 0) ?></div><div class="stat-label">Unread Notifications</div></div>
        </div>
    </div>
</div>

<div class="row g-3">
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <span><span class="card-title-icon bi bi-list-check"></span>Review Queue</span>
                <a href="<?= BASE_URL ?>/supervisor/queue" class="btn btn-sm btn-brand">Full Queue</a>
            </div>
            <div class="table-responsive">
                <table class="table table-hover table-clickable mb-0">
                    <thead><tr><th>App #</th><th>Program</th><th>Citizen</th><th>Officer</th><th>Recommendation</th><th>Days Pending</th><th></th></tr></thead>
                    <tbody>
                    <?php if (empty($data['queue'])): ?>
                        <tr><td colspan="7"><div class="empty-state"><i class="bi bi-check-all"></i>No cases awaiting decision.</div></td></tr>
                    <?php else: ?>
                        <?php foreach ($data['queue'] as $c):
                            $days = floor((time() - strtotime($c['submitted_at'])) / 86400);
                        ?>
                        <tr onclick="window.location='<?= BASE_URL ?>/supervisor/cases/<?= $c['case_id'] ?>'">
                            <td><code><?= htmlspecialchars($c['application_number']) ?></code></td>
                            <td><?= htmlspecialchars($c['program_name']) ?></td>
                            <td><?= htmlspecialchars($c['first_name'].' '.$c['last_name']) ?></td>
                            <td><?= htmlspecialchars(($c['officer_first']??'').' '.($c['officer_last']??'')) ?></td>
                            <td><span class="badge <?= statusBadgeClass($c['status']) ?>"><?= htmlspecialchars($c['status']) ?></span></td>
                            <td><span class="<?= $days > 10 ? 'text-danger fw-bold' : 'text-muted' ?>"><?= $days ?>d</span></td>
                            <td><a href="<?= BASE_URL ?>/supervisor/cases/<?= $c['case_id'] ?>" class="btn btn-sm btn-brand" onclick="event.stopPropagation()">Decide</a></td>
                        </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header"><span class="card-title-icon bi bi-bar-chart"></span>My Decisions This Month</div>
            <div class="card-body">
                <div class="chart-wrap"><canvas id="decisionChart"></canvas></div>
            </div>
        </div>
    </div>
</div>

<script>
const mySummary = <?= json_encode(array_values($data['summary'])) ?>;
const myEntry   = mySummary.find(r => r.user_id == <?= Session::get('user_id') ?>) || {approved:0,denied:0};
new Chart(document.getElementById('decisionChart'), {
    type: 'doughnut',
    data: {
        labels: ['Approved','Denied'],
        datasets: [{ data: [myEntry.approved, myEntry.denied], backgroundColor: ['#16a34a','#C8102E'], borderWidth: 2, borderColor:'#fff' }]
    },
    options: { responsive:true, maintainAspectRatio:false, plugins: { legend: { position:'bottom' } } }
});
</script>

